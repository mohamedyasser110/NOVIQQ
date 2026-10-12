<?php
// Db.php — PDO singleton (sqlite local / mysql production). Pure PHP, no deps.
class Db {
  private static $pdo = null;
  private static $cfg = null;

  public static function cfg($cfg = null) {
    if ($cfg !== null) self::$cfg = $cfg;
    if (self::$cfg === null) self::$cfg = include dirname(__DIR__) . '/config/config.php';
    return self::$cfg;
  }

  public static function pdo($cfg = null) {
    if (self::$pdo !== null) return self::$pdo;
    $c = self::cfg($cfg);
    $driver = $c['DB_DRIVER'];
    if ($driver === 'mysql') {
      $dsn = "mysql:host={$c['DB_HOST']};port={$c['DB_PORT']};dbname={$c['DB_NAME']};charset=utf8mb4";
      $pdo = new PDO($dsn, $c['DB_USER'], $c['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]);
      $pdo->exec("SET NAMES utf8mb4");
    } else {
      $path = $c['SQLITE_PATH'];
      @mkdir(dirname($path), 0777, true);
      $pdo = new PDO('sqlite:' . $path);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
      $pdo->exec('PRAGMA foreign_keys = ON');
      $pdo->exec('PRAGMA journal_mode = WAL');
    }
    self::$pdo = $pdo;
    return $pdo;
  }

  public static function reset() { self::$pdo = null; self::$cfg = null; }

  public static function q($sql, $params = []) {
    $st = self::pdo()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }
  public static function one($sql, $params = []) {
    $st = self::pdo()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    return $r === false ? null : $r;
  }
  public static function exec($sql, $params = []) {
    $st = self::pdo()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
  }
  public static function insert($sql, $params = []) {
    $st = self::pdo()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
  }
}
