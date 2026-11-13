<?php

require_once __DIR__ . '/bootstrap.php';

function clh_db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = clh_config()['db'];
    $dsn = 'mysql:host=' . $db['host'] . ';dbname=' . $db['name'] . ';charset=' . $db['charset'];
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function clh_catalog()
{
    $pdo = clh_db();
    $levels = $pdo->query('SELECT * FROM levels ORDER BY sort_order, id')->fetchAll();
    $tools = $pdo->query('SELECT * FROM tools ORDER BY sort_order, slug')->fetchAll();
    $perms = [];
    foreach ($pdo->query('SELECT level_id, tool, allowed FROM level_tools') as $row) {
        $perms[(int) $row['level_id']][$row['tool']] = (int) $row['allowed'] === 1;
    }
    return [
        'levels' => $levels,
        'tools' => $tools,
        'perms' => $perms,
    ];
}
