<?php

namespace Ninja\Cartographer\Collections;

use Ninja\Cartographer\Contracts\Serializable;
use Ninja\Cartographer\DTO\Script;
use Ninja\Cartographer\Enums\EventType;

class ScriptCollection extends ExportableCollection
{
    public static function from(array|string|Serializable $items): ScriptCollection
    {
        if ($items instanceof self) {
            return $items;
        }

        if (is_string($items)) {
            return self::from(json_decode($items, true));
        }

        return new self(array_map(fn(array $script) => Script::from($script), $items));
    }

    public function forPostman(): array
    {
        return $this->map(fn(Script $script) => $script->forPostman())->filter()->all();
    }

    public function forInsomnia(): array
    {
        return $this->map(fn(Script $script) => [
            $script->type->forInsomnia() => $script->enabled ? $script->content : null
        ])->filter()->values()->all();
    }

    public function findByType(EventType $type): ?Script
    {
        return $this->first(fn(Script $script) => $script->type === $type);
    }
}
