<?php
// tests/run.php — in-process smoke tests (no HTTP server). Usage: php apps/php-api/tests/run.php
// SAFE: always runs on database/test.db — never touches the real app.db.
define('PHP_API_TEST', true);
$root = dirname(__DIR__);
$_testDb = $root . '/database/test.db';
$_ENV['SQLITE_PATH'] = $_testDb; $_SERVER['SQLITE_PATH'] = $_testDb; putenv('SQLITE_PATH=' . $_testDb);
require $root . '/lib/Db.php';
require $root . '/lib/Util.php';
require $root . '/lib/Response.php';
require $root . '/lib/Jwt.php';
require $root . '/lib/Permissions.php';
require $root . '/lib/Auth.php';
require $root . '/lib/Money.php';
foreach (glob($root . '/modules/*.php') as $f) require_once $f;
$cfg = include $root . '/config/config.php';
Db::cfg($cfg);
// self-contained: fresh sqlite DB on every run (mysql: assume test DB already empty)
if (($cfg['DB_DRIVER'] ?? 'sqlite') === 'sqlite' && empty(getenv('PHP_API_KEEP_DB'))) {
  Db::reset();
  if (is_file($cfg['SQLITE_PATH'])) @unlink($cfg['SQLITE_PATH']);
  Db::cfg($cfg);
  $schema = file_get_contents($root . '/database/schema.sqlite.sql');
  foreach (array_filter(array_map('trim', explode(';', $schema))) as $st) {
    if ($st === '') continue;
    Db::pdo()->exec($st);
  }
  // minimal seed (mirror database/seed.php founders+roles+accounts+settings)
  $hash = password_hash($cfg['BOOTSTRAP_PASSWORD'] ?? 'ChangeMe123!', PASSWORD_BCRYPT, ['cost' => 12]);
  foreach (Permissions::ALL as $pk) Db::insert('INSERT INTO "Permission" (id, "key", label) VALUES (?,?,?)', [Util::cuid(), $pk, $pk]);
  foreach (['FOUNDER_ADMIN','PROJECT_MANAGER','EMPLOYEE','FINANCE_MANAGER','VIEWER'] as $rk) {
    $rid = Util::cuid();
    Db::insert('INSERT INTO "Role" (id, "key", label) VALUES (?,?,?)', [$rid, $rk, $rk]);
    foreach (Permissions::grants($rk) as $pk) {
      $perm = Db::one('SELECT * FROM "Permission" WHERE "key"=?', [$pk]);
      Db::insert('INSERT INTO "RolePermission" ("roleId","permissionId") VALUES (?,?)', [$rid, $perm['id']]);
    }
  }
  $admin = Db::one('SELECT * FROM "Role" WHERE "key"=?', ['FOUNDER_ADMIN']);
  for ($i = 1; $i <= 5; $i++) {
    $id = Util::cuid();
    Db::insert('INSERT INTO "User" (id, email, name, "passwordHash", "isActive", "createdAt", "updatedAt") VALUES (?,?,?,?,?,?,?)',
      [$id, "founder$i@example.test", "Founder $i (demo)", $hash, 1, Util::now(), Util::now()]);
    Db::insert('INSERT INTO "UserRole" ("userId","roleId") VALUES (?,?)', [$id, $admin['id']]);
  }
  foreach ([['OPERATING_EXPENSES','Operating Expenses'],['COMPANY_RESERVE','Company Reserve'],['MARKETING','Marketing'],['WORKING_CAPITAL','Working Capital'],['SALARIES_COMPENSATION','Salaries & Compensation'],['TAXES_FEES','Taxes & Fees'],['PROJECT_EXPENSES','Project Expenses'],['UNALLOCATED_CASH','Unallocated Cash']] as $a)
    Db::insert('INSERT INTO "FinancialAccount" (id, "key", label, balance, currency) VALUES (?,?,?,?,?)', [Util::cuid(), $a[0], $a[1], 0, 'EGP']);
  foreach (['COMPANY_CURRENCY' => 'EGP','COMPANY_TIMEZONE' => 'Africa/Cairo','EARN_CONDITION' => 'project_delivery_and_collection','PAYOUT_APPROVAL_MODE' => 'two_person'] as $k => $v)
    Db::insert('INSERT INTO "CompanySetting" ("key", value, "updatedAt") VALUES (?,?,?)', [$k, $v, Util::now()]);
}

