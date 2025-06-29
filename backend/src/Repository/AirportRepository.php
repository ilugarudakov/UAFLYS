<?php
namespace App\Repository;

use PDO;

class AirportRepository
{
    private PDO $pdo;
    private array $allowed = ['FCO','AYT','OTP','DUS','IST'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array[] */
    public function findAll(): array
    {
        $in = "'" . implode("','", $this->allowed) . "'";
        $sql = "SELECT iata, name, city, country
                  FROM airports
                 WHERE iata IN ($in)
                 ORDER BY iata";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
