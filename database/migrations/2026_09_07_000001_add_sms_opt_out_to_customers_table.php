<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing consent, tracked per customer.
 *
 * Nullable timestamp rather than a boolean: knowing WHEN somebody opted out
 * matters if they later dispute having received something, and it doubles as
 * the flag itself.
 *
 * This gates promotional sends only. Order confirmations, rider-assigned and
 * delivery messages are transactional — the customer asked for them by placing
 * an order, and they keep sending regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('sms_opt_out_at')->nullable()->after('is_active');
            $table->string('sms_opt_out_source', 20)->nullable()->after('sms_opt_out_at');

            // Every promo send filters on this, so it is worth an index.
            $table->index('sms_opt_out_at', 'idx_customers_sms_opt_out');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_sms_opt_out');
            $table->dropColumn(['sms_opt_out_at', 'sms_opt_out_source']);
        });
    }
};
