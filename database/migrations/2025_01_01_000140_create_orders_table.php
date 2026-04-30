<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->foreignId('size_id')->constrained('cylinder_sizes');
            $table->foreignId('brand_id')->nullable()->constrained('gas_brands')->nullOnDelete();
            $table->enum('order_type', ['swap', 'new_cylinder']);
            $table->enum('status', [
                'pending',
                'rider_assigned',
                'picked_up',
                'on_the_way',
                'delivered',
                'cancelled',
            ])->default('pending');

            // Pricing snapshot
            $table->unsignedInteger('gas_price');
            $table->unsignedInteger('cylinder_price')->default(0);
            $table->unsignedInteger('delivery_fee');
            $table->unsignedInteger('addons_total')->default(0);
            $table->unsignedInteger('total_amount');

            $table->enum('payment_method', ['cash', 'mpesa']);
            $table->enum('payment_status', ['pending', 'collected', 'disputed', 'refunded'])->default('pending');

            // Delivery location
            $table->decimal('delivery_lat', 10, 8);
            $table->decimal('delivery_lng', 11, 8);
            $table->text('delivery_notes')->nullable();

            // Stage timestamps
            $table->timestamp('rider_assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->enum('cancelled_by', ['customer', 'admin', 'system'])->nullable();

            // Exception tracking
            $table->boolean('has_issue')->default(false);
            $table->string('issue_type', 100)->nullable();
            $table->boolean('issue_resolved')->default(false);

            // Safety checklist (Phase 12)
            $table->json('safety_checklist')->nullable();

            $table->timestamps();

            $table->index('customer_id', 'idx_orders_customer');
            $table->index('rider_id', 'idx_rider');
            $table->index('status', 'idx_status');
            $table->index('created_at', 'idx_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
