<?php
declare(strict_types=1);

namespace App\Application;

use App\Model\Car;

/** Describes each parameter change independently; no fabricated physical score. */
final class Comparison
{
    /** Validate both snapshots with the same car contract before interpreting differences. */
    public function compare(Car $car, mixed $reference, mixed $current): array
    {
        $before = $car->validate($reference);
        $after = $car->validate($current);
        $changes = [];
        foreach ($car->parameters() as $p) {
            $id = $p['id'];
            $delta = round($after[$id] - $before[$id], $p['dec']);
            if (abs($delta) < 1e-7) {
                continue;
            }
            $direction = $delta > 0 ? 'up' : 'down';
            $changes[] = ['id' => $id, 'name' => $p['name'], 'category' => $p['category'], 'before' => $before[$id], 'after' => $after[$id], 'delta' => $delta, 'unit' => $p['unit'], 'label' => $p[$direction . '_label'], 'theory' => $p[$direction], 'caution' => $p['note']];
        }
        return ['changes' => $changes, 'notice' => 'Explicación teórica compartida, pendiente de verificación específica por temporada. No predice agarre ni tiempos.'];
    }
}
