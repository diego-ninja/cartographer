<?php

namespace Ninja\Cartographer\DTO;

use JsonSerializable;
use Ninja\Cartographer\Collections\HeaderCollection;
use Ninja\Cartographer\Collections\RequestCollection;
use Ninja\Cartographer\Collections\RequestGroupCollection;
use Ninja\Cartographer\Collections\ScriptCollection;
use Ninja\Cartographer\Contracts\AuthenticationStrategy;
use Ninja\Cartographer\Enums\EventType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class RequestGroup implements JsonSerializable
{
    public RequestCollection $requests;
    public RequestGroupCollection $children;

    public function __construct(
        public UuidInterface $id,
        public string $name,
        public ?string $description = null,
        public ?AuthenticationStrategy $authentication = null,
        public ?HeaderCollection $headers = null,
        public ?ScriptCollection $scripts = null,
        public ?RequestGroup $parent = null,
    ) {
        $this->requests = new RequestCollection();
        $this->children = new RequestGroupCollection();
    }

    public static function create(
        string $name,
        string $description = '',
        ?AuthenticationStrategy $authentication = null,
        ?HeaderCollection $headers = null,
        ?ScriptCollection $scripts = null,
        ?RequestGroup $parent = null,
    ): self {
        return new self(
            Uuid::uuid4(),
            $name,
            $description,
            $authentication,
            $headers,
            $scripts,
            $parent,
        );
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAuthentication(): ?AuthenticationStrategy
    {
        return $this->authentication ?? $this->parent?->getAuthentication();
    }

    public function getHeaders(): HeaderCollection
    {
        if ($this->parent && $parentHeaders = $this->parent->getHeaders()) {
            return $this->headers
                ? $parentHeaders->merge($this->headers)
                : $parentHeaders;
        }

        return $this->headers ?? new HeaderCollection();
    }

    public function getScripts(): ScriptCollection
    {
        if ($this->parent && $parentScripts = $this->parent->getScripts()) {
            return $this->scripts
                ? $parentScripts->merge($this->scripts)
                : $parentScripts;
        }

        return $this->scripts ?? new ScriptCollection();
    }

    public function getParent(): ?RequestGroup
    {
        return $this->parent;
    }

    public function addRequest(Request $request): self
    {
        $this->requests->add($request);
        return $this;
    }

    public function getRequests(): RequestCollection
    {
        return $this->requests;
    }

    public function addChild(RequestGroup $group): self
    {
        $this->children->add($group);
        return $this;
    }

    public function getChildren(): RequestGroupCollection
    {
        return $this->children;
    }

    public function hasChildren(): bool
    {
        return ! $this->children->isEmpty();
    }

    public function findChildByName(string $name): ?RequestGroup
    {
        return $this->children->findByName($name);
    }

    public function getAllRequests(): RequestCollection
    {
        $requests = clone $this->requests;

        foreach ($this->children as $child) {
            $requests = $requests->merge($child->getAllRequests());
        }

        return $requests;
    }

    public function flatten(): RequestGroupCollection
    {
        $groups = new RequestGroupCollection([$this]);

        /** @var RequestGroup $child */
        foreach ($this->children as $child) {
            $groups = $groups->merge($child->flatten());
        }

        return $groups;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id->toString(),
            'name' => $this->name,
            'description' => $this->description,
            'authentication' => $this->authentication?->toArray(),
            'headers' => $this->headers?->toArray(),
            'scripts' => $this->scripts?->toArray(),
            'requests' => $this->requests->toArray(),
            'children' => $this->children->toArray(),
        ];
    }

    public function forPostman(): array
    {
        $item = [
            'name' => $this->name,
            'description' => $this->description,
            'item' => [],
        ];

        if ($this->authentication) {
            $item['auth'] = $this->authentication->toPostmanFormat();
        }

        if ($this->headers && ! $this->headers->isEmpty()) {
            $item['header'] = $this->headers->formatted();
        }

        if ($this->scripts && ! $this->scripts->isEmpty()) {
            $item['event'] = $this->scripts->map(fn(Script $script) => $script->forPostman());
        }

        foreach ($this->requests as $request) {
            $item['item'][] = $request->forPostman();
        }

        foreach ($this->children as $child) {
            $item['item'][] = $child->forPostman();
        }

        return $item;
    }

    public function forInsomnia(string $workspaceId): array
    {
        $resources = [];

        // Add group/folder resource
        $resources[] = [
            '_id' => 'fld_' . $this->id->toString(),
            '_type' => 'request_group',
            'parentId' => $this->parent?->getId()->toString() ?? $workspaceId,
            'name' => $this->name,
            'description' => $this->description,
            'environment' => [],
            'environmentPropertyOrder' => null,
            'metaSortKey' => 0,
        ];

        if ($this->authentication) {
            $resources[0]['authentication'] = $this->authentication->toInsomniaFormat();
        }

        if ($this->headers && ! $this->headers->isEmpty()) {
            $resources[0]['headers'] = $this->headers->formatted();
        }

        if ($this->scripts && ! $this->scripts->isEmpty()) {
            $resources[0]['preRequestScript'] = $this->scripts->findByType(EventType::PreRequest)->forInsomnia();
            $resources[0]['afterResponseScript'] = $this->scripts->findByType(EventType::AfterResponse)->forInsomnia();
        }

        foreach ($this->requests as $request) {
            $resources[] = $request->forInsomnia('fld_' . $this->id->toString());
        }

        foreach ($this->children as $child) {
            $resources = array_merge($resources, $child->forInsomnia());
        }

        return $resources;
    }
}
