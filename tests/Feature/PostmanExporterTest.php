<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Ninja\Cartographer\Tests\TestCase;
use Ninja\Cartographer\Tests\Fixtures\Controllers\SimpleController;
use Ninja\Cartographer\Tests\Fixtures\Controllers\AuthenticatedController;
use Ninja\Cartographer\Tests\Fixtures\Controllers\ComplexController;

uses(TestCase::class);

beforeEach(function () {
    Route::middleware('api')->group(function () {
        Route::get('/simple', [SimpleController::class, 'index']);
        Route::post('/simple', [SimpleController::class, 'store']);
    });
});

test('can export basic routes to postman collection', function () {
    // Act
    $this->artisan('cartographer:export', ['--format' => 'postman'])
        ->assertSuccessful();

    // Assert
    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

    expect($collection)
        ->toHaveKey('info')
        ->toHaveKey('item')
        ->and($collection['info'])
        ->toHaveKey('name', 'Test API Collection')
        ->and($collection['item'])
        ->toHaveCount(1); // One group for 'simple' endpoints
});

test('can export authenticated routes with bearer token', function () {
    // Arrange
    Route::middleware('auth:api')->group(function () {
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

    // Act
    $this->artisan('cartographer:export', [
        '--format' => 'postman'
    ])->assertSuccessful();

    // Assert
    $collection = json_decode(Storage::get('postman/' . config('cartographer.filename')), true);

    $request = collect($collection['item'])
        ->firstWhere('name', 'Complex')['item'][0]['request'] ?? null;

    expect($request)
        ->not->toBeNull()
        ->toHaveKey('body')
        ->and($request['body'])
        ->toHaveKey('mode', 'raw')
        ->toHaveKey('raw');
});

test('can export routes with pre/post scripts', function () {
    // Arrange
    Route::get('/scripted', [ComplexController::class, 'scriptedEndpoint']);

    // Act
    $this->artisan('cartographer:export', [
        '--format' => 'postman'
    ])->assertSuccessful();

    // Assert
    $collection = json_decode(file_get_contents(
        storage_path('app/postman/test_api_postman_collection.json')
    ), true);

    $request = collect($collection['item'])->first()['item'][0] ?? null;

    expect($request)
        ->not->toBeNull()
        ->toHaveKey('event')
        ->and($request['event'])
        ->toHaveCount(2); // Pre-request and test scripts
});
