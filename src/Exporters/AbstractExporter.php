<?php

namespace Ninja\Cartographer\Exporters;

use Illuminate\Config\Repository;
use Ninja\Cartographer\Collections\RequestGroupCollection;
use Ninja\Cartographer\Contracts\Exporter;
use Ninja\Cartographer\Processors\AuthenticationProcessor;
use Ninja\Cartographer\Processors\RouteProcessor;
use ReflectionException;

abstract class AbstractExporter implements Exporter
{
    protected string $filename;
    protected array $output;
    protected RequestGroupCollection $groups;

    public function __construct(
        protected readonly Repository              $config,
        protected readonly RouteProcessor          $routeProcessor,
        protected readonly AuthenticationProcessor $authProcessor,
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
        $this->groups = $this->routeProcessor->process();
        $this->output = $this->generateStructure();
    }
}
