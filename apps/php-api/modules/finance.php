<?php
// modules/finance.php — invoices, payments, expenses
function idem_key() {
  $h = function_exists('getallheaders') ? getallheaders() : [];
  foreach ($h as $k => $v) if (strtolower($k) === 'idempotency-key' && $v) return $v;
  if (!empty($_SERVER['HTTP_IDEMPOTENCY_KEY'])) return $_SERVER['HTTP_IDEMPOTENCY_KEY'];
  return null;
}
function invoice_summary($id) {
  $inv = Db::one('SELECT * FROM "Invoice" WHERE id=?', [$id]);
  if (!$inv) return null;
  $cols = Db::q('SELECT * FROM "Collection" WHERE "invoiceId"=?', [$id]);
  $collected = 0; foreach ($cols as $c) if ((int)$c['approved'] === 1) $collected += $c['amount'];
  return ['number' => $inv['number'], 'amount' => (int)$inv['amount'], 'currency' => $inv['currency'], 'collected' => $collected, 'receivable' => (int)$inv['amount'] - $collected, 'status' => $inv['status']];
}
function handle_finance($method, $parts, $query, $body, $cfg, $user) {
  $uid = $user['id'];
  // ---- invoices ----
  if ($method === 'GET' && $parts === ['invoices']) {
    $rows = empty($query['clientId']) ? Db::q('SELECT * FROM "Invoice" ORDER BY "issueDate" DESC LIMIT 100') : Db::q('SELECT * FROM "Invoice" WHERE "clientId"=? ORDER BY "issueDate" DESC LIMIT 100', [$query['clientId']]);
    foreach ($rows as &$r) {
      $r = Util::normRow($r);
      $r['collections'] = Db::q('SELECT * FROM "Collection" WHERE "invoiceId"=?', [$r['id']]);
      $r['lines'] = Db::q('SELECT * FROM "InvoiceLine" WHERE "invoiceId"=?', [$r['id']]);
      $r['client'] = Db::one('SELECT name FROM "Client" WHERE id=?', [$r['clientId']]);
    }
    Response::json($rows);
  }
  if ($method === 'GET' && count($parts) === 2 && $parts[0] === 'invoices') {
    $s = invoice_summary($parts[1]);
    if (!$s) Response::bad('invoice not found');
    Response::json($s);
  }
  if ($method === 'POST' && $parts === ['invoices']) {
    Auth::requirePerm($uid, 'invoices.create');
    $num = $body['number'] ?? null;
    if (!is_string($num) || trim($num) === '') Response::bad('invoice number required');
    if (!isset($body['amount']) || !is_int($body['amount']) || $body['amount'] <= 0) Response::bad('amount must be positive integer (minor units)');
    if (empty($body['clientId'])) Response::bad('clientId is required');
    $id = Util::cuid();
    try {
      Db::insert('INSERT INTO "Invoice" (id, number, "clientId", "projectId", "dueDate", amount, currency, status, "issueDate") VALUES (?,?,?,?,?,?,?,?,?)',
        [$id, trim($num), $body['clientId'], $body['projectId'] ?? null, $body['dueDate'] ?? null, $body['amount'], $body['currency'] ?? 'EGP', 'ISSUED', Util::now()]);
    } catch (PDOException $e) { Response::conflict('invoice number already exists'); }
    foreach (($body['lines'] ?? []) as $l) {
      Db::insert('INSERT INTO "InvoiceLine" (id,"invoiceId",description,qty,"unitAmount") VALUES (?,?,?,?,?)',
        [Util::cuid(), $id, $l['description'] ?? '', (int)($l['qty'] ?? 1), (int)($l['unitAmount'] ?? 0)]);
    }
    Util::audit($uid, 'invoices.create', 'Invoice', $id, "$num amount={$body['amount']}");
    Response::json(Util::normRow(Db::one('SELECT * FROM "Invoice" WHERE id=?', [$id])));
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'invoices' && $parts[2] === 'collections') {
    Auth::requirePerm($uid, 'payments.record');
    $invId = $parts[1];
    $amount = $body['amount'] ?? null;
    if (!is_int($amount) || $amount <= 0) Response::bad('amount must be positive integer (minor units)');
    $idem = idem_key() ?? ($body['idemKey'] ?? null);
    if ($idem && Db::one('SELECT id FROM "Collection" WHERE "idemKey"=?', [$idem])) Response::conflict('duplicate collection rejected (idempotency key already used)');
    $inv = Db::one('SELECT * FROM "Invoice" WHERE id=?', [$invId]);
    if (!$inv) Response::bad('invoice not found');
    $cols = Db::q('SELECT * FROM "Collection" WHERE "invoiceId"=?', [$invId]);
    $already = 0; foreach ($cols as $c) if ((int)$c['approved'] === 1) $already += $c['amount'];
    if ($already + $amount > (int)$inv['amount']) Response::bad("overpayment rejected: " . ($already + $amount) . " > invoice {$inv['amount']}");
    $cid = Util::cuid();
    Db::insert('INSERT INTO "Collection" (id,"invoiceId",amount,currency,method,reference,"recorderId",approved,"idemKey",date) VALUES (?,?,?,?,?,?,?,?,?,?)',
      [$cid, $invId, $amount, $inv['currency'], $body['method'] ?? null, $body['reference'] ?? null, $uid, isset($body['approved']) ? (int)(bool)$body['approved'] : 1, $idem, Util::now()]);
    $total = $already + $amount;
    Db::exec('UPDATE "Invoice" SET status=? WHERE id=?', [$total >= (int)$inv['amount'] ? 'PAID' : 'PARTIAL', $invId]);
    Util::audit($uid, 'payments.record', 'Collection', $cid);
    Response::json(['collection' => Util::normRow(Db::one('SELECT * FROM "Collection" WHERE id=?', [$cid])), 'receivable' => (int)$inv['amount'] - $total]);
  }
  if ($method === 'POST' && count($parts) === 4 && $parts[0] === 'invoices' && $parts[1] === 'collections' && $parts[3] === 'reverse') {
    Auth::requirePerm($uid, 'payments.reverse');
    $reason = $body['reason'] ?? null;
    if (!is_string($reason) || trim($reason) === '') Response::bad('reason is required');
    $col = Db::one('SELECT * FROM "Collection" WHERE id=?', [$parts[2]]);
    if (!$col) Response::bad('collection not found');
    $rid = Util::cuid();
    Db::insert('INSERT INTO "Collection" (id,"invoiceId",amount,currency,method,reference,"recorderId",approved,date) VALUES (?,?,?,?,?,?,?,?,?)',
      [$rid, $col['invoiceId'], -(int)$col['amount'], $col['currency'], 'REVERSAL', 'REV-' . ($col['reference'] ?? $col['id']), $uid, 1, Util::now()]);
    Db::exec('UPDATE "Invoice" SET status=? WHERE id=?', ['PARTIAL', $col['invoiceId']]);
    Util::audit($uid, 'payments.reverse', 'Collection', $col['id'], "reversal=$rid amount=" . (-(int)$col['amount']), $reason);
    Response::json(Util::normRow(Db::one('SELECT * FROM "Collection" WHERE id=?', [$rid])));
  }
  // ---- payments (list + reverse only) ----
  if ($method === 'GET' && $parts === ['payments']) {
    $rows = empty($query['invoiceId']) ? Db::q('SELECT * FROM "Collection" ORDER BY date DESC') : Db::q('SELECT * FROM "Collection" WHERE "invoiceId"=? ORDER BY date DESC', [$query['invoiceId']]);
    foreach ($rows as &$r) { $r = Util::normRow($r); $r['invoice'] = Db::one('SELECT number FROM "Invoice" WHERE id=?', [$r['invoiceId']]); }
    Response::json($rows);
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'payments' && $parts[2] === 'reverse') {
    $reason = $body['reason'] ?? null;
    if (!is_string($reason) || trim($reason) === '') Response::bad('reason is required');
    $col = Db::one('SELECT * FROM "Collection" WHERE id=?', [$parts[1]]);
    if (!$col) Response::notFound('collection not found');
    $rid = Util::cuid();
    Db::insert('INSERT INTO "Collection" (id,"invoiceId",amount,currency,method,reference,"recorderId",approved,date) VALUES (?,?,?,?,?,?,?,?,?)',
      [$rid, $col['invoiceId'], -(int)$col['amount'], $col['currency'], 'REVERSAL', 'REV-' . ($col['reference'] ?? $col['id']), $uid, 1, Util::now()]);
    Db::exec('UPDATE "Invoice" SET status=? WHERE id=?', ['PARTIAL', $col['invoiceId']]);
    Util::audit($uid, 'payments.reverse', 'Collection', $col['id'], "reversal=$rid", $reason);
    Response::json(Util::normRow(Db::one('SELECT * FROM "Collection" WHERE id=?', [$rid])));
  }
  // ---- expenses ----
  if ($method === 'GET' && $parts === ['expenses']) {
    $rows = empty($query['projectId']) ? Db::q('SELECT * FROM "Expense" ORDER BY date DESC') : Db::q('SELECT * FROM "Expense" WHERE "projectId"=? ORDER BY date DESC', [$query['projectId']]);
    foreach ($rows as &$r) { $r = Util::normRow($r); $r['project'] = $r['projectId'] ? Db::one('SELECT code, name FROM "Project" WHERE id=?', [$r['projectId']]) : null; }
    Response::json($rows);
  }
  if ($method === 'POST' && $parts === ['expenses']) {
    if (!isset($body['amount']) || !is_int($body['amount']) || $body['amount'] <= 0) Response::bad('amount must be positive integer (minor units)');
    if (empty($body['category'])) Response::bad('category is required');
    $id = Util::cuid();
    Db::insert('INSERT INTO "Expense" (id,"projectId",category,vendor,amount,currency,date,receipt,description,status) VALUES (?,?,?,?,?,?,?,?,?,?)',
      [$id, $body['projectId'] ?? null, $body['category'], $body['vendor'] ?? null, $body['amount'], $body['currency'] ?? 'EGP', $body['date'] ?? Util::now(), $body['receipt'] ?? null, $body['description'] ?? null, 'SUBMITTED']);
    Util::audit($uid, 'expenses.create', 'Expense', $id, "{$body['category']} {$body['amount']}");
    Response::json(Util::normRow(Db::one('SELECT * FROM "Expense" WHERE id=?', [$id])));
  }
  foreach (['approve' => 'APPROVED', 'reject' => 'REJECTED', 'mark-paid' => 'PAID'] as $seg => $to) {
    if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'expenses' && $parts[2] === $seg) {
      Auth::requirePerm($uid, 'expenses.approve');
      $e = Db::one('SELECT * FROM "Expense" WHERE id=?', [$parts[1]]);
      if (!$e) Response::notFound('expense not found');
      $from = $e['status'];
      $ok = ($from === 'SUBMITTED' && in_array($to, ['APPROVED', 'REJECTED'])) || ($from === 'APPROVED' && $to === 'PAID');
      if (!$ok) Response::bad("invalid transition $from → $to");
      if ($to === 'REJECTED' && trim($body['reason'] ?? '') === '') Response::bad('rejection requires a reason');
      Db::exec('UPDATE "Expense" SET status=?, "approverId"=? WHERE id=?', [$to, $uid, $parts[1]]);
      $act = $to === 'APPROVED' ? 'expenses.approved' : ($to === 'REJECTED' ? 'expenses.rejected' : 'expenses.paid');
      Util::audit($uid, $act, 'Expense', $parts[1], "$from → $to", $body['reason'] ?? null);
      Response::json(Util::normRow(Db::one('SELECT * FROM "Expense" WHERE id=?', [$parts[1]])));
    }
  }
  return false;
}
