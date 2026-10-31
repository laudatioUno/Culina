<?php
declare(strict_types=1);

define('CULINA_ROOT', dirname(__DIR__));

require __DIR__ . '/version.php';

$GLOBALS['culina_config'] = [
    'data_dir' => CULINA_ROOT . '/data',
    'session_days' => 30,
    'timezone' => 'Europe/Zurich',
];
if (is_file(CULINA_ROOT . '/config.php')) {
    $GLOBALS['culina_config'] = array_merge($GLOBALS['culina_config'], (array) require CULINA_ROOT . '/config.php');
}
if (getenv('CULINA_DATA_DIR')) {
    $GLOBALS['culina_config']['data_dir'] = (string) getenv('CULINA_DATA_DIR');
}

function config(string $key): mixed
{
    return $GLOBALS['culina_config'][$key] ?? null;
}

date_default_timezone_set((string) config('timezone'));
mb_internal_encoding('UTF-8');

require __DIR__ . '/helpers.php';
require __DIR__ . '/units.php';
require __DIR__ . '/planner.php';
require __DIR__ . '/db.php';
require __DIR__ . '/repo.php';
require __DIR__ . '/shopping.php';
require __DIR__ . '/importer.php';
require __DIR__ . '/pdf.php';
require __DIR__ . '/auth.php';
