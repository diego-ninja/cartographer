<?php

namespace Ninja\Cartographer\Collections;

use Ninja\Cartographer\Contracts\Serializable;
use Ninja\Cartographer\DTO\Variable;

final class VariableCollection extends ExportableCollection
{
    public static function from(array|string|Serializable $items): self
    {
        if ($items instanceof self) {
            return $items;
        }

        if (is_string($items)) {
            return self::from(json_decode($items, true));
        }

        return new self(array_map(fn(array|Variable $variable) => Variable::from($variable), $items));
    }

    public function forPostman(): array
    {
        return $this->map(fn(Variable $variable) => [
            'key' => $variable->key,
            'value' => $variable->value,
            'type' => $variable->type
        ])->values()->all();    }

    public function forInsomnia(): array
    {
        return $this->reduce(
            fn(array $carry, Variable $variable) => array_merge(
                $carry,
                [$variable->key => $variable->value]
            ),
            []
        );
    }
}
