<?php

namespace Ninja\Cartographer\Exporters;

use Illuminate\Config\Repository;
use Ninja\Cartographer\Collections\RequestGroupCollection;
use Ninja\Cartographer\Contracts\Exporter;
use Ninja\Cartographer\Exceptions\ExportException;
use Ninja\Cartographer\Processors\AuthenticationProcessor;
use Ninja\Cartographer\Processors\RouteProcessor;
use Opis\JsonSchema\Validator;
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
     * @throws ExportException
     */
    public function validate(): self
    {
        $validator = new Validator();
        $validator->setMaxErrors(10);
        $validator->setStopAtFirstError(false);
        $result = $validator->validate($this->output, $this->getSchema());

        if (!$result->isValid()) {
            throw new ExportException($result->error());
        }

        return $this;
    }

    /**
     * @throws ReflectionException
     */
    public function export(): self
    {
        $this->groups = $this->routeProcessor->process();
        $this->output = $this->generateStructure();

        return $this;
    }

    abstract protected function getSchema(): string;
}
