<?php

namespace App\Exceptions;

/**
 * Exception thrown when a requested resource is not found
 */
class NotFoundException extends \Exception
{
    public function __construct($message = "Recurso não encontrado", $code = 404, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}