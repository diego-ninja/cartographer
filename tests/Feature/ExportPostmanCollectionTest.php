<?php

namespace Ninja\Cartographer\Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Ninja\Cartographer\Enums\BodyMode;
use Ninja\Cartographer\Enums\Method;
use Ninja\Cartographer\Tests\Fixtures\PostmanCollectionHelpersTrait;
use Ninja\Cartographer\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ExportPostmanCollectionTest extends TestCase
{
    use PostmanCollectionHelpersTrait;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cartographer.filename', 'test.json');

        Storage::disk()->deleteDirectory('postman');
    }

    public static function providerFormDataEnabled(): array
    {
        return [
            [
                false,
            ],
            [
                true,
            ],
        ];
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_standard_export_works(bool $formDataEnabled): void
    {
        config()->set('cartographer.enable_formdata', $formDataEnabled);

        $this->artisan('cartographer:export')->assertExitCode(0);

        $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

        $routes = $this->app['router']->getRoutes();

        $collectionItems = $collection['item'];

        $totalCollectionItems = $this->countCollectionItems($collection['item']);

        $this->assertEquals(count($routes), $totalCollectionItems);

        foreach ($routes as $route) {
            /** @var Route $route */
            $methods = $route->methods();

            $collectionRoutes = Arr::where($collectionItems, fn(array $item) => $item['name'] === $route->getName());
            $collectionRoute = Arr::first($collectionRoutes);

            if ( ! in_array($collectionRoute['request']['method'], $methods)) {
                $methods = collect($collectionRoutes)->pluck('request.method')->toArray();
            }

            $this->assertNotNull($collectionRoute);
            $this->assertTrue(in_array($collectionRoute['request']['method'], $methods));
        }
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_bearer_export_works(bool $formDataEnabled): void
    {
        config()->set('cartographer.enable_formdata', $formDataEnabled);

        $this->artisan('cartographer:export --bearer=1234567890')->assertExitCode(0);

        $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

        $routes = $this->app['router']->getRoutes();

        $collectionVariables = $collection['variable'];

        foreach ($collectionVariables as $variable) {
            if ('token' !== $variable['key']) {
                continue;
            }

            $this->assertEquals('1234567890', $variable['value']);
        }

        $this->assertCount(2, $collectionVariables);

        $totalCollectionItems = $this->countCollectionItems($collection['item']);

        $this->assertEquals(count($routes), $totalCollectionItems);

        foreach ($routes as $route) {
            $methods = $route->methods();

            $collectionRoutes = Arr::where($collection['item'], fn($item) => $item['name'] === $route->getName());

            $collectionRoute = Arr::first($collectionRoutes);

            if ( ! in_array($collectionRoute['request']['method'], $methods)) {
                $methods = collect($collectionRoutes)->pluck('request.method')->toArray();
            }

            $this->assertNotNull($collectionRoute);
            $this->assertTrue(in_array($collectionRoute['request']['method'], $methods));
        }
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_basic_export_works(bool $formDataEnabled): void
    {
        config()->set('cartographer.enable_formdata', $formDataEnabled);

        $this->artisan('cartographer:export --basic=username:password1234')->assertExitCode(0);

        $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

        $routes = $this->app['router']->getRoutes();

        $collectionVariables = $collection['variable'];

        foreach ($collectionVariables as $variable) {
            if ('token' !== $variable['key']) {
                continue;
            }

            $this->assertEquals('username:password1234', $variable['value']);
        }

        $this->assertCount(2, $collectionVariables);

        $totalCollectionItems = $this->countCollectionItems($collection['item']);

        $this->assertEquals(count($routes), $totalCollectionItems);

        foreach ($routes as $route) {
            $methods = $route->methods();

            $collectionRoutes = Arr::where($collection['item'], fn($item) => $item['name'] === $route->getName());

            $collectionRoute = Arr::first($collectionRoutes);

            if ( ! in_array($collectionRoute['request']['method'], $methods)) {
                $methods = collect($collectionRoutes)->pluck('request.method')->toArray();
            }

            $this->assertNotNull($collectionRoute);
            $this->assertTrue(in_array($collectionRoute['request']['method'], $methods));
        }
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_structured_export_works(bool $formDataEnabled): void
    {
        config([
            'cartographer.structured' => true,
            'cartographer.enable_formdata' => $formDataEnabled,
        ]);

        $this->artisan('cartographer:export')->assertExitCode(0);

        $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

        $routes = $this->app['router']->getRoutes();

        $totalCollectionItems = $this->countCollectionItems($collection['item']);

        $this->assertEquals(count($routes), $totalCollectionItems);
    }

    public function test_rules_printing_export_works(): void
    {
        config([
            'cartographer.enable_formdata' => true,
            'cartographer.print_rules' => true,
            'cartographer.rules_to_human_readable' => false,
            'cartographer.body_mode' => BodyMode::UrlEncoded->value,
        ]);

        $this->artisan('cartographer:export')->assertExitCode(0);

        $collection = collect(json_decode(Storage::get('postman/' . config('cartographer.filename')), true)['item']);

        $targetRequest = $collection
            ->where('name', 'example.store-with-form-request')
            ->first();

        $fields = collect($targetRequest['request']['body']['urlencoded']);
        $this->assertCount(1, $fields->where('key', 'field_1')->where('description', 'required'));
        $this->assertCount(1, $fields->where('key', 'field_2')->where('description', 'required, integer'));
        $this->assertCount(1, $fields->where('key', 'field_5')->where('description', 'required, integer, max:30, min:1'));
        $this->assertCount(1, $fields->where('key', 'field_6')->where('description', 'in:"1","2","3"'));
    }

    public function test_rules_printing_get_export_works(): void
    {
        config([
            'cartographer.enable_formdata' => true,
            'cartographer.print_rules' => true,
            'cartographer.rules_to_human_readable' => false,
        ]);

        $this->artisan('cartographer:export')->assertExitCode(0);

        $this->assertTrue(true);

        $collection = collect(json_decode(Storage::get('postman/' . config('cartographer.filename')), true)['item']);

        $targetRequest = $collection
            ->where('name', 'example.get-with-form-request')
            ->first();

        $this->assertEqualsCanonicalizing([
            'raw' => '{{base_url}}/example/getWithFormRequest',
            'host' => [
                '{{base_url}}',
            ],
            'path' => [
                'example',
                'getWithFormRequest',
            ],
            'variable' => [],
        ], array_slice($targetRequest['request']['url'], 0, 4));

        $fields = collect($targetRequest['request']['url']['query']);
        $this->assertCount(1, $fields->where('key', 'field_1')->where('description', 'required'));
        $this->assertCount(1, $fields->where('key', 'field_2')->where('description', 'required, integer'));
        $this->assertCount(1, $fields->where('key', 'field_5')->where('description', 'required, integer, max:30, min:1'));
        $this->assertCount(1, $fields->where('key', 'field_6')->where('description', 'in:"1","2","3"'));

        // Check for the required structure of the get request query
        foreach ($fields as $field) {
            $this->assertEqualsCanonicalizing([
                'key' => $field['key'],
                'value' => null,
                'disabled' => false,
                'description' => $field['description'],
            ], $field);
        }
    }

    public function test_rules_printing_export_to_human_readable_works(): void
    {
        config([
            'cartographer.enable_formdata' => true,
            'cartographer.print_rules' => true,
            'cartographer.rules_to_human_readable' => true,
            'cartographer.body_mode' => BodyMode::UrlEncoded->value,
        ]);

        $this->artisan('cartographer:export')->assertExitCode(0);

        $collection = collect(json_decode(Storage::get('postman/' . config('cartographer.filename')), true)['item']);

        $targetRequest = $collection
            ->where('name', 'example.store-with-form-request')
            ->first();

        $fields = collect($targetRequest['request']['body']['urlencoded']);
        $this->assertCount(1, $fields->where('key', 'field_1')->where('description', 'The field 1 field is required.'));
        $this->assertCount(1, $fields->where('key', 'field_2')->where('description', 'The field 2 field is required., The field 2 field must be an integer.'));
        $this->assertCount(1, $fields->where('key', 'field_3')->where('description', '(Optional), The field 3 field must be an integer.'));
        $this->assertCount(1, $fields->where('key', 'field_4')->where('description', '(Nullable), The field 4 field must be an integer.'));
        // the below fails locally, but passes on GitHub actions?
        $this->assertCount(1, $fields->where('key', 'field_5')->where('description', 'The field 5 field is required., The field 5 field must be an integer., The field 5 field must not be greater than 30., The field 5 field must be at least 1.'));

        /** This looks bad, but this is the default message in lang/en/validation.php, you can update to:.
         *
         * "'in' => 'The selected :attribute is invalid. Allowable values: :values',"
         **/
        $this->assertCount(1, $fields->where('key', 'field_6')->where('description', 'The selected field 6 is invalid.'));
        $this->assertCount(1, $fields->where('key', 'field_7')->where('description', 'The field 7 field is required., The selected field 7 is invalid.'));
        $this->assertCount(1, $fields->where('key', 'field_8')->where('description', 'The field 8 field must be uppercase.'));
        $this->assertCount(1, $fields->where('key', 'field_9')->where('description', 'The field 9 field is required., The field 9 field must be a string., The field 9 field must be uppercase.'));
    }

    public function test_event_export_works(): void
    {
        $eventScriptPath = 'tests/Fixtures/ExampleEvent.js';

        config([
            'cartographer.scripts.pre-request.path' => $eventScriptPath,
            'cartographer.scripts.pre-request.enabled' => true,
            'cartographer.scripts.after-response.path' => $eventScriptPath,
            'cartographer.scripts.after-response.enabled' => true,
        ]);

        $this->artisan('cartographer:export')->assertExitCode(0);

        $collection = collect(json_decode(Storage::get('postman/' . config('cartographer.filename')), true));

        $events = $collection
            ->whereIn('listen', ['prerequest', 'test'])
            ->all();

        $this->assertCount(2, $events);

        $content = mb_trim(file_get_contents($eventScriptPath));

        foreach ($events as $event) {
            $this->assertEquals(Arr::first($event['script']['exec']), $content);
        }
    }

    public function test_php_doc_comment_export(): void
    {
        config([
            'cartographer.include_doc_comments' => true,
        ]);

        $this->artisan('cartographer:export')->assertExitCode(0);

        $collection = collect(json_decode(Storage::get('postman/' . config('cartographer.filename')), true)['item']);

        $targetRequest = $collection
            ->where('name', 'example.php-doc-route')
            ->first();

        $this->assertEquals('This is the php doc route. Which is also multi-line. and has a blank line.', $targetRequest['request']['description']);
    }

    public function test_uri_is_correct(): void
    {
        $this->artisan('cartographer:export')->assertExitCode(0);

        $collection = collect(json_decode(Storage::get('postman/' . config('cartographer.filename')), true)['item']);

        $targetRequest = $collection
            ->where('name', 'example.php-doc-route')
            ->first();

        $this->assertEquals('example.php-doc-route', $targetRequest['name']);
        $this->assertEquals('{{base_url}}/example/phpDocRoute', $targetRequest['request']['url']['raw']);
    }

    public function test_api_resource_routes_set_parameters_correctly_with_hyphens(): void
    {
        $this->artisan('cartographer:export')->assertExitCode(0);

        $collection = collect(json_decode(Storage::get('postman/' . config('cartographer.filename')), true)['item']);

        $targetRequest = $collection
            ->where('name', 'example.users.audit-logs.update')
            ->where('request.method', 'PATCH')
            ->first();

        $this->assertEquals('example.users.audit-logs.update', $targetRequest['name']);
        $this->assertEquals('{{base_url}}/example/users/:user/audit-logs/:audit_log', $targetRequest['request']['url']['raw']);
    }

    public function test_api_resource_routes_set_parameters_correctly_with_underscores(): void
    {
        $this->artisan('cartographer:export')->assertExitCode(0);

        $collection = collect(json_decode(Storage::get('postman/' . config('cartographer.filename')), true)['item']);

        $targetRequest = $collection
            ->where('name', 'example.users.other_logs.update')
            ->where('request.method', 'PATCH')
            ->first();

        $this->assertEquals('example.users.other_logs.update', $targetRequest['name']);
        $this->assertEquals('{{base_url}}/example/users/:user/other_logs/:other_log', $targetRequest['request']['url']['raw']);
    }

    public function test_api_resource_routes_set_parameters_correctly_with_camel_case(): void
    {
        $this->artisan('cartographer:export')->assertExitCode(0);

        $collection = collect(json_decode(Storage::get('postman/' . config('cartographer.filename')), true)['item']);

        $targetRequest = $collection
            ->where('name', 'example.users.someLogs.update')
            ->where('request.method', 'PATCH')
            ->first();

        $this->assertEquals('example.users.someLogs.update', $targetRequest['name']);
        $this->assertEquals('{{base_url}}/example/users/:user/someLogs/:someLog', $targetRequest['request']['url']['raw']);
    }

    public function test_request_attributes_are_applied(): void
    {
        $this->artisan('cartographer:export')->assertExitCode(0);

        $collection = collect(json_decode(Storage::get('postman/' . config('cartographer.filename')), true)['item']);

        $indexRequest = $collection->where('name', 'List Audit Logs')->first();
        $this->assertEquals('List all audit logs', $indexRequest['request']['description']);

        $showWithReflectionRequest = $collection->where('name', 'example.show-with-reflection-method')->first();
        $this->assertEquals('example.show-with-reflection-method', $showWithReflectionRequest['name']);
        $this->assertEquals(Method::GET->value, $showWithReflectionRequest['request']['method']);

    }

    public function test_request_groups_are_applied_in_structured_mode(): void
    {
        config([
            'cartographer.structured' => true,
        ]);

        $this->artisan('cartographer:export')->assertExitCode(0);

        $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

        $logsFolder = Arr::first($collection['item'], fn($item) => isset($item['name']) && 'Logs' === $item['name']);

        $this->assertNotNull($logsFolder);
        $this->assertArrayHasKey('item', $logsFolder);

        $indexRequest = collect($logsFolder['item'])->first(fn($item) => 'List Audit Logs' === $item['name']);

        $this->assertNotNull($indexRequest);
        $this->assertEquals('List all audit logs', $indexRequest['request']['description']);
    }
}
