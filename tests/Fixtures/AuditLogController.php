<?php

namespace Ninja\Cartographer\Tests\Fixtures;

use Ninja\Cartographer\Attributes\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

#[Collection(name: 'Audit Log', description: 'This is log management collection', group: 'Logs')]
class AuditLogController extends Controller
{
    #[\Ninja\Cartographer\Attributes\Request(name: 'List Audit Logs', description: 'List all audit logs')]
    public function index(): void {}

    public function store(Request $request): void {}

    public function show($id): void {}

    public function update(Request $request, ExampleModel $auditLog): void {}

    public function destroy($id): void {}
}
