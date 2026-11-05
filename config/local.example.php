<?php
// Copy to local.php and configure the database used by this installation.
return [
    'dsn' => 'mysql:host=127.0.0.1;dbname=APPLICATION_DATABASE;charset=utf8mb4',
    'username' => 'APPLICATION_USER',
    'password' => '',
    // Optional: Contents:read and Pull requests:read for this private repository.
    // Public repositories do not require a token. Alternatively use F125_GITHUB_TOKEN.
    'github_token' => '',
];