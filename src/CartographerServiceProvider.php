<?php

namespace Ninja\Cartographer;

use Ninja\Cartographer\Commands\ExportCollectionCommand;
use Ninja\Cartographer\Exporters\InsomniaExporter;
use Ninja\Cartographer\Exporters\PostmanExporter;
use Ninja\Cartographer\Processors\RouteProcessor;
use Illuminate\Support\ServiceProvider;

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

        $this->app->bind(PostmanExporter::class, fn($app) => new PostmanExporter(
            $app['config'],
            $app->make(RouteProcessor::class),
        ));

        $this->app->bind(InsomniaExporter::class, fn($app) => new InsomniaExporter(
            $app['config'],
            $app->make(RouteProcessor::class),
        ));
    }
}
