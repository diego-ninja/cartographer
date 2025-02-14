<?php

namespace Ninja\Cartographer\Mappers;

use Illuminate\Support\Collection;
use Ninja\Cartographer\Contracts\Mapper;

abstract readonly class ParameterMapper implements Mapper
{
    protected function isRequired(array|string $rules): bool
    {
        $rules = is_string($rules) ? explode('|', $rules) : $rules;
        return in_array('required', $rules);
    }

    protected function getDefaultValue(array|string $rules): mixed
    {
        $rules = is_string($rules) ? explode('|', $rules) : $rules;

        return match(true) {
            in_array('integer', $rules) => 0,
            in_array('numeric', $rules) => 0.0,
            in_array('boolean', $rules) => false,
            in_array('array', $rules) => [],
            default => ''
        };
    }

    abstract public function map(): Collection;
}
