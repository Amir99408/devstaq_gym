<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Member extends Model
{
    protected $fillable = [
        'registration_number', 'full_name', 'phone', 'email', 'date_of_birth',
        'address', 'profile_photo_path', 'emergency_contact_name',
        'emergency_contact_phone', 'notes', 'status', 'created_by', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'date_of_birth' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Member $member) {
            $member->registration_number ??= static::generateRegistrationNumber();
        });
    }

    private static function generateRegistrationNumber(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->count();

        return sprintf('GYM-%d-%04d', $year, $count + 1);
    }

    // Relations

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(MemberPlan::class);
    }

    public function currentPlan(): HasOne
    {
        return $this->hasOne(MemberPlan::class)->latestOfMany('start_date');
    }

    public function registrationPayment(): HasOne
    {
        return $this->hasOne(RegistrationPayment::class);
    }

    public function monthlyPayments(): HasMany
    {
        return $this->hasMany(MonthlyPayment::class);
    }

    public function currentMonthPayment(): HasOne
    {
        return $this->hasOne(MonthlyPayment::class)
            ->where('billing_month', now()->startOfMonth()->toDateString());
    }

    // Scopes

    public function scopeActive(Builder $query): void
    {
        $query->where('status', MemberStatus::Active);
    }

    public function scopeWithPaymentSummary(Builder $query): void
    {
        $query->with(['currentPlan.membershipPlan', 'registrationPayment'])
            ->withCount([
                'monthlyPayments',
                'monthlyPayments as verified_payments_count' => fn (Builder $q) => $q->where('status', PaymentStatus::Verified),
            ]);
    }

    public function scopePendingThisMonth(Builder $query): void
    {
        $billingMonth = now()->startOfMonth()->toDateString();

        $query->where('status', MemberStatus::Active)
            ->with(['currentPlan.membershipPlan'])
            ->whereDoesntHave('monthlyPayments', fn (Builder $q) => $q
                ->where('billing_month', $billingMonth)
                ->whereIn('status', [PaymentStatus::Verified->value, PaymentStatus::Waived->value])
            );
    }

    // Helpers

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function hasActivePlan(): bool
    {
        return $this->status === MemberStatus::Active;
    }

    public function hasLogin(): bool
    {
        return $this->user_id !== null;
    }

    public function nextPaymentDueDate(): ?Carbon
    {
        $plan = $this->currentPlan;
        if (! $plan) {
            return null;
        }

        $billingDay = $plan->billing_day ?? 1;
        $today = today();
        $thisMonthDue = Carbon::create($today->year, $today->month, min($billingDay, $today->daysInMonth));

        if ($today->lt($thisMonthDue)) {
            return $thisMonthDue;
        }

        $nextMonth = $today->copy()->addMonth()->startOfMonth();

        return Carbon::create($nextMonth->year, $nextMonth->month, min($billingDay, $nextMonth->daysInMonth));
    }

    public function whatsappReminderUrl(float $amount, Carbon $dueDate): ?string
    {
        if (! $this->phone) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $this->phone);

        if (str_starts_with($digits, '0')) {
            $digits = '60' . substr($digits, 1);
        }

        $message = "Hi {$this->full_name}! This is a friendly reminder that your monthly gym fee of RM " . number_format($amount, 2) . " is due on " . $dueDate->format('d M Y') . ". Kindly make your payment and send us the screenshot. Thank you!";

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }
}
