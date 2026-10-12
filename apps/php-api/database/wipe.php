<?php
// wipe.php — deletes ALL business data, keeps users + roles + config.
// Keeps: User, Role, Permission, UserRole, RolePermission, UserPermissionOverride,
//        ProjectAccessScope, FinancialAccount, CompanySetting, AllocationRule.
// Wipes: everything else (projects, tasks, invoices, ledger, payouts, approvals, audit...).
// Usage: php apps/php-api/database/wipe.php
$root = dirname(__DIR__);
require $root . '/lib/Db.php';
$cfg = include $root . '/config/config.php';
Db::cfg($cfg);
$pdo = Db::pdo();

$wipe = ['Attachment','Notification','AuditLog','ApprovalDecision','ApprovalRequest','Payout',
  'CompensationEntitlement','ContributionRecord','AllocationLine','AllocationPlanVersion','AllocationPlan',
  'LedgerEntry','LedgerTransaction','Expense','Collection','InvoiceLine','Invoice',
  'TimeEntry','TaskAttachment','TaskComment','TaskDependency','TaskAssignee','Task',
  'SectionMember','ProjectSection','ProjectMember','Contract','Project','Client'];

if ($cfg['DB_DRIVER'] === 'mysql') {
  $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
  foreach ($wipe as $t) $pdo->exec("TRUNCATE TABLE `$t`");
  $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
} else {
  $pdo->exec('PRAGMA foreign_keys = OFF');
  foreach ($wipe as $t) $pdo->exec("DELETE FROM \"$t\"");
  $pdo->exec('PRAGMA foreign_keys = ON');
  $pdo->exec('VACUUM');
}
$u = Db::one('SELECT COUNT(*) as n FROM "User"');
echo 'wiped business data. users kept: ' . $u['n'] . PHP_EOL;
