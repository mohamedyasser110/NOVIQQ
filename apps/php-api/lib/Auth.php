<?php
// Auth.php — JwtGuard + effectivePermissions + requirePerm + ensureRoles. Port of jwt.guard.ts + access.ts.
class Auth {
  public static $user = null;

  public static function bearer() {
    $h = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) $h = $_SERVER['HTTP_AUTHORIZATION'];
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $h = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    elseif (function_exists('getallheaders')) {
      foreach (getallheaders() as $k => $v) if (strtolower($k) === 'authorization') { $h = $v; break; }
    }
    if (!$h) return null;
    $parts = explode(' ', trim($h), 2);
    if (count($parts) !== 2 || strtolower($parts[0]) !== 'bearer') return null;
    return trim($parts[1]) ?: null;
  }

  // Guard: returns user row or sends 401. $public=true skips auth.
  public static function guard($cfg) {
    $token = self::bearer();
    if (!$token) Response::unauth('missing bearer token');
    $payload = Jwt::verify($token, $cfg['JWT_SECRET']);
    if (!$payload || !isset($payload['sub'])) Response::unauth('invalid token');
    $u = Db::one('SELECT * FROM "User" WHERE id = ?', [$payload['sub']]);
    if (!$u || (int)$u['isActive'] !== 1) Response::unauth('inactive user');
    self::$user = $u;
    return $u;
  }

  public static function effectivePermissions($userId) {
    $perms = [];
    $rows = Db::q(
      'SELECT p."key" as k FROM "UserRole" ur JOIN "Role" r ON r.id = ur."roleId" JOIN "RolePermission" rp ON rp."roleId" = r.id JOIN "Permission" p ON p.id = rp."permissionId" WHERE ur."userId" = ?',
      [$userId]
    );
    foreach ($rows as $r) $perms[$r['k']] = true;
    $ovs = Db::q('SELECT * FROM "UserPermissionOverride" WHERE "userId" = ?', [$userId]);
    foreach ($ovs as $o) {
      if ((int)$o['allowed'] === 1) $perms[$o['permKey']] = true;
      else unset($perms[$o['permKey']]);
    }
    return $perms;
  }

  public static function requirePerm($userId, $key) {
    $p = self::effectivePermissions($userId);
    if (!isset($p[$key])) Response::forbidden("$key required");
  }

  public static function ensureRoles($keys) {
    $ids = [];
    foreach ($keys as $key) {
      $role = Db::one('SELECT * FROM "Role" WHERE "key" = ?', [$key]);
      if (!$role) {
        $id = Util::cuid();
        Db::insert('INSERT INTO "Role" (id, "key", label) VALUES (?,?,?)', [$id, $key, $key]);
        $role = ['id' => $id, 'key' => $key];
      }
      foreach (Permissions::grants($key) as $pk) {
        $perm = Db::one('SELECT * FROM "Permission" WHERE "key" = ?', [$pk]);
        if (!$perm) {
          $pid = Util::cuid();
          Db::insert('INSERT INTO "Permission" (id, "key", label) VALUES (?,?,?)', [$pid, $pk, $pk]);
          $perm = ['id' => $pid];
        }
        $ex = Db::one('SELECT * FROM "RolePermission" WHERE "roleId" = ? AND "permissionId" = ?', [$role['id'], $perm['id']]);
        if (!$ex) Db::insert('INSERT INTO "RolePermission" ("roleId", "permissionId") VALUES (?,?)', [$role['id'], $perm['id']]);
      }
      $ids[] = $role['id'];
    }
    return $ids;
  }
}
