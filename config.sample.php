<?php
// Optional: copy to config.php to override defaults.
return [
    // Folder for the SQLite database and sessions. Must be writable by PHP
    // and should not be reachable from the web.
    'data_dir' => __DIR__ . '/data',
    // How long the login lasts (days).
    'session_days' => 30,
    'timezone' => 'Europe/Zurich',
];
