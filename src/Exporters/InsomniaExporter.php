<?php

namespace Ninja\Cartographer\Exporters;

use Ninja\Cartographer\Builders\InsomniaCollectionBuilder;
use Ninja\Cartographer\Exceptions\ExportException;
use Ninja\Cartographer\Exceptions\ValidationException;
use Ninja\Cartographer\Processors\ScriptsProcessor;
use Ramsey\Uuid\Uuid;

final class InsomniaExporter extends AbstractExporter
{
    private const SCHEMA_FILE = __DIR__ . '/../../schemas/insomnia-schema.json';

    /**
     * @throws ExportException
     * @throws ValidationException
     */
    protected function generateStructure(): array
    {
        return $this->builder()
            ->addBasicInfo(
                name: $this->config->get('cartographer.name'),
                description: $this->config->get('app.description', 'Cartographer API Group'),
                id: Uuid::uuid4(),
            )
            ->addVariable('base_url', $this->config->get('cartographer.base_url'))
            ->when(
                $this->authProcessor->getStrategy(),
                fn(InsomniaCollectionBuilder $builder) => $builder->addVariable(
                    'token',
                    $this->authProcessor->getStrategy()->getToken(),
                ),
            )
            ->setScripts(ScriptsProcessor::processScriptsFromConfig())
            ->build();
    }

    private function builder(): InsomniaCollectionBuilder
    {
        return new InsomniaCollectionBuilder(
            $this->config,
            $this->authProcessor,
            $this->groups,
        );
    }

    public function getSchema(): string
    {
        return file_get_contents(self::SCHEMA_FILE);
    }
}
