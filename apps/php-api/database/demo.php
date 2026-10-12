<?php
// demo.php — simplest end-to-end demo story (idempotent, skips what exists).
// Usage: php apps/php-api/database/demo.php  (API must be running on :4100)
$BASE = getenv('DEMO_API') ?: 'http://127.0.0.1:4100/api';

function call($method, $path, $body = null, $token = null, $extraHeaders = []) {
  global $BASE;
  $h = ["Content-Type: application/json"];
  if ($token) $h[] = "Authorization: Bearer $token";
  foreach ($extraHeaders as $k => $v) $h[] = "$k: $v";
  $opts = ['method' => $method, 'header' => implode("\r\n", $h), 'ignore_errors' => true, 'timeout' => 15];
  if ($body !== null) $opts['content'] = json_encode($body);
  $ctx = stream_context_create(['http' => $opts]);
  $raw = @file_get_contents($BASE . $path, false, $ctx);
  $st = 0;
  if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) $st = (int)$m[1];
  return ['status' => $st, 'data' => $raw ? json_decode($raw, true) : null];
}
function step($name, $r, $ok = [200]) {
  $good = in_array($r['status'], (array)$ok);
  echo ($good ? 'OK   ' : 'FAIL ') . $name . ' [' . $r['status'] . "]\n";
  if (!$good) echo '     ' . json_encode($r['data'], JSON_UNESCAPED_UNICODE) . "\n";
  return $good ? $r['data'] : null;
}

echo "== demo story ==\n";
$f1 = step('login founder1', call('POST', '/auth/login', ['email' => 'founder1@example.test', 'password' => 'ChangeMe123!']));
$f2 = step('login founder2', call('POST', '/auth/login', ['email' => 'founder2@example.test', 'password' => 'ChangeMe123!']));
if (!$f1 || !$f2) exit(1);
$T = $f1['accessToken']; $T2 = $f2['accessToken'];

// users map
$users = call('GET', '/users', null, $T)['data'];
$uid = [];
foreach ($users as $u) $uid[$u['email']] = $u['id'];
$sara = $uid['sara@demo.test'] ?? null;

// 1. client
$clients = call('GET', '/clients', null, $T)['data'];
$cl = null;
foreach ($clients as $c) if ($c['name'] === 'عميل تجريبي') $cl = $c;
if (!$cl) $cl = step('client create', call('POST', '/clients', ['name' => 'عميل تجريبي', 'contact' => '0100-000-000'], $T));

// 2. project
$projs = call('GET', '/projects', null, $T)['data'];
$pr = null;
foreach ($projs as $p) if ($p['code'] === 'DEMO-1') $pr = $p;
if (!$pr) $pr = step('project create', call('POST', '/projects', ['code' => 'DEMO-1', 'name' => 'مشروع تجريبي', 'clientId' => $cl['id']], $T));
$pid = $pr['id'];
step('project member (sara)', call('POST', "/projects/$pid/members", ['userId' => $sara, 'role' => 'MEMBER'], $T));

// 3. section + tasks
$secs = call('GET', "/projects/$pid/sections", null, $T)['data'];
$sec = $secs[0] ?? step('section create', call('POST', '/sections', ['projectId' => $pid, 'name' => 'التنفيذ'], $T));
$tasks = call('GET', "/tasks?projectId=$pid", null, $T)['data'];
// (list has no projectId filter here — filter locally)
$mine = is_array($tasks) ? $tasks : [];
if (count($mine) < 2) {
  $t1 = step('task1 create', call('POST', '/tasks', ['projectId' => $pid, 'sectionId' => $sec['id'], 'title' => 'تصميم الواجهات', 'assigneeIds' => [$sara]], $T));
  $t2 = step('task2 create', call('POST', '/tasks', ['projectId' => $pid, 'sectionId' => $sec['id'], 'title' => 'تسليم العميل'], $T));
  step('task comment', call('POST', "/tasks/{$t1['id']}/comments", ['body' => 'بدأنا الشغل 👍'], $T));
  step('task time', call('POST', "/tasks/{$t1['id']}/time-entries", ['hours' => 3, 'note' => 'يوم 1'], $T));
  step('task dependency', call('POST', "/tasks/{$t2['id']}/dependencies", ['dependsOnTaskId' => $t1['id']], $T));
} else echo "OK   tasks exist (" . count($mine) . ")\n";