$pass = 0; $fail = 0; $fails = [];
function check($name, $cond, $extra = '') {
  global $pass, $fail, $fails;
  if ($cond) { $pass++; echo "PASS $name\n"; }
  else { $fail++; $fails[] = $name . ($extra ? " — $extra" : ''); echo "FAIL $name" . ($extra ? " — $extra" : '') . "\n"; }
}

// dispatch helper replicating public/index.php routing
function req($method, $path, $body = [], $query = [], $token = null) {
  global $cfg;
  $_SERVER['REQUEST_METHOD'] = $method;
  $_SERVER['HTTP_AUTHORIZATION'] = $token ? "Bearer $token" : null;
  if (!$token) unset($_SERVER['HTTP_AUTHORIZATION']);
  $uri = '/' . trim($path, '/');
  $prefix = '/' . trim($cfg['API_PREFIX'], '/');
  if ($prefix !== '/' && strpos($uri, $prefix) === 0) $uri = substr($uri, strlen($prefix));
  $uri = '/' . trim($uri, '/');
  $parts = $uri === '/' ? [] : array_values(array_map('urldecode', explode('/', trim($uri, '/'))));
  try {
    if (count($parts) >= 1 && $parts[0] === 'auth') handle_auth($method, $parts, $query, $body, $cfg);
    if ($parts === ['health']) handle_misc($method, $parts, $query, $body, $cfg, ['id' => null]);
    $user = Auth::guard($cfg);
    foreach (['handle_misc','handle_users','handle_projects','handle_tasks','handle_finance','handle_treasury','handle_governance'] as $fn) $fn($method, $parts, $query, $body, $cfg, $user);
    throw new TestResponse(['statusCode' => 404, 'message' => 'route not found'], 404);
  } catch (TestResponse $t) { return ['status' => $t->status, 'data' => $t->data]; }
}

// 0. health (needs DB up)
$r = req('GET', '/api/health');
check('health', $r['status'] === 200 && $r['data']['status'] === 'ok' && $r['data']['db'] === 'up', json_encode($r));

// 1. login founder1
$r = req('POST', '/api/auth/login', ['email' => 'founder1@example.test', 'password' => 'ChangeMe123!']);
check('login', $r['status'] === 200 && !empty($r['data']['accessToken']), json_encode($r));
$T = $r['data']['accessToken'] ?? null;

// bad login
$r = req('POST', '/api/auth/login', ['email' => 'founder1@example.test', 'password' => 'wrongpass1']);
check('login-401', $r['status'] === 401);

// bootstrap when not empty -> 403
$r = req('POST', '/api/auth/bootstrap');
check('bootstrap-403-not-empty', $r['status'] === 403);

// no token -> 401
$r = req('GET', '/api/users/me');
check('guard-401', $r['status'] === 401);

// 2. users
$r = req('GET', '/api/users/me', [], [], $T);
check('users-me', $r['status'] === 200 && in_array('FOUNDER_ADMIN', $r['data']['roles'] ?? []), json_encode($r));
$r = req('GET', '/api/users', [], [], $T);
check('users-list', $r['status'] === 200 && count($r['data']) >= 5);
$r = req('POST', '/api/users', ['email' => 'emp1@test.local', 'name' => 'Emp One', 'roleKeys' => ['EMPLOYEE']], [], $T);
check('users-create', $r['status'] === 200 && ($r['data']['email'] ?? '') === 'emp1@test.local', json_encode($r));
$empId = $r['data']['id'] ?? null;
$r = req('POST', '/api/users', ['email' => 'emp1@test.local', 'name' => 'Dup'], [], $T);
check('users-create-409', $r['status'] === 409);
$r = req('GET', "/api/users/$empId/effective-permissions", [], [], $T);
check('users-effective', $r['status'] === 200 && in_array('tasks.view', $r['data'] ?? []), json_encode($r));
$r = req('POST', "/api/users/$empId/roles", ['roleKey' => 'VIEWER'], [], $T);
check('users-setrole', $r['status'] === 200 && ($r['data']['role'] ?? '') === 'VIEWER', json_encode($r));
$r = req('POST', "/api/users/$empId/overrides", ['permKey' => 'reports.export', 'allowed' => true], [], $T);
check('users-override', $r['status'] === 200, json_encode($r));
$r = req('POST', "/api/users/$empId/overrides", ['permKey' => 'nope.key', 'allowed' => true], [], $T);
check('users-override-400', $r['status'] === 400);

