<?php

namespace Ninja\Cartographer;

use Ninja\Cartographer\Commands\ExportCollectionCommand;
use Ninja\Cartographer\Exporters\InsomniaExporter;
use Ninja\Cartographer\Exporters\PostmanExporter;
use Ninja\Cartographer\Processors\RouteProcessor;
use Illuminate\Support\ServiceProvider;

class CollectionGeneratorServiceProvider extends ServiceProvider
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
                __DIR__ . '/../config/api-postman.php' => config_path('api-postman.php'),
            ], 'postman-config');
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
            __DIR__ . '/../config/api-postman.php',
            'api-postman',
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
