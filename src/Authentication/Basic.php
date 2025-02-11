<?php

namespace Ninja\Cartographer\Authentication;

class Basic extends AuthenticationMethod
{
    public function prefix(): string
    {
        return 'Basic';
    }
}
