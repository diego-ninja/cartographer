<?php

namespace Ninja\Cartographer\Enums;

enum BodyMode: string
{
    case Raw = 'raw';
    case FormData = 'formdata';
    case UrlEncoded = 'urlencoded';
    case File = 'file';
    case Graphql = 'graphql';
    case None = 'none';

    public function mimeType(): string
    {
        return match ($this) {
            self::Raw,
            self::Graphql => 'application/json',
            self::FormData => 'multipart/form-data',
            self::UrlEncoded => 'application/x-www-form-urlencoded',
            self::File => 'file',
            self::None => 'none',
        };
    }
}
