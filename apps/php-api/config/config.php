<?php
// config.php — reads env with safe defaults. On cPanel put real secrets in .env.php or env vars.
$root = dirname(__DIR__);

function envv($k, $d = null) {
  $v = getenv($k);
  if ($v === false && isset($_ENV[$k])) $v = $_ENV[$k];
  if ($v === false && isset($_SERVER[$k])) $v = $_SERVER[$k];
  return ($v === false || $v === null || $v === '') ? $d : $v;
}

// Optional local .env.php returning array (NOT committed with secrets)
$local = $root . '/config/.env.php';
if (is_file($local)) {
  $arr = include $local;
  if (is_array($arr)) foreach ($arr as $k => $v) {
    if (getenv($k) === false) { $_ENV[$k] = $v; $_SERVER[$k] = $v; putenv("$k=$v"); }
  }
}

return [
  'DB_DRIVER' => envv('DB_DRIVER', 'sqlite'), // sqlite (local) | mysql (cPanel)
  'DB_HOST' => envv('DB_HOST', 'localhost'),
  'DB_PORT' => envv('DB_PORT', '3306'),
  'DB_NAME' => envv('DB_NAME', 'company_os'),
  'DB_USER' => envv('DB_USER', 'root'),
  'DB_PASS' => envv('DB_PASS', ''),
  'SQLITE_PATH' => envv('SQLITE_PATH', $root . '/database/app.db'),
  'JWT_SECRET' => envv('JWT_ACCESS_SECRET', envv('JWT_SECRET', 'dev-only-change-me-32-chars-minimum')),
  'JWT_TTL' => (int)envv('JWT_ACCESS_TTL', '900'),
  'CORS_ORIGIN' => envv('CORS_ORIGIN', 'http://localhost:3100'),
  'API_PREFIX' => trim(envv('API_PREFIX', '/api'), '/'),
  'BOOTSTRAP_ENABLED' => envv('BOOTSTRAP_ENABLED', 'true') === 'true',
  'BOOTSTRAP_PASSWORD' => envv('BOOTSTRAP_PASSWORD', 'ChangeMe123!'),
];
