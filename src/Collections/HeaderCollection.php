<?php

namespace Ninja\Cartographer\Collections;

use Illuminate\Support\Collection;
use Ninja\Cartographer\DTO\Header;
use Ninja\Cartographer\Enums\ParameterLocation;

final class HeaderCollection extends Collection
{
    /**
     * @param array<Header> $headers
     */
    public static function from(array $headers): HeaderCollection
    {
        return new self(array_map(fn(array $header) => Header::from($header), $headers));
    }

    public function formatted(): array
    {
        return $this->unique('key')->map(fn(Header $header) => [
            'key' => $header->key,
            'value' => $header->value,
            'type' => ParameterLocation::Text,
        ])->all();
    }
}
