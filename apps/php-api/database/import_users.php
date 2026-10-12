<?php
// import_users.php — imports missing users (with password hashes + roles + overrides)
// from an old Nest/Prisma SQLite file (e.g. prisma/dev.db) into the PHP DB.
// Usage: php apps/php-api/database/import_users.php <path-to-old-sqlite>
$root = dirname(__DIR__);
require $root . '/lib/Db.php';
require $root . '/lib/Util.php';
require $root . '/lib/Permissions.php';
require $root . '/lib/Auth.php';
$cfg = include $root . '/config/config.php';
Db::cfg($cfg);

$src = $argv[1] ?? null;
if (!$src || !is_file($src)) { echo "usage: php import_users.php <old-sqlite-file>\n"; exit(1); }
$old = new PDO('sqlite:' . $src);
$old->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$added = 0; $skipped = 0;
foreach ($old->query('SELECT * FROM "User"') as $u) {
  if (Db::one('SELECT id FROM "User" WHERE email = ?', [$u['email']])) { $skipped++; continue; }
  Db::insert('INSERT INTO "User" (id, email, name, "passwordHash", "isActive", "createdAt", "updatedAt") VALUES (?,?,?,?,?,?,?)',
    [$u['id'], $u['email'], $u['name'], $u['passwordHash'], (int)$u['isActive'], $u['createdAt'], $u['updatedAt']]);
  // roles by key (old roleIds differ — map through Role.key)
  $keys = [];
  foreach ($old->query('SELECT r."key" FROM "UserRole" ur JOIN "Role" r ON r.id = ur."roleId" WHERE ur."userId" = ' . $old->quote($u['id'])) as $r) $keys[] = $r['key'];
  if ($keys) foreach (Auth::ensureRoles($keys) as $rid) {
    if (!Db::one('SELECT * FROM "UserRole" WHERE "userId"=? AND "roleId"=?', [$u['id'], $rid]))
      Db::insert('INSERT INTO "UserRole" ("userId","roleId") VALUES (?,?)', [$u['id'], $rid]);
  }
  foreach ($old->query('SELECT * FROM "UserPermissionOverride" WHERE "userId" = ' . $old->quote($u['id'])) as $o) {
    Db::insert('INSERT INTO "UserPermissionOverride" (id, "userId", "permKey", allowed) VALUES (?,?,?,?)',
      [Util::cuid(), $u['id'], $o['permKey'], (int)$o['allowed']]);
  }
  $added++;
}
echo "imported: $added users, skipped (already exist): $skipped" . PHP_EOL;
