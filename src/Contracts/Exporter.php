<?php

namespace Ninja\Cartographer\Contracts;

interface Exporter
{
    public function to(string $filename): self;

    public function getOutput(): bool|string;

    public function export(): self;
}
