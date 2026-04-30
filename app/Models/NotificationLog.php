<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    public $timestamps = false;

    protected $table = 'notifications_log';

    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'channel',
        'trigger',
        'message',
        'sent_at',
        'failed_at',
        'error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at'    => 'datetime',
            'failed_at'  => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
