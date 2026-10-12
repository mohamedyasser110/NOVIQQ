<?php
// Jwt.php — HS256 manual (no composer). Compatible with Nest @nestjs/jwt default.
class Jwt {
  public static function b64u_encode($bin) {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
  }
  public static function b64u_decode($s) {
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    return base64_decode($s);
  }
  public static function sign($payload, $secret, $ttl = 900) {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $now = time();
    $payload['iat'] = $now;
    $payload['exp'] = $now + (int)$ttl;
    $h = self::b64u_encode(json_encode($header));
    $p = self::b64u_encode(json_encode($payload));
    $sig = hash_hmac('sha256', "$h.$p", $secret, true);
    return "$h.$p." . self::b64u_encode($sig);
  }
  public static function verify($token, $secret) {
    $parts = explode('.', (string)$token);
    if (count($parts) !== 3) return null;
    list($h, $p, $s) = $parts;
    $expect = hash_hmac('sha256', "$h.$p", $secret, true);
    $got = self::b64u_decode($s);
    if ($got === false || !hash_equals($expect, (string)$got)) return null;
    $payload = json_decode(self::b64u_decode($p), true);
    if (!is_array($payload)) return null;
    if (isset($payload['exp']) && time() > (int)$payload['exp']) return null;
    return $payload;
  }
}
