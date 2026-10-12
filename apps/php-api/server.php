<?php
// server.php — local dev router for `php -S localhost:4100 apps/php-api/server.php`
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri !== '/' && is_file(__DIR__ . '/public' . $uri)) return false;
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/public/index.php';
