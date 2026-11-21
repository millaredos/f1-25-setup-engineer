<?php
declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\Catalog;
use App\Model\Circuit;
use App\Model\Fields;
use App\Model\ValidationError;
use App\Model\Setup;

/** Adapts published setup formats to the model; no persistence or HTTP side effects. */
final class SetupCodec
{
    private Catalog $catalog;

    /** Inject known product data instead of loading it in each entity. */
    public function __construct(Catalog $catalog)
    {
        $this->catalog = $catalog;
    }

    /** Validate supported imports, preserving explicit references and unknown metadata. */
    public function decode(mixed $input, bool $saving = false): array
    {
        if (!is_array($input)) {
            throw new ValidationError('El reglaje debe ser un objeto JSON.');
        }
        $format = $input['format'] ?? null;
        if ($format !== null && !in_array($format, ['f125-setup-engineer', 'f125-comparador-v2'], true)) {
            throw new ValidationError('Formato de reglaje desconocido.');
        }
        if ($format === 'f125-setup-engineer' && ($input['schemaVersion'] ?? null) !== 1) {
            throw new ValidationError('Versión de esquema no admitida.');
        }
        if (isset($input['referenceIsCapture']) && !is_bool($input['referenceIsCapture'])) {
            throw new ValidationError('referenceIsCapture debe ser booleano.');
        }
        $season = Fields::choice($input['season'] ?? 'unknown', array_merge(['unknown'], array_map('strval', array_keys($this->catalog->editions()))), 'Temporada');
        if ($saving && ($season === 'unknown' || !isset($input['reference'], $input['current'], $input['circuit']))) {
            throw new ValidationError('Selecciona temporada, circuito, referencia y valores antes de guardar.');
        }
        // An unspecified legacy season uses only the shared parameter contract, not a guessed edition.
        $car = $season === 'unknown' ? $this->catalog->unidentifiedCar() : $this->catalog->car($season);
        $referenceInput = $input['reference'] ?? $input['base'] ?? null;
        $warnings = $referenceInput === null ? ['El archivo no contiene referencia; se propone una copia sin atribuirla al juego.'] : [];
        $circuit = isset($input['circuit']) ? new Circuit($input['circuit'], $saving ? $this->catalog->circuits($season) : null) : null;
        $source = 'unknown';
        if ($referenceInput !== null) {
            $source = $format === 'f125-setup-engineer' ? ($input['referenceSource'] ?? 'unknown') : (($input['referenceIsCapture'] ?? false) ? 'capture' : 'unknown');
        }
        $canonical = array_replace($input, ['season' => $season, 'current' => $input['current'] ?? $input['values'] ?? $input, 'reference' => $referenceInput, 'referenceSource' => $source]);
        $data = (new Setup($car, $canonical, $circuit, $saving))->data();
        if (!$saving) {
            $data['warnings'] = $warnings;
        }
        return $data;
    }

    /** Validate legacy partial browser drafts without turning validation fillers into recovered values. */
    public function recover(mixed $input): array
    {
        if (!is_array($input) || !is_array($input['current'] ?? null)) { throw new ValidationError('Borrador de navegador inválido.'); }
        $season = Fields::choice($input['season'] ?? 'unknown', array_merge(['unknown'], array_map('strval', array_keys($this->catalog->editions()))), 'Temporada');
        $car = $season === 'unknown' ? $this->catalog->unidentifiedCar() : $this->catalog->car($season);
        $ids = array_column($car->parameters(), 'id');
        if (array_diff(array_keys($input['current']), $ids)) { throw new ValidationError('Ajustes de borrador desconocidos.'); }
        $filled = []; $partial = [];
        foreach ($car->parameters() as $parameter) {
            $value = $input['current'][$parameter['id']] ?? null;
            $partial[$parameter['id']] = $value;
            $filled[$parameter['id']] = $value ?? $parameter['min'];
        }
        $decoded = $this->decode(array_replace($input, ['current' => $filled]));
        $decoded['current'] = $partial;
        if (!isset($input['reference'])) { $decoded['reference'] = null; $decoded['referenceSource'] = 'unknown'; }
        return $decoded;
    }
}
