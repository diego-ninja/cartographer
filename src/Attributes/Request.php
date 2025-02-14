<?php

namespace Ninja\Cartographer\Attributes;

use Attribute;
use Ninja\Cartographer\Collections\HeaderCollection;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Collections\ScriptCollection;
use Ninja\Cartographer\DTO\Header;
use Ninja\Cartographer\DTO\Parameters\Parameter;
use Ninja\Cartographer\DTO\Script;
use Ninja\Cartographer\Enums\EventType;
use Ninja\Cartographer\Enums\ParameterLocation;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Request
{
    public function __construct(
        public string  $name,
        public ?string $description = null,
        public ?string $group = null,
        public ?array  $params = null,
        public ?array  $headers = null,
        public ?array  $scripts = null,
    ) {}

    public function scripts(): ScriptCollection
    {
        $scripts = new ScriptCollection();
        if ($this->scripts) {
            foreach ($this->scripts as $type => $script) {
                $scripts->add(new Script(
                    type: EventType::from($type),
                    content: isset($script['path']) ? file_get_contents($script['path']) : $script['content'],
                ));
            }
        }

        return $scripts;
    }

    public function headers(): HeaderCollection
    {
        $headers = new HeaderCollection();
        if ($this->headers) {
            foreach ($this->headers as $header => $value) {
                $headers->add(new Header(key: $header, value: $value));
            }
        }

        return $headers;
    }

    public function parameters(): ParameterCollection
    {
        $parameters = new ParameterCollection();
        if ($this->params) {
            foreach ($this->params as $name => $description) {
                $parameters->add(new Parameter(
                    name: $name,
                    value: '',
                    description: $description,
                    type: ParameterLocation::Query,
                ));
            }
        }

        return $parameters;
    }

}
