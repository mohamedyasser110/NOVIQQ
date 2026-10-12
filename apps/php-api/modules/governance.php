<?php
// modules/governance.php — approvals, reports
function handle_governance($method, $parts, $query, $body, $cfg, $user) {
  $uid = $user['id'];
  if ($method === 'GET' && $parts === ['approvals']) {
    $rows = !empty($query['state'])
      ? Db::q('SELECT * FROM "ApprovalRequest" WHERE state=? ORDER BY "createdAt" DESC', [$query['state']])
      : Db::q('SELECT * FROM "ApprovalRequest" ORDER BY "createdAt" DESC');
    foreach ($rows as &$r) {
      $r = Util::normRow($r);
      $r['requester'] = Db::one('SELECT name, email FROM "User" WHERE id=?', [$r['requesterId']]);
      $r['decisions'] = Db::q('SELECT * FROM "ApprovalDecision" WHERE "requestId"=?', [$r['id']]);
      foreach ($r['decisions'] as &$d) $d['approver'] = Db::one('SELECT name FROM "User" WHERE id=?', [$d['approverId']]);
    }
    Response::json($rows);
  }
  if ($method === 'POST' && $parts === ['approvals']) {
    $et = $body['entityType'] ?? null; $eid = $body['entityId'] ?? null; $act = $body['action'] ?? null;
    if (!$et || !$eid || !$act) Response::bad('entityType, entityId and action are required');
    if (Db::one('SELECT id FROM "ApprovalRequest" WHERE "entityType"=? AND "entityId"=? AND action=?', [$et, $eid, $act]))
      Response::bad('approval already requested for this action');
    $id = Util::cuid();
    Db::insert('INSERT INTO "ApprovalRequest" (id,"entityType","entityId",action,"requesterId",state,reason,"createdAt") VALUES (?,?,?,?,?,?,?,?)',
      [$id, $et, $eid, $act, $uid, 'PENDING', $body['reason'] ?? null, Util::now()]);
    Util::audit($uid, 'approvals.request', $et, $eid, "action=$act");
    Response::json(Util::normRow(Db::one('SELECT * FROM "ApprovalRequest" WHERE id=?', [$id])));
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'approvals' && $parts[2] === 'decide') {
    $dec = $body['decision'] ?? null;
    if (!in_array($dec, ['APPROVED', 'REJECTED'])) Response::bad('decision must be APPROVED or REJECTED');
    $req = Db::one('SELECT * FROM "ApprovalRequest" WHERE id=?', [$parts[1]]);
    if (!$req) Response::notFound('approval request not found');
    if ($req['state'] !== 'PENDING') Response::bad('request already ' . $req['state']);
    if ($req['requesterId'] === $uid) Response::forbidden('cannot approve your own request (separation of duties)');
    $pdo = Db::pdo();
    try {
      $pdo->beginTransaction();
      $pdo->prepare('INSERT INTO "ApprovalDecision" (id,"requestId","approverId",decision,comment,"createdAt") VALUES (?,?,?,?,?,?)')
        ->execute([Util::cuid(), $parts[1], $uid, $dec, $body['comment'] ?? null, Util::now()]);
      $pdo->prepare('UPDATE "ApprovalRequest" SET state=? WHERE id=?')->execute([$dec, $parts[1]]);
      $pdo->commit();
    } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    Util::audit($uid, 'approvals.' . strtolower($dec), $req['entityType'], $req['entityId'], 'request=' . $parts[1], $body['comment'] ?? null);
    Response::json(Util::normRow(Db::one('SELECT * FROM "ApprovalRequest" WHERE id=?', [$parts[1]])));
  }
  // ---- reports ----
  if ($method === 'GET' && count($parts) === 3 && $parts[0] === 'reports' && $parts[1] === 'project-financial') {
    $p = Db::one('SELECT * FROM "Project" WHERE id=?', [$parts[2]]);
    if (!$p) Response::notFound('project not found');
    $invs = Db::q('SELECT * FROM "Invoice" WHERE "projectId"=?', [$parts[2]]);
    $invoiced = 0; $collected = 0;
    foreach ($invs as $inv) {
      $invoiced += (int)$inv['amount'];
      foreach (Db::q('SELECT * FROM "Collection" WHERE "invoiceId"=?', [$inv['id']]) as $c) if ((int)$c['approved'] === 1) $collected += $c['amount'];
    }
    $exp = 0; foreach (Db::q('SELECT * FROM "Expense" WHERE "projectId"=?', [$parts[2]]) as $e) $exp += (int)$e['amount'];
    $appr = 0; $paid = 0;
    foreach (Db::q('SELECT * FROM "CompensationEntitlement" WHERE "projectId"=?', [$parts[2]]) as $en) {
      if ($en['status'] === 'APPROVED') $appr += (int)$en['amount'];
      if ($en['status'] === 'PAID') $paid += (int)$en['amount'];
    }
    Response::json(['code' => $p['code'], 'name' => $p['name'], 'status' => $p['status'], 'contractValue' => (int)$p['contractValue'],
      'invoiced' => $invoiced, 'collected' => $collected, 'receivable' => $invoiced - $collected, 'expenses' => $exp,
      'approvedCompensation' => $appr, 'paidCompensation' => $paid, 'margin' => (int)$p['contractValue'] - $exp - $appr]);
  }
  if ($method === 'GET' && count($parts) === 3 && $parts[0] === 'reports' && $parts[1] === 'section-performance') {
    $rows = Db::q('SELECT * FROM "ProjectSection" WHERE "projectId"=? ORDER BY "sortOrder" ASC', [$parts[2]]);
    foreach ($rows as &$r) { $r = Util::normRow($r); $r['tasks'] = Util::normList(Db::q('SELECT * FROM "Task" WHERE "sectionId"=?', [$r['id']])); }
    Response::json($rows);
  }
  if ($method === 'GET' && count($parts) === 3 && $parts[0] === 'reports' && $parts[1] === 'team-contribution') {
    $rows = Db::q('SELECT * FROM "CompensationEntitlement" WHERE "projectId"=?', [$parts[2]]);
    $by = [];
    foreach ($rows as $e) {
      $u = $e['userId'];
      if (!isset($by[$u])) $by[$u] = ['planned' => 0, 'approved' => 0, 'paid' => 0];
      if ($e['status'] === 'ALLOCATED') $by[$u]['planned'] += (int)$e['amount'];
      elseif ($e['status'] === 'APPROVED') $by[$u]['approved'] += (int)$e['amount'];
      elseif ($e['status'] === 'PAID') $by[$u]['paid'] += (int)$e['amount'];
    }
    Response::json($by);
  }
  if ($method === 'GET' && $parts === ['reports', 'cash-flow']) {
    $inflow = 0; $outflow = 0;
    foreach (Db::q('SELECT * FROM "LedgerTransaction"') as $t) {
      foreach (Db::q('SELECT * FROM "LedgerEntry" WHERE "txnId"=?', [$t['id']]) as $e) {
        if ($t['type'] === 'INCOME' && $e['amount'] > 0) $inflow += $e['amount'];
        if (in_array($t['type'], ['EXPENSE', 'PAYOUT']) && $e['amount'] < 0) $outflow += abs($e['amount']);
      }
    }
    Response::json(['inflow' => $inflow, 'outflow' => $outflow, 'net' => $inflow - $outflow]);
  }
  if ($method === 'GET' && $parts === ['reports', 'outstanding-receivables']) {
    $out = [];
    foreach (Db::q('SELECT * FROM "Invoice"') as $inv) {
      $col = 0;
      foreach (Db::q('SELECT * FROM "Collection" WHERE "invoiceId"=?', [$inv['id']]) as $c) if ((int)$c['approved'] === 1) $col += $c['amount'];
      $rec = (int)$inv['amount'] - $col;
      if ($rec > 0) {
        $cl = Db::one('SELECT name FROM "Client" WHERE id=?', [$inv['clientId']]);
        $out[] = ['number' => $inv['number'], 'client' => $cl ? $cl['name'] : null, 'amount' => (int)$inv['amount'], 'collected' => $col, 'receivable' => $rec, 'status' => $inv['status']];
      }
    }
    Response::json($out);
  }
  return false;
}
