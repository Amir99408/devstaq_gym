<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->restrictOnDelete();
            $table->decimal('registration_fee_amount', 10, 2);
            $table->decimal('monthly_fee_amount', 10, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_plans');
    }
};
