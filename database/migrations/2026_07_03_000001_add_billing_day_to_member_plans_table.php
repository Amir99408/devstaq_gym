<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('member_plans', 'billing_day')) {
            Schema::table('member_plans', function (Blueprint $table) {
                $table->unsignedTinyInteger('billing_day')->default(1)->after('start_date');
            });
        }

        // Backfill existing rows: derive billing_day from the plan's start_date
        DB::statement('UPDATE member_plans SET billing_day = DAY(start_date)');
    }

    public function down(): void
    {
        Schema::table('member_plans', function (Blueprint $table) {
            $table->dropColumn('billing_day');
        });
    }
};
