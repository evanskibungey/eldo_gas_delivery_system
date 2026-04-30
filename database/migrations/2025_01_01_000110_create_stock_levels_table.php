<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_id')->unique()->constrained('cylinder_sizes')->cascadeOnDelete();
            $table->unsignedInteger('filled_count')->default(0);
            $table->unsignedInteger('empty_count')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
