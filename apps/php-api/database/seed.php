<?php
// seed.php — fresh DB seed: roles+permissions, 5 founders (FOUNDER_ADMIN), accounts, settings.
// Usage: php apps/php-api/database/seed.php
$root = dirname(__DIR__);
require $root . '/lib/Db.php';
require $root . '/lib/Util.php';
require $root . '/lib/Permissions.php';
require $root . '/lib/Auth.php';
$cfg = include $root . '/config/config.php';
Db::cfg($cfg);
$pdo = Db::pdo();

$email = getenv('BOOTSTRAP_EMAIL') ?: 'admin@example.test';
$pass = $cfg['BOOTSTRAP_PASSWORD'] ?? 'ChangeMe123!';
if (!getenv('BOOTSTRAP_EMAIL')) {
  // default demo founders like Nest bootstrap
  $founders = [];
  for ($i = 1; $i <= 5; $i++) $founders[] = ['email' => "founder$i@example.test", 'name' => "Founder $i (demo)"];
} else {
  $founders = [['email' => $email, 'name' => 'Admin']];
}
$hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
foreach (Permissions::ALL as $pk) {
  if (!Db::one('SELECT id FROM "Permission" WHERE "key" = ?', [$pk]))
    Db::insert('INSERT INTO "Permission" (id, "key", label) VALUES (?,?,?)', [Util::cuid(), $pk, $pk]);
}
foreach (['FOUNDER_ADMIN','PROJECT_MANAGER','EMPLOYEE','FINANCE_MANAGER','VIEWER'] as $rk) {
  $role = Db::one('SELECT * FROM "Role" WHERE "key" = ?', [$rk]);
  if (!$role) {
    $rid = Util::cuid();
    Db::insert('INSERT INTO "Role" (id, "key", label) VALUES (?,?,?)', [$rid, $rk, $rk]);
    $role = ['id' => $rid];
  }
  foreach (Permissions::grants($rk) as $pk) {
    $perm = Db::one('SELECT * FROM "Permission" WHERE "key" = ?', [$pk]);
    if ($perm && !Db::one('SELECT * FROM "RolePermission" WHERE "roleId"=? AND "permissionId"=?', [$role['id'], $perm['id']]))
      Db::insert('INSERT INTO "RolePermission" ("roleId","permissionId") VALUES (?,?)', [$role['id'], $perm['id']]);
  }
}
$adminRole = Db::one('SELECT * FROM "Role" WHERE "key" = ?', ['FOUNDER_ADMIN']);
foreach ($founders as $f) {
  $u = Db::one('SELECT * FROM "User" WHERE email = ?', [$f['email']]);
  if (!$u) {
    $id = Util::cuid();
    Db::insert('INSERT INTO "User" (id, email, name, "passwordHash", "isActive", "createdAt", "updatedAt") VALUES (?,?,?,?,?,?,?)',
      [$id, $f['email'], $f['name'], $hash, 1, Util::now(), Util::now()]);
    $u = ['id' => $id];
  }
  if (!Db::one('SELECT * FROM "UserRole" WHERE "userId"=? AND "roleId"=?', [$u['id'], $adminRole['id']]))
    Db::insert('INSERT INTO "UserRole" ("userId","roleId") VALUES (?,?)', [$u['id'], $adminRole['id']]);
}
$accounts = [
  ['OPERATING_EXPENSES','Operating Expenses'],['COMPANY_RESERVE','Company Reserve'],
  ['MARKETING','Marketing'],['WORKING_CAPITAL','Working Capital'],
  ['SALARIES_COMPENSATION','Salaries & Compensation'],['TAXES_FEES','Taxes & Fees'],
  ['PROJECT_EXPENSES','Project Expenses'],['UNALLOCATED_CASH','Unallocated Cash'],
];
foreach ($accounts as $a) {
  if (!Db::one('SELECT id FROM "FinancialAccount" WHERE "key" = ?', [$a[0]]))
    Db::insert('INSERT INTO "FinancialAccount" (id, "key", label, balance, currency) VALUES (?,?,?,?,?)', [Util::cuid(), $a[0], $a[1], 0, 'EGP']);
}
$settings = ['COMPANY_CURRENCY' => 'EGP', 'COMPANY_TIMEZONE' => 'Africa/Cairo', 'EARN_CONDITION' => 'project_delivery_and_collection', 'PAYOUT_APPROVAL_MODE' => 'two_person'];
foreach ($settings as $k => $v) {
  if (!Db::one('SELECT "key" FROM "CompanySetting" WHERE "key" = ?', [$k]))
    Db::insert('INSERT INTO "CompanySetting" ("key", value, "updatedAt") VALUES (?,?,?)', [$k, $v, Util::now()]);
}
echo 'seeded: ' . count($founders) . ' founders + roles + accounts + settings' . PHP_EOL;
