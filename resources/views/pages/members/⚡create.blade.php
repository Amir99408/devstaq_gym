<?php

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\MembershipPlan;
use App\Models\RegistrationPayment;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.app'), Title('Add Member')] class extends Component {

    #[Validate('required|string|max:255')]
    public string $full_name = '';

    #[Validate('required|string|max:30|unique:members,phone')]
    public string $phone = '';

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    #[Validate('nullable|date|before:today')]
    public string $date_of_birth = '';

    #[Validate('nullable|string|max:500')]
    public string $address = '';

    #[Validate('nullable|string|max:255')]
    public string $emergency_contact_name = '';

    #[Validate('nullable|string|max:30')]
    public string $emergency_contact_phone = '';

    #[Validate('nullable|string|max:1000')]
    public string $notes = '';

    #[Validate('required|integer|exists:membership_plans,id')]
    public string $membership_plan_id = '';

    #[Computed]
    public function plans(): Collection
    {
        return MembershipPlan::active()->get();
    }

    #[Computed]
    public function selectedPlan(): ?MembershipPlan
    {
        if (! $this->membership_plan_id) {
            return null;
        }

        return $this->plans->firstWhere('id', $this->membership_plan_id);
    }

    public function save(): void
    {
        $this->validate();

        $plan = MembershipPlan::findOrFail($this->membership_plan_id);

        DB::transaction(function () use ($plan) {
            $member = Member::create([
                'full_name' => $this->full_name,
                'phone' => $this->phone,
                'email' => $this->email ?: null,
                'date_of_birth' => $this->date_of_birth ?: null,
                'address' => $this->address ?: null,
                'emergency_contact_name' => $this->emergency_contact_name ?: null,
                'emergency_contact_phone' => $this->emergency_contact_phone ?: null,
                'notes' => $this->notes ?: null,
                'status' => $plan->registration_fee > 0 ? MemberStatus::PendingPayment : MemberStatus::Active,
                'created_by' => auth()->id(),
            ]);

            $memberPlan = MemberPlan::create([
                'member_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'registration_fee_amount' => $plan->registration_fee,
                'monthly_fee_amount' => $plan->monthly_fee,
                'start_date' => today()->toDateString(),
                'created_by' => auth()->id(),
            ]);

            RegistrationPayment::create([
                'member_id' => $member->id,
                'member_plan_id' => $memberPlan->id,
                'amount' => $plan->registration_fee,
                // If no registration fee, auto-waive it
                'status' => $plan->registration_fee > 0 ? PaymentStatus::Pending : PaymentStatus::Waived,
                'verified_at' => $plan->registration_fee > 0 ? null : now(),
                'verified_by' => $plan->registration_fee > 0 ? null : auth()->id(),
            ]);

            Flux::toast(variant: 'success', text: 'Member added successfully.');

            $this->redirect(route('members.show', $member), navigate: true);
        });
    }
}; ?>

<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">

        {{-- Header --}}
        <div class="mb-6 flex items-center gap-3">
            <flux:button icon="arrow-left" variant="ghost" :href="route('members.index')" wire:navigate size="sm" />
            <div>
                <flux:heading size="xl">Add New Member</flux:heading>
                <flux:text class="mt-0.5">Fill in the member details and select a membership plan.</flux:text>
            </div>
        </div>

        <form wire:submit="save" class="space-y-6">

            {{-- Personal Information --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="base" class="mb-4">Personal Information</flux:heading>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <flux:input
                            wire:model="full_name"
                            label="Full Name"
                            placeholder="Ahmad bin Abdullah"
                            required
                        />
                    </div>
                    <flux:input
                        wire:model="phone"
                        label="Phone Number"
                        placeholder="+60 11-1234 5678"
                        type="tel"
                        required
                    />
                    <flux:input
                        wire:model="email"
                        label="Email Address"
                        placeholder="ahmad@example.com"
                        type="email"
                    />
                    <flux:input
                        wire:model="date_of_birth"
                        label="Date of Birth"
                        type="date"
                        :max="now()->subYears(10)->toDateString()"
                    />
                    <div class="sm:col-span-2">
                        <flux:input wire:model="address" label="Address" placeholder="Full address" />
                    </div>
                </div>
            </div>

            {{-- Emergency Contact --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="base" class="mb-1">Emergency Contact</flux:heading>
                <flux:text class="mb-4 text-sm">Optional but recommended.</flux:text>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="emergency_contact_name" label="Contact Name" placeholder="Contact person name" />
                    <flux:input wire:model="emergency_contact_phone" label="Contact Phone" placeholder="+60 11-9999 8888" type="tel" />
                </div>
            </div>

            {{-- Membership Plan --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="base" class="mb-4">Membership Plan</flux:heading>

                @if ($this->plans->isEmpty())
                    <div class="rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400">
                        No active plans available.
                        <flux:link :href="route('plans.index')" wire:navigate>Create a plan first.</flux:link>
                    </div>
                @else
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($this->plans as $plan)
                            <label
                                class="relative flex cursor-pointer flex-col rounded-lg border-2 p-4 transition-colors
                                    {{ $membership_plan_id == $plan->id
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-950'
                                        : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600' }}"
                            >
                                <input
                                    type="radio"
                                    wire:model.live="membership_plan_id"
                                    value="{{ $plan->id }}"
                                    class="sr-only"
                                />
                                <span class="font-semibold text-zinc-900 dark:text-white">{{ $plan->name }}</span>
                                <span class="mt-1 text-sm text-zinc-500">
                                    Reg: RM {{ number_format($plan->registration_fee, 2) }}
                                </span>
                                <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                                    RM {{ number_format($plan->monthly_fee, 2) }}/month
                                </span>
                                @if ($membership_plan_id == $plan->id)
                                    <flux:icon name="check-circle" class="absolute right-3 top-3 size-5 text-blue-500" />
                                @endif
                            </label>
                        @endforeach
                    </div>

                    @error('membership_plan_id')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    {{-- Fee Preview --}}
                    @if ($this->selectedPlan)
                        <div class="mt-4 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                            <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-500">Fee Summary</flux:text>
                            <div class="mt-2 space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-zinc-600 dark:text-zinc-400">Registration fee (one-time)</span>
                                    <span class="font-medium text-zinc-900 dark:text-white">
                                        @if ($this->selectedPlan->registration_fee > 0)
                                            RM {{ number_format($this->selectedPlan->registration_fee, 2) }}
                                        @else
                                            <span class="text-green-600">Free</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-zinc-600 dark:text-zinc-400">Monthly fee</span>
                                    <span class="font-medium text-zinc-900 dark:text-white">
                                        RM {{ number_format($this->selectedPlan->monthly_fee, 2) }}/month
                                    </span>
                                </div>
                            </div>
                            @if ($this->selectedPlan->registration_fee > 0)
                                <p class="mt-3 text-xs text-zinc-500">
                                    Member will be set to <strong>Pending Payment</strong> until registration fee is verified.
                                </p>
                            @else
                                <p class="mt-3 text-xs text-green-600 dark:text-green-400">
                                    No registration fee — member will be set to <strong>Active</strong> immediately.
                                </p>
                            @endif
                        </div>
                    @endif
                @endif
            </div>

            {{-- Notes --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:textarea
                    wire:model="notes"
                    label="Internal Notes"
                    placeholder="Any notes about this member (visible to staff only)..."
                    rows="3"
                />
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3">
                <flux:button variant="ghost" :href="route('members.index')" wire:navigate>Cancel</flux:button>
                <flux:button type="submit" variant="primary" icon="user-plus">Add Member</flux:button>
            </div>

        </form>
</div>
