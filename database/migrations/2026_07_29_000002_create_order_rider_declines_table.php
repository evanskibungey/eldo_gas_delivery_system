<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Declines used to be passed in memory on OrderPlacedEvent, so they only
 * survived a single re-assignment hop: A declines -> B assigned -> B
 * declines -> the exclusion list is [B] and the order lands back on A.
 *
 * Persisting them makes the exclusion cumulative for the life of the order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_rider_declines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->enum('reason', ['declined', 'acceptance_expired'])->default('declined');
            $table->timestamp('created_at')->nullable();

            // A rider is excluded once per order, however many times they
            // bounce it — keeps re-queues idempotent.
            $table->unique(['order_id', 'rider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_rider_declines');
    }
};
