<?php

namespace Ninja\Cartographer\Builders;

use Illuminate\Config\Repository;
use Ninja\Cartographer\Collections\RequestGroupCollection;
use Ninja\Cartographer\Processors\AuthenticationProcessor;
use Ramsey\Uuid\UuidInterface;

abstract class AbstractCollectionBuilder
{
    protected array $structure = [];
    protected array $variables = [];

    public function __construct(
        protected readonly Repository $config,
        protected readonly AuthenticationProcessor $authProcessor,
        protected readonly RequestGroupCollection $groups,
    ) {}

    abstract public function build(): array;

    abstract protected function generateInfo(string $name, string $description, UuidInterface $id): array;

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

    public function when(mixed $value, callable $callback): self
    {
        if ($value) {
            $callback($this);
        }

        return $this;
    }
}
