<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use InvalidArgumentException;

class InvalidPageRouteException extends InvalidArgumentException implements ShouldntReport
{
    public function __construct(string $message = 'La ruta de la página no es válida o está reservada.')
    {
        parent::__construct($message);
    }
}
