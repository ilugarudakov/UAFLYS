<?php
namespace App\Repository;

use PDO;

class RouteRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Ищет маршруты с 0–2 пересадками, возвращает:
     * [
     *   'path' => ['SRC','MID1',…,'DST'],
     *   'cities' => ['City1','City2',…],
     *   'airlines' => ['AA','BB',…],
     *   'airlineNames' => ['Airline A','Airline B',…],
     *   'stops' => int
     * ]
     *
     * @param string $from  IATA отправления
     * @param string $to    IATA назначения
     * @param string $airline  фильтр по IATA авиакомпании ('' — без фильтра)
     * @param int    $depth 0–2 (количество пересадок)
     * @return array<int, array{path:array<string>,cities:array<string>,airlines:array<string>,airlineNames:array<string>,stops:int}>
     */
    public function findRoutes(string $from, string $to, string $airline, int $depth): array
    {
        // 0 пересадок
        if ($depth === 0) {
            $sql = <<<SQL
                SELECT
                  r.source_airport      AS src_code,
                  sa.city               AS src_city,
                  r.destination_airport AS dst_code,
                  da.city               AS dst_city,
                  r.airline             AS code,
                  al.name               AS airline_name
                  FROM routes r
                  JOIN airports sa  ON sa.iata = r.source_airport
                  JOIN airports da  ON da.iata = r.destination_airport
                  JOIN airlines al  ON al.iata = r.airline
                 WHERE r.source_airport      = :from
                   AND r.destination_airport = :to
            SQL
                . ($airline ? " AND r.airline = :airline" : "");

            $stmt = $this->pdo->prepare($sql);
            $params = ['from'=>$from,'to'=>$to];
            if ($airline) {
                $params['airline'] = $airline;
            }
            $stmt->execute($params);

            return array_map(function(array $r) {
                return [
                    'path'         => [$r['src_code'],$r['dst_code']],
                    'cities'       => [$r['src_city'],$r['dst_city']],
                    'airlines'     => [$r['code']],
                    'airlineNames' => [$r['airline_name']],
                    'stops'        => 0,
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // 1 пересадка
        if ($depth === 1) {
            $sql = <<<SQL
                SELECT
                  r1.source_airport      AS src_code,
                  sa.city               AS src_city,
                  r1.destination_airport AS mid_code,
                  ma.city               AS mid_city,
                  r2.destination_airport AS dst_code,
                  da.city               AS dst_city,
                  r1.airline             AS code1,
                  al1.name               AS name1,
                  r2.airline             AS code2,
                  al2.name               AS name2
                  FROM routes r1
                  JOIN airports sa  ON sa.iata = r1.source_airport
                  JOIN airports ma  ON ma.iata = r1.destination_airport
                  JOIN routes r2  ON r2.source_airport = r1.destination_airport
                  JOIN airports da  ON da.iata = r2.destination_airport
                  JOIN airlines al1 ON al1.iata = r1.airline
                  JOIN airlines al2 ON al2.iata = r2.airline
                 WHERE r1.source_airport      = :from
                   AND r2.destination_airport = :to
            SQL
                . ($airline
                    ? " AND r1.airline = :airline AND r2.airline = :airline"
                    : "");

            $stmt = $this->pdo->prepare($sql);
            $params = ['from'=>$from,'to'=>$to];
            if ($airline) {
                $params['airline'] = $airline;
            }
            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = [];
            foreach ($rows as $r) {
                $result[] = [
                    'path'         => [$r['src_code'],$r['mid_code'],$r['dst_code']],
                    'cities'       => [$r['src_city'],$r['mid_city'],$r['dst_city']],
                    'airlines'     => [$r['code1'],$r['code2']],
                    'airlineNames' => [$r['name1'],$r['name2']],
                    'stops'        => 1,
                ];
            }
            return $result;
        }

        // 2 пересадки
        if ($depth === 2) {
            $sql = <<<SQL
                SELECT
                  r1.source_airport      AS src_code,
                  s1.city               AS src_city,
                  r1.destination_airport AS mid1_code,
                  m1.city               AS mid1_city,
                  r2.destination_airport AS mid2_code,
                  m2.city               AS mid2_city,
                  r3.destination_airport AS dst_code,
                  da.city               AS dst_city,
                  r1.airline             AS code1,
                  al1.name               AS name1,
                  r2.airline             AS code2,
                  al2.name               AS name2,
                  r3.airline             AS code3,
                  al3.name               AS name3
                  FROM routes r1
                  JOIN airports s1  ON s1.iata = r1.source_airport
                  JOIN airports m1  ON m1.iata = r1.destination_airport
                  JOIN routes r2   ON r2.source_airport = r1.destination_airport
                  JOIN airports m2  ON m2.iata = r2.destination_airport
                  JOIN routes r3   ON r3.source_airport = r2.destination_airport
                  JOIN airports da  ON da.iata = r3.destination_airport
                  JOIN airlines al1 ON al1.iata = r1.airline
                  JOIN airlines al2 ON al2.iata = r2.airline
                  JOIN airlines al3 ON al3.iata = r3.airline
                 WHERE r1.source_airport      = :from
                   AND r3.destination_airport = :to
            SQL
                . ($airline
                    ? " AND r1.airline = :airline AND r2.airline = :airline AND r3.airline = :airline"
                    : "");

            $stmt = $this->pdo->prepare($sql);
            $params = ['from'=>$from,'to'=>$to];
            if ($airline) {
                $params['airline'] = $airline;
            }
            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = [];
            foreach ($rows as $r) {
                $result[] = [
                    'path'         => [$r['src_code'],$r['mid1_code'],$r['mid2_code'],$r['dst_code']],
                    'cities'       => [$r['src_city'],$r['mid1_city'],$r['mid2_city'],$r['dst_city']],
                    'airlines'     => [$r['code1'],$r['code2'],$r['code3']],
                    'airlineNames' => [$r['name1'],$r['name2'],$r['name3']],
                    'stops'        => 2,
                ];
            }
            return $result;
        }

        return [];
    }
}
