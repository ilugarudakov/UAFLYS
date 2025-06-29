<?php
if (php_sapi_name() === 'cli-server') {
    $url  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . '/public' . $url;
    // Если файл реально существует — отдадим его напрямую
    if (is_file($file)) {
        return false;
    }
}
// Все прочие запросы — в public/index.php
require __DIR__ . '/public/index.php';
