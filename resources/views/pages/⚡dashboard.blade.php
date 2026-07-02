<?php

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\GymSetting;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MonthlyPayment;
use App\Models\RegistrationPayment;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app'), Title('Dashboard')] class extends Component {

    #[Computed]
    public function gymName(): string
    {
        return GymSetting::value('gym_name') ?? 'My Gym';
    }

    #[Computed]
    public function stats(): object
    {
        $currentMonth   = now()->startOfMonth()->toDateString();
        $lastMonth      = now()->subMonth()->startOfMonth()->toDateString();
        $paidStatuses   = [PaymentStatus::Verified->value, PaymentStatus::Waived->value];

        // Run queries independently — each is a single indexed DB hit
        $activeCount   = Member::where('status', MemberStatus::Active)->count();
        $totalMembers  = Member::count();
        $newThisMonth  = Member::whereMonth('created_at', now()->month)
                               ->whereYear('created_at', now()->year)->count();

        $pendingThisMonth = Member::where('status', MemberStatus::Active)
            ->whereDoesntHave('monthlyPayments', fn ($q) => $q
                ->where('billing_month', $currentMonth)
                ->whereIn('status', $paidStatuses)
            )->count();

        $revenueThisMonth = MonthlyPayment::where('billing_month', $currentMonth)
                                          ->whereIn('status', $paidStatuses)
                                          ->sum('amount_due');

        $revenueLastMonth = MonthlyPayment::where('billing_month', $lastMonth)
                                          ->whereIn('status', $paidStatuses)
                                          ->sum('amount_due');

        $awaitingVerification = RegistrationPayment::where('status', PaymentStatus::Submitted)->count()
                              + MonthlyPayment::where('status', PaymentStatus::Submitted)->count();

        $overdueCount = MonthlyPayment::where('status', PaymentStatus::Overdue)
                                      ->orWhere(fn ($q) => $q
                                          ->where('status', PaymentStatus::Pending)
                                          ->where('due_date', '<', today()->toDateString())
                                      )->count();

        $pendingRegistrations = RegistrationPayment::where('status', PaymentStatus::Pending)->count();

        $totalCollectionMonthly = MonthlyPayment::whereIn('status', $paidStatuses)->sum('amount_due');
        $totalCollectionReg     = RegistrationPayment::whereIn('status', $paidStatuses)->sum('amount');
        $totalCollection        = $totalCollectionMonthly + $totalCollectionReg;

        $pendingCollection = MonthlyPayment::whereIn('status', [
            PaymentStatus::Pending->value,
            PaymentStatus::Overdue->value,
        ])->sum('amount_due');

        $revenueChange = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : null;

        return (object) compact(
            'activeCount', 'totalMembers', 'newThisMonth',
            'pendingThisMonth', 'revenueThisMonth', 'revenueLastMonth',
            'revenueChange', 'awaitingVerification', 'overdueCount', 'pendingRegistrations',
            'totalCollection', 'pendingCollection'
        );
    }

    #[Computed]
    public function statusBreakdown(): Collection
    {
        return Member::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->status->value => (object) [
                    'count' => $row->total,
                    'status' => $row->status,
                ],
            ]);
    }

    #[Computed]
    public function planDistribution(): Collection
    {
        return MembershipPlan::withCount([
            'memberPlans',
            'memberPlans as active_count' => fn ($q) => $q->whereHas('member', fn ($q) => $q->where('status', MemberStatus::Active)),
        ])
        ->orderByDesc('active_count')
        ->get(['id', 'name', 'monthly_fee']);
    }

    #[Computed]
    public function recentActivity(): Collection
    {
        // 3 queries, merged and sorted — no N+1
        $regVerified = RegistrationPayment::where('status', PaymentStatus::Verified)
            ->whereNotNull('verified_at')
            ->orderByDesc('verified_at')
            ->limit(6)
            ->with([
                'member:id,full_name,registration_number,profile_photo_path',
                'verifiedBy:id,name',
            ])
            ->get()
            ->map(fn ($p) => (object) [
                'icon'   => 'check-circle',
                'color'  => 'green',
                'photo'  => $p->member->profile_photo_path,
                'name'   => $p->member->full_name,
                'text'   => 'Registration verified — ' . $p->member->full_name,
                'sub'    => 'RM ' . number_format($p->amount, 2) . ' · by ' . ($p->verifiedBy?->name ?? 'system'),
                'at'     => $p->verified_at,
                'href'   => route('members.show', $p->member_id),
            ]);

        $monthlyVerified = MonthlyPayment::where('status', PaymentStatus::Verified)
            ->whereNotNull('verified_at')
            ->orderByDesc('verified_at')
            ->limit(6)
            ->with([
                'member:id,full_name,registration_number,profile_photo_path',
                'verifiedBy:id,name',
            ])
            ->get()
            ->map(fn ($p) => (object) [
                'icon'   => 'banknotes',
                'color'  => 'blue',
                'photo'  => $p->member->profile_photo_path,
                'name'   => $p->member->full_name,
                'text'   => 'Monthly payment verified — ' . $p->member->full_name,
                'sub'    => 'RM ' . number_format($p->amount_due, 2) . ' · ' . $p->billing_month->format('M Y') . ' · by ' . ($p->verifiedBy?->name ?? 'system'),
                'at'     => $p->verified_at,
                'href'   => route('members.show', $p->member_id),
            ]);

        $newMembers = Member::orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'full_name', 'registration_number', 'status', 'created_at', 'profile_photo_path'])
            ->map(fn ($m) => (object) [
                'icon'   => 'user-plus',
                'color'  => 'purple',
                'photo'  => $m->profile_photo_path,
                'name'   => $m->full_name,
                'text'   => 'New member registered — ' . $m->full_name,
                'sub'    => $m->registration_number . ' · ' . $m->status->label(),
                'at'     => $m->created_at,
                'href'   => route('members.show', $m),
            ]);

        return $regVerified
            ->concat($monthlyVerified)
            ->concat($newMembers)
            ->sortByDesc('at')
            ->take(12)
            ->values();
    }
}; ?>

