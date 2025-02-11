<?php

namespace Ninja\Cartographer\Authentication;

use Illuminate\Contracts\Support\Arrayable;
use ReflectionClass;

abstract class AuthenticationMethod implements Arrayable
{
    public function __construct(protected ?string $token = null) {}

    abstract public function prefix(): string;

    public function toArray(): array
    {
        return [
            'key' => 'Authorization',
            'value' => sprintf('%s %s', $this->prefix(), $this->token ?? '{{token}}'),
        ];
    }

    public function getToken(): string
    {
        return $this->token;
    }


    public function getType(): string
    {
        $className = (new ReflectionClass($this))->getShortName();
        return mb_strtolower($className);
    }
}
