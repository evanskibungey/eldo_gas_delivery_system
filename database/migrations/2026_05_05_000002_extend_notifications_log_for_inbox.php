<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the existing `notifications_log` table so the customer in-app
 * inbox can render proper title + read state. We don't break the
 * existing writes — the new columns are nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications_log', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications_log', 'title')) {
                $table->string('title', 150)->nullable()->after('trigger');
            }
            if (! Schema::hasColumn('notifications_log', 'data')) {
                $table->json('data')->nullable()->after('message');
            }
            if (! Schema::hasColumn('notifications_log', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications_log', function (Blueprint $table) {
            foreach (['title', 'data', 'read_at'] as $column) {
                if (Schema::hasColumn('notifications_log', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
