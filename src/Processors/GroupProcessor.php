<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Config\Repository;
use Illuminate\Support\Str;
use Ninja\Cartographer\Collections\RequestCollection;
use Ninja\Cartographer\Collections\RequestGroupCollection;
use Ninja\Cartographer\DTO\Request;
use Ninja\Cartographer\DTO\RequestGroup;
use Ninja\Cartographer\Enums\StructureMode;

final class GroupProcessor
{
    private array $groupCache = [];
    private ?RequestGroupCollection $collection = null;

    public function __construct(
        private readonly Repository $config,
        private readonly AttributeProcessor $attributeProcessor
    ) {}

    public function processRequests(RequestCollection $requests): RequestGroupCollection
    {
        $this->collection = new RequestGroupCollection();

        if (!$this->config->get('cartographer.structured', true)) {
            return $this->processUnstructuredRequests($requests);
        }

        $mode = StructureMode::from(
            $this->config->get('cartographer.structured_by', StructureMode::Path->value)
        );

        $groupedRequests = $this->groupRequestsByBaseUri($requests);

        foreach ($groupedRequests as $requests) {
            $this->processGroup($requests, $mode);
        }

        return $this->collection;
    }

    private function processUnstructuredRequests(RequestCollection $requests): RequestGroupCollection
    {
        $defaultGroup = $this->createGroup(
            name: $this->config->get('cartographer.name', 'API Endpoints'),
            description: 'Default endpoint group'
        );

        foreach ($requests as $request) {
            $defaultGroup->addRequest($request);
        }

        $this->collection->add($defaultGroup);
        return $this->collection;
    }

    private function processGroup(array $requests, StructureMode $mode): void
    {
        /** @var Request $firstRequest */
        $firstRequest = $requests[0];

        if ($firstRequest->group !== null) {
            $this->processExplicitGroup($requests, $firstRequest);
            return;
        }

        $segments = $this->getSegments($firstRequest, $mode);
        if (empty($segments)) {
            $this->addToDefaultGroup($requests);
            return;
        }

        $this->processSegments($segments, $requests);
    }

    private function processExplicitGroup(array $requests, Request $firstRequest): void
    {
        $groupAttributes = $this->attributeProcessor->getGroupAttribute($firstRequest->action->class);
        $group = $this->getOrCreateGroup(
            name: $firstRequest->group,
            description: $groupAttributes?->description()
        );

        foreach ($requests as $request) {
            $group->addRequest($request);
        }

        if (!$this->collection->contains($group)) {
            $this->collection->add($group);
        }
    }

    private function processSegments(array $segments, array $requests): void
    {
        $currentGroup = null;
        $path = '';

        foreach ($segments as $segment) {
            $path .= '/' . $segment;
            $currentGroup = $this->ensureGroupHierarchy($path, $segment, $currentGroup);
        }

        if ($currentGroup) {
            foreach ($requests as $request) {
                $currentGroup->addRequest($request);
            }
        }
    }

    private function ensureGroupHierarchy(string $path, string $segment, ?RequestGroup $parent): RequestGroup
    {
        $normalizedPath = Str::lower($path);

        if (isset($this->groupCache[$normalizedPath])) {
            return $this->groupCache[$normalizedPath];
        }

        $group = $this->createGroup(
            name: $this->formatGroupName($segment),
            description: sprintf('Endpoints for %s', $segment),
            parent: $parent
        );

        if ($parent) {
            $parent->addChild($group);
        } else {
            $this->collection->add($group);
        }

        $this->groupCache[$normalizedPath] = $group;
        return $group;
    }

    private function getSegments(Request $request, StructureMode $mode): array
    {
        return match ($mode) {
            StructureMode::Route => $this->getRouteSegments($request->name),
            default => $this->getPathSegments($request->uri)
        };
    }

    private function getRouteSegments(string $name): array
    {
        foreach (['.', '::', '-', ':'] as $separator) {
            if (str_contains($name, $separator)) {
                $segments = explode($separator, $name);
                array_pop($segments);
                return array_filter($segments);
            }
        }
        return [];
    }

    private function getPathSegments(string $uri): array
    {
        return array_values(array_filter(
            explode('/', trim($uri, '/')),
            fn($segment) => !empty($segment) && !str_starts_with($segment, '{')
        ));
    }

    private function groupRequestsByBaseUri(RequestCollection $requests): array
    {
        $groups = [];
        foreach ($requests as $request) {
            $baseUri = $this->getBaseUri($request->uri);
            $groups[$baseUri][] = $request;
        }
        return $groups;
    }

    private function getBaseUri(string $uri): string
    {
        $segments = array_filter(
            explode('/', trim($uri, '/')),
            fn($segment) => !str_starts_with($segment, '{')
        );
        return implode('/', $segments);
    }

    private function createGroup(
        string $name,
        string $description,
        ?RequestGroup $parent = null
    ): RequestGroup {
        return new RequestGroup(
            id: Str::uuid(),
            name: $this->formatGroupName($name),
            description: $description,
            parent: $parent
        );
    }

    private function formatGroupName(string $name): string
    {
        return Str::title(
            trim(
                preg_replace(
                    '/(?<=\\w)(?=[A-Z])/',
                    ' $1',
                    str_replace(['_', '-'], ' ', $name)
                )
            )
        );
    }

    private function addToDefaultGroup(array $requests): void
    {
        $defaultGroup = $this->getOrCreateGroup('Default');
        foreach ($requests as $request) {
            $defaultGroup->addRequest($request);
        }

        if (!$this->collection->contains($defaultGroup)) {
            $this->collection->add($defaultGroup);
        }
    }

    private function getOrCreateGroup(string $name, ?string $description = null): RequestGroup
    {
        $normalizedName = '/' . Str::lower($name);

        if (!isset($this->groupCache[$normalizedName])) {
            $this->groupCache[$normalizedName] = $this->createGroup(
                name: $name,
                description: $description ?? sprintf('Endpoints for %s', $name)
            );
        }

        return $this->groupCache[$normalizedName];
    }
}
