<?php

namespace Ninja\Cartographer\Processors;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Ninja\Cartographer\Collections\ScriptCollection;
use Ninja\Cartographer\DTO\Script;
use Ninja\Cartographer\Enums\EventType;
use Ninja\Cartographer\Support\RouteReflector;
use ReflectionException;

final readonly class ScriptsProcessor
{
    public function __construct(private AttributeProcessor $attributeProcessor) {}

    /**
     * @throws ReflectionException
     */
    public function processScripts(Route $route): ScriptCollection
    {
        $request = $this->attributeProcessor->getRequestAttribute(RouteReflector::action($route));
        $group = $this->attributeProcessor->getGroupAttribute(RouteReflector::controller($route));

        if ($request?->scripts) {
            return $request->scripts();
        }

        if ($group?->scripts) {
            return $group->scripts();
        }

        return self::processScriptsFromConfig();
    }

    public static function processScriptsFromConfig(): ScriptCollection
    {
        $scripts = new ScriptCollection();
        $configScripts = config()->get('cartographer.scripts', []);
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