// permissions
$r = req('GET', '/api/permissions', [], [], $T);
check('permissions-registry', $r['status'] === 200 && count($r['data']) >= 50, 'count=' . count($r['data'] ?? []));
$r = req('GET', '/api/roles', [], [], $T);
check('roles-list', $r['status'] === 200 && count($r['data']) >= 5);

// 3. projects + sections
$r = req('POST', '/api/projects', ['code' => 'PRJ-TEST', 'name' => 'Test Project'], [], $T);
check('projects-create', $r['status'] === 200, json_encode($r));
$pid = $r['data']['id'] ?? null;
$r = req('GET', '/api/projects', [], [], $T);
check('projects-list', $r['status'] === 200 && count($r['data']) >= 1);
$r = req('GET', "/api/projects/$pid", [], [], $T);
check('projects-get', $r['status'] === 200 && ($r['data']['code'] ?? '') === 'PRJ-TEST');
$r = req('PATCH', "/api/projects/$pid", ['name' => 'Test Project V2'], [], $T);
check('projects-update', $r['status'] === 200 && ($r['data']['name'] ?? '') === 'Test Project V2');
$r = req('POST', "/api/projects/$pid/members", ['userId' => $empId, 'role' => 'MEMBER'], [], $T);
check('projects-addmember', $r['status'] === 200);
$r = req('GET', "/api/projects/$pid/members", [], [], $T);
check('projects-members', $r['status'] === 200 && count($r['data']) >= 1);
$r = req('POST', '/api/sections', ['projectId' => $pid, 'name' => 'Sec A'], [], $T);
check('sections-create', $r['status'] === 200, json_encode($r));
$secId = $r['data']['id'] ?? null;
$r = req('GET', "/api/projects/$pid/sections", [], [], $T);
check('sections-list', $r['status'] === 200 && count($r['data']) >= 1);

// 4. tasks
$r = req('POST', '/api/tasks', ['projectId' => $pid, 'sectionId' => $secId, 'title' => 'Task 1', 'assigneeIds' => [$empId]], [], $T);
check('tasks-create', $r['status'] === 200, json_encode($r));
$tid = $r['data']['id'] ?? null;
$r = req('POST', '/api/tasks', ['projectId' => $pid, 'title' => '   '], [], $T);
check('tasks-create-400', $r['status'] === 400);
$r = req('GET', '/api/tasks', [], ['projectId' => $pid], $T);
check('tasks-list', $r['status'] === 200 && count($r['data']) >= 1);
$r = req('PATCH', "/api/tasks/$tid", ['dueDate' => '2026-12-01'], [], $T);
check('tasks-duedate-requires-reason', $r['status'] === 400);
$r = req('PATCH', "/api/tasks/$tid", ['dueDate' => '2026-12-01', 'reason' => 'client asked'], [], $T);
check('tasks-update', $r['status'] === 200);
$r = req('POST', "/api/tasks/$tid/comments", ['body' => 'hello'], [], $T);
check('tasks-comment', $r['status'] === 200);
$r = req('POST', "/api/tasks/$tid/time-entries", ['hours' => 2.5, 'note' => 'work'], [], $T);
check('tasks-time', $r['status'] === 200, json_encode($r));
$r = req('POST', "/api/tasks/$tid/time-entries", ['hours' => 0], [], $T);
check('tasks-time-400', $r['status'] === 400);
$r = req('POST', '/api/tasks', ['projectId' => $pid, 'title' => 'Task 2'], [], $T);
$tid2 = $r['data']['id'] ?? null;
$r = req('POST', "/api/tasks/$tid/dependencies", ['dependsOnTaskId' => $tid2], [], $T);
check('tasks-dep', $r['status'] === 200, json_encode($r));
$r = req('POST', "/api/tasks/$tid2/dependencies", ['dependsOnTaskId' => $tid], [], $T);
check('tasks-dep-cycle-400', $r['status'] === 400, json_encode($r));

