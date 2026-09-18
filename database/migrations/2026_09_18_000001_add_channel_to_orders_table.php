<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How the order reached us, as opposed to what was bought.
 *
 *   app      the customer placed it themselves
 *   phone    an admin took it over the phone, delivered as normal
 *   walk_in  a counter sale, collected and paid on the spot
 *
 * Deliberately NOT folded into order_type. That column also exists per line on
 * order_items and answers a different question — a walk-in still buys a swap.
 * Keeping them separate means a basket can mix cylinder types without saying
 * anything about how the order arrived.
 *
 * Defaults to 'app' so every row already in production keeps its meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('channel', ['app', 'phone', 'walk_in'])
                ->default('app')
                ->after('order_type');

            // Reports and the customer-conversion view both filter on this.
            $table->index('channel', 'idx_orders_channel');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_channel');
            $table->dropColumn('channel');
        });
    }
};
