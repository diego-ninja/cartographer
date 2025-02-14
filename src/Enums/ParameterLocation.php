<?php

namespace Ninja\Cartographer\Enums;

enum ParameterLocation: string
{
    case Query = 'query';
    case Path = 'path';
    case Body = 'body';
    case Header = 'header';
}
