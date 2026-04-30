<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cylinder_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20);
            $table->decimal('weight_kg', 5, 1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_commercial')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cylinder_sizes');
    }
};
