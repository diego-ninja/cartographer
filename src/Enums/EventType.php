<?php

namespace Ninja\Cartographer\Enums;

enum EventType: string
{
    case PreRequest = 'pre-request';
    case AfterResponse = 'after-response';

    public function forInsomnia(): string
    {
        return match ($this) {
            self::PreRequest => 'preRequestScript',
            self::AfterResponse => 'afterResponseScript',
        };
    }

    public function forPostman(): string
    {
        return match ($this) {
            self::PreRequest => 'prerequest',
            self::AfterResponse => 'test',
        };
    }
}
