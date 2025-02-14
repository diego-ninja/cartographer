<?php

namespace Ninja\Cartographer\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class SimpleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'ok']);
    }

    public function store(): JsonResponse
    {
        return response()->json(['message' => 'created']);
    }
}
