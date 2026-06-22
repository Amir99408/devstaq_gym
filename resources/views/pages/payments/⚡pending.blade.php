<?php

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\MonthlyPayment;
use App\Models\RegistrationPayment;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app'), Title('Pending Payments')] class extends Component {

    use WithFileUploads;

    // Shared action state
    public string $actioningType = '';  // 'registration' | 'monthly'
    public ?int $actioningId = null;

    // Verify / Reject modal
    public string $adminNotes = '';
    public ?string $screenshotUrl = null;

    // Upload modal
    #[Validate('required|image|mimes:jpg,jpeg,png,webp|max:5120')]
    public $uploadFile = null;

    // ─── Computed data ────────────────────────────────────────────────────────

    #[Computed]
    public function awaitingVerification(): Collection
    {
        $registrations = RegistrationPayment::where('status', PaymentStatus::Submitted)
            ->with(['member', 'memberPlan.membershipPlan'])
            ->get()
            ->map(fn ($p) => (object) [
                'id'             => $p->id,
                'type'           => 'registration',
                'member'         => $p->member,
                'label'          => 'Registration Fee',
                'plan'           => $p->memberPlan?->membershipPlan?->name,
                'amount'         => $p->amount,
                'screenshot_url' => $p->screenshot_path ? Storage::url($p->screenshot_path) : null,
                'submitted_at'   => $p->submitted_at,
                'admin_notes'    => $p->admin_notes,
            ]);

        $monthly = MonthlyPayment::where('status', PaymentStatus::Submitted)
            ->with('member')
            ->get()
            ->map(fn ($p) => (object) [
                'id'             => $p->id,
                'type'           => 'monthly',
                'member'         => $p->member,
                'label'          => 'Monthly — ' . $p->billing_month->format('M Y'),
                'plan'           => null,
                'amount'         => $p->amount_due,
                'screenshot_url' => $p->screenshot_path ? Storage::url($p->screenshot_path) : null,
                'submitted_at'   => $p->submitted_at,
                'admin_notes'    => $p->admin_notes,
            ]);

        return $registrations->concat($monthly)->sortByDesc('submitted_at')->values();
    }

    #[Computed]
    public function awaitingScreenshot(): Collection
    {
        $registrations = RegistrationPayment::where('status', PaymentStatus::Pending)
            ->with(['member', 'memberPlan.membershipPlan'])
            ->get()
            ->map(fn ($p) => (object) [
                'id'     => $p->id,
                'type'   => 'registration',
                'member' => $p->member,
                'label'  => 'Registration Fee',
                'plan'   => $p->memberPlan?->membershipPlan?->name,
                'amount' => $p->amount,
            ]);

        $monthly = MonthlyPayment::where('status', PaymentStatus::Pending)
            ->where('due_date', '>=', today()->toDateString())
            ->with('member')
            ->get()
            ->map(fn ($p) => (object) [
                'id'     => $p->id,
                'type'   => 'monthly',
                'member' => $p->member,
                'label'  => 'Monthly — ' . $p->billing_month->format('M Y'),
                'plan'   => null,
                'amount' => $p->amount_due,
            ]);

        return $registrations->concat($monthly)->values();
    }

    #[Computed]
    public function overduePayments(): Collection
    {
        return MonthlyPayment::where(fn ($q) => $q
            ->where('status', PaymentStatus::Overdue)
            ->orWhere(fn ($q) => $q
                ->where('status', PaymentStatus::Pending)
                ->where('due_date', '<', today()->toDateString())
            ))
            ->with('member')
            ->get()
            ->map(fn ($p) => (object) [
                'id'       => $p->id,
                'type'     => 'monthly',
                'member'   => $p->member,
                'label'    => 'Monthly — ' . $p->billing_month->format('M Y'),
                'plan'     => null,
                'amount'   => $p->amount_due,
                'due_date' => $p->due_date,
            ]);
    }

    // ─── Upload ───────────────────────────────────────────────────────────────

    public function openUpload(string $type, int $id): void
    {
        $this->actioningType = $type;
        $this->actioningId = $id;
        $this->uploadFile = null;
        $this->resetValidation('uploadFile');
        Flux::modal('upload-screenshot')->show();
    }

    public function submitUpload(): void
    {
        $this->validateOnly('uploadFile');

        $path = $this->uploadFile->store('screenshots', 'public');

        if ($this->actioningType === 'registration') {
            RegistrationPayment::whereKey($this->actioningId)->update([
                'screenshot_path' => $path,
                'status'          => PaymentStatus::Submitted->value,
                'submitted_at'    => now(),
            ]);
        } else {
            MonthlyPayment::whereKey($this->actioningId)->update([
                'screenshot_path' => $path,
                'status'          => PaymentStatus::Submitted->value,
                'submitted_at'    => now(),
            ]);
        }

        $this->uploadFile = null;
        $this->clearComputed();
        Flux::modal('upload-screenshot')->close();
        Flux::toast(variant: 'success', text: 'Screenshot uploaded. Ready for verification.');
    }

    // ─── Verify / Reject / Waive ─────────────────────────────────────────────

    public function openVerify(string $type, int $id): void
    {
        $this->actioningType = $type;
        $this->actioningId = $id;
        $this->adminNotes = '';

        $model = $type === 'registration'
            ? RegistrationPayment::find($id)
            : MonthlyPayment::find($id);

        $this->screenshotUrl = $model?->screenshot_path
            ? Storage::url($model->screenshot_path)
            : null;

        Flux::modal('verify-payment')->show();
    }

    public function verify(): void
    {
        $this->processAction(PaymentStatus::Verified);
        Flux::toast(variant: 'success', text: 'Payment verified successfully.');
    }

    public function reject(): void
    {
        $this->processAction(PaymentStatus::Rejected);
        Flux::toast(variant: 'danger', text: 'Payment rejected. Member must resubmit.');
    }

    public function waive(string $type, int $id): void
    {
        $this->actioningType = $type;
        $this->actioningId = $id;
        $this->adminNotes = 'Waived by admin.';
        $this->processAction(PaymentStatus::Waived);
        Flux::toast(text: 'Payment waived.');
    }

    private function processAction(PaymentStatus $status): void
    {
        $now = now();

        if ($this->actioningType === 'registration') {
            $payment = RegistrationPayment::with('member')->findOrFail($this->actioningId);
            $payment->update([
                'status'      => $status->value,
                'admin_notes' => $this->adminNotes ?: null,
                'verified_at' => $now,
                'verified_by' => auth()->id(),
            ]);

            // Activate member when registration is approved or waived
            if (in_array($status, [PaymentStatus::Verified, PaymentStatus::Waived])) {
                $payment->member->update(['status' => MemberStatus::Active->value]);
            }
        } else {
            MonthlyPayment::whereKey($this->actioningId)->update([
                'status'      => $status->value,
                'admin_notes' => $this->adminNotes ?: null,
                'verified_at' => $now,
                'verified_by' => auth()->id(),
            ]);
        }

        $this->clearComputed();
        Flux::modal('verify-payment')->close();
    }

    private function clearComputed(): void
    {
        unset($this->awaitingVerification, $this->awaitingScreenshot, $this->overduePayments);
    }
}; ?>

