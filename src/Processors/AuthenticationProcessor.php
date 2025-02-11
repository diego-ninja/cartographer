<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Config\Repository;
use Ninja\Cartographer\Concerns\HasAuthentication;

final class AuthenticationProcessor
{
    use HasAuthentication;

    public function __construct(
        private readonly Repository $config
    ) {}

    public function processRouteAuthentication(array $middlewares): ?array
    {
        if (!in_array($this->config->get('cartographer.auth_middleware'), $middlewares)) {
            return null;
        }

        $config = $this->config->get('cartographer.authentication');
        return [
            'type' => $config['method'],
            'token' => $config['token'] ?? '{{token}}',
        ];
    }
}
