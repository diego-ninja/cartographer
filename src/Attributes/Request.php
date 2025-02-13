<?php

namespace Ninja\Cartographer\Attributes;

use Attribute;
use Ninja\Cartographer\Collections\ScriptCollection;
use Ninja\Cartographer\DTO\Script;
use Ninja\Cartographer\Enums\EventType;

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
}
