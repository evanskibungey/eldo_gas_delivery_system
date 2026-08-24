<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the delivery address text onto the order.
 *
 * Orders carried coordinates and delivery notes only. The label and the
 * landmark the customer typed ("Kapsoya, opposite Zion Mall") lived on
 * customer_addresses and never reached the rider, who saw a pin and nothing
 * else. Customers reasonably assume the landmark they wrote is what the rider
 * reads.
 *
 * Snapshotting rather than joining is deliberate, and matches how
 * delivery_lat/lng already work: editing an address later must not rewrite
 * where a past order was sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_label', 100)->nullable()->after('delivery_lng');
            $table->string('delivery_address', 255)->nullable()->after('delivery_label');
        });

        // Backfill historical orders from the address that is nearest to the
        // coordinates we stored. Exact matches only — a customer who has since
        // moved the pin keeps a null rather than a plausible-looking guess.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement(<<<'SQL'
                UPDATE orders o
                JOIN customer_addresses a
                  ON a.customer_id = o.customer_id
                 AND a.latitude = o.delivery_lat
                 AND a.longitude = o.delivery_lng
                SET o.delivery_label = a.label,
                    o.delivery_address = a.description
                WHERE o.delivery_label IS NULL
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_label', 'delivery_address']);
        });
    }
};
