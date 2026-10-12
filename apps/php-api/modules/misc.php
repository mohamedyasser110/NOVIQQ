<?php
// modules/misc.php — health, permissions registry, audit, settings, notifications
function handle_misc($method, $parts, $query, $body, $cfg, $user) {
  if ($method === 'GET' && $parts === ['health']) {
    try { Db::q('SELECT 1'); $db = 'up'; } catch (Exception $e) { $db = 'down'; }
    Response::json(['status' => 'ok', 'db' => $db, 'time' => gmdate('Y-m-d\TH:i:s.v\Z'), 'app' => 'company-os-api-0.1.0']);
  }
  if ($parts === ['permissions'] && $method === 'GET') {
    Response::json(array_map(function($k){ return ['key' => $k, 'label' => $k]; }, Permissions::ALL));
  }
  if ($parts === ['roles'] && $method === 'GET') {
    $roles = Db::q('SELECT * FROM "Role"');
    foreach ($roles as &$r) {
      $r['permissions'] = Db::q('SELECT p.* FROM "RolePermission" rp JOIN "Permission" p ON p.id=rp."permissionId" WHERE rp."roleId"=?', [$r['id']]);
      $r['users'] = Db::q('SELECT "userId" FROM "UserRole" WHERE "roleId"=?', [$r['id']]);
    }
    Response::json($roles);
  }
  // audit-logs
  if ($parts === ['audit-logs'] && $method === 'GET') {
    Auth::requirePerm($user['id'], 'audit.view');
    $limit = min(Util::toInt($query['limit'] ?? null, 100), 500);
    $w = []; $p = [];
    foreach (['entity' => 'entity', 'action' => 'action', 'actorId' => 'actorId'] as $qk => $col) {
      if (!empty($query[$qk])) { $w[] = "\"$col\" = ?"; $p[] = $query[$qk]; }
    }
    $sql = 'SELECT * FROM "AuditLog"' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY "createdAt" DESC LIMIT ' . (int)$limit;
    $rows = Db::q($sql, $p);
    foreach ($rows as &$r) {
      $r = Util::normRow($r);
      if (!empty($r['actorId'])) {
        $a = Db::one('SELECT name, email FROM "User" WHERE id=?', [$r['actorId']]);
        $r['actor'] = $a ?: null;
      } else $r['actor'] = null;
    }
    Response::json($rows);
  }
  // settings
  if ($parts === ['settings'] && $method === 'GET') {
    Auth::requirePerm($user['id'], 'settings.manage');
    Response::json(Util::normList(Db::q('SELECT * FROM "CompanySetting" ORDER BY "key" ASC')));
  }
  if (count($parts) === 2 && $parts[0] === 'settings' && $method === 'GET') {
    Auth::requirePerm($user['id'], 'settings.manage');
    $s = Db::one('SELECT * FROM "CompanySetting" WHERE "key"=?', [$parts[1]]);
    if (!$s) Response::notFound('setting not found: ' . $parts[1]);
    Response::json(Util::normRow($s));
  }
  if (count($parts) === 2 && $parts[0] === 'settings' && $method === 'POST') {
    Auth::requirePerm($user['id'], 'settings.manage');
    $key = $parts[1]; $val = $body['value'] ?? null;
    if ($val === null) Response::bad('value is required');
    $ex = Db::one('SELECT * FROM "CompanySetting" WHERE "key"=?', [$key]);
    if ($ex) Db::exec('UPDATE "CompanySetting" SET value=?, "updatedAt"=? WHERE "key"=?', [(string)$val, Util::now(), $key]);
    else Db::insert('INSERT INTO "CompanySetting" ("key", value, "updatedAt") VALUES (?,?,?)', [$key, (string)$val, Util::now()]);
    Util::audit($user['id'], 'settings.manage', 'CompanySetting', $key, (string)$val);
    Response::json(Util::normRow(Db::one('SELECT * FROM "CompanySetting" WHERE "key"=?', [$key])));
  }
  // notifications — read-all BEFORE :id/read
  if ($parts === ['notifications'] && $method === 'GET') {
    $w = '"userId" = ?'; $p = [$user['id']];
    if (($query['unread'] ?? null) === 'true') { $w .= ' AND "read" = 0'; }
    Response::json(Util::normList(Db::q("SELECT * FROM \"Notification\" WHERE $w ORDER BY \"createdAt\" DESC LIMIT 50", $p)));
  }
  if ($parts === ['notifications', 'read-all'] && $method === 'POST') {
    $st = Db::pdo()->prepare('UPDATE "Notification" SET "read"=1 WHERE "userId"=? AND "read"=0');
    $st->execute([$user['id']]);
    Response::json(['count' => $st->rowCount()]);
  }
  if (count($parts) === 3 && $parts[0] === 'notifications' && $parts[2] === 'read' && $method === 'POST') {
    $n = Db::one('SELECT * FROM "Notification" WHERE id=?', [$parts[1]]);
    if (!$n || $n['userId'] !== $user['id']) Response::notFound('notification not found');
    Db::exec('UPDATE "Notification" SET "read"=1 WHERE id=?', [$parts[1]]);
    Response::json(Util::normRow(Db::one('SELECT * FROM "Notification" WHERE id=?', [$parts[1]])));
  }
  return false;
}
