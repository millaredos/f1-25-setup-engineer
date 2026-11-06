<?php
declare(strict_types=1);

final class ValidationError extends RuntimeException {}

/** Reglas puras: no conoce HTTP ni escribe datos. */
final class Domain
{
    public array $catalog;
    public array $symptoms;

    private Closure $translate;
    private ?array $gameCircuits;

    public function __construct(string $directory, ?callable $translate = null, ?array $gameCircuits = null)
    {
        $this->gameCircuits = $gameCircuits === null ? null : array_column($gameCircuits, null, 'id');
        $this->translate = Closure::fromCallable($translate ?? static function (string $message, array $values = []): string {
            $replace = [];
            foreach ($values as $key => $value) { $replace['{' . $key . '}'] = (string) $value; }
            return strtr($message, $replace);
        });
        $this->catalog = json_decode(file_get_contents($directory . '/parameters.json'), true, 64, JSON_THROW_ON_ERROR);
        $this->symptoms = json_decode(file_get_contents($directory . '/symptoms.json'), true, 64, JSON_THROW_ON_ERROR);
        $this->catalog = $this->translatedData($this->catalog);
        $this->symptoms = $this->translatedData($this->symptoms);
    }

    private function tr(string $message, array $values = []): string { return ($this->translate)($message, $values); }

    /** Only bundled descriptive fields are translated; IDs, rules, URLs and imported data stay intact. */
    private function translatedData(array $data, string $parent = ''): array
    {
        $fields = ['notice', 'category', 'name', 'phase', 'what', 'up', 'down', 'up_label', 'down_label', 'note', 'label', 'check', 'why', 'cost', 'combined'];
        foreach ($data as $key => $value) {
            if (is_array($value)) { $data[$key] = $this->translatedData($value, is_string($key) ? $key : $parent); }
            elseif (is_string($value) && (in_array($key, $fields, true) || (in_array($parent, ['up_imp', 'down_imp'], true) && $key === 1))) { $data[$key] = $this->tr($value); }
        }
        return $data;
    }

