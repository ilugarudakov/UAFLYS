<?php
declare(strict_types=1);

// === CORS ===
// Разрешаем запросы с любых источников, методы и заголовки
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Далее — JSON по умолчанию
header('Content-Type: application/json; charset=UTF-8');

// Отключаем HTML-ошибки
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Автозагрузка и подключение БД
require __DIR__ . '/../vendor/autoload.php';
$pdo = require __DIR__ . '/../config/database.php';

// Мапа путей → [Controller, метод, Репозиторий]
use App\Controller\AirportController;
use App\Controller\AirlineController;
use App\Controller\RouteController;
use App\Repository\AirportRepository;
use App\Repository\AirlineRepository;
use App\Repository\RouteRepository;

$routes = [
    '/airports' => [AirportController::class, 'list',   AirportRepository::class],
    '/airlines' => [AirlineController::class, 'list',   AirlineRepository::class],
    '/routes'   => [RouteController::class,   'search', RouteRepository::class],
];

$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

if (isset($routes[$path])) {
    [$ctrlClass, $method, $repoClass] = $routes[$path];
    $repo       = new $repoClass($pdo);
    $controller = new $ctrlClass($repo);
    $controller->$method();
    exit;
}

// 404
http_response_code(404);
echo json_encode(['error'=>'Not found'], JSON_UNESCAPED_UNICODE);
