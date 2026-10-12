<?php
// migrate.php — creates all tables. Usage: php apps/php-api/database/migrate.php [--fresh]
$root = dirname(__DIR__);
require $root . '/lib/Db.php';
require $root . '/lib/Util.php';
$cfg = include $root . '/config/config.php';
Db::cfg($cfg);
$fresh = in_array('--fresh', $argv ?? []);
$pdo = Db::pdo();
$driver = $cfg['DB_DRIVER'];
if ($fresh && $driver === 'sqlite' && is_file($cfg['SQLITE_PATH'])) {
  Db::reset();
  @unlink($cfg['SQLITE_PATH']);
  Db::cfg($cfg);
  $pdo = Db::pdo();
}
$file = $driver === 'mysql' ? __DIR__ . '/schema.mysql.sql' : __DIR__ . '/schema.sqlite.sql';
$sql = file_get_contents($file);
// split by semicolon-newline (simple, safe for this schema)
$stmts = array_filter(array_map('trim', explode(';', $sql)));
foreach ($stmts as $st) {
  if ($st === '' || stripos($st, 'SET ') === 0) continue;
  $pdo->exec($st);
}
echo "migrated ($driver): " . count($stmts) . " statements" . PHP_EOL;
