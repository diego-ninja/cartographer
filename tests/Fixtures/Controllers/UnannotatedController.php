<?php

namespace Ninja\Cartographer\Tests\Fixtures\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ninja\Cartographer\Tests\Fixtures\Requests\UserStoreRequest;
use Ninja\Cartographer\Tests\Fixtures\Requests\UserUpdateRequest;

class UnannotatedController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'List users endpoint'
        ]);
    }

    public function create()
    {
        return response()->json([
            'message' => 'Create user form endpoint'
        ]);
    }

    public function store(UserStoreRequest $request)
    {
        return response()->json([
            'message' => 'Store user endpoint'
        ], 201);
    }

    public function show(string $id)
    {
        return response()->json([
            'message' => 'Show user endpoint',
            'id' => $id
        ]);
    }

    public function edit(string $id)
    {
        return response()->json([
            'message' => 'Edit user form endpoint',
            'id' => $id
        ]);
    }

    public function update(UserUpdateRequest $request, string $id)
    {
        return response()->json([
            'message' => 'Update user endpoint',
            'id' => $id
        ]);
    }

    public function destroy(string $id)
    {
        return response()->json([
            'message' => 'Delete user endpoint',
            'id' => $id
        ], 204);
    }
}
