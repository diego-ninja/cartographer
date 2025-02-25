<?php

namespace Ninja\Cartographer\Collections;

use Illuminate\Support\Collection;
use Ninja\Cartographer\Contracts\Exportable;
use Ninja\Cartographer\Contracts\Serializable;

abstract class ExportableCollection extends Collection implements Exportable, Serializable
{
    abstract public static function from(array|string|Serializable $items): self;

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function array(): array
    {
        return $this->toArray();
    }

    public function json(): string
    {
        return json_encode($this->array());
    }

    public function jsonSerialize(): array
    {
        return $this->array();
    }
}
