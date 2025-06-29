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
     * @param string $from     IATA исходного аэропорта
     * @param string $to       IATA конечного аэропорта
     * @param string $airline  IATA авиакомпании (или '' — без фильтра)
     * @param int    $depth    0–2 (число пересадок)
     * @return array<int, array{
     *     path: string[],
     *     cities: string[],
     *     airlines: string[],
     *     airlineNames: string[],
     *     stops: int
     * }>
     */
    public function findRoutes(string $from, string $to, string $airline, int $depth): array
    {
        if ($depth === 0) {
            return $this->findDirect($from, $to, $airline);
        }
        if ($depth === 1) {
            return $this->findOneStop($from, $to, $airline);
        }
        if ($depth === 2) {
            return $this->findTwoStops($from, $to, $airline);
        }
        return [];
    }

    private function findDirect(string $from, string $to, string $airline): array
    {
        $sql = <<<SQL
            SELECT
              r.source_airport      AS src_code,
              sa.city               AS src_city,
              sa.country            AS src_country,
              r.destination_airport AS dst_code,
              da.city               AS dst_city,
              da.country            AS dst_country,
              r.airline             AS code,
              al.name               AS airline_name
            FROM routes r
            JOIN airports sa  ON sa.iata = r.source_airport
            JOIN airports da  ON da.iata = r.destination_airport
            JOIN airlines al  ON al.iata  = r.airline
           WHERE r.source_airport      = :from
             AND r.destination_airport = :to
        SQL
            . ($airline ? " AND r.airline = :airline" : "");

        $stmt = $this->pdo->prepare($sql);
        $params = ['from' => $from, 'to' => $to];
        if ($airline) {
            $params['airline'] = $airline;
        }
        $stmt->execute($params);

        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $results[] = [
                'path'         => [$r['src_code'], $r['dst_code']],
                'cities'       => [
                    "{$r['src_city']}, {$r['src_country']}",
                    "{$r['dst_city']}, {$r['dst_country']}"
                ],
                'airlines'     => [$r['code']],
                'airlineNames' => [$r['airline_name']],
                'stops'        => 0,
            ];
        }
        return $results;
    }

    private function findOneStop(string $from, string $to, string $airline): array
    {
        $sql = <<<SQL
            SELECT
              r1.source_airport      AS src_code,
              sa.city               AS src_city,
              sa.country            AS src_country,
              r1.destination_airport AS mid_code,
              ma.city               AS mid_city,
              ma.country            AS mid_country,
              r2.destination_airport AS dst_code,
              da.city               AS dst_city,
              da.country            AS dst_country,
              r1.airline             AS code1,
              al1.name               AS name1,
              r2.airline             AS code2,
              al2.name               AS name2
            FROM routes r1
            JOIN airports sa  ON sa.iata = r1.source_airport
            JOIN airports ma  ON ma.iata = r1.destination_airport
            JOIN routes r2   ON r2.source_airport = r1.destination_airport
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
        $params = ['from' => $from, 'to' => $to];
        if ($airline) {
            $params['airline'] = $airline;
        }
        $stmt->execute($params);

        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $results[] = [
                'path'         => [$r['src_code'], $r['mid_code'], $r['dst_code']],
                'cities'       => [
                    "{$r['src_city']}, {$r['src_country']}",
                    "{$r['mid_city']}, {$r['mid_country']}",
                    "{$r['dst_city']}, {$r['dst_country']}"
                ],
                'airlines'     => [$r['code1'], $r['code2']],
                'airlineNames' => [$r['name1'], $r['name2']],
                'stops'        => 1,
            ];
        }
        return $results;
    }

    private function findTwoStops(string $from, string $to, string $airline): array
    {
        $sql = <<<SQL
            SELECT
              r1.source_airport      AS src_code,
              sa.city                AS src_city,
              sa.country             AS src_country,
              r1.destination_airport AS mid1_code,
              m1.city                AS mid1_city,
              m1.country             AS mid1_country,
              r2.destination_airport AS mid2_code,
              m2.city                AS mid2_city,
              m2.country             AS mid2_country,
              r3.destination_airport AS dst_code,
              da.city                AS dst_city,
              da.country             AS dst_country,
              r1.airline             AS code1,
              al1.name               AS name1,
              r2.airline             AS code2,
              al2.name               AS name2,
              r3.airline             AS code3,
              al3.name               AS name3
            FROM routes r1
            JOIN airports sa  ON sa.iata = r1.source_airport
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
        $params = ['from' => $from, 'to' => $to];
        if ($airline) {
            $params['airline'] = $airline;
        }
        $stmt->execute($params);

        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            // Собираем полный путь в массив
            $path = [
                $r['src_code'],
                $r['mid1_code'],
                $r['mid2_code'],
                $r['dst_code']
            ];
            // Если есть повторения — пропускаем
            if (count(array_unique($path)) < count($path)) {
                continue;
            }

            $results[] = [
                'path'         => $path,
                'cities'       => [
                    "{$r['src_city']}, {$r['src_country']}",
                    "{$r['mid1_city']}, {$r['mid1_country']}",
                    "{$r['mid2_city']}, {$r['mid2_country']}",
                    "{$r['dst_city']}, {$r['dst_country']}"
                ],
                'airlines'     => [$r['code1'], $r['code2'], $r['code3']],
                'airlineNames' => [$r['name1'], $r['name2'], $r['name3']],
                'stops'        => 2,
            ];
        }
        return $results;
    }
}
