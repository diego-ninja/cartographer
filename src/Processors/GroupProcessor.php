<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Config\Repository;
use Illuminate\Support\Str;
use Ninja\Cartographer\Collections\RequestCollection;
use Ninja\Cartographer\Collections\RequestGroupCollection;
use Ninja\Cartographer\DTO\Request;
use Ninja\Cartographer\DTO\RequestGroup;

final class GroupProcessor
{
    private array $groupMap = [];

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function processRequests(RequestCollection $requests): RequestGroupCollection
    {
        $collection = new RequestGroupCollection();

        foreach ($requests as $request) {
            if (null !== $request->group) {
                $group = $this->getOrCreateGroup($request->group);
                $group->requests->add($request);
                if ( ! $collection->contains($group)) {
                    $collection->add($group);
                }
                continue;
            }

            $structuredBy = $this->config->get('cartographer.structured_by', 'route_path');
            $segments = $this->getSegments($request, $structuredBy);

            $this->processRequestWithSegments($request, $segments, $collection);
        }

        return $collection;
    }

    private function getSegments(Request $request, string $structuredBy): array
    {
        if ('route_name' === $structuredBy && $request->name) {
            return array_filter(preg_split('/[.:]++/', $request->name));
        }

        return array_values(array_filter(
            explode('/', $request->uri),
            fn($segment) => ! empty($segment) && ! Str::startsWith($segment, '{'),
        ));
    }

    private function processRequestWithSegments(
        Request $request,
        array $segments,
        RequestGroupCollection $collection,
    ): void {
        if (empty($segments) || ($this->shouldBeRoot($segments, $request))) {
            $collection->add($request);
            return;
        }

        $groupSegments = $this->getGroupSegments($segments, $request);
        $currentGroup = $this->buildGroupHierarchy($groupSegments, $collection);

        if ($currentGroup) {
            $currentGroup->requests->add($request);
        } else {
            $collection->add($request);
        }
    }

    private function shouldBeRoot(array $segments): bool
    {
        $structuredBy = $this->config->get('cartographer.structured_by', 'route_path');
        return 'route_path' === $structuredBy && 1 === count($segments);
    }

    private function getGroupSegments(array $segments): array
    {
        return array_slice($segments, 0, -1);
    }

    private function buildGroupHierarchy(array $groupSegments, RequestGroupCollection $collection): ?RequestGroup
    {
        if (empty($groupSegments)) {
            return null;
        }

        $currentGroup = null;
        $groupPath = '';

        foreach ($groupSegments as $segment) {
            $groupPath .= '/' . $segment;
            $currentGroup = $this->ensureGroup($groupPath, $segment, $currentGroup, $collection);
        }

        return $currentGroup;
    }

    private function ensureGroup(
        string $groupPath,
        string $segment,
        ?RequestGroup $parentGroup,
        RequestGroupCollection $collection,
    ): RequestGroup {
        if (isset($this->groupMap[$groupPath])) {
            return $this->groupMap[$groupPath];
        }

        $group = new RequestGroup(
            id: Str::uuid(),
            name: Str::title($segment),
            description: sprintf('Endpoints for %s', $segment),
            parent: $parentGroup,
        );

        if ($parentGroup) {
            $parentGroup->children->add($group);
        } else {
            $collection->add($group);
        }

        $this->groupMap[$groupPath] = $group;
        return $group;
    }

    private function getOrCreateGroup(string $name): RequestGroup
    {
        $normalizedName = '/' . Str::lower($name);

        if ( ! isset($this->groupMap[$normalizedName])) {
            $this->groupMap[$normalizedName] = new RequestGroup(
                id: Str::uuid(),
                name: Str::title($name),
                description: sprintf('Endpoints for %s', $name),
            );
        }

        return $this->groupMap[$normalizedName];
    }
}
