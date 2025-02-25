<?php

namespace Ninja\Cartographer\Contracts;

interface Serializable extends \JsonSerializable
{
    public static function from(array|string|self $items): self;
    public function array(): array;
    public function json(): string;
}
