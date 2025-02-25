<?php

namespace Ninja\Cartographer\Builders;

use Illuminate\Config\Repository;
use Ninja\Cartographer\Collections\RequestGroupCollection;
use Ninja\Cartographer\Collections\ScriptCollection;
use Ninja\Cartographer\Collections\VariableCollection;
use Ninja\Cartographer\Contracts\AuthenticationStrategy;
use Ninja\Cartographer\DTO\Script;
use Ninja\Cartographer\DTO\Variable;
use Ninja\Cartographer\Exceptions\ValidationException;
use Ninja\Cartographer\Processors\AuthenticationProcessor;
use Ramsey\Uuid\UuidInterface;

abstract class AbstractCollectionBuilder
{
    protected array $structure = [];
    protected VariableCollection $variables;

    protected ?AuthenticationStrategy $auth = null;
    protected ?ScriptCollection $scripts = null;

    public function __construct(
        protected readonly Repository $config,
        protected readonly AuthenticationProcessor $authProcessor,
        protected readonly RequestGroupCollection $groups,
    ) {
        $this->variables = new VariableCollection();
        $this->scripts = new ScriptCollection();
    }

    abstract public function build(): array;
    abstract protected function generateInfo(string $name, string $description, UuidInterface $id): array;

    /**
     * @throws ValidationException
     */
    public function addBasicInfo(string $name, string $description, UuidInterface $id): self
    {
        if (empty($name)) {
            throw ValidationException::invalidParameter('name', 'Group name cannot be empty');
        }

        $this->structure['info'] = $this->generateInfo($name, $description, $id);
        return $this;
    }

    public function addVariable(string $key, string $value, string $type = 'string', bool $enabled = true): self
    {
        $this->variables->add(new Variable($key, $value, $type));
        return $this;
    }

    public function when(mixed $value, callable $callback): self
    {
        if ($value) {
            $callback($this);
        }

        return $this;
    }

    public function unless(mixed $value, callable $callback): self
    {
        if (!$value) {
            $callback($this);
        }

        return $this;
    }

    public function setAuthentication(?AuthenticationStrategy $auth): self
    {
        $this->auth = $auth;
        return $this;
    }

    public function setScripts(ScriptCollection $scripts): self
    {
        $this->scripts = $scripts;
        return $this;
    }

    public function addScipt(Script $script): self
    {
        $this->scripts->add($script);
        return $this;
    }
}
