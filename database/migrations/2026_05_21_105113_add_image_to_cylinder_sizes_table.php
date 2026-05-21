<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cylinder_sizes', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('cylinder_sizes', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
