<?php

namespace App\Support;

final class RequestId
{
    public static function get(): string
    {
        return (string) request()->attributes->get('request_id', 'REQ_UNKNOWN');
    }
}
