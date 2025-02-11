<?php

namespace Ninja\Cartographer\Enums;

enum Method: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case COPY = 'COPY';
    case HEAD = 'HEAD';
    case OPTIONS = 'OPTIONS';
    case LINK = 'LINK';
    case UNLINK = 'UNLINK';
    case PURGE = 'PURGE';
    case LOCK = 'LOCK';
    case UNLOCK = 'UNLOCK';
    case PROPFIND = 'PROPFIND';
    case VIEW = 'VIEW';

    public function action(): string
    {
        return match ($this) {
            self::GET => 'index',
            self::POST => 'store',
            self::PUT => 'replace',
            self::PATCH => 'update',
            self::DELETE => 'destroy',
            self::COPY,
            self::HEAD,
            self::OPTIONS,
            self::LINK,
            self::UNLINK,
            self::PURGE,
            self::LOCK,
            self::UNLOCK,
            self::PROPFIND,
            self::VIEW => $this->value,
        };
    }
}
