<?php

namespace mindplay\host;

/**
 * Thin wrapper-abstraction around the PHP SAPI functions.
 */
interface SapiFunctions
{
    public function emitHeader(string $string, bool $replace = true, ?int $http_response_code = null): void;
    public function headersSent(?string &$file, ?int &$line): bool;
    public function finishRequest(): void;
}
