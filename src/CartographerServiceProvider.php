<?php

namespace Ninja\Cartographer;

use Illuminate\Support\ServiceProvider;
use Ninja\Cartographer\Commands\ExportCollectionCommand;
use Ninja\Cartographer\Exporters\InsomniaExporter;
use Ninja\Cartographer\Exporters\PostmanExporter;
use Ninja\Cartographer\Processors\AttributeProcessor;
use Ninja\Cartographer\Processors\AuthenticationProcessor;
use Ninja\Cartographer\Processors\BodyProcessor;
use Ninja\Cartographer\Processors\GroupProcessor;
use Ninja\Cartographer\Processors\HeaderProcessor;
use Ninja\Cartographer\Processors\ParameterProcessor;
use Ninja\Cartographer\Processors\RouteProcessor;
use Ninja\Cartographer\Processors\ScriptsProcessor;

class CartographerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cartographer.php' => config_path('cartographer.php'),
            ], 'cartographer-config');
        }

        $this->commands(ExportCollectionCommand::class);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cartographer.php',
            'cartographer',
        );

        // Register Processors
        $this->app->singleton(AuthenticationProcessor::class);
        $this->app->singleton(ParameterProcessor::class);
        $this->app->singleton(BodyProcessor::class);
        $this->app->singleton(HeaderProcessor::class);
        $this->app->singleton(ScriptsProcessor::class);
        $this->app->singleton(GroupProcessor::class);

        $this->app->singleton(RouteProcessor::class, fn($app) => new RouteProcessor(
            $app['router'],
            $app['config'],
            $app->make(AttributeProcessor::class),
            $app->make(AuthenticationProcessor::class),
            $app->make(ParameterProcessor::class),
            $app->make(BodyProcessor::class),
            $app->make(HeaderProcessor::class),
            $app->make(ScriptsProcessor::class),
            $app->make(GroupProcessor::class),
        ));

        // Register Exporters
        $this->app->bind(PostmanExporter::class, fn($app) => new PostmanExporter(
            $app['config'],
            $app->make(RouteProcessor::class),
            $app->make(AuthenticationProcessor::class),
        ));

        $this->app->bind(InsomniaExporter::class, fn($app) => new InsomniaExporter(
            $app['config'],
            $app->make(RouteProcessor::class),
            $app->make(AuthenticationProcessor::class),
        ));
    }
}
