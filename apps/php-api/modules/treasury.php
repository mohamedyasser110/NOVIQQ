<?php
// modules/treasury.php — financial-accounts, treasury, ledger, payouts, allocations
function post_ledger_txn($uid, $dto) {
  $entries = $dto['entries'] ?? null;
  if (!is_array($entries) || !count($entries)) Response::bad('entries are required');
  foreach ($entries as $e) if (!isset($e['amount']) || !is_int($e['amount'])) Response::bad('amounts must be integers (minor units)');
  try { Money::assertBalanced($entries); } catch (Exception $e) { Response::bad($e->getMessage()); }
  $idem = idem_key() ?? ($dto['idemKey'] ?? null);
  if ($idem && Db::one('SELECT id FROM "LedgerTransaction" WHERE "idemKey"=?', [$idem])) Response::conflict('duplicate ledger transaction (idempotency key already used)');
  $ids = array_values(array_unique(array_map(function($e){ return $e['accountId']; }, $entries)));
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $found = Db::q("SELECT id FROM \"FinancialAccount\" WHERE id IN ($ph)", $ids);
  if (count($found) !== count($ids)) Response::bad('unknown account id');
  $type = $dto['type'] ?? 'ADJUSTMENT';
  $ref = $dto['reference'] ?? ($type . '-' . time() . '-' . substr(Util::cuid(), 1, 6));
  $pdo = Db::pdo();
  try {
    $pdo->beginTransaction();
    $tid = Util::cuid();
    $st = $pdo->prepare('INSERT INTO "LedgerTransaction" (id, type, currency, reference, "projectId", note, "creatorId", "idemKey", "reversesId", date) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $st->execute([$tid, $type, $dto['currency'] ?? 'EGP', $ref, $dto['projectId'] ?? null, $dto['note'] ?? null, $uid, $idem, $dto['reversesRef'] ?? null, Util::now()]);
    $st2 = $pdo->prepare('INSERT INTO "LedgerEntry" (id,"txnId","accountId",amount) VALUES (?,?,?,?)');
    $st3 = $pdo->prepare('UPDATE "FinancialAccount" SET balance = balance + ? WHERE id=?');
    foreach ($entries as $e) { $st2->execute([Util::cuid(), $tid, $e['accountId'], $e['amount']]); $st3->execute([$e['amount'], $e['accountId']]); }
    $pdo->commit();
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (strpos($e->getMessage(), 'UNIQUE') !== false || $e->getCode() == '23000') Response::conflict('duplicate ledger reference');
    throw $e;
  }
  Util::audit($uid, 'finance.' . strtolower($type), 'LedgerTransaction', $tid, "$ref entries=" . count($entries));
  $txn = Db::one('SELECT * FROM "LedgerTransaction" WHERE id=?', [$tid]);
  $txn = Util::normRow($txn);
  $txn['entries'] = Db::q('SELECT * FROM "LedgerEntry" WHERE "txnId"=?', [$tid]);
  return $txn;
}
function handle_treasury($method, $parts, $query, $body, $cfg, $user) {
  $uid = $user['id'];
  if ($method === 'GET' && $parts === ['financial-accounts']) {
    Response::json(Db::q('SELECT * FROM "FinancialAccount" ORDER BY "key" ASC'));
  }
  if ($method === 'GET' && $parts === ['treasury', 'balances']) {
    $accs = Db::q('SELECT * FROM "FinancialAccount"');
    $out = [];
    foreach ($accs as $a) {
      $s = Db::one('SELECT COALESCE(SUM(amount),0) as t FROM "LedgerEntry" WHERE "accountId"=?', [$a['id']]);
      $out[] = ['key' => $a['key'], 'label' => $a['label'], 'currency' => $a['currency'], 'balance' => (int)($s['t'] ?? 0)];
    }
    Response::json($out);
  }
  if ($method === 'GET' && $parts === ['treasury', 'cash-position']) {
    $txns = Db::q('SELECT * FROM "LedgerTransaction"');
    $inflow = 0; $outflow = 0;
    foreach ($txns as $t) {
      $es = Db::q('SELECT * FROM "LedgerEntry" WHERE "txnId"=?', [$t['id']]);
      foreach ($es as $e) {
        if ($t['type'] === 'INCOME' && $e['amount'] > 0) $inflow += $e['amount'];
        if (in_array($t['type'], ['EXPENSE', 'PAYOUT']) && $e['amount'] < 0) $outflow += abs($e['amount']);
      }
    }
    Response::json(['inflow' => $inflow, 'outflow' => $outflow, 'cash' => $inflow - $outflow]);
  }
  if ($method === 'GET' && $parts === ['ledger-transactions']) {
    $limit = min(Util::toInt($query['limit'] ?? null, 100), 200);
    $rows = Db::q('SELECT * FROM "LedgerTransaction" ORDER BY date DESC LIMIT ' . (int)$limit);
    foreach ($rows as &$r) {
      $r = Util::normRow($r);
      $es = Db::q('SELECT e.*, a."key" as accountKey, a.label as accountLabel FROM "LedgerEntry" e JOIN "FinancialAccount" a ON a.id=e."accountId" WHERE e."txnId"=?', [$r['id']]);
      foreach ($es as &$e) $e['account'] = ['key' => $e['accountKey'], 'label' => $e['accountLabel']];
      $r['entries'] = $es;
    }
    Response::json($rows);
  }
  if ($method === 'POST' && $parts === ['ledger-transactions']) {
    Auth::requirePerm($uid, 'finance.record_expense');
    Response::json(post_ledger_txn($uid, $body));
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'ledger-transactions' && $parts[2] === 'reverse') {
    Auth::requirePerm($uid, 'finance.reverse_transaction');
    $reason = $body['reason'] ?? null;
    if (!is_string($reason) || trim($reason) === '') Response::bad('reason is required');
    $orig = Db::one('SELECT * FROM "LedgerTransaction" WHERE id=?', [$parts[1]]);
    if (!$orig) Response::notFound('transaction not found');
    if ($orig['type'] === 'REVERSAL') Response::bad('cannot reverse a reversal');
    if (Db::one('SELECT id FROM "LedgerTransaction" WHERE "reversesId"=?', [$parts[1]])) Response::conflict('transaction already reversed');
    $es = Db::q('SELECT * FROM "LedgerEntry" WHERE "txnId"=?', [$parts[1]]);
    $rev = post_ledger_txn($uid, ['type' => 'REVERSAL', 'entries' => array_map(function($e){ return ['accountId' => $e['accountId'], 'amount' => -(int)$e['amount']]; }, $es),
      'reference' => 'REV-' . $orig['reference'], 'note' => 'Reversal of ' . $orig['reference'] . ': ' . $reason, 'reversesRef' => $orig['id']]);
    Util::audit($uid, 'finance.reverse', 'LedgerTransaction', $orig['id'], 'reversal=' . $rev['id'], $reason);
    Response::json($rev);
  }
  // ---- payouts ----
  if ($method === 'GET' && $parts === ['payouts']) {
    $rows = empty($query['payeeId']) ? Db::q('SELECT * FROM "Payout" ORDER BY "createdAt" DESC') : Db::q('SELECT * FROM "Payout" WHERE "payeeId"=? ORDER BY "createdAt" DESC', [$query['payeeId']]);
    foreach ($rows as &$r) {
      $r = Util::normRow($r);
      $r['payee'] = Db::one('SELECT name, email FROM "User" WHERE id=?', [$r['payeeId']]);
      $r['project'] = $r['projectId'] ? Db::one('SELECT code, name FROM "Project" WHERE id=?', [$r['projectId']]) : null;
    }
    Response::json($rows);
  }
  if ($method === 'POST' && $parts === ['payouts']) {
    $entId = $body['entitlementId'] ?? null;
    if (!$entId) Response::bad('entitlementId is required');
    $ent = Db::one('SELECT * FROM "CompensationEntitlement" WHERE id=?', [$entId]);
    if (!$ent) Response::notFound('entitlement not found');
    if ($ent['status'] !== 'APPROVED') Response::bad("payout requires APPROVED entitlement (current: {$ent['status']})");
    $idem = idem_key() ?? ($body['idemKey'] ?? null);
    if ($idem && Db::one('SELECT id FROM "Payout" WHERE "idemKey"=?', [$idem])) Response::conflict('duplicate payout (idempotency key already used)');
    if (Db::one('SELECT id FROM "Payout" WHERE "entitlementId"=?', [$entId])) Response::conflict('payout already requested for this entitlement');
    $id = Util::cuid();
    Db::insert('INSERT INTO "Payout" (id,"payeeId","projectId","entitlementId",amount,currency,status,reference,"idemKey","createdAt") VALUES (?,?,?,?,?,?,?,?,?,?)',
      [$id, $ent['userId'], $ent['projectId'], $entId, $ent['amount'], $ent['currency'], 'REQUESTED', 'PAY-' . time() . '-' . substr($id, 1, 6), $idem, Util::now()]);
    Util::audit($uid, 'payouts.request', 'Payout', $id, "amount={$ent['amount']} entitlement=$entId");
    Response::json(Util::normRow(Db::one('SELECT * FROM "Payout" WHERE id=?', [$id])));
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'payouts' && $parts[2] === 'approve') {
    Auth::requirePerm($uid, 'finance.approve_payout');
    $p = Db::one('SELECT * FROM "Payout" WHERE id=?', [$parts[1]]);
    if (!$p) Response::notFound('payout not found');
    if ($p['status'] !== 'REQUESTED') Response::bad('payout is not REQUESTED');
    $req = Db::one('SELECT * FROM "AuditLog" WHERE entity=? AND "entityId"=? AND action=? ORDER BY "createdAt" ASC LIMIT 1', ['Payout', $parts[1], 'payouts.request']);
    if ($req && $req['actorId'] === $uid) Response::bad('separation of duties: requester cannot approve their own payout');
    Db::exec('UPDATE "Payout" SET status=?, "approverId"=? WHERE id=?', ['APPROVED', $uid, $parts[1]]);
    Util::audit($uid, 'payouts.approve', 'Payout', $parts[1]);
    Response::json(Util::normRow(Db::one('SELECT * FROM "Payout" WHERE id=?', [$parts[1]])));
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'payouts' && $parts[2] === 'mark-paid') {
    Auth::requirePerm($uid, 'finance.execute_payout');
    $ref = $body['reference'] ?? null;
    if (!is_string($ref) || trim($ref) === '') Response::bad('payment reference required');
    $p = Db::one('SELECT * FROM "Payout" WHERE id=?', [$parts[1]]);
    if (!$p) Response::notFound('payout not found');
    if ($p['status'] !== 'APPROVED') Response::bad('payout is not APPROVED');
    $pdo = Db::pdo();
    try {
      $pdo->beginTransaction();
      if ($p['entitlementId']) $pdo->prepare('UPDATE "CompensationEntitlement" SET status=? WHERE id=?')->execute(['PAID', $p['entitlementId']]);
      $pdo->prepare('UPDATE "Payout" SET status=?, reference=? WHERE id=?')->execute(['PAID', trim($ref), $parts[1]]);
      $pdo->commit();
    } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    Util::audit($uid, 'payouts.paid', 'Payout', $parts[1], trim($ref));
    Response::json(Util::normRow(Db::one('SELECT * FROM "Payout" WHERE id=?', [$parts[1]])));
  }
  // ---- allocations ----
  if ($method === 'GET' && $parts === ['allocation-plans']) {
    if (empty($query['projectId'])) Response::bad('projectId is required');
    $rows = Db::q('SELECT * FROM "AllocationPlan" WHERE "projectId"=? ORDER BY "createdAt" DESC', [$query['projectId']]);
    foreach ($rows as &$r) {
      $r['versions'] = Db::q('SELECT * FROM "AllocationPlanVersion" WHERE "planId"=? ORDER BY version DESC', [$r['id']]);
      foreach ($r['versions'] as &$v) $v['lines'] = Db::q('SELECT * FROM "AllocationLine" WHERE "versionId"=?', [$v['id']]);
    }
    Response::json($rows);
  }
  if ($method === 'POST' && $parts === ['allocation-plans']) {
    Auth::requirePerm($uid, 'allocations.create');
    $basis = $body['basis'] ?? null; $lines = $body['lines'] ?? null; $pid = $body['projectId'] ?? null;
    if (!is_int($basis) || $basis <= 0) Response::bad('basis must be positive integer');
    if (!is_array($lines) || !count($lines)) Response::bad('lines are required');
    if (!$pid) Response::bad('projectId is required');
    $mapped = [];
    foreach ($lines as $i => $l) {
      $m = ['key' => $l['sectionId'] ?? $l['userId'] ?? "line$i"];
      foreach (['percent','amount','weight'] as $k) if (isset($l[$k])) $m[$k] = $l[$k];
      $mapped[] = $m;
    }
    try { $res = Money::splitAllocation($basis, $mapped); } catch (Exception $e) { Response::bad($e->getMessage()); }
    $planId = Util::cuid(); $verId = Util::cuid();
    Db::insert('INSERT INTO "AllocationPlan" (id,"projectId",basis,currency,method,status,"createdById","createdAt") VALUES (?,?,?,?,?,?,?,?)',
      [$planId, $pid, $basis, $body['currency'] ?? 'EGP', $body['method'] ?? 'MANUAL', 'DRAFT', $uid, Util::now()]);
    Db::insert('INSERT INTO "AllocationPlanVersion" (id,"planId",version,status,"createdAt") VALUES (?,?,?, ?,?)', [$verId, $planId, 1, 'DRAFT', Util::now()]);
    foreach ($lines as $i => $l) {
      Db::insert('INSERT INTO "AllocationLine" (id,"versionId","sectionId","userId",percent,amount) VALUES (?,?,?,?,?,?)',
        [Util::cuid(), $verId, $l['sectionId'] ?? null, $l['userId'] ?? null, isset($l['percent']) ? (float)$l['percent'] : null, $res['out'][$i]['amount']]);
    }
    Util::audit($uid, 'allocations.create', 'AllocationPlan', $planId, "basis=$basis lines=" . count($lines) . " unallocated={$res['unallocated']}");
    $plan = Db::one('SELECT * FROM "AllocationPlan" WHERE id=?', [$planId]);
    $plan['versions'] = Db::q('SELECT * FROM "AllocationPlanVersion" WHERE "planId"=?', [$planId]);
    $plan['unallocated'] = $res['unallocated'];
    Response::json($plan);
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'allocation-plans' && in_array($parts[2], ['submit', 'approve'])) {
    $plan = Db::one('SELECT * FROM "AllocationPlan" WHERE id=?', [$parts[1]]);
    if (!$plan) Response::notFound('plan not found');
    if ($parts[2] === 'submit') {
      Auth::requirePerm($uid, 'allocations.create');
      if ($plan['status'] !== 'DRAFT') Response::bad("cannot submit plan in status {$plan['status']}");
      Db::exec('UPDATE "AllocationPlan" SET status=? WHERE id=?', ['SUBMITTED', $parts[1]]);
      Db::exec('UPDATE "AllocationPlanVersion" SET status=? WHERE "planId"=?', ['SUBMITTED', $parts[1]]);
      Util::audit($uid, 'allocations.submit', 'AllocationPlan', $parts[1]);
      Response::json(['ok' => true, 'status' => 'SUBMITTED']);
    } else {
      Auth::requirePerm($uid, 'allocations.approve');
      if ($plan['status'] !== 'SUBMITTED') Response::bad("cannot approve plan in status {$plan['status']}");
      $ver = Db::one('SELECT * FROM "AllocationPlanVersion" WHERE "planId"=? ORDER BY version DESC LIMIT 1', [$parts[1]]);
      $lines = $ver ? Db::q('SELECT * FROM "AllocationLine" WHERE "versionId"=?', [$ver['id']]) : [];
      $ulines = array_values(array_filter($lines, function($l){ return !empty($l['userId']); }));
      if (!count($ulines)) Response::bad('approved plan needs member lines to generate entitlements');
      $ids = [];
      foreach ($ulines as $l) {
        $eid = Util::cuid();
        Db::insert('INSERT INTO "CompensationEntitlement" (id,"projectId","userId",amount,currency,status) VALUES (?,?,?,?,?,?)',
          [$eid, $plan['projectId'], $l['userId'], $l['amount'], $plan['currency'], 'ALLOCATED']);
        $ids[] = $eid;
      }
      Db::exec('UPDATE "AllocationPlan" SET status=? WHERE id=?', ['APPROVED', $parts[1]]);
      Db::exec('UPDATE "AllocationPlanVersion" SET status=? WHERE "planId"=?', ['APPROVED', $parts[1]]);
      Util::audit($uid, 'allocations.approve', 'AllocationPlan', $parts[1], 'entitlements=' . count($ids) . ' (status=ALLOCATED)');
      Response::json(['ok' => true, 'status' => 'APPROVED', 'entitlements' => $ids]);
    }
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'allocation-plans' && $parts[2] === 'supersede') {
    Auth::requirePerm($uid, 'allocations.edit');
    $reason = $body['reason'] ?? null;
    if (!is_string($reason) || trim($reason) === '') Response::bad('reason is required');
    $plan = Db::one('SELECT * FROM "AllocationPlan" WHERE id=?', [$parts[1]]);
    if (!$plan) Response::notFound('plan not found');
    $latest = Db::one('SELECT * FROM "AllocationPlanVersion" WHERE "planId"=? ORDER BY version DESC LIMIT 1', [$parts[1]]);
    $lv = $latest ? (int)$latest['version'] : 0;
    $oldLines = $latest ? Db::q('SELECT * FROM "AllocationLine" WHERE "versionId"=?', [$latest['id']]) : [];
    Db::exec('UPDATE "AllocationPlanVersion" SET status=? WHERE "planId"=?', ['SUPERSEDED', $parts[1]]);
    $nv = Util::cuid();
    Db::insert('INSERT INTO "AllocationPlanVersion" (id,"planId",version,status,"createdAt") VALUES (?,?,?,?,?)', [$nv, $parts[1], $lv + 1, 'DRAFT', Util::now()]);
    foreach ($oldLines as $l) {
      Db::insert('INSERT INTO "AllocationLine" (id,"versionId","sectionId","userId",percent,amount) VALUES (?,?,?,?,?,?)',
        [Util::cuid(), $nv, $l['sectionId'], $l['userId'], $l['percent'], $l['amount']]);
    }
    Db::exec('UPDATE "AllocationPlan" SET status=?, reason=? WHERE id=?', ['DRAFT', trim($reason), $parts[1]]);
    Util::audit($uid, 'allocations.supersede', 'AllocationPlan', $parts[1], 'new version v' . ($lv + 1), trim($reason));
    Response::json(['ok' => true, 'newVersion' => $lv + 1, 'status' => 'DRAFT']);
  }
  return false;
}
