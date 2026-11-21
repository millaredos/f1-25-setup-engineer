<?php
declare(strict_types=1);

namespace App\Model;

/** A validated setup snapshot owns its metadata and car-specific values, without HTTP or PDO. */
final class Setup
{
    private array $data;

    /** Assemble invariants once so imports and database writes cannot validate different rules. */
    public function __construct(Car $car, array $input, ?Circuit $circuit, bool $saving)
    {
        $season = Fields::text($input['season'] ?? 'unknown', 'Temporada', 80);
        if ($saving && ($season === 'unknown' || $circuit === null || !isset($input['reference'], $input['current']))) {
            throw new ValidationError('Selecciona temporada, circuito, referencia y valores antes de guardar.');
        }
        $current = $car->validate($input['current'] ?? null);
        $reference = isset($input['reference']) ? $car->validate($input['reference']) : $current;
        $notes = $input['groupNotes'] ?? [];
        if (!is_array($notes) || array_diff(array_keys($notes), ['aero', 'transmission', 'geometry', 'suspension', 'brakes', 'tyres'])) {
            throw new ValidationError('Comentarios por grupo inválidos.');
        }
        foreach ($notes as $key => $value) { $notes[$key] = Fields::text($value, 'Comentario', 4000, true); }
        $conditions = $input['conditions'] ?? [];
        if (!is_array($conditions)) { throw new ValidationError('Condiciones inválidas.'); }
        $this->data = [
            'format' => 'f125-setup-engineer',
            'schemaVersion' => 1,
            'reference' => $reference,
            'current' => $current,
            'referenceSource' => Fields::choice($input['referenceSource'] ?? 'unknown', ['unknown', 'game-assigned', 'capture', 'personal'], 'Origen'),
            'season' => $season,
            'circuit' => $circuit?->data(),
            'conditions' => [
                'surface' => Fields::choice($conditions['surface'] ?? 'unknown', ['unknown', 'dry', 'wet'], 'Superficie'),
                'session' => Fields::choice($conditions['session'] ?? 'unknown', ['unknown', 'race', 'time-trial'], 'Sesión'),
            ],
            'title' => Fields::text($input['title'] ?? 'Reglaje importado', 'Nombre', 120),
            'notes' => Fields::text($input['notes'] ?? '', 'Notas', 4000, true),
            'groupNotes' => $notes,
        ];
        foreach (['catalogVersion', 'theoryVersion'] as $key) {
            if (isset($input[$key]) && (is_int($input[$key]) || is_string($input[$key]))) { $this->data[$key] = $input[$key]; }
        }
    }

    /**
     * Expose a value snapshot; callers cannot mutate the entity through a shared array reference.
     * @return array{format:string,schemaVersion:int,season:string,title:string,notes:string,reference:array<string,float>,current:array<string,float>,referenceSource:string,circuit:array|null,conditions:array{surface:string,session:string},groupNotes:array<string,string>}
     */
    public function data(): array
    {
        return $this->data;
    }
}
