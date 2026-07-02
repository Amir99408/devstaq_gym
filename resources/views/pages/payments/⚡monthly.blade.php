<?php

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\MonthlyPayment;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app'), Title('Monthly Fees')] class extends Component {

    use WithFileUploads;

    #[Url(as: 'month')]
    public string $selectedMonth = '';

    // Action state
    public ?int $actioningId = null;
    public string $adminNotes = '';
    public ?string $screenshotUrl = null;

    #[Validate('required|image|mimes:jpg,jpeg,png,webp|max:5120')]
    public $uploadFile = null;

    public function mount(): void
    {
        if (! $this->selectedMonth) {
            $this->selectedMonth = now()->format('Y-m');
        }
    }

    #[Computed]
    public function billingMonth(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->selectedMonth)->startOfMonth();
    }

    #[Computed]
    public function monthlyPayments(): Collection
    {
        $billingDate = $this->billingMonth->toDateString();

        // All active members with their payment for this month (if exists)
        $members = Member::active()
            ->with([
                'currentPlan.membershipPlan',
                'monthlyPayments' => fn ($q) => $q->where('billing_month', $billingDate),
                'monthlyPayments.verifiedBy',
            ])
            ->orderBy('full_name')
            ->get();

        return $members->map(fn ($member) => (object) [
            'member'   => $member,
            'payment'  => $member->monthlyPayments->first(),
            'plan'     => $member->currentPlan?->membershipPlan,
            'amount'   => $member->currentPlan?->monthly_fee_amount ?? 0,
        ]);
    }

    #[Computed]
    public function isGenerated(): bool
    {
        return MonthlyPayment::where('billing_month', $this->billingMonth->toDateString())->exists();
    }

    #[Computed]
    public function stats(): object
    {
        $payments = collect($this->monthlyPayments);

        return (object) [
            'total'    => $payments->count(),
            'verified' => $payments->filter(fn ($r) => $r->payment?->status === PaymentStatus::Verified)->count(),
            'pending'  => $payments->filter(fn ($r) => ! $r->payment || $r->payment->status->needsAction())->count(),
            'waived'   => $payments->filter(fn ($r) => $r->payment?->status === PaymentStatus::Waived)->count(),
        ];
    }

    // ─── Generate payments for selected month ─────────────────────────────────

    public function generate(): void
    {
        Artisan::call('payments:generate-monthly', [
            '--month' => $this->selectedMonth,
        ]);

        unset($this->monthlyPayments, $this->isGenerated, $this->stats);
        Flux::toast(variant: 'success', text: 'Monthly payment records generated.');
    }

    // ─── Upload ───────────────────────────────────────────────────────────────

    public function openUpload(int $id): void
    {
        $this->actioningId = $id;
        $this->uploadFile = null;
        $this->resetValidation('uploadFile');
        Flux::modal('monthly-upload')->show();
    }

    public function submitUpload(): void
    {
        $this->validateOnly('uploadFile');

        $path = $this->uploadFile->store('screenshots', 'public');

        MonthlyPayment::whereKey($this->actioningId)->update([
            'screenshot_path' => $path,
            'status'          => PaymentStatus::Submitted->value,
            'submitted_at'    => now(),
        ]);

        $this->uploadFile = null;
        unset($this->monthlyPayments, $this->stats);
        Flux::modal('monthly-upload')->close();
        Flux::toast(variant: 'success', text: 'Screenshot uploaded successfully.');
    }

    // ─── Review ───────────────────────────────────────────────────────────────

    public function openReview(int $id): void
    {
        $payment = MonthlyPayment::findOrFail($id);
        $this->actioningId = $id;
        $this->adminNotes = $payment->admin_notes ?? '';
        $this->screenshotUrl = $payment->screenshot_path ? Storage::url($payment->screenshot_path) : null;
        Flux::modal('monthly-review')->show();
    }

    public function verify(): void
    {
        MonthlyPayment::whereKey($this->actioningId)->update([
            'status'      => PaymentStatus::Verified->value,
            'admin_notes' => $this->adminNotes ?: null,
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        unset($this->monthlyPayments, $this->stats);
        Flux::modal('monthly-review')->close();
        Flux::toast(variant: 'success', text: 'Payment verified.');
    }

    public function reject(): void
    {
        MonthlyPayment::whereKey($this->actioningId)->update([
            'status'      => PaymentStatus::Rejected->value,
            'admin_notes' => $this->adminNotes ?: null,
        ]);

        unset($this->monthlyPayments, $this->stats);
        Flux::modal('monthly-review')->close();
        Flux::toast(variant: 'danger', text: 'Payment rejected.');
    }

    public function waive(int $id): void
    {
        MonthlyPayment::whereKey($id)->update([
            'status'      => PaymentStatus::Waived->value,
            'admin_notes' => 'Waived by admin.',
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        unset($this->monthlyPayments, $this->stats);
        Flux::toast(text: 'Payment waived.');
    }
}; ?>

<div>
    <div class="flex flex-col gap-6 px-6 py-6 sm:px-8">

        {{-- Header --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">Monthly Fees</h1>
                <p class="mt-1 text-sm font-medium text-zinc-500">Track monthly payment status for all active members.</p>
            </div>
            <div class="flex items-center gap-3">
                <flux:input
                    wire:model.live="selectedMonth"
                    type="month"
                    class="w-44"
                />
                @if (! $this->isGenerated)
                    <flux:button variant="primary" wire:click="generate" wire:confirm="Generate payment records for {{ $this->billingMonth->format('F Y') }}?">
                        Generate Records
                    </flux:button>
                @endif
            </div>
        </div>

        {{-- Month stats --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-500 shadow-sm">
                        <flux:icon name="users" class="size-5 text-white" />
                    </div>
                    <p class="text-xs font-bold uppercase tracking-wide text-zinc-500">Total Members</p>
                </div>
                <p class="mt-3 text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ $this->stats->total }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-5 shadow-sm dark:border-green-900 dark:bg-green-950">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-green-500 shadow-sm">
                        <flux:icon name="check-circle" class="size-5 text-white" />
                    </div>
                    <p class="text-xs font-bold uppercase tracking-wide text-green-600 dark:text-green-400">Verified</p>
                </div>
                <p class="mt-3 text-4xl font-extrabold tracking-tight text-green-700 dark:text-green-300">{{ $this->stats->verified }}</p>
            </div>
            <div class="rounded-xl border border-orange-200 bg-orange-50 p-5 shadow-sm dark:border-orange-900 dark:bg-orange-950">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-orange-500 shadow-sm">
                        <flux:icon name="clock" class="size-5 text-white" />
                    </div>
                    <p class="text-xs font-bold uppercase tracking-wide text-orange-600 dark:text-orange-400">Pending</p>
                </div>
                <p class="mt-3 text-4xl font-extrabold tracking-tight text-orange-700 dark:text-orange-300">{{ $this->stats->pending }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-5 shadow-sm dark:border-blue-900 dark:bg-blue-950">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-500 shadow-sm">
                        <flux:icon name="gift" class="size-5 text-white" />
                    </div>
                    <p class="text-xs font-bold uppercase tracking-wide text-blue-600 dark:text-blue-400">Waived</p>
                </div>
                <p class="mt-3 text-4xl font-extrabold tracking-tight text-blue-700 dark:text-blue-300">{{ $this->stats->waived }}</p>
            </div>
        </div>

        {{-- Not generated banner --}}
        @if (! $this->isGenerated && $this->monthlyPayments->isNotEmpty())
            <div class="flex items-start gap-3 rounded-xl border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-950">
                <flux:icon name="exclamation-triangle" class="mt-0.5 size-5 shrink-0 text-yellow-600" />
                <div>
                    <p class="font-medium text-yellow-800 dark:text-yellow-300">
                        Payment records not yet generated for {{ $this->billingMonth->format('F Y') }}
                    </p>
                    <p class="mt-0.5 text-sm text-yellow-700 dark:text-yellow-400">
                        Click "Generate Records" to create payment entries for all active members.
                    </p>
                </div>
            </div>
        @endif

        {{-- Table --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            @if ($this->monthlyPayments->isEmpty())
                <div class="py-20 text-center">
                    <flux:icon name="users" class="mx-auto size-10 text-zinc-300" />
                    <flux:text class="mt-3 text-sm">No active members found.</flux:text>
                </div>
            @else
                <flux:table>
                    <thead>
                        <flux:table.row>
                            <flux:table.column class="!ps-5">Member</flux:table.column>
                            <flux:table.column>Plan</flux:table.column>
                            <flux:table.column>Amount</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column>Verified By</flux:table.column>
                            <flux:table.column>Actions</flux:table.column>
                        </flux:table.row>
                    </thead>
                    <tbody>
                        @foreach ($this->monthlyPayments as $row)
                            <flux:table.row :class="$row->payment?->status === PaymentStatus::Overdue ? 'bg-red-50 dark:bg-red-950/30' : ''">
                                <flux:table.cell class="!ps-5">
                                    <flux:link :href="route('members.show', $row->member)" wire:navigate class="font-medium">
                                        {{ $row->member->full_name }}
                                    </flux:link>
                                    <p class="text-xs text-zinc-500">{{ $row->member->registration_number }}</p>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:text class="text-sm">{{ $row->plan?->name ?? '—' }}</flux:text>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="font-medium">RM {{ number_format($row->amount, 2) }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($row->payment)
                                        <div class="flex items-center gap-1.5">
                                            <flux:badge :color="$row->payment->status->color()" size="sm">
                                                {{ $row->payment->status->label() }}
                                            </flux:badge>
                                            @if ($row->payment->screenshot_path)
                                                <a href="{{ Storage::url($row->payment->screenshot_path) }}" target="_blank"
                                                   class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                                    <flux:icon name="photo" class="size-4" />
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <flux:badge color="zinc" size="sm">Not Generated</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:text class="text-sm">
                                        @if ($row->payment?->verifiedBy)
                                            {{ $row->payment->verifiedBy->name }}
                                            <span class="block text-xs text-zinc-400">
                                                {{ $row->payment->verified_at?->format('d M Y') }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </flux:text>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($row->payment)
                                        <div class="flex items-center gap-1">
                                            @if ($row->payment->status === PaymentStatus::Submitted)
                                                <flux:button size="sm" variant="primary"
                                                    wire:click="openReview({{ $row->payment->id }})">
                                                    Review
                                                </flux:button>
                                            @elseif ($row->payment->status->needsAction())
                                                <flux:button size="sm"
                                                    wire:click="openUpload({{ $row->payment->id }})">
                                                    Upload
                                                </flux:button>
                                                <flux:button size="sm" variant="ghost"
                                                    wire:click="waive({{ $row->payment->id }})"
                                                    wire:confirm="Waive this monthly payment?">
                                                    Waive
                                                </flux:button>
                                            @elseif ($row->payment->status === PaymentStatus::Rejected)
                                                <flux:button size="sm"
                                                    wire:click="openUpload({{ $row->payment->id }})">
                                                    Re-upload
                                                </flux:button>
                                            @endif
                                        </div>
                                    @else
                                        <flux:text class="text-xs text-zinc-400">Generate first</flux:text>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </tbody>
                </flux:table>
            @endif
        </div>
    </div>

    {{-- Upload Modal --}}
    <flux:modal name="monthly-upload" class="w-full max-w-md">
        <div class="p-6">
            <flux:heading size="lg">Upload Payment Screenshot</flux:heading>
            <form wire:submit="submitUpload" class="mt-4 space-y-4">
                <input type="file" wire:model="uploadFile" accept="image/*"
                    class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-300" />
                @error('uploadFile') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @if ($uploadFile)
                    <img src="{{ $uploadFile->temporaryUrl() }}" class="max-h-40 w-full rounded object-contain" />
                @endif
                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" x-on:click="$flux.modal('monthly-upload').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Upload</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Review Modal --}}
    <flux:modal name="monthly-review" class="w-full max-w-lg">
        <div class="p-6">
            <flux:heading size="lg">Review Monthly Payment</flux:heading>
            @if ($screenshotUrl)
                <div class="mt-4 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <img src="{{ $screenshotUrl }}" class="max-h-72 w-full object-contain" />
                </div>
                <a href="{{ $screenshotUrl }}" target="_blank"
                   class="mt-1 inline-flex items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400">
                    <flux:icon name="arrow-top-right-on-square" class="size-3" /> Open full size
                </a>
            @else
                <div class="mt-4 rounded-lg bg-zinc-50 p-4 text-center text-sm text-zinc-500 dark:bg-zinc-800">No screenshot uploaded.</div>
            @endif
            <div class="mt-4">
                <flux:textarea wire:model="adminNotes" label="Admin Notes (optional)" placeholder="Reason for rejection..." rows="2" />
            </div>
            <div class="mt-5 flex items-center justify-between">
                <flux:button variant="ghost" x-on:click="$flux.modal('monthly-review').close()">Cancel</flux:button>
                <div class="flex gap-2">
                    <flux:button variant="danger" wire:click="reject" wire:confirm="Reject this payment?">Reject</flux:button>
                    <flux:button variant="primary" wire:click="verify">Verify Payment</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

</div>
