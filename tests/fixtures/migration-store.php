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
    public function circuits(): array {
        $path = dirname(__DIR__) . '/config/test-circuits.json';
        $saved = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        return array_values(array_replace(array_column($this->versions(), 'circuit', 'circuit_id'), array_column($saved, null, 'id')));
    }
    public function saveCircuit(array $circuit): void {
        $path = dirname(__DIR__) . '/config/test-circuits.json';
        $rows = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        $rows[] = $circuit;
        file_put_contents($path, json_encode($rows, JSON_THROW_ON_ERROR));
    }
    public function setups(): array { return array_map(static fn($row) => ['id' => $row['id'], 'circuit_id' => $row['circuit_id'], 'title' => $row['title'], 'created_at' => $row['created_at'], 'season' => $row['season'] ?? 'unknown'], $this->versions()); }
    public function saveSetup(array $data): string {
        $rows = $this->versions(); $id = bin2hex(random_bytes(16));
        $rows[] = $data + ['id' => $id, 'circuit_id' => $data['circuit']['id'], 'created_at' => gmdate('c')];
        file_put_contents(dirname(__DIR__) . '/config/test-setups.json', json_encode($rows, JSON_THROW_ON_ERROR));
        return $id;
    }
    public function loadSetup(string $id): array { foreach ($this->versions() as $row) { if ($row['id'] === $id) { return $row; } } throw new ValidationError('Missing fixture setup.'); }
    public function updateSetup(string $id, array $data): void {
        $rows = $this->versions();
        foreach ($rows as &$row) { if ($row['id'] === $id) { $row = $data + ['id' => $id, 'circuit_id' => $row['circuit_id'], 'created_at' => $row['created_at']]; file_put_contents(dirname(__DIR__) . '/config/test-setups.json', json_encode($rows, JSON_THROW_ON_ERROR)); return; } }
        throw new ValidationError('Missing fixture setup.');
    }
    public function deleteSetup(string $id): void {
        $rows = $this->versions(); $remaining = array_values(array_filter($rows, static fn($row) => $row['id'] !== $id));
        if (count($rows) === count($remaining)) { throw new ValidationError('Missing fixture setup.'); }
        file_put_contents(dirname(__DIR__) . '/config/test-setups.json', json_encode($remaining, JSON_THROW_ON_ERROR));
    }
}
