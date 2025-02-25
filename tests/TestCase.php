<?php

namespace Ninja\Cartographer\Tests;

use Ninja\Cartographer\Enums\BodyMode;
use Ninja\Cartographer\Enums\ParameterFormat;
use Ninja\Cartographer\Enums\StructureMode;
use Orchestra\Testbench\TestCase as Orchestra;
use Random\RandomException;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('cartographer', [
            'base_url' => 'http://api.test',
            'name' => 'Test API Group',
            'filename' => 'test.json',
            'auth_middleware' => 'auth:api',
            'include_middleware' => ['api'],
            'structured' => true,
            'structured_by' => StructureMode::Path->value,
            'body_mode' => BodyMode::Raw->value,
            'enable_formdata' => true,
            'disk' => 'local',
            'headers' => [
                ['key' => 'Accept', 'value' => 'application/json'],
                ['key' => 'Content-Type', 'value' => 'application/json'],
            ],
        ]);

        $this->ensureStorageDirectoriesExist();
    }

    protected function ensureStorageDirectoriesExist(): void
    {
        $directories = ['postman', 'insomnia'];
        foreach ($directories as $dir) {
            $path = storage_path("app/$dir");
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            'Ninja\Cartographer\CartographerServiceProvider',
        ];
    }

    /**
     * @throws RandomException
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
