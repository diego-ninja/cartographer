<?php

namespace Ninja\Cartographer\Tests\Fixtures;

use Illuminate\Http\Request;

class ExampleService
{
    public Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getRequestData(): array
    {
        return $this->request->all();
    }
}
