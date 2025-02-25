<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Ninja\Cartographer\Tests\Fixtures\Controllers\ResourceController;
use Ninja\Cartographer\Tests\Fixtures\Controllers\UnannotatedController;
use Ninja\Cartographer\Tests\TestCase;
use Ninja\Cartographer\Tests\Fixtures\Controllers\SimpleController;
use Ninja\Cartographer\Tests\Fixtures\Controllers\AuthenticatedController;
use Ninja\Cartographer\Tests\Fixtures\Controllers\ComplexController;

uses(TestCase::class);

test('can export basic routes to postman collection', function () {

    Route::middleware('api')->group(function () {
        Route::get('/simple', [SimpleController::class, 'index']);
        Route::post('/simple', [SimpleController::class, 'store']);
    });

    // Act
    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    // Assert
    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

    expect($collection)
        ->toHaveKey('info')
        ->toHaveKey('item')
        ->and($collection['info'])
        ->toHaveKey('name', 'Test API Group')
        ->and($collection['item'])
        ->toHaveCount(1)
        ->and($collection['item'][0])
        ->toHaveKey('name', 'Simple')
        ->and($collection['item'][0]['item'])
        ->toHaveCount(2)
        ->and(collect($collection['item'][0]['item'])->pluck('name'))
        ->toContain('simple');
});

test('can export authenticated routes with bearer token', function () {
    // Arrange
    Route::middleware(['auth:api','api'])->group(function () {
        Route::get('/protected', [AuthenticatedController::class, 'index']);
    });

    // Act
    $this->artisan('cartographer:export', [
        '--format' => 'postman',
        '--bearer' => 'test-token'
    ])->assertSuccessful();

    // Assert
    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

    expect($collection)
        ->toHaveKey('auth')
        ->and($collection['auth'])
        ->toHaveKey('type', 'bearer')
        ->and($collection['auth']['bearer'][0]['value'])
        ->toBe('test-token');
});

test('can export routes with form requests', function () {
    // Arrange
    Route::middleware('api')->group(function () {
        Route::post('/complex/store', [ComplexController::class, 'store']);
    });

    config(['cartographer.structured' => false]);

    // Act
    $this->artisan('cartographer:export', [
        '--format' => 'postman'
    ])->assertSuccessful();

    // Assert
    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

    $request = collect($collection['item'][0]['item'])
        ->firstWhere('name', 'Complex')['request'] ?? null;

    expect($request)
        ->not->toBeNull()
        ->toHaveKey('body')
        ->and($request['body'])
        ->toHaveKey('mode', 'raw')
        ->toHaveKey('raw');
});

test('can export routes with pre/post scripts', function () {
    // Arrange
    Route::middleware('api')->group(function () {
        Route::get('/scripted', [ComplexController::class, 'scriptedEndpoint']);
    });

    // Act
    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    // Assert
    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

    $request = collect($collection['item'])->first()['item'][0] ?? null;

    expect($request)
        ->not->toBeNull()
        ->toHaveKey('event')
        ->and($request['event'])
        ->toHaveCount(2); // Pre-request and test scripts
});

test('can export collections with pre/post scripts', function () {
    // Arrange
    $eventScriptPath = 'tests/Fixtures/ExampleEvent.js';

    config([
        'cartographer.scripts.pre-request.path' => $eventScriptPath,
        'cartographer.scripts.pre-request.enabled' => true,
        'cartographer.scripts.after-response.path' => $eventScriptPath,
        'cartographer.scripts.after-response.enabled' => true,
    ]);

    // Act
    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    // Assert
    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

    expect($collection)
        ->not->toBeNull()
        ->toHaveKey('event')
        ->and($collection['event'])
        ->toHaveCount(2); // Pre-request and test scripts
});

test('can export structured routes by path', function () {
    Route::middleware('api')->group(function () {
        Route::prefix('api/v1')->group(function () {
            Route::get('users', [UnannotatedController::class, 'index']);
            Route::get('users/{id}', [UnannotatedController::class, 'show']);
            Route::get('posts', [UnannotatedController::class, 'index']);
        });
    });

    config(['cartographer.structured' => true]);
    config(['cartographer.structured_by' => 'path']);

    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

    expect($collection['item'])
        ->toHaveCount(1) // 'api' and default group
        ->and(collect($collection['item'])->first()['item'])
        ->toHaveCount(1); // 'users' and 'posts' groups
});

test('can export structured routes by name', function () {
    Route::middleware('api')->group(function () {
        Route::name('api.')->group(function () {
            Route::name('users.')->group(function () {
                Route::get('users', [UnannotatedController::class, 'index'])->name('index');
                Route::get('users/{id}', [UnannotatedController::class, 'show'])->name('show');
            });
        });
    });

    config([
        'cartographer.structured' => true,
        'cartographer.structured_by' => 'route',
        'cartographer.name' => 'Test API Group',
        'cartographer.base_url' => 'http://localhost',
        'cartographer.enable_formdata' => true,
        'cartographer.include_doc_comments' => false
    ]);

    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

    expect($collection['item'])
        ->toHaveCount(1) // 'api' and default group
        ->and(collect($collection['item'])->first()['item'])
            ->toHaveCount(1); // 'users' group
});


