<?php

/*
| LOCAL MEASUREMENT ONLY. Router for PHP's built-in server that gzips PHP-rendered responses (as Apache/Nginx would in
| production). With PERF_GZIP_STATIC=1 it also gzips CSS/JS, simulating the compression rules in public/.htaccess /
| the Nginx snippet in STOREFRONT_DEPLOYMENT_CHECKLIST.md. Run from public/:
|   php -S 127.0.0.1:8124 ../tests/Support/perf-router.php
*/

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$file = getcwd().$uri;
if ($uri !== '/' && is_file($file)) {
    $types = ['css' => 'text/css; charset=UTF-8', 'js' => 'application/javascript; charset=UTF-8'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (getenv('PERF_GZIP_STATIC') && isset($types[$ext]) && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
        $body = gzencode(file_get_contents($file), 6);
        header('Content-Type: '.$types[$ext]);
        header('Content-Encoding: gzip');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Content-Length: '.strlen($body));
        echo $body;

        return true;
    }

    return false;
}
ini_set('zlib.output_compression', '1');
require getcwd().'/index.php';
