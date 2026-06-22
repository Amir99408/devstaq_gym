<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MonthlyPayment extends Model
{
    protected $fillable = [
        'member_id', 'billing_month', 'amount_due', 'status',
        'screenshot_path', 'notes', 'admin_notes', 'due_date',
        'submitted_at', 'verified_at', 'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_due' => 'decimal:2',
            'billing_month' => 'date',
            'due_date' => 'date',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Scopes

    public function scopeForMonth(Builder $query, Carbon $month): void
    {
        $query->where('billing_month', $month->startOfMonth()->toDateString());
    }

    public function scopeCurrentMonth(Builder $query): void
    {
        $query->forMonth(now());
    }

    public function scopeNeedsAction(Builder $query): void
    {
        $query->whereIn('status', [
            PaymentStatus::Pending->value,
            PaymentStatus::Submitted->value,
            PaymentStatus::Overdue->value,
        ])->with('member');
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', PaymentStatus::Overdue)
            ->orWhere(fn (Builder $q) => $q
                ->where('status', PaymentStatus::Pending)
                ->where('due_date', '<', now()->toDateString())
            );
    }
}