<div>
    <div class="flex flex-col gap-6 px-4 py-6 sm:px-6">

        {{-- Header --}}
        <div>
            <flux:heading size="xl">Pending Payments</flux:heading>
            <flux:text class="mt-1">Review screenshots, verify or reject payments, and track overdue members.</flux:text>
        </div>

        {{-- Summary Counters --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-center dark:border-yellow-800 dark:bg-yellow-950">
                <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">{{ $this->awaitingVerification->count() }}</p>
                <flux:text class="mt-1 text-xs text-yellow-600 dark:text-yellow-400">Awaiting Verification</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 text-center dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-2xl font-bold text-zinc-700 dark:text-zinc-300">{{ $this->awaitingScreenshot->count() }}</p>
                <flux:text class="mt-1 text-xs text-zinc-500">Awaiting Screenshot</flux:text>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-center dark:border-red-900 dark:bg-red-950">
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->overduePayments->count() }}</p>
                <flux:text class="mt-1 text-xs text-red-500">Overdue</flux:text>
            </div>
        </div>

        {{-- Section 1: Awaiting Verification --}}
        <div>
            <div class="mb-3 flex items-center gap-2">
                <flux:heading size="base">Awaiting Your Verification</flux:heading>
                @if ($this->awaitingVerification->isNotEmpty())
                    <flux:badge color="yellow" size="sm">{{ $this->awaitingVerification->count() }}</flux:badge>
                @endif
            </div>

            @if ($this->awaitingVerification->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-200 py-10 text-center dark:border-zinc-700">
                    <flux:icon name="check-circle" class="mx-auto size-8 text-green-400" />
                    <flux:text class="mt-2 text-sm">All clear — nothing awaiting verification.</flux:text>
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:table>
                        <thead>
                            <flux:table.row>
                                <flux:table.column>Member</flux:table.column>
                                <flux:table.column>Payment</flux:table.column>
                                <flux:table.column>Amount</flux:table.column>
                                <flux:table.column>Submitted</flux:table.column>
                                <flux:table.column>Screenshot</flux:table.column>
                                <flux:table.column>Actions</flux:table.column>
                            </flux:table.row>
                        </thead>
                        <tbody>
                            @foreach ($this->awaitingVerification as $item)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ $item->member->full_name }}</p>
                                        <p class="text-xs text-zinc-500">{{ $item->member->registration_number }}</p>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$item->type === 'registration' ? 'blue' : 'purple'" size="sm">
                                            {{ $item->label }}
                                        </flux:badge>
                                        @if ($item->plan)
                                            <p class="mt-0.5 text-xs text-zinc-500">{{ $item->plan }}</p>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <span class="font-medium">RM {{ number_format($item->amount, 2) }}</span>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:text class="text-sm">
                                            {{ $item->submitted_at?->diffForHumans() ?? '—' }}
                                        </flux:text>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($item->screenshot_url)
                                            <a href="{{ $item->screenshot_url }}" target="_blank"
                                               class="inline-flex items-center gap-1 text-sm text-blue-600 underline hover:text-blue-800 dark:text-blue-400">
                                                <flux:icon name="photo" class="size-4" />
                                                View
                                            </a>
                                        @else
                                            <flux:text class="text-xs text-zinc-400">No file</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex items-center gap-1">
                                            <flux:button
                                                size="sm"
                                                variant="primary"
                                                wire:click="openVerify('{{ $item->type }}', {{ $item->id }})"
                                            >
                                                Review
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                wire:click="waive('{{ $item->type }}', {{ $item->id }})"
                                                wire:confirm="Waive this payment? No further proof required."
                                            >
                                                Waive
                                            </flux:button>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </tbody>
                    </flux:table>
                </div>
            @endif
        </div>

        {{-- Section 2: Awaiting Screenshot --}}
        <div>
            <div class="mb-3 flex items-center gap-2">
                <flux:heading size="base">Awaiting Payment Screenshot</flux:heading>
                @if ($this->awaitingScreenshot->isNotEmpty())
                    <flux:badge color="zinc" size="sm">{{ $this->awaitingScreenshot->count() }}</flux:badge>
                @endif
            </div>

            @if ($this->awaitingScreenshot->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-200 py-10 text-center dark:border-zinc-700">
                    <flux:text class="text-sm text-zinc-400">No pending screenshots.</flux:text>
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:table>
                        <thead>
                            <flux:table.row>
                                <flux:table.column>Member</flux:table.column>
                                <flux:table.column>Payment</flux:table.column>
                                <flux:table.column>Amount</flux:table.column>
                                <flux:table.column>Actions</flux:table.column>
                            </flux:table.row>
                        </thead>
                        <tbody>
                            @foreach ($this->awaitingScreenshot as $item)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ $item->member->full_name }}</p>
                                        <p class="text-xs text-zinc-500">{{ $item->member->registration_number }}</p>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$item->type === 'registration' ? 'blue' : 'purple'" size="sm">
                                            {{ $item->label }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <span class="font-medium">RM {{ number_format($item->amount, 2) }}</span>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex items-center gap-1">
                                            <flux:button
                                                size="sm"
                                                wire:click="openUpload('{{ $item->type }}', {{ $item->id }})"
                                            >
                                                Upload Screenshot
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                wire:click="waive('{{ $item->type }}', {{ $item->id }})"
                                                wire:confirm="Waive this payment? No proof required."
                                            >
                                                Waive
                                            </flux:button>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </tbody>
                    </flux:table>
                </div>
            @endif
        </div>

        {{-- Section 3: Overdue --}}
        @if ($this->overduePayments->isNotEmpty())
            <div>
                <div class="mb-3 flex items-center gap-2">
                    <flux:heading size="base" class="text-red-600 dark:text-red-400">Overdue Payments</flux:heading>
                    <flux:badge color="red" size="sm">{{ $this->overduePayments->count() }}</flux:badge>
                </div>

                <div class="overflow-hidden rounded-xl border border-red-200 bg-white dark:border-red-900 dark:bg-zinc-900">
                    <flux:table>
                        <thead>
                            <flux:table.row>
                                <flux:table.column>Member</flux:table.column>
                                <flux:table.column>Payment</flux:table.column>
                                <flux:table.column>Amount</flux:table.column>
                                <flux:table.column>Due Date</flux:table.column>
                                <flux:table.column>Actions</flux:table.column>
                            </flux:table.row>
                        </thead>
                        <tbody>
                            @foreach ($this->overduePayments as $item)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ $item->member->full_name }}</p>
                                        <p class="text-xs text-zinc-500">{{ $item->member->registration_number }}</p>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge color="purple" size="sm">{{ $item->label }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <span class="font-medium text-red-600">RM {{ number_format($item->amount, 2) }}</span>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:text class="text-sm text-red-500">
                                            {{ $item->due_date->format('d M Y') }}
                                            ({{ $item->due_date->diffForHumans() }})
                                        </flux:text>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex items-center gap-1">
                                            <flux:button
                                                size="sm"
                                                wire:click="openUpload('{{ $item->type }}', {{ $item->id }})"
                                            >
                                                Upload Proof
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                wire:click="waive('{{ $item->type }}', {{ $item->id }})"
                                                wire:confirm="Waive this overdue payment?"
                                            >
                                                Waive
                                            </flux:button>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </tbody>
                    </flux:table>
                </div>
            </div>
        @endif

    </div>

    {{-- Upload Screenshot Modal --}}
    <flux:modal name="upload-screenshot" class="w-full max-w-md">
        <div class="p-6">
            <flux:heading size="lg">Upload Payment Screenshot</flux:heading>
            <flux:text class="mt-1">Select the payment proof image (JPG, PNG, WebP — max 5MB).</flux:text>

            <form wire:submit="submitUpload" class="mt-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Screenshot
                    </label>
                    <div class="mt-1">
                        <input
                            type="file"
                            wire:model="uploadFile"
                            accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-300"
                        />
                    </div>
                    @error('uploadFile')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Preview --}}
                @if ($uploadFile)
                    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <img src="{{ $uploadFile->temporaryUrl() }}" alt="Preview" class="max-h-48 w-full object-contain" />
                    </div>
                @endif

                <div wire:loading wire:target="uploadFile" class="text-sm text-zinc-500">
                    Uploading...
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="ghost" x-on:click="$flux.modal('upload-screenshot').close()">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="submitUpload">
                        <span wire:loading.remove wire:target="submitUpload">Submit Screenshot</span>
                        <span wire:loading wire:target="submitUpload">Uploading...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Verify / Reject Modal --}}
    <flux:modal name="verify-payment" class="w-full max-w-lg">
        <div class="p-6">
            <flux:heading size="lg">Review Payment</flux:heading>
            <flux:text class="mt-1">Verify or reject the payment proof below.</flux:text>

            {{-- Screenshot --}}
            @if ($screenshotUrl)
                <div class="mt-4 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <img src="{{ $screenshotUrl }}" alt="Payment proof" class="max-h-72 w-full object-contain" />
                </div>
                <a href="{{ $screenshotUrl }}" target="_blank"
                   class="mt-1 inline-flex items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400">
                    <flux:icon name="arrow-top-right-on-square" class="size-3" />
                    Open full size
                </a>
            @else
                <div class="mt-4 rounded-lg bg-zinc-50 p-4 text-center text-sm text-zinc-500 dark:bg-zinc-800">
                    No screenshot uploaded.
                </div>
            @endif

            <div class="mt-4">
                <flux:textarea
                    wire:model="adminNotes"
                    label="Admin Notes (optional)"
                    placeholder="Reason for rejection or any remarks..."
                    rows="2"
                />
            </div>

            <div class="mt-5 flex items-center justify-between">
                <flux:button
                    variant="ghost"
                    x-on:click="$flux.modal('verify-payment').close()"
                >
                    Cancel
                </flux:button>
                <div class="flex items-center gap-2">
                    <flux:button
                        variant="danger"
                        wire:click="reject"
                        wire:confirm="Reject this payment? Member will need to resubmit."
                    >
                        Reject
                    </flux:button>
                    <flux:button variant="primary" wire:click="verify">
                        Verify Payment
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

</div>
