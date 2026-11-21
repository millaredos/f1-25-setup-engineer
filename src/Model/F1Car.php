<?php
declare(strict_types=1);

namespace App\Model;

/** F1 25 car edition: shared attributes, separately supplied presets and interpretation. */
final class F1Car extends Car
{
    private string $edition;
    private array $presets;

    /** Keep edition data separate even when both editions share their parameter definitions. */
    public function __construct(string $edition, array $parameters, array $presets)
    {
        parent::__construct($parameters);
        $this->edition = $edition;
        $this->presets = $presets;
    }

    /** Return the season stored with the setup. */
    public function season(): string
    {
        return $this->edition;
    }

    /** Return only known presets; an empty catalog never inherits another season's values. */
    public function presets(): array
    {
        return $this->presets;
    }
}
