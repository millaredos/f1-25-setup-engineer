<?php
declare(strict_types=1);

// Shared layout owns navigation and semantic landmarks; page templates receive prepared values only.
$links = ['home' => 'Inicio', 'circuits' => 'Circuitos', 'workshop' => 'Taller', 'tests' => 'Pruebas', 'faq' => 'Docs'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup Engineer · F1 25 / 26</title>
    <link rel="stylesheet" href="assets/base.css">
    <link rel="stylesheet" href="assets/components.css">
    <link rel="stylesheet" href="assets/workshop.css">
    <script type="module" src="assets/navigation.js">
</script>
    <?php if (in_array($page ?? '', ['circuits', 'workshop', 'tests'], true)): ?>
    <script type="module" src="assets/workshop.js">
</script>
    <?php endif; ?>
</head>
<body data-page="<?= $escape($page ?? '') ?>">
<a class="skip-link" href="#main">Saltar al contenido</a>
<header class="site-header">
    <a class="brand" href="./">
<strong>SETUP ENGINEER</strong>
<span>Tu taller de F1 25 / 26</span>
</a>
    <nav aria-label="Apartados">
        <?php foreach ($links as $route => $label): ?>
        <a href="?page=<?= $escape($route) ?>" <?= ($page ?? '') === $route ? 'aria-current="page"' : '' ?>>
<?= $escape($translate($label)) ?>
</a>
        <?php endforeach; ?>
    </nav>
    <details class="menu">
<summary>Versión <?= $escape($version ?? '') ?>
<span id="update-indicator" hidden> · Actualización</span>
</summary>
<div class="panel">
<a href="?page=options">Opciones</a>
<a href="?page=updates">Actualizaciones</a>
<a href="?page=database">Base de datos</a>
</div>
</details>
</header>
<main id="main" class="container">
    <?php if (!empty($error)): ?>
<p class="alert alert-error" role="alert">
<?= $escape($error) ?>
</p>
<?php endif; ?>
    <?php if (!empty($message)): ?>
<p class="alert" role="status">
<?= $escape($message) ?>
</p>
<?php endif; ?>
    <?php require __DIR__ . '/' . $template . '.php'; ?>
</main>
<footer class="site-footer">F1 25 / 26 Setup Engineer · <a href="https://github.com/millaredos/f1-25-setup-engineer" rel="noopener noreferrer">GitHub</a>
</footer>
</body>
</html>
