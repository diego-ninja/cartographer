<?php

namespace Ninja\Cartographer\Collections;

use Ninja\Cartographer\Contracts\Serializable;
use Ninja\Cartographer\DTO\RequestGroup;

final class RequestGroupCollection extends ExportableCollection
{

    public static function from(array|string|Serializable $items): self
    {
        return new self(array_map(
            fn(array $group) => RequestGroup::from($group),
            $items
        ));
    }

    public function addGroup(RequestGroup $group): self
    {
        $this->add($group);
        return $this;
    }

    public function findByName(string $name): ?RequestGroup
    {
        return $this->first(fn(RequestGroup $group) => $group->getName() === $name);
    }

    public function flat(): RequestCollection
    {
        $requests = new RequestCollection();

        foreach ($this as $group) {
            $requests = $requests->merge($group->getRequests());

            foreach ($group->getChildren() as $child) {
                $requests = $requests->merge($child->getRequests());
            }
        }

        return $requests;
    }

    public function forPostman(): array
    {
        return $this->map->forPostman()->values()->all();
    }

    public function forInsomnia(): array
    {
        return $this->map->forInsomnia()->values()->all();
    }
}
