<?php

use App\Models\MembershipPlan;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.app'), Title('Membership Plans')] class extends Component {

    public ?int $editingId = null;

    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('nullable|string|max:500')]
    public string $description = '';

    #[Validate('required|numeric|min:0')]
    public string $registration_fee = '0';

    #[Validate('required|numeric|min:1')]
    public string $monthly_fee = '';

    #[Validate('nullable|string|max:1000')]
    public string $features = '';

    #[Validate('boolean')]
    public bool $is_active = true;

    #[Validate('integer|min:0')]
    public int $sort_order = 0;

    #[Computed]
    public function plans(): Collection
    {
        return MembershipPlan::withCount('memberPlans')->orderBy('sort_order')->get();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->is_active = true;
        $this->sort_order = MembershipPlan::max('sort_order') + 1;
        Flux::modal('plan-form')->show();
    }

    public function openEdit(int $planId): void
    {
        $plan = MembershipPlan::findOrFail($planId);
        $this->editingId = $planId;
        $this->name = $plan->name;
        $this->description = $plan->description ?? '';
        $this->registration_fee = (string) $plan->registration_fee;
        $this->monthly_fee = (string) $plan->monthly_fee;
        $this->features = implode(', ', $plan->features ?? []);
        $this->is_active = $plan->is_active;
        $this->sort_order = $plan->sort_order;
        Flux::modal('plan-form')->show();
    }

    public function save(): void
    {
        $this->validate();

        $features = array_values(array_filter(array_map('trim', explode(',', $this->features))));

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'registration_fee' => $this->registration_fee,
            'monthly_fee' => $this->monthly_fee,
            'features' => $features,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];

        if ($this->editingId) {
            MembershipPlan::findOrFail($this->editingId)->update($data);
            Flux::toast(variant: 'success', text: 'Plan updated successfully.');
        } else {
            MembershipPlan::create($data);
            Flux::toast(variant: 'success', text: 'Plan created successfully.');
        }

        Flux::modal('plan-form')->close();
        $this->resetForm();
        unset($this->plans);
    }

    public function toggleActive(int $planId): void
    {
        $plan = MembershipPlan::findOrFail($planId);

        if (! $plan->is_active && $plan->member_plans_count > 0) {
            // Allow toggling even with members — just a flag
        }

        $plan->update(['is_active' => ! $plan->is_active]);
        Flux::toast(text: $plan->is_active ? 'Plan is now active.' : 'Plan deactivated.');
        unset($this->plans);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->registration_fee = '0';
        $this->monthly_fee = '';
        $this->features = '';
        $this->is_active = true;
        $this->sort_order = 0;
        $this->resetValidation();
    }
}; ?>

<div>
    <div class="flex flex-col gap-6 px-4 py-6 sm:px-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Membership Plans</flux:heading>
                <flux:text class="mt-1">Manage the plans available to your gym members.</flux:text>
            </div>
            <flux:button icon="plus" variant="primary" wire:click="openCreate">
                New Plan
            </flux:button>
        </div>

        {{-- Plans Grid --}}
        @if ($this->plans->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-20 dark:border-zinc-700">
                <flux:icon name="tag" class="size-12 text-zinc-400" />
                <flux:heading class="mt-4">No plans yet</flux:heading>
                <flux:text class="mt-1">Create your first membership plan to get started.</flux:text>
                <flux:button class="mt-6" variant="primary" wire:click="openCreate">Create a Plan</flux:button>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->plans as $plan)
                    <div class="flex flex-col rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">

                        {{-- Plan header --}}
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                                @if ($plan->description)
                                    <flux:text class="mt-1 text-sm line-clamp-2">{{ $plan->description }}</flux:text>
                                @endif
                            </div>
                            <flux:badge :color="$plan->is_active ? 'green' : 'zinc'" size="sm">
                                {{ $plan->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </div>

                        {{-- Fees --}}
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                                <flux:text class="text-xs uppercase tracking-wider text-zinc-500">Registration</flux:text>
                                <p class="mt-0.5 text-lg font-semibold text-zinc-900 dark:text-white">
                                    RM {{ number_format($plan->registration_fee, 2) }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                                <flux:text class="text-xs uppercase tracking-wider text-zinc-500">Monthly</flux:text>
                                <p class="mt-0.5 text-lg font-semibold text-zinc-900 dark:text-white">
                                    RM {{ number_format($plan->monthly_fee, 2) }}
                                </p>
                            </div>
                        </div>

                        {{-- Features --}}
                        @if ($plan->features)
                            <ul class="mt-4 space-y-1.5">
                                @foreach ($plan->features as $feature)
                                    <li class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                                        <flux:icon name="check-circle" class="size-4 shrink-0 text-green-500" />
                                        {{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="mt-4 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                            <flux:text class="text-xs text-zinc-500">
                                {{ $plan->member_plans_count }} {{ Str::plural('member', $plan->member_plans_count) }} on this plan
                            </flux:text>
                        </div>

                        {{-- Actions --}}
                        <div class="mt-3 flex items-center gap-2">
                            <flux:button size="sm" wire:click="openEdit({{ $plan->id }})">
                                Edit
                            </flux:button>
                            <flux:button
                                size="sm"
                                :variant="$plan->is_active ? 'ghost' : 'primary'"
                                wire:click="toggleActive({{ $plan->id }})"
                                wire:confirm="{{ $plan->is_active ? 'Deactivate this plan?' : 'Activate this plan?' }}"
                            >
                                {{ $plan->is_active ? 'Deactivate' : 'Activate' }}
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Create / Edit Modal --}}
    <flux:modal name="plan-form" class="w-full max-w-lg">
        <div class="p-6">
            <flux:heading size="lg">
                {{ $editingId ? 'Edit Plan' : 'New Membership Plan' }}
            </flux:heading>
            <flux:text class="mt-1">
                {{ $editingId ? 'Update the plan details below.' : 'Fill in the details for the new plan.' }}
            </flux:text>

            <form wire:submit="save" class="mt-6 space-y-4">
                <flux:input wire:model="name" label="Plan Name" placeholder="e.g. Premium" required />

                <flux:textarea wire:model="description" label="Description" placeholder="Short description shown to members" rows="2" />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input
                        wire:model="registration_fee"
                        label="Registration Fee (RM)"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                    />
                    <flux:input
                        wire:model="monthly_fee"
                        label="Monthly Fee (RM)"
                        type="number"
                        min="1"
                        step="0.01"
                        placeholder="0.00"
                        required
                    />
                </div>

                <flux:input
                    wire:model="features"
                    label="Features"
                    placeholder="e.g. Gym access, Locker room, Free WiFi"
                    description="Comma-separated list of features"
                />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="sort_order" label="Sort Order" type="number" min="0" />
                    <div class="flex items-end pb-1">
                        <flux:checkbox wire:model="is_active" label="Active (visible to members)" />
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="ghost" x-on:click="$flux.modal('plan-form').close()">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ $editingId ? 'Update Plan' : 'Create Plan' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
