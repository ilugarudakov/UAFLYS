<?php
declare(strict_types=1);

// Отключаем вывод HTML-ошибок
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Автозагрузчик и заголовки JSON/CORS
require __DIR__ . '/../vendor/autoload.php';
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = require __DIR__ . '/../config/database.php';
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    if ($path === '/airports') {
        $stmt = $pdo->query(<<<'SQL'
            SELECT iata, name, city, country
              FROM airports
             ORDER BY iata
        SQL);
        echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($path === '/airlines') {
        $stmt = $pdo->query(<<<'SQL'
            SELECT iata AS code, name
              FROM airlines
             ORDER BY name
        SQL);
        echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($path === '/routes') {
        $from    = $_GET['from']    ?? '';
        $to      = $_GET['to']      ?? '';
        $airline = $_GET['airline'] ?? '';
        $depth   = min(2, max(0, (int)($_GET['depth'] ?? 0)));

        if (!$from || !$to) {
            http_response_code(400);
            echo json_encode(
                ['error' => 'Параметры from и to обязательны'],
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }

        if ($depth === 0) {
            $sql = <<<SQL
                SELECT
                  r.source_airport      AS src,
                  r.destination_airport AS dst,
                  r.airline             AS code
                  FROM routes r
                 WHERE r.source_airport      = :from
                   AND r.destination_airport = :to
            SQL
                . ($airline ? " AND r.airline = :airline" : "");

            $stmt = $pdo->prepare($sql);
            $params = ['from' => $from, 'to' => $to];
            if ($airline) {
                $params['airline'] = $airline;
            }
            $stmt->execute($params);

            $routes = array_map(fn($r) => [
                'path'     => [$r['src'], $r['dst']],
                'airlines' => [$r['code']],
                'stops'    => 0,
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));

            echo json_encode($routes, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($depth === 1) {
            $sql = <<<SQL
                SELECT
                  r1.source_airport      AS src,
                  r1.destination_airport AS mid,
                  r2.destination_airport AS dst,
                  r1.airline             AS code1,
                  r2.airline             AS code2
                  FROM routes r1
                  JOIN routes r2
                    ON r1.destination_airport = r2.source_airport
                 WHERE r1.source_airport      = :from
                   AND r2.destination_airport = :to
            SQL
                . ($airline
                    ? " AND r1.airline = :airline AND r2.airline = :airline"
                    : "");

            $stmt = $pdo->prepare($sql);
            $params = ['from' => $from, 'to' => $to];
            if ($airline) {
                $params['airline'] = $airline;
            }
            $stmt->execute($params);

            $routes = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $routes[] = [
                    'path'     => [$r['src'], $r['mid'], $r['dst']],
                    'airlines' => [$r['code1'], $r['code2']],
                    'stops'    => 1,
                ];
            }

            echo json_encode($routes, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($depth === 2) {
            $sql = <<<SQL
                SELECT
                  r1.source_airport      AS src,
                  r1.destination_airport AS mid1,
                  r2.destination_airport AS mid2,
                  r3.destination_airport AS dst,
                  r1.airline             AS code1,
                  r2.airline             AS code2,
                  r3.airline             AS code3
                  FROM routes r1
                  JOIN routes r2
                    ON r1.destination_airport = r2.source_airport
                  JOIN routes r3
                    ON r2.destination_airport = r3.source_airport
                 WHERE r1.source_airport      = :from
                   AND r3.destination_airport = :to
            SQL
                . ($airline
                    ? " AND r1.airline = :airline AND r2.airline = :airline AND r3.airline = :airline"
                    : "");

            $stmt = $pdo->prepare($sql);
            $params = ['from' => $from, 'to' => $to];
            if ($airline) {
                $params['airline'] = $airline;
            }
            $stmt->execute($params);

            $routes = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $routes[] = [
                    'path'     => [$r['src'], $r['mid1'], $r['mid2'], $r['dst']],
                    'airlines' => [$r['code1'], $r['code2'], $r['code3']],
                    'stops'    => 2,
                ];
            }

            echo json_encode($routes, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found'], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Internal Server Error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
