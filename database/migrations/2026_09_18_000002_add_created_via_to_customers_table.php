<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the customer record came from.
 *
 * A customer an admin typed in at the counter has never verified a phone, so
 * `phone_verified_at` is null — but so is a customer mid-way through signing up
 * on the app. This separates "we created them" from "they have not finished
 * registering", which is what makes the walk-in conversion list meaningful.
 *
 * Conversion itself is still read from phone_verified_at: OtpService backfills
 * it on first login, so the day a walk-in installs the app their record flips
 * without anything else having to notice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('created_via', ['app', 'admin'])
                ->default('app')
                ->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('created_via');
        });
    }
};