// timeline
$r = req('GET', '/api/timeline/overdue', [], [], $T);
check('timeline-overdue', $r['status'] === 200 && is_array($r['data']));
$r = req('GET', '/api/timeline/upcoming', [], ['days' => 30], $T);
check('timeline-upcoming', $r['status'] === 200 && is_array($r['data']));
$r = req('GET', '/api/timeline/workload', [], [], $T);
check('timeline-workload', $r['status'] === 200 && is_array($r['data']));
$r = req('GET', '/api/timeline/tasks', [], ['projectId' => $pid], $T);
check('timeline-tasks', $r['status'] === 200 && is_array($r['data']));

// 5. clients + contracts
$r = req('POST', '/api/clients', ['name' => 'Acme'], [], $T);
check('clients-create', $r['status'] === 200, json_encode($r));
$cid = $r['data']['id'] ?? null;
$r = req('POST', '/api/clients', ['name' => '  '], [], $T);
check('clients-create-400', $r['status'] === 400);
$r = req('GET', '/api/clients', [], [], $T);
check('clients-list', $r['status'] === 200 && count($r['data']) >= 1);
$r = req('POST', '/api/contracts', ['reference' => 'CTR-1', 'clientId' => $cid, 'projectId' => $pid, 'amount' => 100000], [], $T);
check('contracts-create', $r['status'] === 200, json_encode($r));
$r = req('GET', '/api/contracts', [], ['projectId' => $pid], $T);
check('contracts-list', $r['status'] === 200 && count($r['data']) >= 1);

// 6. invoices
$r = req('POST', '/api/invoices', ['number' => 'INV-001', 'clientId' => $cid, 'projectId' => $pid, 'amount' => 100000, 'lines' => [['description' => 'design', 'qty' => 2, 'unitAmount' => 50000]]], [], $T);
check('invoices-create', $r['status'] === 200, json_encode($r));
$invId = $r['data']['id'] ?? null;
$r = req('GET', "/api/invoices/$invId", [], [], $T);
check('invoices-summary', $r['status'] === 200 && ($r['data']['receivable'] ?? -1) === 100000, json_encode($r));
$r = req('POST', "/api/invoices/$invId/collections", ['amount' => 40000, 'method' => 'CASH', 'idemKey' => 'idem-1'], [], $T);
check('invoices-collect', $r['status'] === 200 && ($r['data']['receivable'] ?? -1) === 60000, json_encode($r));
$r = req('POST', "/api/invoices/$invId/collections", ['amount' => 1000, 'idemKey' => 'idem-1'], [], $T);
check('invoices-collect-idem-409', $r['status'] === 409, json_encode($r));
$r = req('POST', "/api/invoices/$invId/collections", ['amount' => 70000], [], $T);
check('invoices-overpay-400', $r['status'] === 400, json_encode($r));
$colId = Db::one('SELECT id FROM "Collection" WHERE "invoiceId"=? ORDER BY date ASC LIMIT 1', [$invId])['id'];
$r = req('POST', "/api/invoices/collections/$colId/reverse", ['reason' => 'mistake'], [], $T);
check('invoices-reverse', $r['status'] === 200, json_encode($r));
$r = req('GET', '/api/payments', [], ['invoiceId' => $invId], $T);
check('payments-list', $r['status'] === 200 && count($r['data']) >= 2, json_encode($r));

// 7. expenses
$r = req('POST', '/api/expenses', ['projectId' => $pid, 'category' => 'TRAVEL', 'amount' => 5000], [], $T);
check('expenses-create', $r['status'] === 200, json_encode($r));
$expId = $r['data']['id'] ?? null;
$r = req('POST', "/api/expenses/$expId/approve", [], [], $T);
check('expenses-approve', $r['status'] === 200, json_encode($r));
$r = req('POST', "/api/expenses/$expId/mark-paid", [], [], $T);
check('expenses-paid', $r['status'] === 200, json_encode($r));
$r = req('POST', "/api/expenses/$expId/reject", ['reason' => 'x'], [], $T);
check('expenses-invalid-transition', $r['status'] === 400);
$r = req('GET', '/api/expenses', [], ['projectId' => $pid], $T);
check('expenses-list', $r['status'] === 200 && count($r['data']) >= 1);

