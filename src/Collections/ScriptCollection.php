<?php

namespace Ninja\Cartographer\Collections;

use Illuminate\Support\Collection;
use Ninja\Cartographer\DTO\Script;
use Ninja\Cartographer\Enums\EventType;

class ScriptCollection extends Collection
{
    public static function from(array $scripts): ScriptCollection
    {
        return new self(array_map(fn(array $script) => Script::from($script), $scripts));
    }

    public function forPostman(): array
    {
        return $this->map(fn(Script $script) => $script->forPostman())->filter()->all();
    }

    public function findByType(EventType $type): ?Script
    {
        return $this->first(fn(Script $script) => $script->type === $type);
    }
}