    public function values(mixed $input): array
    {
        if (!is_array($input)) {
            throw new ValidationError($this->tr('Se necesita un mapa con los veinte parámetros.'));
        }
        $result = [];
        foreach ($this->catalog['parameters'] as $p) {
            $value = $input[$p['id']] ?? null;
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
                throw new ValidationError($this->tr('{name}: introduce un número.', ['name' => $p['name']]));
            }
            if ($value < $p['min'] - 1e-8 || $value > $p['max'] + 1e-8) {
                throw new ValidationError($this->tr('{name}: valor fuera de rango.', ['name' => $p['name']]));
            }
            // Escalar elimina errores binarios como 24.3 - 22.5 != 1.8 exacto.
            $scale = 10 ** $p['dec'];
            $scaled = $value * $scale;
            $ticks = (int) round(($value - $p['min']) * $scale);
            $step = (int) round($p['step'] * $scale);
            if (abs($scaled - round($scaled)) > 1e-6 || $ticks % $step !== 0) {
                throw new ValidationError($this->tr('{name}: no coincide con el paso permitido ({step}).', ['name' => $p['name'], 'step' => $p['step']]));
            }
            $result[$p['id']] = round($value, $p['dec']);
        }
        if (array_diff(array_keys($input), array_keys($result))) {
            throw new ValidationError($this->tr('Hay identificadores de parámetros desconocidos.'));
        }
        return $result;
    }

    public function compare(mixed $reference, mixed $current): array
    {
        $reference = $this->values($reference);
        $current = $this->values($current);
        $changes = [];
        foreach ($this->catalog['parameters'] as $p) {
            $id = $p['id'];
            $delta = round($current[$id] - $reference[$id], $p['dec']);
            if (abs($delta) < 1e-7) {
                continue;
            }
            $direction = $delta > 0 ? 'up' : 'down';
            $changes[] = [
                'id' => $id, 'name' => $p['name'], 'category' => $p['category'],
                'before' => $reference[$id], 'after' => $current[$id], 'delta' => $delta,
                'unit' => $p['unit'], 'label' => $p[$direction . '_label'],
                'theory' => $p[$direction], 'caution' => $p['note'],
            ];
        }
        return [
            'changes' => $changes,
            'notice' => $this->tr('Siempre teoría: se describe cada cambio, sin sumar sus efectos ni predecir agarre, carga física o tiempos. Cambios opuestos pueden compensarse parcialmente; no se calcula cuánto.'),
        ];
    }

    public function importSetup(mixed $input): array
    {
        if (!is_array($input)) {
            throw new ValidationError($this->tr('El reglaje debe ser un objeto JSON.'));
        }
        $format = $input['format'] ?? null;
        if ($format !== null && !in_array($format, ['f125-comparador-v2', 'f125-setup-engineer'], true)) {
            throw new ValidationError($this->tr('Formato de reglaje desconocido.'));
        }
        if ($format === 'f125-setup-engineer' && ($input['schemaVersion'] ?? null) !== 1) {
            throw new ValidationError($this->tr('Versión del esquema no admitida.'));
        }
        $current = $this->values($input['current'] ?? $input['values'] ?? $input);
        $rawReference = $input['reference'] ?? $input['base'] ?? null;
        $warnings = [];
        if ($rawReference === null) {
            $reference = $current;
            $warnings[] = $this->tr('El archivo no tiene referencia: se propone una copia de los valores importados, sin atribuirla al juego.');
        } else {
            $reference = $this->values($rawReference);
        }
        if (isset($input['referenceIsCapture']) && !is_bool($input['referenceIsCapture'])) {
            throw new ValidationError($this->tr('referenceIsCapture debe ser booleano.'));
        }
        if ($format !== 'f125-setup-engineer') {
            $warnings[] = $this->tr('Formato heredado: circuito y condiciones no se deducen del archivo.');
        }
        $source = $format === 'f125-setup-engineer' ? ($input['referenceSource'] ?? 'unknown') : 'unknown';
        return [
            'format' => 'f125-setup-engineer', 'schemaVersion' => 1,
            'reference' => $reference, 'current' => $current,
            'referenceSource' => $this->choice($source, ['unknown', 'game-assigned', 'capture', 'personal'], $this->tr('Origen de referencia')),
            'circuit' => isset($input['circuit']) ? $this->circuit($input['circuit']) : null,
            'conditions' => $this->conditions($input['conditions'] ?? []),
            'title' => $this->text($input['title'] ?? $this->tr('Reglaje importado'), 'Nombre', 120),
            'notes' => $this->text($input['notes'] ?? '', 'Notas', 4000, true),
            'warnings' => $warnings,
        ];
    }

    public function importCircuit(mixed $input): array
    {
        if (!is_array($input) || ($input['format'] ?? null) !== 'f125-circuit' || ($input['schemaVersion'] ?? null) !== 1) {
            throw new ValidationError($this->tr('Se necesita format f125-circuit y schemaVersion 1.'));
        }
        return $this->circuit($input['circuit'] ?? null);
    }

    public function circuit(mixed $input): array
    {
        if (!is_array($input)) {
            throw new ValidationError($this->tr('Falta la ficha del circuito.'));
        }
        $id = $input['id'] ?? '';
        if (!is_string($id) || !preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', $id)) {
            throw new ValidationError($this->tr('El ID del circuito admite minúsculas, números y guiones (máximo 80).'));
        }
        if (($input['origin'] ?? null) === 'game-catalog') {
            // Offline imports retain a validated identity snapshot; saves resolve against the live catalog.
            $record = $this->gameCircuits === null ? $input : ($this->gameCircuits[$id] ?? null);
            if ($record === null) { throw new ValidationError($this->tr('Ese circuito no está en el catálogo del juego.')); }
            // A catalog entry identifies a circuit; it does not invent a technical profile or sources.
            return ['id' => $id, 'name' => $this->text($record['name'] ?? null, 'Circuito', 120),
                'origin' => 'game-catalog', 'location' => $this->text($record['location'] ?? null, $this->tr('Ubicación'), 80),
                'layout' => '', 'gameVersion' => 'unknown', 'downforce' => 'unknown', 'summary' => '',
                'characteristics' => array_fill_keys(['corners', 'straights', 'braking', 'traction', 'kerbs', 'aero'], ''),
                'sources' => [], 'hypotheses' => ''];
        }
        $characteristics = $input['characteristics'] ?? [];
        if (!is_array($characteristics)) {
            throw new ValidationError($this->tr('Características debe ser un objeto.'));
        }
        $technical = [];
        foreach (['corners', 'straights', 'braking', 'traction', 'kerbs', 'aero'] as $key) {
            $technical[$key] = $this->text($characteristics[$key] ?? '', $key, 2000, true);
        }
        $sources = $input['sources'] ?? [];
        if (!is_array($sources) || count($sources) < 1 || count($sources) > 20) {
            throw new ValidationError($this->tr('La ficha necesita entre una y veinte fuentes.'));
        }
        $cleanSources = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                throw new ValidationError($this->tr('Fuente inválida.'));
            }
            $url = $source['url'] ?? '';
            if (!is_string($url) || strlen($url) > 1500 || !filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true)) {
                throw new ValidationError($this->tr('La fuente necesita una URL HTTP o HTTPS válida.'));
            }
            $date = $source['accessedAt'] ?? '';
            if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || !($parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date)) || $parsed->format('Y-m-d') !== $date) {
                throw new ValidationError($this->tr('Fecha de fuente inválida: utiliza AAAA-MM-DD.'));
            }
            $cleanSources[] = ['title' => $this->text($source['title'] ?? '', 'Fuente', 200), 'url' => $url, 'accessedAt' => $date];
        }
        return [
            'id' => $id, 'name' => $this->text($input['name'] ?? '', 'Circuito', 120),
            'layout' => $this->text($input['layout'] ?? '', 'Trazado', 120, true),
            'downforce' => $this->choice($input['downforce'] ?? '', ['low', 'medium', 'high', 'mixed'], 'Carga'),
            'summary' => $this->text($input['summary'] ?? '', 'Resumen', 4000),
            'characteristics' => $technical, 'sources' => $cleanSources,
            'hypotheses' => $this->text($input['hypotheses'] ?? '', $this->tr('Hipótesis'), 4000, true),
            'gameVersion' => $this->text($input['gameVersion'] ?? 'desconocida', $this->tr('Versión del juego'), 80),
        ];
    }

    public function setup(mixed $input): array
    {
        if (!is_array($input)) {
            throw new ValidationError($this->tr('Reglaje inválido.'));
        }
        if (!isset($input['reference'], $input['current'])) {
            throw new ValidationError($this->tr('Fija una referencia y completa los valores actuales antes de guardar.'));
        }
        $data = $this->importSetup(array_replace($input, ['format' => 'f125-setup-engineer', 'schemaVersion' => 1]));
        if ($data['circuit'] === null) {
            throw new ValidationError($this->tr('Asocia primero una ficha de circuito al reglaje.'));
        }
        unset($data['warnings']);
        // La interfaz no solicita hardware ni ayudas: no atribuir un perfil al usuario.
        $data['catalogVersion'] = $this->catalog['version'];
        $data['theoryVersion'] = $this->symptoms['version'];
        return $data;
    }

    private function conditions(mixed $input): array
    {
        if (!is_array($input)) {
            throw new ValidationError($this->tr('Condiciones inválidas.'));
        }
        return [
            'surface' => $this->choice($input['surface'] ?? 'unknown', ['unknown', 'dry', 'wet'], 'Superficie'),
            'session' => $this->choice($input['session'] ?? 'unknown', ['unknown', 'race', 'time-trial'], $this->tr('Sesión')),
        ];
    }

    private function choice(mixed $value, array $allowed, string $name): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new ValidationError($this->tr('{name}: opción inválida.', ['name' => $this->tr($name)]));
        }
        return $value;
    }

    private function text(mixed $value, string $name, int $max, bool $empty = false): string
    {
        if (!is_string($value) || strlen($value) > $max || (!$empty && trim($value) === '')) {
            throw new ValidationError($this->tr('{name}: texto requerido o demasiado largo (máximo {max} bytes).', ['name' => $this->tr($name), 'max' => $max]));
        }
        return trim($value);
    }
}