// 8. treasury
$r = req('GET', '/api/financial-accounts', [], [], $T);
check('treasury-accounts', $r['status'] === 200 && count($r['data']) >= 8, 'count=' . count($r['data'] ?? []));
$accs = $r['data']; $a1 = $accs[0]['id']; $a2 = $accs[1]['id'];
$r = req('POST', '/api/ledger-transactions', ['type' => 'INCOME', 'entries' => [['accountId' => $a1, 'amount' => 5000], ['accountId' => $a2, 'amount' => -5000]], 'idemKey' => 'txn-1'], [], $T);
check('treasury-post', $r['status'] === 200, json_encode($r));
$txnId = $r['data']['id'] ?? null;
$r = req('POST', '/api/ledger-transactions', ['type' => 'INCOME', 'entries' => [['accountId' => $a1, 'amount' => 5], ['accountId' => $a2, 'amount' => 3]]], [], $T);
check('treasury-unbalanced-400', $r['status'] === 400, json_encode($r));
$r = req('POST', '/api/ledger-transactions', ['type' => 'INCOME', 'entries' => [['accountId' => $a1, 'amount' => 1], ['accountId' => $a2, 'amount' => -1]], 'idemKey' => 'txn-1'], [], $T);
check('treasury-idem-409', $r['status'] === 409);
$r = req('POST', "/api/ledger-transactions/$txnId/reverse", ['reason' => 'oops'], [], $T);
check('treasury-reverse', $r['status'] === 200, json_encode($r));
$r = req('POST', "/api/ledger-transactions/$txnId/reverse", ['reason' => 'again'], [], $T);
check('treasury-double-reverse-409', $r['status'] === 409);
$r = req('GET', '/api/treasury/balances', [], [], $T);
check('treasury-balances', $r['status'] === 200 && is_array($r['data']));
$r = req('GET', '/api/treasury/cash-position', [], [], $T);
check('treasury-cash', $r['status'] === 200 && isset($r['data']['cash']), json_encode($r));
$r = req('GET', '/api/ledger-transactions', [], [], $T);
check('treasury-ledger', $r['status'] === 200 && count($r['data']) >= 2);

// 9. allocations + payouts (needs APPROVED entitlement: create plan with member line, submit, approve, then manually APPROVE entitlement)
$r = req('POST', '/api/allocation-plans', ['projectId' => $pid, 'basis' => 10000, 'lines' => [['userId' => $empId, 'percent' => 50], ['userId' => $empId, 'amount' => 1000]]], [], $T);
check('alloc-create', $r['status'] === 200, json_encode($r));
$planId = $r['data']['id'] ?? null;
$r = req('POST', "/api/allocation-plans/$planId/submit", [], [], $T);
check('alloc-submit', $r['status'] === 200, json_encode($r));
$r = req('POST', "/api/allocation-plans/$planId/approve", [], [], $T);
check('alloc-approve', $r['status'] === 200 && !empty($r['data']['entitlements']), json_encode($r));
$entId = $r['data']['entitlements'][0] ?? null;
// payouts require APPROVED entitlement -> update directly (simulates earn/approve flow)
Db::exec('UPDATE "CompensationEntitlement" SET status=? WHERE id=?', ['APPROVED', $entId]);
$r = req('POST', '/api/payouts', ['entitlementId' => $entId, 'idemKey' => 'pay-1'], [], $T);
check('payouts-request', $r['status'] === 200, json_encode($r));
$payId = $r['data']['id'] ?? null;
$r = req('POST', "/api/payouts/$payId/approve", [], [], $T);
// SoD: founder requested? No — entitlement approve was direct, requester is founder (T). Approver is same founder -> SoD triggers because audit requester == approver
check('payouts-approve-sod-400', $r['status'] === 400, json_encode($r));
// create second founder token to approve
$f2 = Db::one('SELECT * FROM "User" WHERE email=?', ['founder2@example.test']);
$T2 = Jwt::sign(['sub' => $f2['id'], 'email' => $f2['email']], $cfg['JWT_SECRET'], $cfg['JWT_TTL']);
$r = req('POST', "/api/payouts/$payId/approve", [], [], $T2);
check('payouts-approve', $r['status'] === 200, json_encode($r));
$r = req('POST', "/api/payouts/$payId/mark-paid", ['reference' => 'BANK-123'], [], $T2);
check('payouts-paid', $r['status'] === 200, json_encode($r));
$r = req('GET', '/api/payouts', [], [], $T);
check('payouts-list', $r['status'] === 200 && count($r['data']) >= 1);
$r = req('POST', "/api/allocation-plans/$planId/supersede", ['reason' => 'recalc'], [], $T);
check('alloc-supersede', $r['status'] === 200, json_encode($r));

