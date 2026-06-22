<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('billing_month');
            $table->decimal('amount_due', 10, 2);
            $table->string('status')->default('pending')->index();
            $table->string('screenshot_path')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->date('due_date');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['member_id', 'billing_month']);
            $table->index(['billing_month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_payments');
    }
};
