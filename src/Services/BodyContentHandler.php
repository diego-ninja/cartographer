<?php

namespace Ninja\Cartographer\Services;

use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\DTO\Parameters\Parameter;
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
    ): array|string {
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
            if (!isset($param->structure)) {
                continue;
            }

            return $this->processStructure($param->structure);
        }

        return $content;
    }

    private function processStructure(array $structure): array
    {
        $result = [];

        foreach ($structure as $key => $value) {
            if (is_array($value)) {
                if (isset($value['value'])) {
                    $result[$key] = $value['value'];
                } else {
                    $result[$key] = $this->processStructure($value);
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
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
}
