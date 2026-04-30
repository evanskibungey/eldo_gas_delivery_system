<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        // Add correction_in_progress to orders.status enum (MySQL only — SQLite ignores enum constraints)
        if ($isMysql) {
            DB::statement("ALTER TABLE orders MODIFY status ENUM(
                'pending','rider_assigned','picked_up','on_the_way',
                'correction_in_progress','delivered','cancelled'
            ) NOT NULL DEFAULT 'pending'");
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'issue_description')) {
                $table->string('issue_description', 500)->nullable()->after('issue_type');
            }
        });

        Schema::table('riders', function (Blueprint $table) {
            if (! Schema::hasColumn('riders', 'incident_count')) {
                $table->unsignedSmallInteger('incident_count')->default(0)->after('total_deliveries');
            }
        });

        // Add damaged_cylinder_flag to stock_audit_logs.change_type enum (MySQL only)
        if ($isMysql) {
            DB::statement("ALTER TABLE stock_audit_logs MODIFY change_type ENUM(
                'auto_deduction','auto_return','manual_adjustment',
                'out_of_stock_cancel','damaged_cylinder_flag'
            ) NOT NULL");
        }
    }

    public function down(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        if ($isMysql) {
            DB::statement("ALTER TABLE orders MODIFY status ENUM(
                'pending','rider_assigned','picked_up','on_the_way','delivered','cancelled'
            ) NOT NULL DEFAULT 'pending'");
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'issue_description')) {
                $table->dropColumn('issue_description');
            }
        });

        Schema::table('riders', function (Blueprint $table) {
            if (Schema::hasColumn('riders', 'incident_count')) {
                $table->dropColumn('incident_count');
            }
        });

        if ($isMysql) {
            DB::statement("ALTER TABLE stock_audit_logs MODIFY change_type ENUM(
                'auto_deduction','auto_return','manual_adjustment','out_of_stock_cancel'
            ) NOT NULL");
        }
    }
};
