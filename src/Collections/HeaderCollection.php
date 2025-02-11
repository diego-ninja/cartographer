<?php

namespace Ninja\Cartographer\Collections;

use Ninja\Cartographer\DTO\Header;
use Ninja\Cartographer\Enums\ParameterType;
use Illuminate\Support\Collection;

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
        return $this->map(fn(Header $header) => [
            'key' => $header->key,
            'value' => $header->value,
            'type' => ParameterType::TEXT,
        ])->all();
    }
}
