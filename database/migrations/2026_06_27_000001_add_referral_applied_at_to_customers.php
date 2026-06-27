<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'referral_applied_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->timestamp('referral_applied_at')->nullable()->after('referred_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'referral_applied_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('referral_applied_at');
            });
        }
    }
};
