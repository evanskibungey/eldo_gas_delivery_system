<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_size_availability', function (Blueprint $table) {
            $table->foreignId('brand_id')->constrained('gas_brands')->cascadeOnDelete();
            $table->foreignId('size_id')->constrained('cylinder_sizes')->cascadeOnDelete();
            $table->primary(['brand_id', 'size_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_size_availability');
    }
};
