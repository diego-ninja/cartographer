<?php

namespace Ninja\Cartographer\Collections;

use Illuminate\Support\Collection;
use Ninja\Cartographer\DTO\RequestGroup;

final class RequestGroupCollection extends Collection
{
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
}
