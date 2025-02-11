<?php

namespace Ninja\Cartographer\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Request
{
    public function __construct(
        public string  $name,
        public ?string $description = null,
        public ?array  $headers = null,
        public ?array  $params = null,
        public ?string $group = null,
    ) {}
}
