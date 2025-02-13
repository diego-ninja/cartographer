<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Config\Repository;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Ninja\Cartographer\Collections\ScriptCollection;
use Ninja\Cartographer\DTO\Script;
use Ninja\Cartographer\Enums\EventType;
use Ninja\Cartographer\Support\RouteReflector;
use ReflectionException;

final readonly class ScriptsProcessor
{
    public function __construct(private AttributeProcessor $attributeProcessor, private Repository $config) {}

    /**
     * @throws ReflectionException
     */
    public function processScripts(Route $route): ScriptCollection
    {
        $request = $this->attributeProcessor->getRequestAttribute(RouteReflector::method($route));
        $collection = $this->attributeProcessor->getCollectionAttribute(RouteReflector::class($route));

        if ($request?->scripts) {
            return $request->scripts();
        }

        if ($collection?->scripts) {
            return $collection->scripts();
        }

        return $this->processScriptsFromConfig();
    }

    private function processScriptsFromConfig(): ScriptCollection
    {
        $scripts = new ScriptCollection();
        $configScripts = $this->config->get('cartographer.scripts', []);
        foreach ($configScripts as $type => $script) {
            if ($script["enabled"]) {
                $type = EventType::from($type);
                $content = null !== $script["path"] ? File::get($script['path']) : $script["content"];

                $scripts->add(new Script(type: $type, content: $content));
            }
        }

        return $scripts;
    }
}