// 4. invoice + partial collection
$invs = call('GET', '/invoices', null, $T)['data'];
$inv = null;
foreach ($invs as $i) if ($i['number'] === 'INV-DEMO') $inv = $i;
if (!$inv) {
  $inv = step('invoice create', call('POST', '/invoices', ['number' => 'INV-DEMO', 'clientId' => $cl['id'], 'projectId' => $pid, 'amount' => 100000, 'lines' => [['description' => 'تصميم', 'qty' => 1, 'unitAmount' => 100000]]], $T));
  step('invoice collect 40%', call('POST', "/invoices/{$inv['id']}/collections", ['amount' => 40000, 'method' => 'BANK_TRANSFER'], $T, ['Idempotency-Key' => 'demo-1']));
} else echo "OK   invoice INV-DEMO exists\n";

// 5. expense full cycle
$exps = call('GET', '/expenses', null, $T)['data'];
if (!count($exps)) {
  $ex = step('expense create', call('POST', '/expenses', ['projectId' => $pid, 'category' => 'TRAVEL', 'vendor' => 'Uber', 'amount' => 5000], $T));
  step('expense approve', call('POST', "/expenses/{$ex['id']}/approve", [], $T));
  step('expense mark-paid', call('POST', "/expenses/{$ex['id']}/mark-paid", [], $T));
} else echo "OK   expenses exist (" . count($exps) . ")\n";

// 6. ledger income
$txns = call('GET', '/ledger-transactions', null, $T)['data'];
if (!count($txns)) {
  $accs = call('GET', '/financial-accounts', null, $T)['data'];
  step('ledger income', call('POST', '/ledger-transactions', ['type' => 'INCOME', 'reference' => 'DEMO-TXN-1',
    'entries' => [['accountId' => $accs[0]['id'], 'amount' => 50000], ['accountId' => $accs[7]['id'], 'amount' => -50000]]], $T));
} else echo "OK   ledger exists (" . count($txns) . ")\n";

// 7. allocation -> entitlements -> payout
$plans = call('GET', "/allocation-plans?projectId=$pid", null, $T)['data'];
if (!count($plans)) {
  $pl = step('allocation create', call('POST', '/allocation-plans', ['projectId' => $pid, 'basis' => 10000, 'lines' => [['userId' => $sara, 'percent' => 60]]], $T));
  step('allocation submit', call('POST', "/allocation-plans/{$pl['id']}/submit", [], $T));
  $ap = step('allocation approve', call('POST', "/allocation-plans/{$pl['id']}/approve", [], $T));
  $entId = $ap['entitlements'][0];
  // simulate the earn+approve step (no UI/API for it yet) straight in local sqlite:
  $db = new PDO('sqlite:' . __DIR__ . '/app.db');
  $db->exec("UPDATE \"CompensationEntitlement\" SET status='APPROVED' WHERE id=" . $db->quote($entId));
  $pay = step('payout request (founder1)', call('POST', '/payouts', ['entitlementId' => $entId], $T));
  step('payout approve (founder2, SoD)', call('POST', "/payouts/{$pay['id']}/approve", [], $T2));
  step('payout mark-paid', call('POST', "/payouts/{$pay['id']}/mark-paid", ['reference' => 'BANK-DEMO'], $T2));
} else echo "OK   allocation exists (" . count($plans) . ")\n";

// 8. one pending approval to view
$aprs = call('GET', '/approvals?state=PENDING', null, $T)['data'];
if (!count($aprs)) step('approval request', call('POST', '/approvals', ['entityType' => 'Expense', 'entityId' => 'demo', 'action' => 'review', 'reason' => 'مراجعة تجريبية'], $T));
else echo "OK   pending approvals (" . count($aprs) . ")\n";

echo "== done ==\n";