// 10. approvals
$r = req('POST', '/api/approvals', ['entityType' => 'Expense', 'entityId' => $expId, 'action' => 'approve'], [], $T);
check('approvals-request', $r['status'] === 200, json_encode($r));
$apId = $r['data']['id'] ?? null;
$r = req('POST', '/api/approvals', ['entityType' => 'Expense', 'entityId' => $expId, 'action' => 'approve'], [], $T);
check('approvals-dup-400', $r['status'] === 400);
$r = req('POST', "/api/approvals/$apId/decide", ['decision' => 'APPROVED'], [], $T);
check('approvals-sod-403', $r['status'] === 403, json_encode($r));
$r = req('POST', "/api/approvals/$apId/decide", ['decision' => 'APPROVED'], [], $T2);
check('approvals-decide', $r['status'] === 200, json_encode($r));
$r = req('GET', '/api/approvals', [], ['state' => 'APPROVED'], $T);
check('approvals-list', $r['status'] === 200 && count($r['data']) >= 1);

// 11. reports
$r = req('GET', "/api/reports/project-financial/$pid", [], [], $T);
check('report-financial', $r['status'] === 200 && isset($r['data']['margin']), json_encode($r));
$r = req('GET', "/api/reports/section-performance/$pid", [], [], $T);
check('report-sections', $r['status'] === 200 && is_array($r['data']));
$r = req('GET', "/api/reports/team-contribution/$pid", [], [], $T);
check('report-team', $r['status'] === 200 && is_array($r['data']));
$r = req('GET', '/api/reports/cash-flow', [], [], $T);
check('report-cashflow', $r['status'] === 200 && isset($r['data']['net']));
$r = req('GET', '/api/reports/outstanding-receivables', [], [], $T);
check('report-receivables', $r['status'] === 200 && is_array($r['data']));

// 12. notifications + audit + settings
Db::insert('INSERT INTO "Notification" (id,"userId",kind,title,body,"read","createdAt") VALUES (?,?,?,?,?,?,?)', [Util::cuid(), Db::one('SELECT id FROM "User" WHERE email=?', ['founder1@example.test'])['id'], 'info', 'Hi', 'body', 0, Util::now()]);
$r = req('GET', '/api/notifications', [], ['unread' => 'true'], $T);
check('notif-unread', $r['status'] === 200 && count($r['data']) >= 1, json_encode($r));
$nid = $r['data'][0]['id'];
$r = req('POST', "/api/notifications/$nid/read", [], [], $T);
check('notif-read', $r['status'] === 200);
$r = req('POST', '/api/notifications/read-all', [], [], $T);
check('notif-readall', $r['status'] === 200 && isset($r['data']['count']));
$r = req('GET', '/api/audit-logs', [], ['limit' => 5], $T);
check('audit-list', $r['status'] === 200 && count($r['data']) >= 1, json_encode($r));
$r = req('GET', '/api/settings', [], [], $T);
check('settings-list', $r['status'] === 200 && count($r['data']) >= 4);
$r = req('POST', '/api/settings/COMPANY_CURRENCY', ['value' => 'EGP'], [], $T);
check('settings-set', $r['status'] === 200);
$r = req('GET', '/api/settings/COMPANY_CURRENCY', [], [], $T);
check('settings-get', $r['status'] === 200 && ($r['data']['value'] ?? '') === 'EGP');

