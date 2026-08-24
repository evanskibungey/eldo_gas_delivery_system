<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The devices registry was customer-only, so riders had no push transport
 * at all — they learned about an assignment purely via an open WebSocket or
 * an SMS, neither of which reliably wakes a backgrounded app inside the
 * 60-second acceptance window.
 *
 * A device row now belongs to exactly one of customer_id / rider_id; both
 * are nullable and ownership is enforced by the registering controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->change();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();

            if (! Schema::hasColumn('devices', 'rider_id')) {
                $table->foreignId('rider_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained()
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'rider_id')) {
                $table->dropForeign(['rider_id']);
                $table->dropColumn('rider_id');
            }
        });

        // Rows owned by a rider have no customer, so they must go before
        // customer_id can be non-nullable again.
        Schema::disableForeignKeyConstraints();
        DB::table('devices')->whereNull('customer_id')->delete();
        Schema::enableForeignKeyConstraints();

        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable(false)->change();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();
        });
    }
};
