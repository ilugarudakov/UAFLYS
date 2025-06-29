<?php
namespace App\Controller;

use App\Repository\AirlineRepository;

class AirlineController
{
    private AirlineRepository $repo;
    public function __construct(AirlineRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($this->repo->findAll(), JSON_UNESCAPED_UNICODE);
    }
}
