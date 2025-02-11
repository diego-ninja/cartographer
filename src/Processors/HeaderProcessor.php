<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Config\Repository;
use Ninja\Cartographer\Attributes\Request as RequestAttribute;
use Ninja\Cartographer\Collections\HeaderCollection;

final readonly class HeaderProcessor
{
    public function __construct(
        private Repository $config
    ) {}

    public function processHeaders(?RequestAttribute $request = null): HeaderCollection
    {
        $configHeaders = $this->config->get('cartographer.headers', []);

        if (empty($request?->headers)) {
            return HeaderCollection::from($configHeaders);
        }

        $mergedHeaders = collect($configHeaders)
            ->merge($request->headers)
            ->map(function($header, $key) {
                if (is_string($key)) {
                    return ['key' => $key, 'value' => $header];
                }
                return $header;
            })
            ->values()
            ->all();

        return HeaderCollection::from($mergedHeaders);
    }
}
