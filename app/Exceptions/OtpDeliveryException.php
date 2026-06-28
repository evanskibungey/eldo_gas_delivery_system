<?php

namespace App\Exceptions;

use RuntimeException;

class OtpDeliveryException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self("We couldn't send a verification code right now. Please try again.");
    }
}
