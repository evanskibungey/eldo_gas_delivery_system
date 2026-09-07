<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A bulk SMS send. See the migration for why the counts are snapshotted.
 */
class SmsCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'title',
        'message',
        'kind',
        'audience',
        'recipient_count',
        'skipped_opted_out',
        'segments_per_message',
        'total_segments',
        'status',
        'queued_at',
    ];

    protected function casts(): array
    {
        return [
            'recipient_count' => 'integer',
            'skipped_opted_out' => 'integer',
            'segments_per_message' => 'integer',
            'total_segments' => 'integer',
            'queued_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'sms_campaign_recipients')
            ->withPivot('phone');
    }

    /** Promotional sends carry the opt-out line and skip opted-out customers. */
    public function isPromo(): bool
    {
        return $this->kind === 'promo';
    }
}
