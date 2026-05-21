<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track GasPoints redemption applied to an order at placement time.
 * `gaspoints_redeemed` is the points spent; `gaspoints_discount` is the
 * KES value subtracted from the order total. Storing both makes audit
 * trivial and lets us refund cleanly on cancellation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'gaspoints_redeemed')) {
                $table->unsignedInteger('gaspoints_redeemed')
                    ->default(0)
                    ->after('addons_total');
            }
            if (! Schema::hasColumn('orders', 'gaspoints_discount')) {
                $table->unsignedInteger('gaspoints_discount')
                    ->default(0)
                    ->after('gaspoints_redeemed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['gaspoints_redeemed', 'gaspoints_discount'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
