<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MemberPlan extends Model
{
    protected $fillable = [
        'member_id', 'membership_plan_id', 'registration_fee_amount',
        'monthly_fee_amount', 'discount_type', 'discount_amount',
        'start_date', 'end_date', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'registration_fee_amount' => 'decimal:2',
            'monthly_fee_amount'      => 'decimal:2',
            'discount_amount'         => 'decimal:2',
            'start_date'              => 'date',
            'end_date'                => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrationPayment(): HasOne
    {
        return $this->hasOne(RegistrationPayment::class);
    }
}
