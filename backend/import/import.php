<?php

declare(strict_types=1);

/**
 * Создание соединения с SQLite и включение внешних ключей
 */
function connectDB(string $dbFile): PDO {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON;');
    return $db;
}

/**
 * Выполнить SQL-схему из файла
 */
function runSchema(PDO $db, string $schemaFile): void {
    $schema = file_get_contents($schemaFile);
    $db->exec($schema);
}

/**
 * Импорт аэропортов из CSV
 */
function importAirports(PDO $db, string $filePath): void {
    echo "Импорт аэропортов...\n";
    $file = new SplFileObject($filePath, 'r');
    $file->setFlags(SplFileObject::READ_CSV);
    $file->setCsvControl(',', '"', '\\');

    $stmt = $db->prepare(
        'INSERT OR IGNORE INTO airports (id, name, city, country, iata, icao) VALUES (?, ?, ?, ?, ?, ?)'
    );

    $count = 0;
    foreach ($file as $row) {
        if (!is_array($row) || count($row) < 6) {
            continue;
        }
        [$id, $name, $city, $country, $iata, $icao] = $row;
        if ($iata === '\\N' || strlen($iata) !== 3) {
            continue;
        }
        $stmt->execute([$id, $name, $city, $country, $iata, $icao]);
        if (++$count % 500 === 0) {
            echo '.';
        }
    }
    echo "\nАэропорты загружены: $count\n";
}

/**
 * Импорт авиакомпаний из CSV
 */
function importAirlines(PDO $db, string $filePath): void {
    echo "Импорт авиакомпаний...\n";
    $file = new SplFileObject($filePath, 'r');
    $file->setFlags(SplFileObject::READ_CSV);
    $file->setCsvControl(',', '"', '\\');

    $stmt = $db->prepare(
        'INSERT OR IGNORE INTO airlines (id, name, alias, iata, icao, active) VALUES (?, ?, ?, ?, ?, ?)'
    );

    $count = 0;
    foreach ($file as $row) {
        if (!is_array($row) || count($row) < 6) {
            continue;
        }
        $row = array_pad($row, 8, null);
        [$id, $name, $alias, $iata, $icao] = $row;
        $active = $row[7] ?? null;
        if ($iata === '\\N') {
            continue;
        }
        $stmt->execute([$id, $name, $alias, $iata, $icao, $active]);
        if (++$count % 500 === 0) {
            echo '.';
        }
    }
    echo "\nАвиакомпании загружены: $count\n";
}

/**
 * Импорт маршрутов из CSV с проверкой наличия внешних ключей
 */
function importRoutes(PDO $db, string $filePath): void {
    echo "Импорт маршрутов...\n";
    $file = new SplFileObject($filePath, 'r');
    $file->setFlags(SplFileObject::READ_CSV);
    $file->setCsvControl(',', '"', '\\');

    // Загружаем существующие ключи для фильтрации
    $validAirlines = [];
    foreach ($db->query('SELECT id FROM airlines') as $row) {
        $validAirlines[(int)$row['id']] = true;
    }
    $validAirports = [];
    foreach ($db->query('SELECT iata FROM airports') as $row) {
        $validAirports[$row['iata']] = true;
    }

    $stmt = $db->prepare(
        'INSERT INTO routes (
            airline, airline_id, source_airport, source_airport_id,
            destination_airport, destination_airport_id, codeshare, stops, equipment
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)' .
        ' ON CONFLICT(source_airport, destination_airport, airline_id) DO NOTHING'
    );

    $count = 0;
    $inserted = 0;
    $seen = [];

    foreach ($file as $row) {
        if (!is_array($row) || count($row) < 9) {
            continue;
        }
        $row = array_pad($row, 9, null);
        [$airline, $airline_id, $source, $source_id, $dest, $dest_id, $codeshare, $stops, $equipment] = $row;

        // Пропускаем несуществующие FK
        if (!isset($validAirlines[(int)$airline_id]) || !isset($validAirports[$source]) || !isset($validAirports[$dest])) {
            continue;
        }
        $key = implode('_', [$airline_id, $source, $dest]);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $stmt->execute([$airline, $airline_id, $source, $source_id, $dest, $dest_id, $codeshare, $stops, $equipment]);
        $inserted++;
        if (++$count % 1000 === 0) {
            echo '.';
        }
    }
    echo "\nМаршрутов загружено: $inserted\n";
}

function main(): void {
    $dbFile     = __DIR__ . '/../data/data.sqlite';
    $schemaFile = __DIR__ . '/../migrations/schema_with_fks.sql';

    if (file_exists($dbFile)) {
        unlink($dbFile);
    }
    if (!is_dir(dirname($dbFile))) {
        mkdir(dirname($dbFile), 0777, true);
    }

    $db = connectDB($dbFile);
    runSchema($db, $schemaFile);

    $files = [
        'airports'  => __DIR__ . '/raw/airports.dat',
        'airlines'  => __DIR__ . '/raw/airlines.dat',
        'routes'    => __DIR__ . '/raw/routes.dat',
    ];

    foreach ($files as $type => $path) {
        if (!file_exists($path)) {
            fwrite(STDERR, "Ошибка: файл данных для импорта «{$type}» не найден по пути: {$path}\n");
            exit(1);
        }
    }

    importAirports($db, $files['airports']);
    importAirlines($db, $files['airlines']);
    importRoutes($db, $files['routes']);

    echo "Импорт завершен успешно.\n";
}

main();
