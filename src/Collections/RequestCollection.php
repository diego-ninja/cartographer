<?php

namespace Ninja\Cartographer\Collections;

use Illuminate\Support\Collection;
use Ninja\Cartographer\DTO\Request;
use Ninja\Cartographer\Enums\Method;

final class RequestCollection extends Collection
{
    public static function from(array $requests): RequestCollection
    {
        return new self(array_map(fn(array $request) => Request::from($request), $requests));
    }

    public function groupByNestedPath(): array
    {
        $grouped = [];

        /** @var Request $request */
        foreach ($this as $request) {
            if (Method::HEAD === $request->method) {
                continue;
            }

            $path = $request->getNestedPath();

            if (empty($path)) {
                if ( ! isset($grouped['Default'])) {
                    $grouped['Default'] = ['requests' => [], 'children' => []];
                }
                $grouped['Default']['requests'][] = $request;
                continue;
            }

            $current = &$grouped;

            for ($i = 0; $i < count($path) - 1; $i++) {
                $segment = $path[$i];
                if ( ! isset($current[$segment])) {
                    $current[$segment] = ['requests' => [], 'children' => []];
                }
                $current = &$current[$segment]['children'];
            }

            $lastSegment = end($path);
            if ( ! isset($current[$lastSegment])) {
                $current[$lastSegment] = ['requests' => [], 'children' => []];
            }
            $current[$lastSegment]['requests'][] = $request;
        }

        return $grouped;
    }
}
