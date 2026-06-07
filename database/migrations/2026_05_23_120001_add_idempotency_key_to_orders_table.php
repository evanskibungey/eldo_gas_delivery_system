<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('delivery_notes');
            $table->unique(['customer_id', 'idempotency_key'], 'orders_customer_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_customer_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};

