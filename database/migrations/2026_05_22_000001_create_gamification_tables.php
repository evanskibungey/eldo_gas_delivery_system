<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per customer — tracks order streak and total count.
        Schema::create('customer_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('current_streak')->default(0)->comment('Consecutive months with ≥1 delivered order');
            $table->unsignedSmallInteger('longest_streak')->default(0);
            $table->unsignedInteger('order_count')->default(0)->comment('Total delivered orders');
            $table->date('last_order_month')->nullable()->comment('Year-month of the last qualifying delivery');
            $table->timestamps();
            $table->unique('customer_id');
        });

        // Immutable earned-badge log — one row per (customer, badge_key) pair.
        Schema::create('customer_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('badge_key', 50);
            $table->timestamp('earned_at')->useCurrent();
            $table->unique(['customer_id', 'badge_key']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_badges');
        Schema::dropIfExists('customer_streaks');
    }
};
