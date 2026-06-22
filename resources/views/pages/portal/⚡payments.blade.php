<?php

use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\MonthlyPayment;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.portal'), Title('Payment History')] class extends Component {

    use WithFileUploads;

    #[Validate('required|image|mimes:jpg,jpeg,png,webp|max:5120')]
    public $uploadFile = null;
    public ?int $uploadingPaymentId = null;

    #[Computed]
    public function member(): Member
    {
        return Member::with([
            'registrationPayment.verifiedBy',
            'monthlyPayments' => fn ($q) => $q->orderByDesc('billing_month'),
            'monthlyPayments.verifiedBy',
        ])->where('user_id', auth()->id())->firstOrFail();
    }

    #[Computed]
    public function totalPaid(): float
    {
        $reg = $this->member->registrationPayment;
        $regAmount = ($reg && in_array($reg->status->value, ['verified', 'waived']))
            ? (float) $reg->amount
            : 0;

        $monthly = $this->member->monthlyPayments
            ->whereIn('status.value', ['verified', 'waived'])
            ->sum(fn ($p) => (float) $p->amount_due);

        return $regAmount + $monthly;
    }

    #[Computed]
    public function pendingAmount(): float
    {
        return (float) $this->member->monthlyPayments
            ->whereIn('status.value', ['pending', 'overdue'])
            ->sum(fn ($p) => (float) $p->amount_due);
    }

    public function openUpload(int $paymentId): void
    {
        $this->uploadingPaymentId = $paymentId;
        $this->uploadFile = null;
        $this->resetValidation('uploadFile');
        Flux::modal('upload-proof')->show();
    }

    public function submitUpload(): void
    {
        $this->validateOnly('uploadFile');

        $payment = $this->member->monthlyPayments->find($this->uploadingPaymentId);
        abort_unless($payment, 403);

        $path = $this->uploadFile->store('screenshots', 'public');
        MonthlyPayment::whereKey($payment->id)->update([
            'screenshot_path' => $path,
            'status'          => PaymentStatus::Submitted->value,
            'submitted_at'    => now(),
        ]);

        $this->uploadFile = null;
        $this->uploadingPaymentId = null;
        unset($this->member);
        Flux::modal('upload-proof')->close();
        Flux::toast(variant: 'success', text: 'Screenshot submitted! Admin will verify shortly.');
    }
}; ?>

