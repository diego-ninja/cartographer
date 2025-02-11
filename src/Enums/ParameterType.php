<?php

namespace Ninja\Cartographer\Enums;

enum ParameterType: string
{
    case QUERY = 'query';
    case PATH = 'path';
    case HEADER = 'header';
    case TEXT = 'text';
    case JSON = 'json';
}
