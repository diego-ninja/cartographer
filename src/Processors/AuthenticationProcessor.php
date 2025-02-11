<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Config\Repository;
use Ninja\Cartographer\Authentication\Strategy\AuthStrategyFactory;
use Ninja\Cartographer\Contracts\AuthenticationStrategy;

final class AuthenticationProcessor
{
    private ?AuthenticationStrategy $strategy = null;

    public function __construct(
        private readonly Repository $config
    ) {
        $this->resolveStrategy();
    }

    public function processRouteAuthentication(array $middlewares): ?array
    {
        if (!$this->shouldAuthenticate($middlewares)) {
            return null;
        }

        return $this->strategy?->toArray();
    }

    public function getStrategy(): ?AuthenticationStrategy
    {
        return $this->strategy;
    }

    public function setStrategy(?AuthenticationStrategy $strategy): self
    {
        $this->strategy = $strategy;
        return $this;
    }

    private function resolveStrategy(): void
    {
        $config = $this->config->get('cartographer.authentication');

        if (!empty($config['method'])) {
            $this->strategy = AuthStrategyFactory::create(
                type: $config['method'],
                token: $config['token'] ?? null,
                options: $config['options'] ?? []
            );
        }
    }

    private function shouldAuthenticate(array $middlewares): bool
    {
        return in_array($this->config->get('cartographer.auth_middleware'), $middlewares);
    }
}
