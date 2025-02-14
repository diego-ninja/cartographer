<?php

namespace Ninja\Cartographer\Contracts;

interface Exportable
{
    public function forPostman(): array;
    public function forInsomnia(): array;
}
