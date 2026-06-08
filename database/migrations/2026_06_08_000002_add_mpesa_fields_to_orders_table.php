<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('mpesa_checkout_request_id')->nullable()->after('delivery_photo_path');
            $table->string('mpesa_merchant_request_id')->nullable()->after('mpesa_checkout_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['mpesa_checkout_request_id', 'mpesa_merchant_request_id']);
        });
    }
};
