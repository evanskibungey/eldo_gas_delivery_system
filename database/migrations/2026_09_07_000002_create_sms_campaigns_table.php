<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A bulk SMS send: what was written, who it went to, and what it cost.
 *
 * Kept as a record rather than fire-and-forget because a send cannot be
 * recalled. When somebody asks "who did we text about the price change, and
 * when", the answer has to exist.
 *
 * Counts are snapshotted at dispatch. Recomputing them later from
 * notifications_log would drift as customers are added, deleted or opt out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();

            $table->string('title', 120);
            $table->text('message');

            // promo messages carry the opt-out line and skip opted-out
            // customers; info is operational (a price change, a closure) and
            // goes to everyone selected.
            $table->enum('kind', ['promo', 'info'])->default('promo');

            // How the audience was chosen: a saved filter key, or 'selected'
            // when an admin ticked names off the customer list.
            $table->string('audience', 40)->default('selected');

            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('skipped_opted_out')->default(0);
            $table->unsignedSmallInteger('segments_per_message')->default(1);
            // recipient_count * segments_per_message, frozen at dispatch.
            $table->unsignedInteger('total_segments')->default(0);

            $table->enum('status', ['draft', 'queued', 'sent', 'failed'])->default('draft');
            $table->timestamp('queued_at')->nullable();

            $table->timestamps();

            $table->index('status', 'idx_sms_campaigns_status');
            $table->index('created_at', 'idx_sms_campaigns_created');
        });

        // Who each campaign actually went to. Answers "did this customer get
        // it", which the campaign row alone cannot.
        Schema::create('sms_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->timestamp('created_at')->nullable();

            $table->unique(['sms_campaign_id', 'customer_id'], 'uq_campaign_customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_campaign_recipients');
        Schema::dropIfExists('sms_campaigns');
    }
};
