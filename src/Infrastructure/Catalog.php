<?php
declare(strict_types=1);

namespace App\Infrastructure;

use App\Model\F1Car;
use App\Model\ValidationError;

/** Reads distributed data and assembles edition-specific objects without owning business validation. */
final class Catalog
{
    private string $directory;

    /** Locate versioned product data independently of deployment URL and current directory. */
    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    /** Read a fixed internal catalog, propagating corrupt data instead of fabricating defaults. */
    public function read(string $name): array
    {
        if (!preg_match('/^[a-z0-9-]+$/D', $name)) {
            throw new \LogicException('Invalid internal catalog name.');
        }
        return json_decode(file_get_contents($this->directory . '/' . $name . '.json'), true, 64, JSON_THROW_ON_ERROR);
    }

    /** Supported editions come from product data, not duplicated lists in controllers or JavaScript. */
    public function editions(): array
    {
        return $this->read('editions');
    }

    /** Instantiate a car with edition-owned presets, never copying values between seasons. */
    public function car(string $edition): F1Car
    {
        $definition = $this->editions()[$edition] ?? null;
        if ($definition === null) {
            throw new ValidationError('Selecciona una temporada disponible.');
        }
        return new F1Car($edition, $this->read($definition['parameters'])['parameters'], $definition['presets'] === null ? [] : $this->read($definition['presets']));
    }

    /** Legacy files without a season use the shared contract without receiving an invented edition. */
    public function unidentifiedCar(): F1Car
    {
        return new F1Car('unknown', $this->read('parameters')['parameters'], []);
    }

    /** Return identities available in this edition, independent of personal imported profiles. */
    public function circuits(string $edition): array
    {
        $definition = $this->editions()[$edition] ?? null;
        if ($definition === null) {
            throw new ValidationError('Temporada desconocida.');
        }
        return array_values(array_filter($this->read('catalog')['circuits'], static fn (array $row): bool => !in_array($row['id'], $definition['excludedCircuits'], true)));
    }
}
