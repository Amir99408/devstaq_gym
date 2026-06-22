<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number')->unique();
            $table->string('full_name');
            $table->string('phone')->index();
            $table->string('email')->nullable()->index();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('inquiry')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
