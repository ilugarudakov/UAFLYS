<?php
namespace App\Controller;

use App\Repository\AirportRepository;

class AirportController
{
    private AirportRepository $repo;
    public function __construct(AirportRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($this->repo->findAll(), JSON_UNESCAPED_UNICODE);
    }
}
