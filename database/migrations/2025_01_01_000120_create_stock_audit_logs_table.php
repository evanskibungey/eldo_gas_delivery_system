<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_id')->constrained('cylinder_sizes')->cascadeOnDelete();
            $table->enum('change_type', [
                'auto_deduction',
                'auto_return',
                'manual_adjustment',
                'out_of_stock_cancel',
            ]);
            $table->integer('change_amount');
            $table->unsignedInteger('new_count');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('size_id', 'idx_stock_audit_size');
            $table->index('order_id', 'idx_stock_audit_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_logs');
    }
};
