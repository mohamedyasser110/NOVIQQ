<?php
// modules/auth.php — POST /auth/bootstrap, POST /auth/login (public)
function handle_auth($method, $parts, $query, $body, $cfg) {
  if ($method === 'POST' && $parts === ['auth', 'bootstrap']) {
    if (!$cfg['BOOTSTRAP_ENABLED']) Response::forbidden('bootstrap disabled');
    $c = Db::one('SELECT COUNT(*) as n FROM "User"');
    if ($c && (int)$c['n'] > 0) Response::forbidden('already bootstrapped');
    $hash = password_hash($cfg['BOOTSTRAP_PASSWORD'] ?? 'ChangeMe123!', PASSWORD_BCRYPT, ['cost' => 12]);
    for ($i = 1; $i <= 5; $i++) {
      Db::insert('INSERT INTO "User" (id, email, name, "passwordHash", "isActive", "createdAt", "updatedAt") VALUES (?,?,?,?,?,?,?)',
        [Util::cuid(), "founder$i@example.test", "Founder $i (demo)", $hash, 1, Util::now(), Util::now()]);
    }
    // grant FOUNDER_ADMIN to the 5 (mirror seed behavior for usable login)
    Auth::ensureRoles(['FOUNDER_ADMIN']);
    $admin = Db::one('SELECT * FROM "Role" WHERE "key"=?', ['FOUNDER_ADMIN']);
    foreach (Db::q('SELECT id FROM "User" WHERE email LIKE ?', ['founder%@example.test']) as $u) {
      if (!Db::one('SELECT * FROM "UserRole" WHERE "userId"=? AND "roleId"=?', [$u['id'], $admin['id']]))
        Db::insert('INSERT INTO "UserRole" ("userId","roleId") VALUES (?,?)', [$u['id'], $admin['id']]);
    }
    Response::json(['created' => 5]);
  }
  if ($method === 'POST' && $parts === ['auth', 'login']) {
    $email = $body['email'] ?? null; $pass = $body['password'] ?? null;
    if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) Response::bad('validation failed');
    if (!is_string($pass) || strlen($pass) < 8) Response::bad('validation failed');
    $u = Db::one('SELECT * FROM "User" WHERE email = ?', [$email]);
    if (!$u || (int)$u['isActive'] !== 1) Response::unauth('invalid credentials');
    if (!password_verify($pass, $u['passwordHash'])) Response::unauth('invalid credentials');
    $token = Jwt::sign(['sub' => $u['id'], 'email' => $u['email']], $cfg['JWT_SECRET'], $cfg['JWT_TTL']);
    Util::audit($u['id'], 'auth.login', 'User', $u['id']);
    Response::json(['accessToken' => $token, 'user' => ['id' => $u['id'], 'email' => $u['email'], 'name' => $u['name']]]);
  }
  return false;
}
