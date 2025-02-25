<?php

namespace Ninja\Cartographer\DTO;

use JsonSerializable;
use Ninja\Cartographer\Collections\HeaderCollection;
use Ninja\Cartographer\Collections\RequestCollection;
use Ninja\Cartographer\Collections\RequestGroupCollection;
use Ninja\Cartographer\Collections\ScriptCollection;
use Ninja\Cartographer\Contracts\AuthenticationStrategy;
use Ninja\Cartographer\Authentication\Strategy\AuthStrategyFactory;
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

    public static function from(string|array|self $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        $group = new self(
            id: isset($data['id']) ? Uuid::fromString($data['id']) : Uuid::uuid4(),
            name: $data['name'],
            description: $data['description'] ?? null,
            authentication: self::resolveAuthentication($data['authentication'] ?? null),
            headers: isset($data['headers']) ? HeaderCollection::from($data['headers']) : null,
            scripts: isset($data['scripts']) ? ScriptCollection::from($data['scripts']) : null,
            parent: isset($data['parent']) ? self::from($data['parent']) : null
        );

        if (isset($data['requests'])) {
            foreach ($data['requests'] as $request) {
                $group->addRequest(Request::from($request));
            }
        }

        if (isset($data['children'])) {
            foreach ($data['children'] as $child) {
                $childGroup = self::from($child);
                $childGroup->parent = $group;
                $group->addChild($childGroup);
            }
        }

        return $group;
    }

    private static function resolveAuthentication(?array $auth): ?AuthenticationStrategy
    {
        if (!$auth || !isset($auth['type'])) {
            return null;
        }

        return AuthStrategyFactory::create(
            type: $auth['type'],
            token: $auth['token'] ?? null,
            options: $auth['options'] ?? []
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
        return $this->description ?? '';
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
        return !$this->children->isEmpty();
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
            $item['auth'] = $this->authentication->forPostman();
        }

        if ($this->headers && !$this->headers->isEmpty()) {
            $item['header'] = $this->headers->forPostman();
        }

        if ($this->scripts && !$this->scripts->isEmpty()) {
            $item['event'] = $this->scripts->forPostman();
        }

        foreach ($this->requests as $request) {
            $item['item'][] = $request->forPostman();
        }

        foreach ($this->children as $child) {
            $item['item'][] = $child->forPostman();
        }

        return $item;
    }

    public function forInsomnia(): array
    {
        $resources = [];

        // Add group/folder resource
        $resources[] = [
            '_id' => 'fld_' . $this->id->toString(),
            '_type' => 'request_group',
            'parentId' => $this->parent?->getId()->toString() ?? 'wrk_default',
            'name' => $this->name,
            'description' => $this->description,
            'environment' => [],
            'environmentPropertyOrder' => null,
            'metaSortKey' => 0,
        ];

        if ($this->authentication) {
            $resources[0]['authentication'] = $this->authentication->forInsomnia();
        }

        if ($this->headers && !$this->headers->isEmpty()) {
            $resources[0]['headers'] = $this->headers->forInsomnia();
        }

        if ($this->scripts && !$this->scripts->isEmpty()) {
            if ($preRequest = $this->scripts->findByType(EventType::PreRequest)) {
                $resources[0]['preRequestScript'] = $preRequest->content;
            }
            if ($postResponse = $this->scripts->findByType(EventType::AfterResponse)) {
                $resources[0]['postResponseScript'] = $postResponse->content;
            }
        }

        foreach ($this->requests as $request) {
            $resources[] = array_merge(
                $request->forInsomnia(),
                ['parentId' => 'fld_' . $this->id->toString()]
            );
        }

        foreach ($this->children as $child) {
            $resources = array_merge($resources, $child->forInsomnia());
        }

        return $resources;
    }
}
