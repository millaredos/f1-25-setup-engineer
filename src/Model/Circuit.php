<?php
declare(strict_types=1);

namespace App\Model;

/** Validated circuit snapshot; identity and descriptive data never contain setup values. */
final class Circuit
{
    private array $data;

    /** Validate the supported custom, catalog and legacy snapshot shapes. */
    public function __construct(mixed $input, ?array $catalog = null)
    {
        if (!is_array($input) || !is_string($input['id'] ?? null) || !preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', $input['id'])) {
            throw new ValidationError('Falta una identidad de circuito válida.');
        }
        $id = $input['id'];
        $origin = $input['origin'] ?? 'legacy';
        if ($origin === 'game-catalog') {
            $row = $catalog === null ? $input : (array_column($catalog, null, 'id')[$id] ?? null);
            if ($row === null) {
                throw new ValidationError('Circuito no disponible para esta temporada.');
            }
            $this->data = ['id' => $id, 'origin' => $origin, 'name' => Fields::text($row['name'] ?? null, 'Circuito', 120), 'location' => Fields::text($row['location'] ?? '', 'Ubicación', 80, true), 'layout' => '', 'gameVersion' => 'unknown', 'downforce' => 'unknown', 'summary' => '', 'characteristics' => array_fill_keys(['corners', 'straights', 'braking', 'traction', 'kerbs', 'aero'], ''), 'sources' => [], 'hypotheses' => ''];
        } elseif ($origin === 'custom-profile') {
            $this->data = $this->custom($input);
        } elseif ($origin === 'legacy') {
            $this->data = $this->legacy($input);
        } else {
            throw new ValidationError('Origen de circuito desconocido.');
        }
    }

    /** Preserve the published v2 profile contract and numeric limits. */
    private function custom(array $input): array
    {
        if (array_diff(array_keys($input), ['id', 'origin', 'name', 'location', 'lengthKm', 'cornerCount', 'downforce', 'cornersAndStraights', 'drivingCharacteristics'])) {
            throw new ValidationError('Campos de circuito desconocidos.');
        }
        if (!preg_match('/^custom-[a-f0-9]{32}$/D', $input['id'])) {
            throw new ValidationError('ID de ficha personalizada inválido.');
        }
        $length = $input['lengthKm'] ?? null;
        $corners = $input['cornerCount'] ?? null;
        if ($length !== null && ((!is_int($length) && !is_float($length)) || !is_finite((float) $length) || $length <= 0 || $length > 1000)) {
            throw new ValidationError('Longitud inválida.');
        }
        if ($corners !== null && (!is_int($corners) || $corners < 1 || $corners > 1000)) {
            throw new ValidationError('Número de curvas inválido.');
        }
        $result = ['id' => $input['id'], 'origin' => 'custom-profile', 'lengthKm' => $length, 'cornerCount' => $corners];
        foreach (['name' => 120, 'location' => 80, 'downforce' => 100, 'cornersAndStraights' => 4000, 'drivingCharacteristics' => 4000] as $field => $limit) {
            $result[$field] = Fields::text($input[$field] ?? '', $field, $limit, $field !== 'name');
        }
        return $result;
    }

    /** Keep evidence and exact legacy field names so existing exports can still be opened. */
    private function legacy(array $input): array
    {
        $sources = $input['sources'] ?? null;
        $characteristics = $input['characteristics'] ?? [];
        if (!is_array($sources) || count($sources) < 1 || count($sources) > 20 || !is_array($characteristics)) {
            throw new ValidationError('La ficha necesita entre una y veinte fuentes.');
        }
        $clean = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                throw new ValidationError('Fuente inválida.');
            }
            $url = Fields::text($source['url'] ?? '', 'URL', 1500);
            $date = Fields::text($source['accessedAt'] ?? '', 'Fecha', 10);
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || !$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new ValidationError('URL o fecha de fuente inválida.');
            }
            $clean[] = ['title' => Fields::text($source['title'] ?? '', 'Fuente', 200), 'url' => $url, 'accessedAt' => $date];
        }
        $data = ['id' => $input['id'], 'sources' => $clean, 'characteristics' => []];
        foreach (['name' => 120, 'layout' => 120, 'summary' => 4000, 'hypotheses' => 4000, 'gameVersion' => 80] as $key => $limit) {
            $data[$key] = Fields::text($input[$key] ?? ($key === 'gameVersion' ? 'desconocida' : ''), $key, $limit, in_array($key, ['layout', 'hypotheses'], true));
        }
        $data['downforce'] = Fields::choice($input['downforce'] ?? null, ['low', 'medium', 'high', 'mixed'], 'Carga');
        foreach (['corners', 'straights', 'braking', 'traction', 'kerbs', 'aero'] as $key) {
            $data['characteristics'][$key] = Fields::text($characteristics[$key] ?? '', $key, 2000, true);
        }
        return $data;
    }

    /** Export a validated snapshot without changing the user's saved object. */
    public function data(): array
    {
        return $this->data;
    }
}
