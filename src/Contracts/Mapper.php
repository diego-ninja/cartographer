<?php

namespace Ninja\Cartographer\Contracts;

use Illuminate\Support\Collection;

interface Mapper
{
    public function map(): Collection;
}
