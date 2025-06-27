<?php
$dbFile = __DIR__ . '/../data/data.sqlite';

if (!file_exists($dbFile)) {
    if (!is_dir(dirname($dbFile))) {
        mkdir(dirname($dbFile), 0777, true);
    }
    touch($dbFile);
}

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}

return $pdo;
