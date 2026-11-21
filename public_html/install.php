<?php
declare(strict_types=1);

// Compatibility URL for installation state redirects; all behavior is owned by the front controller.
$_GET['page'] = 'install';
require __DIR__ . '/index.php';
