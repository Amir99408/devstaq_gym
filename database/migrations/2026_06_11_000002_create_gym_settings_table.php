<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gym_name');
            $table->string('tagline')->nullable();
            $table->text('about')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('primary_color')->default('#3b82f6');
            $table->string('secondary_color')->default('#1e40af');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_settings');
    }
};
