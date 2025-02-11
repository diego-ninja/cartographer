<?php

namespace Ninja\Cartographer\Tests\Fixtures;

trait InsomniaCollectionHelpersTrait
{
    private function retrieveRoutes(array $route): int
    {
        // Skip patch routes
        if (isset($route['method']) && 'PATCH' === $route['method']) {
            return 0;
        }

        // For Insomnia format
        if (isset($route['_type']) && 'request' === $route['_type']) {
            return 1;
        }

        return 0;
    }

    private function countCollectionItems(array $collectionItems): int
    {
        // For Insomnia format
        return collect($collectionItems)
            ->filter(fn($item) => 'request' === $item['_type'])
            ->filter(fn($item) => ! in_array($item['method'], ['HEAD', 'PATCH']))
            ->count();
    }
}
