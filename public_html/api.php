<?php
declare(strict_types=1);

// Existing integrations may keep their endpoint while controllers use the new routing scheme.
$_GET['api'] = $_GET['action'] ?? 'bootstrap';
require __DIR__ . '/index.php';
