<?php

namespace App\Exceptions;

/**
 * Exception thrown when access is denied due to insufficient permissions
 */
class ForbiddenException extends \Exception
{
    public function __construct($message = "Acesso proibido - permissões insuficientes", $code = 403, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}