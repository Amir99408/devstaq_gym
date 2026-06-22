<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationPayment extends Model
{
    protected $fillable = [
        'member_id', 'member_plan_id', 'amount', 'status',
        'screenshot_path', 'notes', 'admin_notes',
        'submitted_at', 'verified_at', 'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function memberPlan(): BelongsTo
    {
        return $this->belongsTo(MemberPlan::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeNeedsAction(Builder $query): void
    {
        $query->whereIn('status', [
            PaymentStatus::Pending->value,
            PaymentStatus::Submitted->value,
        ])->with(['member', 'memberPlan.membershipPlan']);
    }
}
