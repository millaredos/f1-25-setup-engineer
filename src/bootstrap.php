<?php
declare(strict_types=1);

// Project-only autoloading: names cannot select files outside the private source tree.
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $name = substr($class, 4);
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\\\\[A-Za-z][A-Za-z0-9_]*)*$/D', $name)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', $name) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
