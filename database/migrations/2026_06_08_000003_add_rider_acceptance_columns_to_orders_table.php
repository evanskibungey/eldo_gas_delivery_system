<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Set when a rider is auto-assigned; cleared on accept, decline, or expiry.
            $table->timestamp('rider_acceptance_deadline')->nullable()->after('rider_assigned_at');
            // Set when the rider explicitly taps Accept.
            $table->timestamp('rider_accepted_at')->nullable()->after('rider_acceptance_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['rider_acceptance_deadline', 'rider_accepted_at']);
        });
    }
};