// 13. permission enforcement: viewer cannot create clients
$r = req('POST', '/api/users', ['email' => 'viewer1@test.local', 'name' => 'V'], [], $T);
$viewerId = $r['data']['id'] ?? null;
Db::exec('DELETE FROM "UserRole" WHERE "userId"=?', [$viewerId]);
Auth::ensureRoles(['VIEWER']);
$vr = Db::one('SELECT * FROM "Role" WHERE "key"=?', ['VIEWER']);
Db::insert('INSERT INTO "UserRole" ("userId","roleId") VALUES (?,?)', [$viewerId, $vr['id']]);
$vu = Db::one('SELECT * FROM "User" WHERE id=?', [$viewerId]);
$VT = Jwt::sign(['sub' => $vu['id'], 'email' => $vu['email']], $cfg['JWT_SECRET'], $cfg['JWT_TTL']);
$r = req('POST', '/api/clients', ['name' => 'Nope'], [], $VT);
check('perm-403', $r['status'] === 403, json_encode($r));

// 14. deactivate + archive cleanup
$r = req('POST', "/api/users/$empId/deactivate", [], [], $T);
check('users-deactivate', $r['status'] === 200, json_encode($r));
$r = req('POST', "/api/projects/$pid/archive", [], [], $T);
check('projects-archive', $r['status'] === 200);

// 15. account settings (any role — here founder; viewer VT also tries below)
$r = req('PATCH', '/api/users/me', ['name' => 'Founder One Updated'], [], $T);
check('account-name', $r['status'] === 200 && ($r['data']['name'] ?? '') === 'Founder One Updated', json_encode($r));
$r = req('PATCH', '/api/users/me', ['name' => '   '], [], $T);
check('account-name-400', $r['status'] === 400);
$r = req('PATCH', '/api/users/me', ['name' => 'F1', 'email' => 'not-an-email'], [], $T);
check('account-email-400', $r['status'] === 400);
$r = req('PATCH', '/api/users/me', ['name' => 'F1', 'email' => 'founder2@example.test'], [], $T);
check('account-email-dup-409', $r['status'] === 409);
$r = req('PATCH', '/api/users/me', ['name' => 'Founder One', 'email' => 'founder1b@example.test'], [], $T);
check('account-email-change', $r['status'] === 200 && ($r['data']['email'] ?? '') === 'founder1b@example.test', json_encode($r));
$r = req('POST', '/api/auth/login', ['email' => 'founder1b@example.test', 'password' => 'ChangeMe123!']);
check('account-login-new-email', $r['status'] === 200 && !empty($r['data']['accessToken']), json_encode($r));
$T = $r['data']['accessToken'] ?? $T;
$r = req('PATCH', '/api/users/me', ['name' => 'Founder 1 (demo)', 'email' => 'founder1@example.test'], [], $T);
check('account-email-revert', $r['status'] === 200, json_encode($r));
$r = req('PATCH', '/api/users/me', ['name' => 'Viewer V'], [], $VT);
check('account-name-viewer-ok', $r['status'] === 200, json_encode($r));
$r = req('POST', '/api/users/me/password', ['currentPassword' => 'wrong1234', 'newPassword' => 'NewPass123!'], [], $T);
check('account-pw-wrong-401', $r['status'] === 401);
$r = req('POST', '/api/users/me/password', ['currentPassword' => 'ChangeMe123!', 'newPassword' => 'short'], [], $T);
check('account-pw-short-400', $r['status'] === 400);
$r = req('POST', '/api/users/me/password', ['currentPassword' => 'ChangeMe123!', 'newPassword' => 'NewPass123!'], [], $T);
check('account-pw-change', $r['status'] === 200, json_encode($r));
$r = req('POST', '/api/auth/login', ['email' => 'founder1@example.test', 'password' => 'NewPass123!']);
check('account-login-new-pw', $r['status'] === 200 && !empty($r['data']['accessToken']), json_encode($r));

echo "\n==== $pass passed, $fail failed ====\n";
if ($fail) { echo implode("\n", $fails) . "\n"; exit(1); }
