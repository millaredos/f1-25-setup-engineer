<?php

declare(strict_types=1);

/** File-backed storage fixture used only inside the isolated browser test application. */
// Used only in an isolated web test copy to exercise the real migration page.
final class Store
{
    /**
     * Match the production Store interface without opening a database.
     */
    public function __construct(array $config)
    {
    }

    /**
     * Represent a ready fixture schema; no real database is contacted.
     */
    public function ready(): void
    {
    }

    /**
     * Expose pending state through a marker owned by the temporary test site.
     */
    public function pendingMigrations(): array
    {
        return is_file(dirname(__DIR__) . '/config/migration-applied') ? [] : ['001-game-catalog'];
    }

    /**
     * Complete the simulated migration through that isolated marker.
     */
    public function migrate(): void
    {
        file_put_contents(dirname(__DIR__) . '/config/migration-applied', 'done');
    }

    /**
     * Load bundled fixture catalog data in the temporary site.
     */
    public function gameCatalog(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/config/game-catalog.json'), true, 64, JSON_THROW_ON_ERROR);
    }

    /**
     * Read only the temporary JSON setup store used by browser tests.
     */
    private function versions(): array
    {
        return is_file(dirname(__DIR__) . '/config/test-setups.json') ? json_decode(file_get_contents(dirname(__DIR__) . '/config/test-setups.json'), true) : [];
    }

    /**
     * Merge fixture snapshots and explicitly imported fixture circuits for selectors.
     */
    public function circuits(): array
    {
        $path = dirname(__DIR__) . '/config/test-circuits.json';
        $saved = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        return array_values(array_replace(array_column($this->versions(), 'circuit', 'circuit_id'), array_column($saved, null, 'id')));
    }

    /**
     * Append a circuit to the temporary JSON store, never the real application database.
     */
    public function saveCircuit(array $circuit): void
    {
        $path = dirname(__DIR__) . '/config/test-circuits.json';
        $rows = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        $rows[] = $circuit;
        file_put_contents($path, json_encode($rows, JSON_THROW_ON_ERROR));
    }

    /**
     * Project the fixture metadata returned by the production selection API.
     */
    public function setups(): array
    {
        return array_map(static fn ($row) => ['id' => $row['id'], 'circuit_id' => $row['circuit_id'], 'title' => $row['title'], 'created_at' => $row['created_at'], 'season' => $row['season'] ?? 'unknown'], $this->versions());
    }

    /**
     * Assign a fixture identifier and retain the full draft for round-trip tests.
     */
    public function saveSetup(array $data): string
    {
        $rows = $this->versions();
        $id = bin2hex(random_bytes(16));
        $rows[] = $data + ['id' => $id, 'circuit_id' => $data['circuit']['id'], 'created_at' => gmdate('c')];
        file_put_contents(dirname(__DIR__) . '/config/test-setups.json', json_encode($rows, JSON_THROW_ON_ERROR));
        return $id;
    }

    /**
     * Find one fixture snapshot or fail so missing-record behavior is exercised.
     */
    public function loadSetup(string $id): array
    {
        foreach ($this->versions() as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }
        throw new ValidationError('Missing fixture setup.');
    }

    /**
     * Replace one fixture payload while retaining its identifier and creation metadata.
     */
    public function updateSetup(string $id, array $data): void
    {
        $rows = $this->versions();
        foreach ($rows as &$row) {
            if ($row['id'] === $id) {
                $row = $data + ['id' => $id, 'circuit_id' => $row['circuit_id'], 'created_at' => $row['created_at']];
                file_put_contents(dirname(__DIR__) . '/config/test-setups.json', json_encode($rows, JSON_THROW_ON_ERROR));
                return;
            }
        }
        throw new ValidationError('Missing fixture setup.');
    }

    /**
     * Remove exactly one fixture record and persist the remaining temporary rows.
     */
    public function deleteSetup(string $id): void
    {
        $rows = $this->versions();
        $remaining = array_values(array_filter($rows, static fn ($row) => $row['id'] !== $id));
        if (count($rows) === count($remaining)) {
            throw new ValidationError('Missing fixture setup.');
        }
        file_put_contents(dirname(__DIR__) . '/config/test-setups.json', json_encode($remaining, JSON_THROW_ON_ERROR));
    }
}
