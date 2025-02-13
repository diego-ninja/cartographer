<?php

namespace Ninja\Cartographer\Exporters;

use Ninja\Cartographer\Builders\PostmanCollectionBuilder;
use Ninja\Cartographer\Exceptions\ExportException;
use Ramsey\Uuid\Uuid;

final class PostmanExporter extends AbstractExporter
{
    /**
     * @throws ExportException
     */
    protected function generateStructure(): array
    {
        return $this->builder()
            ->addBasicInfo(
                name: $this->config->get('cartographer.name'),
                description: $this->config->get('app.description', 'Cartographer API Collection'),
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
                fn($builder) => $builder->setAuthentication(
                    $this->authProcessor->getStrategy()->toPostmanFormat(),
                ),
            )
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
}
