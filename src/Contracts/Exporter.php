<?php

namespace Ninja\Cartographer\Contracts;

use Ninja\Cartographer\Authentication\AuthenticationMethod;

interface Exporter
{
    public function to(string $filename): self;

    public function getOutput(): bool|string;

    public function export(): void;

    public function setAuthentication(?AuthenticationMethod $authentication): self;

    public function resolveAuth(): self;
}
