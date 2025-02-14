<?php

namespace Ninja\Cartographer\Tests\Fixtures\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class AuthenticatedController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'authenticated']);
    }
}
