<?php

namespace App\Services\Push;

use RuntimeException;

class FcmException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly bool $unregistered = false,
    ) {
        parent::__construct($message);
    }

    /**
     * True when FCM told us the token is dead (uninstalled app, expired
     * registration). The caller should delete the device row rather than
     * retry — retrying a stale token never succeeds.
     */
    public function isUnregistered(): bool
    {
        return $this->unregistered;
    }
}
