<?php

namespace Ninja\Cartographer\Collections;

use Ninja\Cartographer\Contracts\Serializable;
use Ninja\Cartographer\DTO\Header;

final class HeaderCollection extends ExportableCollection
{
    /**
     * @param array<Header> $items
     */
    public static function from(array|string|Serializable $items): HeaderCollection
    {
        if ($items instanceof self) {
            return $items;
        }

        if (is_string($items)) {
            return self::from(json_decode($items, true));
        }

        return new self(array_map(fn(array $header) => Header::from($header), $items));
    }

    public function forPostman(): array
    {
        return $this->unique('key')
            ->map(fn(Header $header) => [
                'key' => $header->key,
                'value' => $header->value,
                'type' => 'text',
            ])
            ->values()
            ->all();
    }

    public function forInsomnia(): array
    {
        return $this
            ->map(fn(Header $header) => [
                'name' => $header->key,
                'value' => $header->value,
                'description' => $header->description ?? '',
                'disabled' => false
            ])
            ->values()
            ->all();
    }
}
