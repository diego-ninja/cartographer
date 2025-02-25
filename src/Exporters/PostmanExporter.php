<?php

namespace Ninja\Cartographer\Exporters;

use Ninja\Cartographer\Builders\PostmanCollectionBuilder;
use Ninja\Cartographer\Exceptions\ExportException;
use Ninja\Cartographer\Exceptions\ValidationException;
use Ninja\Cartographer\Processors\ScriptsProcessor;
use Ramsey\Uuid\Uuid;

final class PostmanExporter extends AbstractExporter
{
    private const SCHEMA_FILE = __DIR__ . '/../../schemas/postman-schema.json';

    /**
     * @throws ExportException|ValidationException
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
                fn($builder) => $builder->addVariable(
                    'token',
                    $this->authProcessor->getStrategy()->getToken(),
                ),
            )
            ->when(
                $this->authProcessor->getStrategy(),
                fn(PostmanCollectionBuilder $builder) => $builder->setAuthentication(
                    $this->authProcessor->getStrategy(),
                ),
            )
            ->setScripts(ScriptsProcessor::processScriptsFromConfig())
            ->build();
    }

    private function builder(): PostmanCollectionBuilder
    {
        return new PostmanCollectionBuilder(
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
