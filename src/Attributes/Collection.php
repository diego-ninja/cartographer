<?php

namespace Ninja\Cartographer\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Collection
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $version = null,
        public ?string $group = null,
    ) {}
}
