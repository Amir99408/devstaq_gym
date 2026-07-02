<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_settings', function (Blueprint $table) {
            $table->text('google_maps_embed_url')->nullable()->after('whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('gym_settings', function (Blueprint $table) {
            $table->dropColumn('google_maps_embed_url');
        });
    }
};
