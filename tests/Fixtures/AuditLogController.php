<?php

namespace Ninja\Cartographer\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ninja\Cartographer\Attributes\Group;
use Ninja\Cartographer\Enums\EventType;

#[Group(
    name: 'Audit Log',
    description: 'This is log management collection',
    group: 'Logs',
    headers: [
        'X-Demo-Header' => 'Demo',
    ],
    scripts: [
        EventType::PreRequest->value => ['content' => 'console.log("Pre-Request script")'],
        EventType::AfterResponse->value => ['content' => 'console.log("After-Response script")'],
    ],
)]
class AuditLogController extends Controller
{
    #[\Ninja\Cartographer\Attributes\Request(
        name: 'List Audit Logs',
        description: 'List all audit logs',
        params: ['page' => 'The page number', 'limit' => 'The number of items per page'],
    )]
    public function index(): void {}

    public function store(Request $request): void {}

    public function show(int $id): void {}

    public function update(Request $request, ExampleModel $auditLog): void {}

    public function destroy($id): void {}
}
