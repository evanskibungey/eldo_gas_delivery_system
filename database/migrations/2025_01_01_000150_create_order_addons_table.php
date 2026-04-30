<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_item_id')->constrained('addon_items')->cascadeOnDelete();
            $table->unsignedInteger('price');
            $table->index('order_id', 'idx_order_addons_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_addons');
    }
};
