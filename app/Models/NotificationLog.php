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
        'title',
        'message',
        'data',
        'sent_at',
        'failed_at',
        'read_at',
        'error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data'       => 'array',
            'sent_at'    => 'datetime',
            'failed_at'  => 'datetime',
            'read_at'    => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