<div class="flex flex-col gap-6 px-6 py-6 sm:px-8">

    {{-- Greeting --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            @php
                $hour = now()->hour;
                $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
            @endphp
            <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">
                {{ $greeting }}, {{ auth()->user()->name }} 👋
            </h1>
            <p class="mt-1 text-sm font-medium text-zinc-500">
                {{ now()->format('l, d F Y') }}
                <span class="mx-1.5 text-zinc-300 dark:text-zinc-600">·</span>
                <span class="text-zinc-700 dark:text-zinc-300">{{ $this->gymName }}</span>
            </p>
        </div>
        <flux:button icon="user-plus" variant="primary" :href="route('members.create')" wire:navigate>
            Add Member
        </flux:button>
    </div>

    {{-- Alert banners (clickable cards) --}}
    @if ($this->stats->awaitingVerification > 0 || $this->stats->overdueCount > 0)
        <div class="flex flex-col gap-2 sm:flex-row">
            @if ($this->stats->awaitingVerification > 0)
                <a href="{{ route('payments.pending') }}" wire:navigate
                   class="flex flex-1 items-center gap-3 rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 transition-colors hover:bg-yellow-100 dark:border-yellow-700/50 dark:bg-yellow-900/20 dark:hover:bg-yellow-900/30">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900/50">
                        <flux:icon name="bell-alert" class="size-4 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-200">
                            {{ $this->stats->awaitingVerification }} {{ Str::plural('payment', $this->stats->awaitingVerification) }} to verify
                        </p>
                        <p class="text-xs text-yellow-600 dark:text-yellow-400">Tap to review screenshots</p>
                    </div>
                    <flux:icon name="chevron-right" class="size-4 shrink-0 text-yellow-400" />
                </a>
            @endif
            @if ($this->stats->overdueCount > 0)
                <a href="{{ route('payments.pending') }}" wire:navigate
                   class="flex flex-1 items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 transition-colors hover:bg-red-100 dark:border-red-700/50 dark:bg-red-900/20 dark:hover:bg-red-900/30">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/50">
                        <flux:icon name="exclamation-circle" class="size-4 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-red-800 dark:text-red-200">
                            {{ $this->stats->overdueCount }} overdue {{ Str::plural('payment', $this->stats->overdueCount) }}
                        </p>
                        <p class="text-xs text-red-600 dark:text-red-400">Members past their due date</p>
                    </div>
                    <flux:icon name="chevron-right" class="size-4 shrink-0 text-red-400" />
                </a>
            @endif
        </div>
    @endif

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">

        {{-- Active Members --}}
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-5 shadow-sm transition-shadow hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-500 shadow-sm">
                    <flux:icon name="users" class="size-5 text-white" />
                </div>
                <p class="text-sm font-bold uppercase tracking-wide text-blue-600">Active Members</p>
            </div>
            <p class="mt-4 text-5xl font-extrabold tracking-tight text-blue-700">{{ $this->stats->activeCount }}</p>
            <p class="mt-1.5 text-xs font-semibold text-blue-400">
                {{ $this->stats->newThisMonth > 0 ? '+' . $this->stats->newThisMonth . ' new this month' : 'of ' . $this->stats->totalMembers . ' total' }}
            </p>
        </div>

        {{-- Revenue This Month --}}
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm transition-shadow hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500 shadow-sm">
                    <flux:icon name="banknotes" class="size-5 text-white" />
                </div>
                <p class="text-sm font-bold uppercase tracking-wide text-emerald-600">Revenue ({{ now()->format('M') }})</p>
            </div>
            <p class="mt-4 text-4xl font-extrabold tracking-tight text-emerald-700">
                RM {{ number_format($this->stats->revenueThisMonth, 0) }}
            </p>
            @if ($this->stats->revenueChange !== null)
                <p class="mt-1.5 flex items-center gap-1 text-xs font-semibold {{ $this->stats->revenueChange >= 0 ? 'text-emerald-500' : 'text-red-500' }}">
                    <flux:icon :name="$this->stats->revenueChange >= 0 ? 'arrow-trending-up' : 'arrow-trending-down'" class="size-3.5" />
                    {{ abs($this->stats->revenueChange) }}% vs last month
                </p>
            @else
                <p class="mt-1.5 text-xs font-semibold text-emerald-400">RM {{ number_format($this->stats->revenueLastMonth, 0) }} last month</p>
            @endif
        </div>

        {{-- Not Paid Yet --}}
        @php $hasPending = $this->stats->pendingThisMonth > 0; @endphp
        <div class="rounded-xl border p-5 shadow-sm transition-shadow hover:shadow-md
            {{ $hasPending ? 'border-orange-200 bg-orange-50' : 'border-zinc-200 bg-white' }}">
            <div class="flex items-center gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl shadow-sm
                    {{ $hasPending ? 'bg-orange-500' : 'bg-zinc-200' }}">
                    <flux:icon name="clock" class="size-5 {{ $hasPending ? 'text-white' : 'text-zinc-400' }}" />
                </div>
                <p class="text-sm font-bold uppercase tracking-wide {{ $hasPending ? 'text-orange-600' : 'text-zinc-400' }}">
                    Not Paid Yet
                </p>
            </div>
            <p class="mt-4 text-5xl font-extrabold tracking-tight {{ $hasPending ? 'text-orange-700' : 'text-zinc-300' }}">
                {{ $this->stats->pendingThisMonth }}
            </p>
            <p class="mt-1.5 text-xs font-semibold {{ $hasPending ? 'text-orange-400' : 'text-zinc-400' }}">
                active {{ Str::plural('member', $this->stats->pendingThisMonth) }} this month
            </p>
        </div>

        {{-- To Verify --}}
        @php $hasVerify = $this->stats->awaitingVerification > 0; @endphp
        <div class="rounded-xl border p-5 shadow-sm transition-shadow hover:shadow-md
            {{ $hasVerify ? 'border-amber-200 bg-amber-50' : 'border-zinc-200 bg-white' }}">
            <div class="flex items-center gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl shadow-sm
                    {{ $hasVerify ? 'bg-amber-400' : 'bg-zinc-200' }}">
                    <flux:icon name="document-check" class="size-5 {{ $hasVerify ? 'text-white' : 'text-zinc-400' }}" />
                </div>
                <p class="text-sm font-bold uppercase tracking-wide {{ $hasVerify ? 'text-amber-600' : 'text-zinc-400' }}">
                    To Verify
                </p>
            </div>
            <p class="mt-4 text-5xl font-extrabold tracking-tight {{ $hasVerify ? 'text-amber-700' : 'text-zinc-300' }}">
                {{ $this->stats->awaitingVerification }}
            </p>
            <p class="mt-1.5 text-xs font-semibold {{ $hasVerify ? 'text-amber-400' : 'text-zinc-400' }}">
                screenshot{{ $this->stats->awaitingVerification !== 1 ? 's' : '' }} pending review
            </p>
        </div>

    </div>

    {{-- Collection Row --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">

        {{-- Total Collection --}}
        <div class="rounded-xl border border-teal-200 bg-teal-50 p-5 shadow-sm transition-shadow hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-teal-500 shadow-sm">
                    <flux:icon name="chart-bar" class="size-5 text-white" />
                </div>
                <p class="text-sm font-bold uppercase tracking-wide text-teal-600">Total Collection</p>
            </div>
            <p class="mt-4 text-4xl font-extrabold tracking-tight text-teal-700">
                RM {{ number_format($this->stats->totalCollection, 2) }}
            </p>
            <p class="mt-1.5 text-xs font-semibold text-teal-400">All verified monthly + registration fees</p>
        </div>

        {{-- Pending Collection --}}
        @php $hasPendingAmount = $this->stats->pendingCollection > 0; @endphp
        <div class="rounded-xl border p-5 shadow-sm transition-shadow hover:shadow-md
            {{ $hasPendingAmount ? 'border-rose-200 bg-rose-50' : 'border-zinc-200 bg-white' }}">
            <div class="flex items-center gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl shadow-sm
                    {{ $hasPendingAmount ? 'bg-rose-500' : 'bg-zinc-200' }}">
                    <flux:icon name="exclamation-triangle" class="size-5 {{ $hasPendingAmount ? 'text-white' : 'text-zinc-400' }}" />
                </div>
                <p class="text-sm font-bold uppercase tracking-wide {{ $hasPendingAmount ? 'text-rose-600' : 'text-zinc-400' }}">
                    Pending Collection
                </p>
            </div>
            <p class="mt-4 text-4xl font-extrabold tracking-tight {{ $hasPendingAmount ? 'text-rose-700' : 'text-zinc-300' }}">
                RM {{ number_format($this->stats->pendingCollection, 2) }}
            </p>
            <p class="mt-1.5 text-xs font-semibold {{ $hasPendingAmount ? 'text-rose-400' : 'text-zinc-400' }}">
                Pending + overdue monthly fees outstanding
            </p>
        </div>

    </div>

    {{-- Main content: Activity + Sidebar --}}
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Recent Activity (2/3) --}}
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-white">Recent Activity</h3>
                        <p class="text-xs font-medium text-zinc-400">Latest 12 events</p>
                    </div>
                    <flux:icon name="clock" class="size-4 text-zinc-300" />
                </div>

                @if ($this->recentActivity->isEmpty())
                    <div class="flex flex-col items-center py-16">
                        <div class="flex size-14 items-center justify-center rounded-2xl bg-zinc-100">
                            <flux:icon name="clock" class="size-7 text-zinc-400" />
                        </div>
                        <p class="mt-4 text-sm font-medium text-zinc-600">No activity yet</p>
                        <p class="mt-1 text-xs text-zinc-400">Add your first member to get started.</p>
                        <flux:button class="mt-5" variant="primary" size="sm" icon="user-plus" :href="route('members.create')" wire:navigate>
                            Add First Member
                        </flux:button>
                    </div>
                @else
                    <div class="divide-y divide-zinc-50">
                        @foreach ($this->recentActivity as $event)
                            @php
                                $iconBg = match($event->color) {
                                    'green'  => 'bg-emerald-500',
                                    'blue'   => 'bg-blue-500',
                                    'purple' => 'bg-violet-500',
                                    default  => 'bg-zinc-300',
                                };
                            @endphp
                            <a href="{{ $event->href }}" wire:navigate
                               class="flex items-center gap-4 px-5 py-3.5 transition-colors hover:bg-zinc-50">
                                <div class="relative shrink-0">
                                    @if($event->photo)
                                        <img src="{{ Storage::url($event->photo) }}"
                                             alt="{{ $event->name }}"
                                             class="size-9 rounded-full object-cover" />
                                    @else
                                        <div class="flex size-9 items-center justify-center rounded-full bg-zinc-100 text-sm font-bold text-zinc-500">
                                            {{ mb_substr($event->name, 0, 1) }}
                                        </div>
                                    @endif
                                    <div class="absolute -bottom-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full {{ $iconBg }} ring-2 ring-white">
                                        <flux:icon :name="$event->icon" class="size-2.5 text-white" />
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-zinc-800">{{ $event->text }}</p>
                                    <p class="truncate text-xs font-medium text-zinc-400">{{ $event->sub }}</p>
                                </div>
                                <p class="shrink-0 whitespace-nowrap text-xs font-medium text-zinc-400">{{ $event->at->diffForHumans() }}</p>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Right sidebar (1/3) --}}
        <div class="flex flex-col gap-4">

            {{-- Quick Actions --}}
            <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-100 px-5 py-3.5">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">Quick Actions</h3>
                </div>
                <div class="p-2">
                    <a href="{{ route('members.create') }}" wire:navigate
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-zinc-700 transition-colors hover:bg-blue-50">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-blue-100">
                            <flux:icon name="user-plus" class="size-4 text-blue-600" />
                        </div>
                        Add Member
                    </a>
                    <a href="{{ route('payments.pending') }}" wire:navigate
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-zinc-700 transition-colors hover:bg-amber-50">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-amber-100">
                            <flux:icon name="clock" class="size-4 text-amber-600" />
                        </div>
                        <span class="flex-1">Pending Payments</span>
                        @if ($this->stats->awaitingVerification > 0)
                            <span class="rounded-full bg-amber-400 px-2 py-0.5 text-xs font-bold text-white">
                                {{ $this->stats->awaitingVerification }}
                            </span>
                        @endif
                    </a>
                    <a href="{{ route('payments.monthly') }}" wire:navigate
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-zinc-700 transition-colors hover:bg-violet-50">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-violet-100">
                            <flux:icon name="calendar" class="size-4 text-violet-600" />
                        </div>
                        Monthly Fees
                    </a>
                    <a href="{{ route('members.index') }}" wire:navigate
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-zinc-700 transition-colors hover:bg-zinc-50">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-100">
                            <flux:icon name="users" class="size-4 text-zinc-500" />
                        </div>
                        All Members
                    </a>
                    <a href="{{ route('plans.index') }}" wire:navigate
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-zinc-700 transition-colors hover:bg-emerald-50">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-100">
                            <flux:icon name="tag" class="size-4 text-emerald-600" />
                        </div>
                        Membership Plans
                    </a>
                </div>
            </div>

            {{-- Member Status Breakdown --}}
            <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-100 px-5 py-3.5">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">Member Breakdown</h3>
                </div>
                <div class="p-5">
                    @if ($this->statusBreakdown->isEmpty())
                        <p class="text-sm text-zinc-400">No members yet.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($this->statusBreakdown as $statusValue => $data)
                                @php
                                    $s = $data->status;
                                    $pct = $this->stats->totalMembers > 0
                                        ? round(($data->count / $this->stats->totalMembers) * 100)
                                        : 0;
                                    $barColor = match($s->color()) {
                                        'green'  => 'bg-green-500',
                                        'orange' => 'bg-orange-500',
                                        'yellow' => 'bg-yellow-400',
                                        'red'    => 'bg-red-500',
                                        default  => 'bg-zinc-400',
                                    };
                                @endphp
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="size-2.5 rounded-full {{ $barColor }}"></div>
                                            <span class="text-sm font-semibold text-zinc-700">{{ $s->label() }}</span>
                                        </div>
                                        <span class="text-sm font-bold text-zinc-800">
                                            {{ $data->count }}
                                            <span class="text-xs font-medium text-zinc-400">({{ $pct }}%)</span>
                                        </span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100">
                                        <div class="h-1.5 rounded-full {{ $barColor }} transition-all" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Plan Distribution --}}
            @if ($this->planDistribution->isNotEmpty())
                <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
                    <div class="border-b border-zinc-100 px-5 py-3.5">
                        <h3 class="text-base font-bold text-zinc-900 dark:text-white">Plan Distribution</h3>
                    </div>
                    <div class="p-5">
                        <div class="space-y-3">
                            @foreach ($this->planDistribution as $plan)
                                @php
                                    $pct = $this->stats->activeCount > 0
                                        ? round(($plan->active_count / $this->stats->activeCount) * 100)
                                        : 0;
                                @endphp
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between text-sm">
                                        <span class="font-semibold text-zinc-700">{{ $plan->name }}</span>
                                        <span class="font-bold text-zinc-800">
                                            {{ $plan->active_count }}
                                            <span class="text-xs font-medium text-zinc-400">({{ $pct }}%)</span>
                                        </span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100">
                                        <div class="h-1.5 rounded-full bg-blue-500 transition-all" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

</div>
