<?php
declare(strict_types=1);
// Used only in an isolated web test copy to exercise the real migration page.
final class Store
{
    public function __construct(array $config) {}
    public function ready(): void {}
    public function pendingMigrations(): array { return is_file(dirname(__DIR__) . '/config/migration-applied') ? [] : ['001-game-catalog']; }
    public function migrate(): void { file_put_contents(dirname(__DIR__) . '/config/migration-applied', 'done'); }
    public function gameCatalog(): array { return json_decode(file_get_contents(dirname(__DIR__) . '/config/game-catalog.json'), true, 64, JSON_THROW_ON_ERROR); }
    private function versions(): array { return is_file(dirname(__DIR__) . '/config/test-setups.json') ? json_decode(file_get_contents(dirname(__DIR__) . '/config/test-setups.json'), true) : []; }
    public function circuits(): array { return array_values(array_column($this->versions(), 'circuit', 'circuit_id')); }
    public function setups(): array { return array_map(static fn($row) => ['id' => $row['id'], 'circuit_id' => $row['circuit_id'], 'title' => $row['title'], 'created_at' => $row['created_at']], $this->versions()); }
    public function saveSetup(array $data): string {
        $rows = $this->versions(); $id = (string) (count($rows) + 1);
        $rows[] = $data + ['id' => $id, 'circuit_id' => $data['circuit']['id'], 'created_at' => gmdate('c')];
        file_put_contents(dirname(__DIR__) . '/config/test-setups.json', json_encode($rows, JSON_THROW_ON_ERROR));
        return $id;
    }
    public function loadSetup(string $id): array { foreach ($this->versions() as $row) { if ($row['id'] === $id) { return $row; } } throw new ValidationError('Missing fixture setup.'); }
}
