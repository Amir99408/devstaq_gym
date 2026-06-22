<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_settings', function (Blueprint $table) {
            $table->string('favicon_path')->nullable()->after('hero_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('gym_settings', function (Blueprint $table) {
            $table->dropColumn('favicon_path');
        });
    }
};
