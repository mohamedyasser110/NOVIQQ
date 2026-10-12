<?php
// Util.php — cuid, dates, body parsing, audit.
class Util {
  public static function cuid() {
    // cuid-like: c + 24 base36 chars
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $s = 'c';
    try { $b = random_bytes(18); } catch (Exception $e) { $b = openssl_random_pseudo_bytes(18); }
    for ($i = 0; $i < 24; $i++) $s .= $chars[ord($b[$i % strlen($b)]) % 36];
    return substr($s, 0, 25);
  }
  public static function now() { return gmdate('Y-m-d H:i:s'); }
  public static function iso($dbDate) {
    if (!$dbDate) return null;
    try {
      $t = is_numeric($dbDate) ? (int)$dbDate : strtotime(str_replace('T', ' ', (string)$dbDate));
      if ($t === false) return (string)$dbDate;
      return gmdate('Y-m-d\TH:i:s.v\Z', $t);
    } catch (Exception $e) { return (string)$dbDate; }
  }
  public static function body() {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
  }
  public static function audit($actorId, $action, $entity, $entityId = null, $diff = null, $reason = null) {
    try {
      Db::insert(
        "INSERT INTO \"AuditLog\" (id, actorId, action, entity, entityId, diff, reason, createdAt) VALUES (?,?,?,?,?,?,?,?)",
        [self::cuid(), $actorId, $action, $entity, $entityId, $diff !== null ? substr((string)$diff, 0, 2000) : null, $reason, self::now()]
      );
    } catch (Exception $e) { /* audit must never break main flow */ }
  }
  public static function toInt($v, $def = null) {
    if ($v === null || $v === '') return $def;
    if (is_int($v)) return $v;
    if (is_numeric($v)) return (int)$v;
    return $def;
  }
  public static function isIntVal($v) { return is_int($v); }
  public static function bool01($v) { return $v ? 1 : 0; }
  public static function from01($v) { return ((int)$v) === 1; }
  // Normalize a DB row: convert *_dates to ISO + 0/1 to bool for known fields
  public static function normRow($row) {
    if (!is_array($row)) return $row;
    foreach (['createdAt','updatedAt','issueDate','dueDate','date','startDate','endDate','plannedDate','actualDate','origDueDate'] as $k) {
      if (array_key_exists($k, $row)) $row[$k] = self::iso($row[$k]);
    }
    foreach (['isActive','approved','read','archived','isPrimary','isActive'] as $k) {
      if (array_key_exists($k, $row) && ($row[$k] === 0 || $row[$k] === 1 || $row[$k] === '0' || $row[$k] === '1'))
        $row[$k] = ((int)$row[$k]) === 1;
    }
    return $row;
  }
  public static function normList($rows) { return array_map([self::class, 'normRow'], $rows); }
}
