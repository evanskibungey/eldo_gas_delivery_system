<?php

namespace App\Services\Sms;

interface SmsServiceInterface
{
    /**
     * Send an SMS message to the given phone number.
     * Phone must be in international format (+254XXXXXXXXX).
     */
    public function send(string $phone, string $message): bool;
}
