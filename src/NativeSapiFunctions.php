<?php

namespace mindplay\host;

use function fastcgi_finish_request;
use function function_exists;
use function header;
use function headers_sent;

/**
 * Wrapper around the native PHP SAPI functions.
 */
class NativeSapiFunctions implements SapiFunctions
{
    public function emitHeader(string $string, bool $replace = true, ?int $http_response_code = null): void
    {
        header($string, $replace, $http_response_code);
    }

    public function headersSent(?string &$file, ?int &$line): bool
    {
        return headers_sent($file, $line);
    }

    public function finishRequest(): void
    {
        if (function_exists("fastcgi_finish_request")) {
            fastcgi_finish_request();
        }
    }
}
