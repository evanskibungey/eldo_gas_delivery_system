<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a first-order discount actually took off this order.
 *
 * Recorded rather than folded into total_amount, for the same reason
 * gaspoints_discount is: an order whose parts do not add up to its total is
 * unexplainable to the customer who paid it and to whoever reconciles the
 * books.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'first_order_discount')) {
                $table->unsignedInteger('first_order_discount')
                    ->default(0)
                    ->after('gaspoints_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'first_order_discount')) {
                $table->dropColumn('first_order_discount');
            }
        });
    }
};
