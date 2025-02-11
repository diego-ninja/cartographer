<?php

namespace Ninja\Cartographer\DTO;

use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Enums\BodyMode;
use Ninja\Cartographer\Services\BodyContentHandler;
use JsonSerializable;

final readonly class Body implements JsonSerializable
{
    public function __construct(
        public BodyMode $mode = BodyMode::Raw,
        public mixed $content = null,
        public ?array $options = null,
        public bool $disabled = false
    ) {}

    public static function from(string|array|self $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        if (is_string($data)) {
            return self::from(json_decode($data, true));
        }

        return new self(
            mode: BodyMode::from($data['mode'] ?? BodyMode::Raw->value),
            content: $data['content'] ?? null,
            options: $data['options'] ?? null,
            disabled: $data['disabled'] ?? false,
        );
    }

    public static function fromParameters(
        ParameterCollection $parameters,
        ?array $formdata = [],
        ?BodyMode $mode = null
    ): self {
        $contentHandler = app(BodyContentHandler::class);
        $bodyMode = $mode ?? BodyMode::Raw;

        return new self(
            mode: $bodyMode,
            content: $contentHandler->prepareContent($parameters, $bodyMode, $formdata),
            options: $contentHandler->getBodyOptions($bodyMode)
        );
    }

    public function forPostman(): ?array
    {
        if ($this->mode === BodyMode::None) {
            return null;
        }

        return [
            'mode' => $this->mode->value,
            $this->mode->value => $this->formatContentForPostman(),
            'options' => $this->options,
            'disabled' => $this->disabled,
        ];
    }

    public function forInsomnia(): ?array
    {
        if ($this->mode === BodyMode::None) {
            return null;
        }

        return [
            'mimeType' => $this->getMimeType(),
            'text' => $this->formatContentForInsomnia(),
        ];
    }

    private function formatContentForPostman(): mixed
    {
        return match($this->mode) {
            BodyMode::Raw => is_string($this->content)
                ? $this->content
                : json_encode($this->content, JSON_PRETTY_PRINT),
            default => $this->content
        };
    }

    private function formatContentForInsomnia(): string
    {
        return match($this->mode) {
            BodyMode::Raw => is_string($this->content)
                ? $this->content
                : json_encode($this->content, JSON_PRETTY_PRINT),
            BodyMode::UrlEncoded => http_build_query($this->content),
            BodyMode::FormData => $this->formatFormDataForInsomnia(),
            default => ''
        };
    }

    private function formatFormDataForInsomnia(): string
    {
        if (!is_array($this->content)) {
            return '';
        }

        $parts = [];
        foreach ($this->content as $key => $value) {
            $parts[] = sprintf('--%s', uniqid());
            $parts[] = sprintf('Content-Disposition: form-data; name="%s"', $key);
            $parts[] = '';
            $parts[] = is_array($value) ? json_encode($value) : (string)$value;
        }
        $parts[] = sprintf('--%s--', uniqid());

        return implode("\n", $parts);
    }

    private function getMimeType(): string
    {
        return $this->mode->mimeType() ?? 'application/json';
    }

    public function jsonSerialize(): array
    {
        return [
            'mode' => $this->mode->value,
            'mime_type' => $this->getMimeType(),
            'content' => $this->content,
            'options' => $this->options,
            'disabled' => $this->disabled,
        ];
    }
}
