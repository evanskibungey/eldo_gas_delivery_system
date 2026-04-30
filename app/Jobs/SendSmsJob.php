<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 3;
    public int    $backoff = 30;

    public function __construct(
        private readonly string      $phone,
        private readonly string      $message,
        private readonly string      $trigger,
        private readonly string      $recipientType,
        private readonly int         $recipientId,
    ) {}

    public function handle(SmsServiceInterface $sms): void
    {
        $log = NotificationLog::create([
            'recipient_type' => $this->recipientType,
            'recipient_id'   => $this->recipientId,
            'channel'        => 'sms',
            'trigger'        => $this->trigger,
            'message'        => $this->message,
            'created_at'     => now(),
        ]);

        $success = $sms->send($this->phone, $this->message);

        if ($success) {
            $log->update(['sent_at' => now()]);
        } else {
            $log->update(['failed_at' => now(), 'error' => 'SMS gateway returned failure']);
            $this->fail('SMS gateway returned failure status');
        }
    }
}
