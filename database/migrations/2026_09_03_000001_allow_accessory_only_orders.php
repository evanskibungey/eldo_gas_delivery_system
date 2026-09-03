<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a customer order accessories without ordering gas.
 *
 * Three things stood in the way, all of them structural:
 *
 *  - addon_groups.size_id was a required FK, so every accessory belonged to
 *    exactly one cylinder size. A 1.5m hose sold for 6kg and 13kg was two
 *    separate rows, and "order a hose" had no single answer. Null now means
 *    the group applies to every size, and can be bought on its own.
 *
 *  - orders.size_id was NOT NULL, so an order had to name a cylinder.
 *
 *  - order_type was an enum of two values.
 *
 * All three widen rather than move, so every row already in production stays
 * valid and every existing per-size group keeps working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addon_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('size_id')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('size_id')->nullable()->change();
        });

        $this->setOrderTypeValues(['swap', 'new_cylinder', 'accessory']);
    }

    public function down(): void
    {
        // Accessory orders have no cylinder, so they cannot survive a column
        // that requires one. Drop them rather than fail the rollback halfway.
        DB::table('orders')->where('order_type', 'accessory')->delete();
        DB::table('addon_groups')->whereNull('size_id')->delete();

        $this->setOrderTypeValues(['swap', 'new_cylinder']);

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('size_id')->nullable(false)->change();
        });

        Schema::table('addon_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('size_id')->nullable(false)->change();
        });
    }

    /**
     * MySQL stores an enum natively and has to be told the new value list.
     * SQLite (used by the test suite) models it as a varchar with a check
     * constraint, which Laravel rebuilds through the schema builder.
     */
    private function setOrderTypeValues(array $values): void
    {
        if (DB::getDriverName() === 'mysql') {
            $list = implode(',', array_map(fn ($v) => "'".$v."'", $values));
            DB::statement("ALTER TABLE orders MODIFY order_type ENUM($list) NOT NULL");

            return;
        }

        Schema::table('orders', function (Blueprint $table) use ($values) {
            $table->enum('order_type', $values)->change();
        });
    }
};
