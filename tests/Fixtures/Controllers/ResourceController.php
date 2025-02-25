<?php

namespace Ninja\Cartographer\Tests\Fixtures\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ninja\Cartographer\Attributes\Group;
use Ninja\Cartographer\Attributes\Request as ApiRequest;
use Ninja\Cartographer\Tests\Fixtures\Requests\UserStoreRequest;
use Ninja\Cartographer\Tests\Fixtures\Requests\UserUpdateRequest;

#[Group(
    name: 'Users API',
    description: 'CRUD operations for users management',
    group: 'Users',
    headers: [
        'Accept' => 'application/json',
        'X-Module' => 'users'
    ]
)]
class ResourceController extends Controller
{
    /**
     * Display a listing of users.
     */
    #[ApiRequest(
        name: 'List Users',
        description: 'Get a paginated list of all users',
        params: [
            'page' => 'Page number for pagination',
            'per_page' => 'Number of items per page',
            'sort' => 'Field to sort by',
            'order' => 'Sort direction (asc/desc)'
        ]
    )]
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'List users endpoint'
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    #[ApiRequest(
        name: 'Create User Form',
        description: 'Get the form for creating a new user'
    )]
    public function create()
    {
        return response()->json([
            'message' => 'Create user form endpoint'
        ]);
    }

    /**
     * Store a newly created user.
     */
    #[ApiRequest(
        name: 'Store User',
        description: 'Create a new user with the provided data'
    )]
    public function store(UserStoreRequest $request)
    {
        return response()->json([
            'message' => 'Store user endpoint'
        ], 201);
    }

    /**
     * Display the specified user.
     */
    #[ApiRequest(
        name: 'Show User',
        description: 'Get detailed information about a specific user',
        params: [
            'include' => 'Related resources to include (comma-separated)'
        ]
    )]
    public function show(string $id)
    {
        return response()->json([
            'message' => 'Show user endpoint',
            'id' => $id
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    #[ApiRequest(
        name: 'Edit User Form',
        description: 'Get the form for editing an existing user'
    )]
    public function edit(string $id)
    {
        return response()->json([
            'message' => 'Edit user form endpoint',
            'id' => $id
        ]);
    }

    /**
     * Update the specified user.
     */
    #[ApiRequest(
        name: 'Update User',
        description: 'Update an existing user with the provided data'
    )]
    public function update(UserUpdateRequest $request, string $id)
    {
        return response()->json([
            'message' => 'Update user endpoint',
            'id' => $id
        ]);
    }

    /**
     * Remove the specified user.
     */
    #[ApiRequest(
        name: 'Delete User',
        description: 'Remove an existing user from the system'
    )]
    public function destroy(string $id)
    {
        return response()->json([
            'message' => 'Delete user endpoint',
            'id' => $id
        ], 204);
    }
}
