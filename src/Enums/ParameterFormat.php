<?php

namespace Ninja\Cartographer\Enums;

enum ParameterFormat: string
{
    case Json = 'application/json';
    case UrlEncoded = 'application/x-www-form-urlencoded';
    case FormData = 'multipart/form-data';
    case Raw = 'text/plain';
    case GraphQL = 'application/graphql';

}
