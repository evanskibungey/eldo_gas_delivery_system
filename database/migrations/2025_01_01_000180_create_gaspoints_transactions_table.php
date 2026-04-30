<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gaspoints_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['earned', 'redeemed', 'bonus', 'referral', 'referral_bonus']);
            $table->integer('points');
            $table->unsignedInteger('balance_after');
            $table->string('description', 255);
            $table->timestamp('created_at')->nullable();
            $table->index('customer_id', 'idx_gaspoints_customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gaspoints_transactions');
    }
};
