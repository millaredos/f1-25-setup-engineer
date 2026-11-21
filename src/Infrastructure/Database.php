<?php
declare(strict_types=1);

namespace App\Infrastructure;

/** Owns connection configuration; credentials never enter controllers or templates. */
final class Database
{
    /** Open a MariaDB connection using native prepared statements. */
    public static function connect(string $root): \PDO
    {
        $config = ['dsn' => getenv('F125_DSN') ?: '', 'username' => getenv('F125_DB_USER') ?: '', 'password' => getenv('F125_DB_PASSWORD') ?: ''];
        if (is_file($root . '/config/local.php')) {
            $local = require $root . '/config/local.php';
            if (!is_array($local)) {
                throw new \RuntimeException('Invalid private configuration.');
            }
            $config = array_replace($config, $local);
        }
        if (!is_string($config['dsn']) || !str_starts_with($config['dsn'], 'mysql:')) {
            throw new \RuntimeException('Configure MariaDB in config/local.php.');
        }
        return new \PDO($config['dsn'], $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, \PDO::ATTR_EMULATE_PREPARES => false]);
    }
}
