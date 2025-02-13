<?php

namespace Ninja\Cartographer\DTO;

use Ninja\Cartographer\Enums\EventType;
use Ramsey\Uuid\Uuid;

final readonly class Script
{
    private const SEPARATOR = "\n";

    public function __construct(public EventType $type, public string $content, public bool $enabled = true) {}

    public static function from(string|array $script): Script
    {
        if (is_string($script)) {
            $script = json_decode($script, true);
        }

        return new self(EventType::from($script['type']), $script['content'], $script['enabled']);
    }

    public function forInsomnia(): ?string
    {
        if ( ! $this->enabled) {
            return null;
        }

        return $this->content;
    }

    public function forPostman(): ?array
    {
        return [
            'id' => Uuid::uuid4()->toString(),
            'listen' => $this->type->forPostman(),
            'script' => [
                'type' => 'text/javascript',
                'exec' => explode(self::SEPARATOR, $this->content),
            ],
            'disabled' => ! $this->enabled,
        ];
    }
}
