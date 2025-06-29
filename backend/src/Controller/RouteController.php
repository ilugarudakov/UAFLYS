<?php
namespace App\Controller;

use App\Repository\RouteRepository;

class RouteController
{
    private RouteRepository $repo;
    public function __construct(RouteRepository $repo)
    {
        $this->repo = $repo;
    }

    public function search(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $from    = $_GET['from']    ?? '';
        $to      = $_GET['to']      ?? '';
        $airline = $_GET['airline'] ?? '';
        $depth   = min(2, max(0, (int)($_GET['depth'] ?? 0)));

        // Новая проверка: если точки совпадают — сразу пустой массив
        if ($from === $to) {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$from || !$to) {
            http_response_code(400);
            echo json_encode(['error'=>'from и to обязательны'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = $this->repo->findRoutes($from, $to, $airline, $depth);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
