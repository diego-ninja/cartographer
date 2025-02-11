<?php

namespace Ninja\Cartographer\Contracts;

use Illuminate\Contracts\Support\Arrayable;

interface AuthenticationStrategy extends Arrayable
{
    /**
     * Get the prefix for the authentication header
     */
    public function prefix(): string;

    /**
     * Get the token value
     */
    public function getToken(): string;

    /**
     * Get the authentication type
     */
    public function getType(): string;

    /**
     * Convert the strategy into a Postman compatible array
     */
    public function toPostmanFormat(): array;

    /**
     * Convert the strategy into an Insomnia compatible array
     */
    public function toInsomniaFormat(): array;
}
