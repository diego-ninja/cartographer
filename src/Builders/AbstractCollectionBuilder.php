<?php

namespace Ninja\Cartographer\Builders;

use Illuminate\Config\Repository;
use Ninja\Cartographer\Collections\RequestCollection;
use Ninja\Cartographer\DTO\Request;
use Ninja\Cartographer\Processors\AuthenticationProcessor;
use Ramsey\Uuid\UuidInterface;

abstract class AbstractCollectionBuilder
{
    protected array $structure = [];
    protected array $variables = [];
    protected array $events = [];
    protected ?array $auth = null;
    protected array $items = [];

    public function __construct(
        protected readonly Repository $config,
        protected readonly AuthenticationProcessor $authProcessor,
        protected readonly RequestCollection $requests
    ) {}

    abstract public function build(): array;

    public function addBasicInfo(string $name, string $description, UuidInterface $id): self
    {
        $this->structure['info'] = $this->generateInfo($name, $description, $id);
        return $this;
    }

    public function addVariable(string $key, string $value, string $type = 'string', bool $enabled = true): self
    {
        $this->variables[] = [
            'key' => $key,
            'value' => $value,
            'type' => $type,
            'enabled' => $enabled,
        ];
        return $this;
    }

    public function addEvent(string $type, string $script): self
    {
        $this->events[] = [
            'type' => $type,
            'script' => $script,
        ];
        return $this;
    }

    public function setAuthentication(?array $auth): self
    {
        $this->auth = $auth;
        return $this;
    }

    public function processRequests(bool $structured = false): self
    {
        $this->items = $structured
            ? $this->processStructuredRequests()
            : $this->processFlatRequests();
        return $this;
    }

    abstract protected function generateInfo(string $name, string $description, UuidInterface $id): array;
    abstract protected function processStructuredRequests(): array;
    abstract protected function processFlatRequests(): array;
    abstract protected function formatRequest(Request $request): array;
}
