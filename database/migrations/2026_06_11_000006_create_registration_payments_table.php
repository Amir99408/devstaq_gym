<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending')->index();
            $table->string('screenshot_path')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_payments');
    }
};
