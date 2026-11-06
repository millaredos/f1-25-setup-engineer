<?php
declare(strict_types=1);
// Used only in an isolated web test copy to exercise the real migration page.
final class Store
{
    public function __construct(array $config) {}
    public function ready(): void {}
    public function pendingMigrations(): array { return is_file(dirname(__DIR__) . '/config/migration-applied') ? [] : ['001-game-catalog']; }
    public function migrate(): void { file_put_contents(dirname(__DIR__) . '/config/migration-applied', 'done'); }
    public function gameCatalog(): array { return ['presets' => [['name' => 'Máxima carga aerodinámica'], ['name' => 'Mayor carga aerodinámica'], ['name' => 'Equilibrado'], ['name' => 'Mayor velocidad punta'], ['name' => 'Máxima velocidad punta']]]; }
}
