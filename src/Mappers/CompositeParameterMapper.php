<?php

namespace Ninja\Cartographer\Mappers;

use Illuminate\Support\Collection;
use Ninja\Cartographer\Collections\ParameterCollection;
use Ninja\Cartographer\Contracts\Mapper;

class CompositeParameterMapper implements Mapper
{
    private Collection $mappers;

    public function __construct()
    {
        $this->mappers = new Collection();
    }

    public function addMapper(ParameterMapper $mapper): self
    {
        $this->mappers->push($mapper);
        return $this;
    }

    public function map(): ParameterCollection
    {
        $parameters = new ParameterCollection();

        foreach ($this->mappers as $mapper) {
            $parameters = $parameters->merge($mapper->map());
        }

        return ParameterCollection::from($parameters->all());
    }
}
