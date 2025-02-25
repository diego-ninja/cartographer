<?php

namespace Ninja\Cartographer\DTO;

use JsonSerializable;
use Ninja\Cartographer\Contracts\Exportable;

final readonly class Variable implements JsonSerializable, Exportable
{
    public function __construct(
        public string $key,
        public string $value,
        public string $type = 'string',
    ) {}

    public static function from(string|array|self $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return new self(
            key: $data['key'],
            value: $data['value'],
            type: $data['type'] ?? 'string',
        );
    }

    public function array(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type,
        ];
    }

    public function json(): string
    {
        return json_encode($this->array());
    }

    public function forPostman(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type,
        ];
    }

    public function forInsomnia(): array
    {
        return [$this->key => $this->value];
    }

    public function jsonSerialize(): array
    {
        return $this->array();
    }
}

