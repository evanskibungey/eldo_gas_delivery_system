<?php

namespace App\Exceptions;

use Exception;

class OutOfStockException extends Exception
{
    public function __construct(string $message = 'This cylinder size is currently out of stock.')
    {
        parent::__construct($message);
    }
}