<div class="space-y-6">

    {{-- Page header --}}
    <div>
        <flux:heading size="xl">Payment History</flux:heading>
        <flux:text class="mt-1 text-sm">All your registration and monthly payment records.</flux:text>
    </div>

    @php $member = $this->member; @endphp

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-green-200 bg-green-50 p-4">
            <p class="text-xs font-medium uppercase tracking-wider text-green-600">Total Paid</p>
            <p class="mt-1 text-2xl font-bold text-green-700">RM {{ number_format($this->totalPaid, 2) }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-medium uppercase tracking-wider text-amber-600">Outstanding</p>
            <p class="mt-1 text-2xl font-bold text-amber-700">RM {{ number_format($this->pendingAmount, 2) }}</p>
        </div>
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
            <p class="text-xs font-medium uppercase tracking-wider text-blue-600">Total Months</p>
            <p class="mt-1 text-2xl font-bold text-blue-700">{{ $member->monthlyPayments->count() }}</p>
        </div>
    </div>

    {{-- Registration Fee --}}
    @if($member->registrationPayment)
        @php $reg = $member->registrationPayment; @endphp
        <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-100 px-5 py-4">
                <flux:heading size="sm">Registration Fee</flux:heading>
            </div>
            <div class="flex items-center justify-between px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-full bg-violet-100">
                        <flux:icon name="document-check" class="size-5 text-violet-600" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-zinc-900">RM {{ number_format($reg->amount, 2) }}</p>
                        <p class="text-xs text-zinc-500">
                            One-time fee
                            @if($reg->verified_at)
                                · Verified {{ $reg->verified_at->format('d M Y') }}
                                @if($reg->verifiedBy) by {{ $reg->verifiedBy->name }} @endif
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($reg->screenshot_path)
                        <a href="{{ Storage::url($reg->screenshot_path) }}" target="_blank"
                           class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                            <flux:icon name="photo" class="size-3.5" /> View
                        </a>
                    @endif
                    <flux:badge :color="$reg->status->color()" size="sm">{{ $reg->status->label() }}</flux:badge>
                </div>
            </div>
        </div>
    @endif

    {{-- Monthly Payments --}}
    <div class="rounded-xl border border-zinc-200 bg-white shadow-sm">
        <div class="border-b border-zinc-100 px-5 py-4">
            <flux:heading size="sm">Monthly Payments</flux:heading>
        </div>

        @if($member->monthlyPayments->isEmpty())
            <div class="flex flex-col items-center py-16">
                <div class="flex size-14 items-center justify-center rounded-full bg-zinc-100">
                    <flux:icon name="banknotes" class="size-7 text-zinc-400" />
                </div>
                <flux:heading size="sm" class="mt-4 text-zinc-500">No payment records yet</flux:heading>
                <flux:text class="mt-1 text-sm">Records are generated on the 1st of each month.</flux:text>
            </div>
        @else
            <div class="divide-y divide-zinc-100">
                @foreach($member->monthlyPayments as $payment)
                    @php
                        $isPaid    = in_array($payment->status->value, ['verified', 'waived']);
                        $isOverdue = $payment->status->value === 'overdue';
                        $canUpload = in_array($payment->status->value, ['pending', 'overdue', 'rejected']);
                    @endphp
                    <div class="flex items-center justify-between px-5 py-4 {{ $isOverdue ? 'bg-red-50' : '' }}">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-full
                                {{ $isPaid ? 'bg-green-100' : ($isOverdue ? 'bg-red-100' : 'bg-zinc-100') }}">
                                <flux:icon
                                    :name="$isPaid ? 'check' : ($isOverdue ? 'exclamation-triangle' : 'clock')"
                                    class="size-5 {{ $isPaid ? 'text-green-600' : ($isOverdue ? 'text-red-600' : 'text-zinc-400') }}"
                                />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-900">
                                    {{ $payment->billing_month->format('F Y') }}
                                </p>
                                <p class="text-xs text-zinc-500">
                                    RM {{ number_format($payment->amount_due, 2) }}
                                    @if($payment->due_date && !$isPaid)
                                        · Due {{ $payment->due_date->format('d M Y') }}
                                    @endif
                                    @if($payment->submitted_at)
                                        · Submitted {{ $payment->submitted_at->format('d M') }}
                                    @endif
                                    @if($payment->verified_at)
                                        · Verified {{ $payment->verified_at->format('d M') }}
                                        @if($payment->verifiedBy) by {{ $payment->verifiedBy->name }} @endif
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if($payment->screenshot_path)
                                <a href="{{ Storage::url($payment->screenshot_path) }}" target="_blank"
                                   class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                                    <flux:icon name="photo" class="size-3.5" /> Proof
                                </a>
                            @endif
                            <flux:badge :color="$payment->status->color()" size="sm">
                                {{ $payment->status->label() }}
                            </flux:badge>
                            @if($canUpload)
                                <flux:button size="sm" variant="primary" wire:click="openUpload({{ $payment->id }})">
                                    Upload
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Upload proof modal --}}
    <flux:modal name="upload-proof" class="w-full max-w-md">
        <div class="p-6">
            <flux:heading size="lg">Upload Payment Proof</flux:heading>
            <flux:text class="mt-1 text-sm">Upload your bank transfer or receipt screenshot. JPG, PNG, WebP — max 5MB.</flux:text>
            <form wire:submit="submitUpload" class="mt-4 space-y-4">
                <input type="file" wire:model="uploadFile" accept="image/*"
                    class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200" />
                @error('uploadFile') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @if($uploadFile)
                    <img src="{{ $uploadFile->temporaryUrl() }}" class="max-h-40 w-full rounded object-contain" />
                @endif
                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" x-on:click="$flux.modal('upload-proof').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Submit Proof</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

</div>
