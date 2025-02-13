<?php

namespace Ninja\Cartographer\Services;

use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\DTO\Parameter;
use Ninja\Cartographer\Enums\BodyMode;
use Ninja\Cartographer\Formatters\RuleFormatter;
use stdClass;

final readonly class BodyContentHandler
{
    public function __construct(
        private RuleFormatter $ruleFormatter,
    ) {}

    public function prepareContent(
        ParameterCollection $parameters,
        BodyMode $mode,
        ?array $formdata = [],
    ): array {
        return match ($mode) {
            BodyMode::Raw => $this->prepareRawContent($parameters, $formdata),
            BodyMode::UrlEncoded,
            BodyMode::FormData => $this->prepareParameterBasedContent($parameters, $formdata),
            default => [],
        };
    }

    public function getBodyOptions(BodyMode $mode): ?array
    {
        return match ($mode) {
            BodyMode::Raw => [
                'raw' => [
                    'language' => 'json',
                ],
            ],
            default => null,
        };
    }

    private function prepareRawContent(ParameterCollection $parameters, array $formdata): array
    {
        $content = [];

        foreach ($parameters as $param) {
            $value = $formdata[$param->name] ?? $this->getDefaultValueForType($param->description ?? '');
            $this->setNestedValue($content, $param->name, $value);
        }

        return $content;
    }

    private function prepareParameterBasedContent(ParameterCollection $parameters, array $formdata): array
    {
        return $parameters->map(fn(Parameter $param) => [
            'key' => $param->name,
            'value' => $formdata[$param->name] ?? '',
            'type' => 'text',
            'description' => $this->ruleFormatter->format($param->name, $param->rules),
        ])->values()->all();
    }

    private function getDefaultValueForType(string $description): mixed
    {
        $description = mb_strtolower($description);

        return match (true) {
            str_contains($description, 'integer') => 0,
            str_contains($description, 'number') => 0.0,
            str_contains($description, 'boolean') => false,
            str_contains($description, 'array') => [],
            str_contains($description, 'object') => new stdClass(),
            default => "",
        };
    }

    private function setNestedValue(array &$array, string $key, mixed $value): void
    {
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $current = &$array;

            foreach ($keys as $nestedKey) {
                if ( ! isset($current[$nestedKey])) {
                    $current[$nestedKey] = [];
                }
                $current = &$current[$nestedKey];
            }

            $current = $value;
        } else {
            $array[$key] = $value;
        }
    }
}
