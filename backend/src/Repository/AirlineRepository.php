<?php
// backend/src/Repository/AirlineRepository.php
namespace App\Repository;

use PDO;

class AirlineRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array[] */
    public function findAll(): array
    {
        // выбираем только валидные IATA-коды длиной 2
        $sql = "SELECT iata AS code, name
                  FROM airlines
                 WHERE iata <> '' AND LENGTH(iata)=2
                 ORDER BY name";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
