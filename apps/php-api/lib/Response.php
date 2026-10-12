<?php
// Response.php — JSON helpers with Nest-compatible status codes.
class TestResponse extends Exception {
  public $status; public $data;
  public function __construct($data, $status) { parent::__construct('test-response'); $this->data = $data; $this->status = $status; }
}
class Response {
  public static function json($data, $status = 200) {
    if (defined('PHP_API_TEST') && PHP_API_TEST) throw new TestResponse($data, $status);
    http_response_code($status);
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
  public static function err($status, $message) {
    self::json(['statusCode' => $status, 'message' => $message, 'error' => self::label($status)], $status);
  }
  private static function label($s) {
    return [400=>'Bad Request',401=>'Unauthorized',403=>'Forbidden',404=>'Not Found',409=>'Conflict',422=>'Unprocessable Entity',500=>'Internal Server Error'][$s] ?? 'Error';
  }
  public static function bad($m) { self::err(400, $m); }
  public static function unauth($m = 'unauthorized') { self::err(401, $m); }
  public static function forbidden($m) { self::err(403, $m); }
  public static function notFound($m) { self::err(404, $m); }
  public static function conflict($m) { self::err(409, $m); }
}