test('can export routes with collection attributes', function () {
    Route::middleware('api')->group(function () {
        Route::resource('users', ResourceController::class);
    });

    config(['cartographer.structured' => true]);
    config(['cartographer.structured_by' => 'path']);

    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

    // Encontrar el grupo Users
    $usersGroup = collect($collection['item'])->firstWhere('name', 'Users');
    expect($usersGroup)
        ->not->toBeNull()
        ->and($usersGroup)
        ->toHaveKey('name', 'Users')
        ->toHaveKey('description', 'CRUD operations for users management')
        ->and($usersGroup['item'][0]['request']['header'])
        ->toBeArray()
        ->toHaveCount(3)
        ->and(collect($usersGroup['item'][0]['request']['header'])->pluck('key'))
        ->toContain('Accept', 'X-Module');
});

test('request attributes override collection attributes', function () {
    Route::middleware('api')->group(function () {
        Route::get('users', [ResourceController::class, 'index']);
    });

    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);
    $indexRequest = collect($collection['item'])
        ->firstWhere('name', 'Users')['item'][0];

    expect($indexRequest)
        ->toHaveKey('name', 'List Users')
        ->and($indexRequest['request'])
        ->toHaveKey('description', 'Get a paginated list of all users');
});

test('exports request with query parameters from attributes', function () {
    Route::middleware('api')->group(function () {
        Route::get('users', [ResourceController::class, 'index']);
    });

    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);
    $indexRequest = collect($collection['item'])
        ->firstWhere('name', 'Users')['item'][0]['request'];

    expect($indexRequest['url'])
        ->toHaveKey('query')
        ->and($indexRequest['url']['query'])
        ->toHaveCount(4) // page, per_page, sort, order
        ->and(collect($indexRequest['url']['query'])->pluck('key'))
        ->toContain('page', 'per_page', 'sort', 'order');
});

test('exports store endpoint with form request validation', function () {
    Route::middleware('api')->group(function () {
        Route::post('users', [ResourceController::class, 'store']);
    });

    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);
    $storeRequest = collect($collection['item'])
        ->firstWhere('name', 'Users')['item'][0]['request'];

    expect($storeRequest)
        ->toHaveKey('body')
        ->and($storeRequest['body'])
        ->toHaveKey('mode', 'raw')
        ->toHaveKey('raw')
        ->and(json_decode($storeRequest['body']['raw'], true))
        ->toHaveKeys(['name', 'email', 'password', 'role', 'metadata']);
});

test('exports route parameters for show endpoint', function () {
    Route::middleware('api')->group(function () {
        Route::get('users/{id}', [ResourceController::class, 'show']);
    });

    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);
    $showRequest = collect($collection['item'])
        ->firstWhere('name', 'Users')['item'][0]['request'];

    expect($showRequest['url'])
        ->toHaveKey('variable')
        ->and($showRequest['url']['variable'])
        ->toHaveCount(1)
        ->and($showRequest['url']['variable'][0])
        ->toMatchArray([
            'key' => 'id',
            'value' => '',
            'type' => 'string'
        ])
        ->and($showRequest['url'])
        ->toHaveKey('query')
        ->and($showRequest['url']['query'])
        ->toHaveCount(1)
        ->and($showRequest['url']['query'][0])
        ->toMatchArray([
            'key' => 'include',
            'value' => '',
            'description' => 'Related resources to include (comma-separated)'
        ]);

});

test('exports update endpoint with form request validation', function () {
    Route::middleware('api')->group(function () {
        Route::put('users/{id}', [ResourceController::class, 'update']);
    });

    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);
    $updateRequest = collect($collection['item'])
        ->firstWhere('name', 'Users')['item'][0]['request'];

    expect($updateRequest)
        ->toHaveKey('body')
        ->and($updateRequest['body'])
        ->toHaveKey('mode', 'raw')
        ->toHaveKey('raw')
        ->and(json_decode($updateRequest['body']['raw'], true))
        ->toHaveKeys(['name', 'email', 'role', 'metadata'])
        ->and($updateRequest['url'])
        ->toHaveKey('variable')
        ->and($updateRequest['url']['variable'])
        ->toHaveCount(1)
        ->and($updateRequest['url']['variable'][0])
        ->toMatchArray([
            'key' => 'id',
            'value' => '',
            'type' => 'string'
        ]);

});

test('collection headers are applied to all group endpoints', function () {
    Route::middleware('api')->group(function () {
        Route::resource('users', ResourceController::class);
    });

    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);
    $usersGroup = collect($collection['item'])->firstWhere('name', 'Users');

    collect($usersGroup['item'])->each(function ($request) {
        expect($request['request']['header'])
            ->toBeArray()
            ->toHaveCount(3)
            ->and(collect($request['request']['header'])->pluck('key'))
            ->toContain('Accept', 'X-Module');
    });
});
