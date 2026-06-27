<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gaspoints_transactions', 'reward_key')) {
            Schema::table('gaspoints_transactions', function (Blueprint $table) {
                $table->string('reward_key', 120)->nullable()->unique('uq_gaspoints_reward_key')->after('order_id');
            });
        }

        if (! Schema::hasColumn('gaspoints_transactions', 'event_code')) {
            Schema::table('gaspoints_transactions', function (Blueprint $table) {
                $table->string('event_code', 64)->nullable()->after('type');
            });
        }

        if (! Schema::hasColumn('gaspoints_transactions', 'remaining_points')) {
            Schema::table('gaspoints_transactions', function (Blueprint $table) {
                $table->unsignedInteger('remaining_points')->nullable()->after('points');
            });
        }

        if (! Schema::hasColumn('gaspoints_transactions', 'expires_at')) {
            Schema::table('gaspoints_transactions', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('created_at');
            });
        }

        if (! Schema::hasColumn('gaspoints_transactions', 'expired_at')) {
            Schema::table('gaspoints_transactions', function (Blueprint $table) {
                $table->timestamp('expired_at')->nullable()->after('expires_at');
            });
        }

        DB::table('gaspoints_transactions')
            ->whereNull('remaining_points')
            ->update([
                'remaining_points' => DB::raw('CASE WHEN points > 0 THEN points ELSE 0 END'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('gaspoints_transactions', 'reward_key')) {
            Schema::table('gaspoints_transactions', function (Blueprint $table) {
                $table->dropUnique('uq_gaspoints_reward_key');
                $table->dropColumn('reward_key');
            });
        }

        foreach (['event_code', 'remaining_points', 'expires_at', 'expired_at'] as $column) {
            if (Schema::hasColumn('gaspoints_transactions', $column)) {
                Schema::table('gaspoints_transactions', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
