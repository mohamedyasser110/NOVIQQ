<?php
// public/index.php — single entry. Works on php -S + Apache (cPanel/Hostinger).
// Layouts supported (no edits needed):
//   dev:    apps/php-api/public/index.php + apps/php-api/{lib,modules,config}
//   shared: public_html/api/index.php     + public_html/api/app/{lib,modules,config}
$root = dirname(__DIR__);
if (!is_file($root . '/lib/Db.php') && is_file(__DIR__ . '/app/lib/Db.php')) $root = __DIR__ . '/app';
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

// CORS + security headers (helmet-lite)
$origin = $cfg['CORS_ORIGIN'];
if (!headers_sent()) {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization, Idempotency-Key');
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: DENY');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

// path parsing: support /api/... and ?path= fallback (some shared hosts)
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (isset($_GET['path'])) $uri = '/' . ltrim($_GET['path'], '/');
$prefix = '/' . trim($cfg['API_PREFIX'], '/');
if ($prefix !== '/' && strpos($uri, $prefix) === 0) $uri = substr($uri, strlen($prefix));
$uri = '/' . trim($uri, '/');
$parts = $uri === '/' ? [] : explode('/', trim($uri, '/'));
// decode each
$parts = array_values(array_map('urldecode', $parts));
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$query = $_GET; unset($query['path']);
$body = Util::body();

try {
  // public routes (no guard)
  if (count($parts) >= 1 && $parts[0] === 'auth') { handle_auth($method, $parts, $query, $body, $cfg); }
  if ($parts === ['health']) { handle_misc($method, $parts, $query, $body, $cfg, ['id' => null]); }

  // everything else requires JWT
  $user = Auth::guard($cfg);

  foreach (['handle_misc', 'handle_users', 'handle_projects', 'handle_tasks', 'handle_finance', 'handle_treasury', 'handle_governance'] as $fn) {
    if (function_exists($fn) && $fn($method, $parts, $query, $body, $cfg, $user) !== false) break;
  }
  Response::notFound('route not found: ' . $method . ' /' . implode('/', $parts));
} catch (PDOException $e) {
  error_log('[php-api PDO] ' . $e->getMessage());
  Response::err(500, 'database error');
} catch (Exception $e) {
  error_log('[php-api] ' . $e->getMessage());
  Response::err(500, 'internal error');
}
