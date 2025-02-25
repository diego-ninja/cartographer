<?php

namespace Ninja\Cartographer\Collections;

use Illuminate\Support\Collection;
use Ninja\Cartographer\Contracts\Serializable;
use Ninja\Cartographer\DTO\Request;
use Ninja\Cartographer\Enums\Method;

final class RequestCollection extends ExportableCollection
{
    public static function from(array|string|Serializable $items): RequestCollection
    {
        if ($items instanceof self) {
            return $items;
        }

        if (is_string($items)) {
            return self::from(json_decode($items, true));
        }

        return new self(array_map(fn(array $request) => Request::from($request), $items));
    }


    public function forPostman(): array
    {
        return $this->map->forPostman()->values()->all();
    }

    public function forInsomnia(): array
    {
        return $this->map->forInsomnia()->values()->all();
    }

    public function groupByPath(): array
    {
        return $this->groupBy(function (Request $request) {
            return implode('/', array_filter(
                explode('/', trim($request->uri, '/')),
                fn($segment) => !str_starts_with($segment, '{')
            ));
        })->all();
    }
}
