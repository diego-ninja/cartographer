<?php

namespace Ninja\Cartographer\Authentication;

class Bearer extends AuthenticationMethod
{
    public function prefix(): string
    {
        return 'Bearer';
    }
}
