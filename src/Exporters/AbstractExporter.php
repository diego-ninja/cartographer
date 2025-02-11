<?php

namespace Ninja\Cartographer\Exporters;

use Ninja\Cartographer\Collections\RequestCollection;
use Ninja\Cartographer\Processors\AuthenticationProcessor;
use Ninja\Cartographer\Processors\RouteProcessor;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\File;
use ReflectionException;

abstract class AbstractExporter
{
    protected string $filename;
    protected array $output;
    protected RequestCollection $requests;

    public function __construct(
        protected readonly Repository $config,
        protected readonly RouteProcessor $router,
        protected readonly AuthenticationProcessor $authProcessor
    ) {}

    abstract protected function generateStructure(): array;

    public function to(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getOutput(): bool|string
    {
        return json_encode($this->output, JSON_PRETTY_PRINT);
    }

    /**
     * @throws ReflectionException
     */
    public function export(): void
    {
        $this->requests = $this->router->process();
        $this->output = $this->generateStructure();
    }

    protected function getScript(string $type): ?string
    {
        $scriptConfig = $this->config->get(sprintf('cartographer.scripts.%s', $type));

        if (!empty($scriptConfig['content'])) {
            return $scriptConfig['content'];
        }

        if (!empty($scriptConfig['path']) && File::exists($scriptConfig['path'])) {
            return File::get($scriptConfig['path']);
        }

        return null;
    }
}
