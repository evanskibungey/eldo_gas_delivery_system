<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cylinder_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_id')->unique()->constrained('cylinder_sizes')->cascadeOnDelete();
            $table->unsignedInteger('gas_refill_price');
            $table->unsignedInteger('new_cylinder_price');
            $table->unsignedInteger('new_gas_fill_price');
            $table->unsignedInteger('delivery_fee');
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cylinder_prices');
    }
};
