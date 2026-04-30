<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->enum('recipient_type', ['customer', 'rider', 'admin']);
            $table->unsignedBigInteger('recipient_id');
            $table->enum('channel', ['push', 'sms', 'in_app']);
            $table->string('trigger', 100);
            $table->text('message');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['recipient_type', 'recipient_id'], 'idx_recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
