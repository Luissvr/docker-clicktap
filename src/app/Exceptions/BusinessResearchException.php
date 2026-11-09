<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class BusinessResearchException extends RuntimeException implements ShouldntReport
{
    public function __construct(string $message = 'No se pudo investigar el negocio.')
    {
        parent::__construct($message);
    }
}
