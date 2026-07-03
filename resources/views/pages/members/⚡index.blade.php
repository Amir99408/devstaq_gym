<?php

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\Member;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Members')] class extends Component {

    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function members()
    {
        return Member::query()
            ->when($this->search, fn ($q) => $q
                ->where(fn ($q) => $q
                    ->where('full_name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")
                    ->orWhere('registration_number', 'like', "%{$this->search}%")
                )
            )
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->with([
                'currentPlan.membershipPlan',
                'registrationPayment',
                'monthlyPayments' => fn ($q) => $q->where('billing_month', now()->startOfMonth()->toDateString()),
            ])
            ->withCount([
                'monthlyPayments',
                'monthlyPayments as paid_months_count' => fn ($q) => $q->where('status', PaymentStatus::Verified->value),
            ])
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    #[Computed]
    public function statuses(): array
    {
        return MemberStatus::cases();
    }

    #[Computed]
    public function totalActive(): int
    {
        return Member::active()->count();
    }

    #[Computed]
    public function pendingThisMonth(): int
    {
        return Member::pendingThisMonth()->count();
    }
}; ?>

<div class="flex flex-col gap-6 px-6 py-6 sm:px-8">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Members</h1>
                <p class="mt-1 text-sm font-medium text-zinc-500">Manage all gym members and their registrations.</p>
            </div>
            <flux:button icon="user-plus" variant="primary" :href="route('members.create')" wire:navigate>
                Add Member
            </flux:button>
        </div>

        {{-- Stats Bar --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-500">Total Active</p>
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-blue-500 shadow-sm">
                        <flux:icon name="users" class="size-4 text-white" />
                    </div>
                </div>
                <p class="mt-4 text-4xl font-extrabold tracking-tight text-blue-800">{{ $this->totalActive }}</p>
            </div>
            <div class="rounded-2xl border border-orange-100 bg-orange-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-orange-500">Pending This Month</p>
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-orange-500 shadow-sm">
                        <flux:icon name="clock" class="size-4 text-white" />
                    </div>
                </div>
                <p class="mt-4 text-4xl font-extrabold tracking-tight text-orange-700">{{ $this->pendingThisMonth }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-100 bg-zinc-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Total Members</p>
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-zinc-400 shadow-sm">
                        <flux:icon name="user-group" class="size-4 text-white" />
                    </div>
                </div>
                <p class="mt-4 text-4xl font-extrabold tracking-tight text-zinc-700">{{ $this->members->total() }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-100 bg-zinc-50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Showing</p>
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-zinc-400 shadow-sm">
                        <flux:icon name="funnel" class="size-4 text-white" />
                    </div>
                </div>
                <p class="mt-4 text-4xl font-extrabold tracking-tight text-zinc-700">{{ $this->members->count() }}</p>
            </div>
        </div>

        {{-- Filters --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="Search by name, phone, or registration number..."
                    clearable
                />
            </div>
            <div class="w-full sm:w-52">
                <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    @foreach ($this->statuses as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            @if ($this->members->isEmpty())
                <div class="flex flex-col items-center justify-center py-20">
                    <flux:icon name="users" class="size-12 text-zinc-400" />
                    <flux:heading class="mt-4">No members found</flux:heading>
                    <flux:text class="mt-1">
                        @if ($search || $statusFilter)
                            Try clearing your filters.
                        @else
                            Add your first member to get started.
                        @endif
                    </flux:text>
                    @if (! $search && ! $statusFilter)
                        <flux:button class="mt-6" variant="primary" :href="route('members.create')" wire:navigate>
                            Add First Member
                        </flux:button>
                    @endif
                </div>
            @else
                <flux:table>
                    <thead>
                        <flux:table.row>
                            <flux:table.column class="!ps-5">Member</flux:table.column>
                            <flux:table.column>Phone</flux:table.column>
                            <flux:table.column>Plan</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column>This Month</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.row>
                    </thead>
                    <tbody>
                        @foreach ($this->members as $member)
                            <flux:table.row>
                                <flux:table.cell class="!ps-5">
                                    <div class="flex items-center gap-3">
                                        @if($member->profile_photo_path)
                                            <img src="{{ Storage::url($member->profile_photo_path) }}"
                                                 alt="{{ $member->full_name }}"
                                                 class="size-9 shrink-0 rounded-full object-cover" />
                                        @else
                                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-600">
                                                {{ mb_substr($member->full_name, 0, 1) }}
                                            </div>
                                        @endif
                                        <div>
                                            <p class="font-semibold text-zinc-900 dark:text-white">{{ $member->full_name }}</p>
                                            <p class="text-xs font-medium text-zinc-500">{{ $member->registration_number }}</p>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:text class="text-sm">{{ $member->phone }}</flux:text>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if ($member->currentPlan?->membershipPlan)
                                        <flux:text class="text-sm">{{ $member->currentPlan->membershipPlan->name }}</flux:text>
                                        <p class="text-xs text-zinc-500">RM {{ number_format($member->currentPlan->monthly_fee_amount, 2) }}/mo</p>
                                    @else
                                        <flux:text class="text-xs text-zinc-400">No plan</flux:text>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge :color="$member->status->color()" size="sm">
                                        {{ $member->status->label() }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if ($member->status === App\Enums\MemberStatus::Active)
                                        @php
                                            $currentMonthPayment = $member->monthlyPayments
                                                ->first(fn ($p) => $p->billing_month->isSameMonth(now()));
                                        @endphp
                                        @if ($currentMonthPayment)
                                            <flux:badge :color="$currentMonthPayment->status->color()" size="sm">
                                                {{ $currentMonthPayment->status->label() }}
                                            </flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">Not Generated</flux:badge>
                                        @endif
                                    @else
                                        <flux:text class="text-xs text-zinc-400">—</flux:text>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        :href="route('members.show', $member)"
                                        wire:navigate
                                    >
                                        View
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </tbody>
                </flux:table>

                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    {{ $this->members->links() }}
                </div>
            @endif
        </div>
</div>
