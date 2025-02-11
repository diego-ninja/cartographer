<?php

namespace Ninja\Cartographer\Authentication\Strategy;

use InvalidArgumentException;
use Ninja\Cartographer\Contracts\AuthenticationStrategy;

final readonly class AuthStrategyFactory
{
    private const STRATEGIES = [
        'bearer' => BearerStrategy::class,
        'basic' => BasicStrategy::class,
        'apikey' => ApiKeyStrategy::class,
    ];

    public static function create(string $type, ?string $token = null, array $options = []): AuthenticationStrategy
    {
        $strategyClass = self::STRATEGIES[mb_strtolower($type)] ?? null;

        if (!$strategyClass) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid authentication type "%s". Available types: %s',
                    $type,
                    implode(', ', array_keys(self::STRATEGIES))
                )
            );
        }

        return match ($strategyClass) {
            ApiKeyStrategy::class => new ApiKeyStrategy($token, $options['prefix'] ?? 'ApiKey'),
            default => new $strategyClass($token)
        };
    }

    public static function supportedStrategies(): array
    {
        return array_keys(self::STRATEGIES);
    }
}
