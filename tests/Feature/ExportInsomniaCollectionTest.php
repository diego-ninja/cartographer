<?php

namespace Ninja\Cartographer\Tests\Feature;

use Ninja\Cartographer\Tests\Fixtures\InsomniaCollectionHelpersTrait;
use Ninja\Cartographer\Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

class ExportInsomniaCollectionTest extends TestCase
{
    use InsomniaCollectionHelpersTrait;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cartographer.filename', 'test.json');
        config()->set('cartographer.base_url', 'http://api.test');

        Storage::disk()->deleteDirectory('insomnia');
    }

    public static function providerFormDataEnabled(): array
    {
        return [
            [false],
            [true],
        ];
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_standard_export_works(bool $formDataEnabled): void
    {
        config()->set('cartographer.enable_formdata', $formDataEnabled);

        $this->artisan('cartographer:export --format=insomnia')->assertExitCode(0);

        $collection = json_decode(Storage::get('insomnia/' . config('cartographer.filename')), true);

        // Verify basic structure
        $this->assertEquals('export', $collection['_type']);
        $this->assertEquals(4, $collection['__export_format']);
        $this->assertArrayHasKey('resources', $collection);

        // Verify workspace
        $workspace = Arr::first($collection['resources'], fn($r) => 'workspace' === $r['_type']);
        $this->assertNotNull($workspace);
        $this->assertStringStartsWith('wrk_', $workspace['_id']);

        // Verify environment
        $environment = Arr::first($collection['resources'], fn($r) => 'environment' === $r['_type']);
        $this->assertNotNull($environment);
        $this->assertStringStartsWith('env_', $environment['_id']);
        $this->assertEquals('http://api.test', Arr::first($environment['data']));

        // Verify requests
        $requests = Arr::where($collection['resources'], fn($r) => 'request' === $r['_type']);
        $routes = $this->app['router']->getRoutes();

        $totalRequests = $this->countCollectionItems($requests);
        $this->assertEquals(count($routes), $totalRequests);

        foreach ($routes as $route) {
            $methods = $route->methods();

            $matchingRequests = Arr::where($requests, fn($request) => $request['name'] === $route->getName());

            $matchingRequest = Arr::first($matchingRequests);

            $this->assertNotNull($matchingRequest);
            $this->assertTrue(in_array($matchingRequest['method'], $methods));
        }
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_bearer_export_works(bool $formDataEnabled): void
    {
        config()->set('cartographer.enable_formdata', $formDataEnabled);

        $this->artisan('cartographer:export --format=insomnia --bearer=1234567890')->assertExitCode(0);

        $collection = json_decode(Storage::get('insomnia/' . config('cartographer.filename')), true);

        // Verify environment token
        $environment = Arr::first($collection['resources'], fn($r) => 'environment' === $r['_type']);
        $tokenVariable = Arr::get($environment['data'], 'token');
        $this->assertEquals('1234567890', $tokenVariable);

        // Verify requests authentication
        $requests = Arr::where($collection['resources'], fn($r) => 'request' === $r['_type']);
        foreach ($requests as $request) {
            if (in_array($request['method'], ['GET', 'HEAD', 'OPTIONS'])) {
                continue;
            }

            $this->assertEquals('bearer', $request['authentication']['type']);
            $this->assertEquals('Bearer', $request['authentication']['prefix']);
            $this->assertEquals('{{ token }}', $request['authentication']['token']);
        }
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_basic_export_works(bool $formDataEnabled): void
    {
        config()->set('cartographer.enable_formdata', $formDataEnabled);

        $this->artisan('cartographer:export --format=insomnia --basic=username:password1234')->assertExitCode(0);

        $collection = json_decode(Storage::get('insomnia/' . config('cartographer.filename')), true);

        // Verify environment token
        $environment = Arr::first($collection['resources'], fn($r) => 'environment' === $r['_type']);
        $tokenVariable = Arr::get($environment['data'], 'token');
        $this->assertEquals('username:password1234', $tokenVariable);

        // Verify requests authentication
        $requests = Arr::where($collection['resources'], fn($r) => 'request' === $r['_type']);
        foreach ($requests as $request) {
            if (in_array($request['method'], ['GET', 'HEAD', 'OPTIONS'])) {
                continue;
            }

            $this->assertEquals('basic', $request['authentication']['type']);
            $this->assertEquals('Basic', $request['authentication']['prefix']);
            $this->assertEquals('{{ token }}', $request['authentication']['token']);
        }
    }

    #[DataProvider('providerFormDataEnabled')]
    public function test_structured_export_works(bool $formDataEnabled): void
    {
        config([
            'cartographer.structured' => true,
            'cartographer.enable_formdata' => $formDataEnabled,
        ]);

        $this->artisan('cartographer:export --format=insomnia')->assertExitCode(0);

        $collection = json_decode(Storage::get('insomnia/' . config('cartographer.filename')), true);

        // Verify folders exist
        $folders = Arr::where($collection['resources'], fn($r) => 'request_group' === $r['_type']);
        $this->assertNotEmpty($folders);

        // Verify requests are in folders
        $requests = Arr::where($collection['resources'], fn($r) => 'request' === $r['_type']);
        foreach ($requests as $request) {
            $this->assertStringStartsWith('fld_', $request['parentId']);
        }
    }

    public function test_rules_printing_export_works(): void
    {
        config([
            'cartographer.enable_formdata' => true,
            'cartographer.print_rules' => true,
            'cartographer.rules_to_human_readable' => false,
            'cartographer.body_mode' => 'formdata',
        ]);

        $this->artisan('cartographer:export --format=insomnia')->assertExitCode(0);

        $collection = json_decode(Storage::get('insomnia/' . config('cartographer.filename')), true);
        $requests = Arr::where($collection['resources'], fn($r) => 'request' === $r['_type']);

        $targetRequest = Arr::first($requests, fn($r) => str_contains($r['name'], 'store-with-form-request'));
        $this->assertNotNull($targetRequest);

        $fields = collect($targetRequest['body']['params']);
        $this->assertCount(1, $fields->where('name', 'field_1')->where('description', 'required'));
        $this->assertCount(1, $fields->where('name', 'field_2')->where('description', 'required, integer'));
        $this->assertCount(1, $fields->where('name', 'field_5')->where('description', 'required, integer, max:30, min:1'));
        $this->assertCount(1, $fields->where('name', 'field_6')->where('description', 'in:"1","2","3"'));
    }

    public function test_rules_printing_get_export_works(): void
    {
        config([
            'cartographer.enable_formdata' => true,
            'cartographer.print_rules' => true,
            'cartographer.rules_to_human_readable' => false,
        ]);

        $this->artisan('cartographer:export --format=insomnia')->assertExitCode(0);

        $collection = json_decode(Storage::get('insomnia/' . config('cartographer.filename')), true);
        $requests = Arr::where($collection['resources'], fn($r) => 'request' === $r['_type']);

        $targetRequest = Arr::first($requests, fn($r) => str_contains($r['name'], 'get-with-form-request'));
        $this->assertNotNull($targetRequest);

        $parameters = collect($targetRequest['parameters']);
        $this->assertCount(1, $parameters->where('name', 'field_1')->where('description', 'required'));
        $this->assertCount(1, $parameters->where('name', 'field_2')->where('description', 'required, integer'));
        $this->assertCount(1, $parameters->where('name', 'field_5')->where('description', 'required, integer, max:30, min:1'));
        $this->assertCount(1, $parameters->where('name', 'field_6')->where('description', 'in:"1","2","3"'));
    }

    public function test_rules_printing_export_to_human_readable_works(): void
    {
        config([
            'cartographer.enable_formdata' => true,
            'cartographer.print_rules' => true,
            'cartographer.rules_to_human_readable' => true,
        ]);

        $this->artisan('cartographer:export --format=insomnia')->assertExitCode(0);

        $collection = json_decode(Storage::get('insomnia/' . config('cartographer.filename')), true);
        $requests = Arr::where($collection['resources'], fn($r) => 'request' === $r['_type']);

        $targetRequest = Arr::first($requests, fn($r) => str_contains($r['name'], 'store-with-form-request'));
        $this->assertNotNull($targetRequest);

        $fields = collect($targetRequest['body']['params']);
        $this->assertCount(1, $fields->where('name', 'field_1')->where('description', 'The field 1 field is required.'));
        $this->assertCount(1, $fields->where('name', 'field_2')->where('description', 'The field 2 field is required., The field 2 field must be an integer.'));
        $this->assertCount(1, $fields->where('name', 'field_3')->where('description', '(Optional), The field 3 field must be an integer.'));
        $this->assertCount(1, $fields->where('name', 'field_4')->where('description', '(Nullable), The field 4 field must be an integer.'));
        $this->assertCount(1, $fields->where('name', 'field_5')->where('description', 'The field 5 field is required., The field 5 field must be an integer., The field 5 field must not be greater than 30., The field 5 field must be at least 1.'));
    }

    public function test_uri_is_correct(): void
    {
        $this->artisan('cartographer:export --format=insomnia')->assertExitCode(0);

        $collection = json_decode(Storage::get('insomnia/' . config('cartographer.filename')), true);
        $requests = Arr::where($collection['resources'], fn($r) => 'request' === $r['_type']);

        $targetRequest = Arr::first($requests, fn($r) => str_contains($r['name'], 'php-doc-route'));
        $this->assertNotNull($targetRequest);

        $this->assertEquals('example.php-doc-route', $targetRequest['name']);
        $this->assertEquals('{{ base_url }}/example/phpDocRoute', $targetRequest['url']);
    }

    public function test_api_resource_routes_parameters(): void
    {
        $this->artisan('cartographer:export --format=insomnia')->assertExitCode(0);

        $collection = json_decode(Storage::get('insomnia/' . config('cartographer.filename')), true);
        $requests = Arr::where($collection['resources'], fn($r) => 'request' === $r['_type']);

        // Test hyphenated parameters
        $auditLogRequest = Arr::first($requests, fn($r) => 'example.users.audit-logs.update' === $r['name'] && 'PATCH' === $r['method']);
        $this->assertNotNull($auditLogRequest);
        $this->assertEquals('{{ base_url }}/example/users/:user/audit-logs/:audit_log', $auditLogRequest['url']);

        // Test underscore parameters
        $otherLogRequest = Arr::first($requests, fn($r) => 'example.users.other_logs.update' === $r['name'] && 'PATCH' === $r['method']);
        $this->assertNotNull($otherLogRequest);
        $this->assertEquals('{{ base_url }}/example/users/:user/other_logs/:other_log', $otherLogRequest['url']);

        // Test camelCase parameters
        $someLogRequest = Arr::first($requests, fn($r) => 'example.users.someLogs.update' === $r['name'] && 'PATCH' === $r['method']);
        $this->assertNotNull($someLogRequest);
        $this->assertEquals('{{ base_url }}/example/users/:user/someLogs/:someLog', $someLogRequest['url']);
    }
}
