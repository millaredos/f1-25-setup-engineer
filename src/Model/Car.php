<?php
declare(strict_types=1);

namespace App\Model;

/** Common adjustable-car behavior; editions supply their own identity and preset data. */
abstract class Car
{
    protected array $parameters;

    /**
     * Use supplied definitions so validation never reads files, HTTP or a database.
     * @param array<int, array{id:string,name:string,min:int|float,max:int|float,step:int|float,dec:int}> $parameters
     */
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /** Identify the edition whose settings and interpretation this car uses. */
    abstract public function season(): string;

    /** Expose parameter definitions to both server validation and the editor. */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Reject missing, unknown, non-finite, out-of-range and off-step values.
     * @return array<string, float> Complete parameter map in catalog order.
     * @throws ValidationError When the complete map fails the supplied car contract.
     */
    public function validate(mixed $values): array
    {
        if (!is_array($values)) {
            throw new ValidationError('Se necesita un mapa de ajustes.');
        }
        $result = [];
        foreach ($this->parameters as $parameter) {
            $id = $parameter['id'];
            $value = $values[$id] ?? null;
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
                throw new ValidationError($parameter['name'] . ': introduce un número.');
            }
            $scale = 10 ** $parameter['dec'];
            $ticks = ($value - $parameter['min']) * $scale;
            $step = (int) round($parameter['step'] * $scale);
            if ($step < 1 || $value < $parameter['min'] - 1e-8 || $value > $parameter['max'] + 1e-8 || abs($value * $scale - round($value * $scale)) > 1e-6 || (int) round($ticks) % $step !== 0) {
                throw new ValidationError($parameter['name'] . ': rango o paso inválido.');
            }
            $result[$id] = round($value, $parameter['dec']);
        }
        if (array_diff(array_keys($values), array_keys($result))) {
            throw new ValidationError('Hay ajustes desconocidos.');
        }
        return $result;
    }
}
