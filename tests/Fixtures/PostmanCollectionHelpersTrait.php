<?php

namespace Ninja\Cartographer\Tests\Fixtures;

trait PostmanCollectionHelpersTrait
{
    private function retrieveRoutes(array $route): int
    {
        // Skip patch routes
        if (isset($route['request']['method']) && 'PATCH' === $route['request']['method']) {
            return 0;
        }

        if (isset($route['item'])) {
            $sum = 0;

            foreach ($route['item'] as $item) {
                $sum += $this->retrieveRoutes($item);
            }

            return $sum;
        }

        return 1;
    }

    private function countCollectionItems(array $collectionItems): int
    {
        $sum = 0;

        foreach ($collectionItems as $item) {
            $sum += $this->retrieveRoutes($item);
        }

        return $sum;
    }
}
