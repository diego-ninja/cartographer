<?php

namespace Ninja\Cartographer\Tests\Fixtures\Controllers;

use Illuminate\Routing\Controller;
use Ninja\Cartographer\Tests\Fixtures\Requests\ComplexStoreRequest;
use Ninja\Cartographer\Attributes\Group;
use Ninja\Cartographer\Attributes\Request;

#[Group(
    name: 'Complex API',
    description: 'Complex endpoints with various features',
    scripts: [
        'pre-request' => ['content' => 'console.log("Group Pre-request")'],
        'after-response' => ['content' => 'console.log("Group Post-response")']
    ]
)]
class ComplexController extends Controller
{
    #[Request(
        name: 'Complex',
        description: 'Complex endpoint with multiple methods',
    )]
    public function store(ComplexStoreRequest $request)
    {
        return response()->json(['message' => 'stored']);
    }

    #[Request(
        name: 'Scripted Endpoint',
        description: 'Endpoint with pre/post scripts',
        scripts: [
            'pre-request' => ['content' => 'console.log("Pre-request")'],
            'after-response' => ['content' => 'console.log("Post-response")']
        ]
    )]
    public function scriptedEndpoint()
    {
        return response()->json(['message' => 'scripted']);
    }
}
