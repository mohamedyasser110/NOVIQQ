<?php
// Money.php — port of money.ts (minor units only, largest-remainder).
class Money {
  public static function splitAllocation($basis, $lines) {
    if (!is_int($basis) || $basis < 0) throw new Exception('basis must be non-negative int');
    $out = []; $assigned = 0;
    $fixed = []; $pct = []; $w = [];
    foreach ($lines as $l) {
      if (isset($l['amount'])) $fixed[] = $l;
      elseif (isset($l['percent'])) $pct[] = $l;
      else $w[] = $l;
    }
    foreach ($fixed as $l) {
      if (!is_int($l['amount']) || $l['amount'] < 0) throw new Exception('bad fixed amount ' . ($l['key'] ?? ''));
      $out[] = ['key' => $l['key'], 'amount' => $l['amount']];
      $assigned += $l['amount'];
    }
    $totalPct = 0; foreach ($pct as $l) $totalPct += $l['percent'];
    if ($totalPct - 100 > 1e-9) throw new Exception("percentages exceed 100: $totalPct");
    if (count($pct)) {
      $tmp = [];
      foreach ($pct as $l) { $raw = $basis * $l['percent'] / 100; $tmp[] = ['key' => $l['key'], 'raw' => $raw, 'amt' => (int)floor($raw)]; }
      $used = 0; foreach ($tmp as $t) $used += $t['amt'];
      $resid = $basis - $assigned - $used;
      usort($tmp, function($a,$b){ return ($b['raw']-$b['amt']) <=> ($a['raw']-$a['amt']); });
      $i = 0;
      while ($resid > 0 && count($tmp)) {
        $t = $tmp[$i % count($tmp)];
        if ($t['amt'] < (int)ceil($t['raw'])) { $tmp[$i % count($tmp)]['amt']++; $resid--; }
        $i++;
        if ($i > count($tmp) * 3 + $resid + 10) break;
      }
      foreach ($tmp as $t) { $out[] = ['key' => $t['key'], 'amount' => $t['amt']]; $assigned += $t['amt']; }
    }
    if (count($w)) {
      $totalW = 0; foreach ($w as $l) $totalW += ($l['weight'] ?? 0);
      if ($totalW <= 0) throw new Exception('weights must sum > 0');
      $remain = $basis - $assigned;
      if ($remain < 0) throw new Exception('fixed amounts exceed basis');
      $tmp = [];
      foreach ($w as $l) { $raw = $remain * ($l['weight'] ?? 0) / $totalW; $tmp[] = ['key' => $l['key'], 'raw' => $raw, 'amt' => (int)floor($raw)]; }
      $used = 0; foreach ($tmp as $t) $used += $t['amt'];
      $resid = $remain - $used;
      usort($tmp, function($a,$b){ return ($b['raw']-$b['amt']) <=> ($a['raw']-$a['amt']); });
      for ($i = 0; $resid > 0 && count($tmp); $i++, $resid--) $tmp[$i % count($tmp)]['amt']++;
      foreach ($tmp as $t) { $out[] = ['key' => $t['key'], 'amount' => $t['amt']]; $assigned += $t['amt']; }
    }
    return ['out' => $out, 'unallocated' => $basis - $assigned];
  }
  public static function assertBalanced($entries) {
    $s = 0; foreach ($entries as $e) $s += $e['amount'];
    if ($s !== 0) throw new Exception("ledger entries must sum to 0, got $s");
  }
}
