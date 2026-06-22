<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    protected $fillable = [
        'name', 'description', 'registration_fee', 'monthly_fee',
        'features', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'registration_fee' => 'decimal:2',
            'monthly_fee' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function memberPlans(): HasMany
    {
        return $this->hasMany(MemberPlan::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order');
    }
}
