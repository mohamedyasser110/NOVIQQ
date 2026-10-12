<?php
// modules/users.php
function handle_users($method, $parts, $query, $body, $cfg, $user) {
  $uid = $user['id'];
  if ($method === 'GET' && $parts === ['users']) {
    Auth::requirePerm($uid, 'users.view');
    $rows = Db::q('SELECT id, email, name, "isActive", "createdAt" FROM "User" ORDER BY "createdAt" ASC');
    foreach ($rows as &$r) {
      $r = Util::normRow($r);
      $roles = Db::q('SELECT r."key", r."label" FROM "UserRole" ur JOIN "Role" r ON r.id=ur."roleId" WHERE ur."userId"=?', [$r['id']]);
      $r['roles'] = array_map(function($x){ return ['role' => $x]; }, $roles);
    }
    Response::json($rows);
  }
  if ($method === 'GET' && $parts === ['users', 'me']) {
    $u = Db::one('SELECT id, email, name FROM "User" WHERE id=?', [$uid]);
    if (!$u) Response::notFound('user not found');
    $roles = Db::q('SELECT r."key" FROM "UserRole" ur JOIN "Role" r ON r.id=ur."roleId" WHERE ur."userId"=?', [$uid]);
    $perms = array_keys(Auth::effectivePermissions($uid));
    Response::json(['id' => $u['id'], 'email' => $u['email'], 'name' => $u['name'], 'roles' => array_column($roles, 'key'), 'permissions' => $perms]);
  }
  // Account settings: any authenticated user can update OWN name + email + password. No permission needed.
  // Note: JWT is keyed by user id (sub), so changing email does NOT invalidate the token.
  if ($method === 'PATCH' && $parts === ['users', 'me']) {
    $name = $body['name'] ?? null;
    if (!is_string($name) || trim($name) === '') Response::bad('name is required');
    if (strlen(trim($name)) > 191) Response::bad('name is too long');
    $sets = ['name=?']; $p = [trim($name)];
    if (array_key_exists('email', $body)) {
      $email = $body['email'];
      if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) Response::bad('valid email is required');
      $dup = Db::one('SELECT id FROM "User" WHERE email=?', [$email]);
      if ($dup && $dup['id'] !== $uid) Response::conflict("email already exists: $email");
      $sets[] = 'email=?'; $p[] = $email;
    }
    $sets[] = '"updatedAt"=?'; $p[] = Util::now(); $p[] = $uid;
    Db::exec('UPDATE "User" SET ' . implode(',', $sets) . ' WHERE id=?', $p);
    Util::audit($uid, 'users.edit', 'User', $uid, 'own profile updated');
    Response::json(Db::one('SELECT id, email, name FROM "User" WHERE id=?', [$uid]));
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'users' && $parts[1] === 'me' && $parts[2] === 'password') {
    $cur = $body['currentPassword'] ?? null; $new = $body['newPassword'] ?? null;
    if (!is_string($cur) || $cur === '') Response::bad('current password is required');
    if (!is_string($new) || strlen($new) < 8) Response::bad('new password must be at least 8 characters');
    $u = Db::one('SELECT * FROM "User" WHERE id=?', [$uid]);
    if (!$u || !password_verify($cur, $u['passwordHash'])) Response::unauth('current password is incorrect');
    Db::exec('UPDATE "User" SET "passwordHash"=?, "updatedAt"=? WHERE id=?', [password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), Util::now(), $uid]);
    Util::audit($uid, 'users.edit', 'User', $uid, 'own password changed');
    Response::json(['ok' => true]);
  }
  if ($method === 'POST' && $parts === ['users']) {
    $perms = Auth::effectivePermissions($uid);
    if (!isset($perms['users.invite'])) Response::forbidden('users.invite required');
    $email = $body['email'] ?? null; $name = $body['name'] ?? null; $roleKeys = $body['roleKeys'] ?? null;
    if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) Response::bad('email is required');
    if (!is_string($name) || trim($name) === '') Response::bad('name is required');
    if (Db::one('SELECT id FROM "User" WHERE email=?', [$email])) Response::conflict("email already exists: $email");
    $roleKeys = is_array($roleKeys) && count($roleKeys) ? $roleKeys : ['EMPLOYEE'];
    foreach ($roleKeys as $key) {
      $extra = array_values(array_filter(Permissions::grants($key), function($g) use ($perms) { return !isset($perms[$g]); }));
      if (count($extra)) Response::forbidden("cannot grant $key: exceeds your permissions (" . implode(', ', array_slice($extra, 0, 3)) . "…)");
    }
    $rids = Auth::ensureRoles($roleKeys);
    $id = Util::cuid();
    Db::insert('INSERT INTO "User" (id, email, name, "passwordHash", "isActive", "createdAt", "updatedAt") VALUES (?,?,?,?,?,?,?)',
      [$id, $email, $name, '!seed-' . bin2hex(random_bytes(6)), 1, Util::now(), Util::now()]);
    foreach ($rids as $rid) Db::insert('INSERT INTO "UserRole" ("userId","roleId") VALUES (?,?)', [$id, $rid]);
    Util::audit($uid, 'users.edit', 'User', $id, "created $email");
    Response::json(['id' => $id, 'email' => $email, 'name' => $name, 'isActive' => true, 'roles' => $roleKeys]);
  }
  if ($method === 'GET' && count($parts) === 3 && $parts[0] === 'users' && $parts[2] === 'effective-permissions') {
    $target = $parts[1];
    $u = Db::one('SELECT id FROM "User" WHERE id=?', [$target]);
    if (!$u) Response::notFound('user not found');
    Response::json(array_keys(Auth::effectivePermissions($target)));
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'users' && $parts[2] === 'roles') {
    $target = $parts[1]; $roleKey = $body['roleKey'] ?? null;
    $perms = Auth::effectivePermissions($uid);
    if (!isset($perms['roles.manage'])) Response::forbidden('roles.manage required');
    $extra = array_values(array_filter(Permissions::grants($roleKey), function($g) use ($perms) { return !isset($perms[$g]); }));
    if (count($extra)) Response::forbidden("cannot grant $roleKey: exceeds your permissions (" . implode(', ', array_slice($extra, 0, 3)) . "…)");
    if ($uid === $target) Response::forbidden('cannot change your own roles (separation of duties)');
    $rids = Auth::ensureRoles([$roleKey]);
    if (!Db::one('SELECT * FROM "UserRole" WHERE "userId"=? AND "roleId"=?', [$target, $rids[0]]))
      Db::insert('INSERT INTO "UserRole" ("userId","roleId") VALUES (?,?)', [$target, $rids[0]]);
    Util::audit($uid, 'roles.manage', 'User', $target, "role=$roleKey");
    Response::json(['ok' => true, 'role' => $roleKey]);
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'users' && $parts[2] === 'overrides') {
    $target = $parts[1]; $permKey = $body['permKey'] ?? null; $allowed = $body['allowed'] ?? null;
    Auth::requirePerm($uid, 'permissions.manage');
    if (!in_array($permKey, Permissions::ALL, true)) Response::bad("unknown permission key: $permKey");
    $ex = Db::one('SELECT * FROM "UserPermissionOverride" WHERE "userId"=? AND "permKey"=?', [$target, $permKey]);
    if ($ex) Db::exec('UPDATE "UserPermissionOverride" SET allowed=? WHERE "userId"=? AND "permKey"=?', [(int)(bool)$allowed, $target, $permKey]);
    else Db::insert('INSERT INTO "UserPermissionOverride" (id, "userId", "permKey", allowed) VALUES (?,?,?,?)', [Util::cuid(), $target, $permKey, (int)(bool)$allowed]);
    Util::audit($uid, 'permissions.manage', 'User', $target, "$permKey=" . ((bool)$allowed ? 'true' : 'false'));
    Response::json(['ok' => true, 'permKey' => $permKey, 'allowed' => (bool)$allowed]);
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'users' && $parts[2] === 'deactivate') {
    $target = $parts[1];
    Auth::requirePerm($uid, 'users.deactivate');
    if ($uid === $target) Response::forbidden('cannot deactivate yourself');
    $admins = Db::q('SELECT u.id FROM "User" u JOIN "UserRole" ur ON ur."userId"=u.id JOIN "Role" r ON r.id=ur."roleId" WHERE u."isActive"=1 AND r."key"=?', ['FOUNDER_ADMIN']);
    if (count($admins) <= 1) Response::forbidden('cannot deactivate the last active FOUNDER_ADMIN');
    $t = Db::one('SELECT * FROM "User" WHERE id=?', [$target]);
    if (!$t) Response::notFound('user not found');
    Db::exec('UPDATE "User" SET "isActive"=0 WHERE id=?', [$target]);
    Util::audit($uid, 'users.deactivate', 'User', $target, $t['email']);
    Response::json(['ok' => true]);
  }
  return false;
}
