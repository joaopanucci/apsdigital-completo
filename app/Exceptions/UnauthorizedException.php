<?php

namespace App\Exceptions;

/**
 * Exception thrown when access is denied due to lack of authentication
 */
class UnauthorizedException extends \Exception
{
    public function __construct($message = "Acesso negado - autenticação necessária", $code = 401, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}