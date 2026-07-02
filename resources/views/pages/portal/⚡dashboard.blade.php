<?php

use App\Enums\PaymentStatus;
use App\Models\Member;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.portal'), Title('My Dashboard')] class extends Component {

    use WithFileUploads;

    #[Validate('required|image|mimes:jpg,jpeg,png,webp|max:5120')]
    public $uploadFile = null;
    public ?int $uploadingPaymentId = null;

    #[Computed]
    public function member(): Member
    {
        return Member::with([
            'user',
            'currentPlan.membershipPlan',
            'registrationPayment',
            'currentMonthPayment.verifiedBy',
        ])->where('user_id', auth()->id())->firstOrFail();
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

        $payment = $this->member->monthlyPayments()->findOrFail($this->uploadingPaymentId);

        $path = $this->uploadFile->store('screenshots', 'public');
        $payment->update([
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

    @php
        $member  = $this->member;
        $plan    = $member->currentPlan;
        $reg     = $member->registrationPayment;
        $monthly = $member->currentMonthPayment;
    @endphp

    {{-- Welcome Banner --}}
    <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 to-blue-500 px-6 py-6 text-white shadow-sm">
        <p class="text-sm font-medium text-blue-100">Welcome back,</p>
        <h1 class="mt-0.5 text-2xl font-bold">{{ $member->full_name }}</h1>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <span class="flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-medium ring-1 ring-white/30">
                <flux:icon name="identification" class="size-3.5" />
                {{ $member->registration_number }}
            </span>
            <span class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-white/30
                {{ $member->status->value === 'active' ? 'bg-green-500/30 text-green-100' : 'bg-white/20 text-white/80' }}">
                {{ $member->status->label() }}
            </span>
            @if($member->created_at)
                <span class="text-xs text-blue-200">Member since {{ $member->created_at->format('M Y') }}</span>
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">

        {{-- Current Plan Card --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-lg bg-blue-100">
                    <flux:icon name="tag" class="size-4 text-blue-600" />
                </div>
                <flux:heading size="sm">Membership Plan</flux:heading>
            </div>
            @if($plan)
                <p class="text-lg font-bold text-zinc-900">{{ $plan->membershipPlan->name }}</p>
                <p class="mt-1 text-sm text-zinc-500">Started {{ $plan->start_date->format('d M Y') }}</p>
                <div class="mt-3 rounded-lg bg-blue-50 px-4 py-3">
                    <p class="text-xs text-blue-500">Monthly Fee</p>
                    <p class="text-xl font-bold text-blue-700">RM {{ number_format($plan->monthly_fee_amount, 2) }}</p>
                </div>
            @else
                <p class="text-sm text-zinc-400">No active plan assigned yet.</p>
            @endif
        </div>

        {{-- This Month's Payment Card --}}
        <div class="rounded-xl border bg-white p-5 shadow-sm
            {{ $monthly?->status->value === 'overdue' ? 'border-red-200' : ($monthly?->status->value === 'verified' || $monthly?->status->value === 'waived' ? 'border-green-200' : 'border-zinc-200') }}">
            <div class="mb-3 flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-lg
                    {{ $monthly?->status->value === 'overdue' ? 'bg-red-100' : ($monthly?->status->value === 'verified' || $monthly?->status->value === 'waived' ? 'bg-green-100' : 'bg-amber-100') }}">
                    <flux:icon
                        :name="($monthly?->status->value === 'verified' || $monthly?->status->value === 'waived') ? 'check-circle' : ($monthly?->status->value === 'overdue' ? 'exclamation-triangle' : 'banknotes')"
                        class="size-4 {{ $monthly?->status->value === 'overdue' ? 'text-red-600' : ($monthly?->status->value === 'verified' || $monthly?->status->value === 'waived' ? 'text-green-600' : 'text-amber-600') }}"
                    />
                </div>
                <flux:heading size="sm">This Month</flux:heading>
            </div>

            @if($monthly)
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-lg font-bold text-zinc-900">RM {{ number_format($monthly->amount_due, 2) }}</p>
                        <p class="text-xs text-zinc-500">{{ $monthly->billing_month->format('F Y') }}</p>
                    </div>
                    <flux:badge :color="$monthly->status->color()" size="sm">
                        {{ $monthly->status->label() }}
                    </flux:badge>
                </div>

                @if($monthly->due_date && !in_array($monthly->status->value, ['verified', 'waived']))
                    <p class="mt-2 text-xs {{ $monthly->status->value === 'overdue' ? 'font-medium text-red-600' : 'text-zinc-500' }}">
                        Due: {{ $monthly->due_date->format('d M Y') }}
                        @if($monthly->status->value === 'overdue')
                            — OVERDUE
                        @endif
                    </p>
                @endif

                @if($monthly->submitted_at)
                    <p class="mt-1 text-xs text-zinc-400">Submitted {{ $monthly->submitted_at->format('d M Y') }}</p>
                @endif

                @if(in_array($monthly->status->value, ['pending', 'overdue', 'rejected']))
                    <div class="mt-3">
                        <flux:button size="sm" variant="primary" wire:click="openUpload({{ $monthly->id }})">
                            <flux:icon name="arrow-up-tray" class="size-4" />
                            Upload Payment Proof
                        </flux:button>
                    </div>
                @elseif($monthly->status->value === 'submitted')
                    <div class="mt-3 flex items-center gap-1.5 text-xs text-amber-600">
                        <flux:icon name="clock" class="size-3.5" />
                        Awaiting admin verification
                    </div>
                @elseif($monthly->status->value === 'verified')
                    <div class="mt-3 flex items-center gap-1.5 text-xs text-green-600">
                        <flux:icon name="check-circle" class="size-3.5" />
                        Verified by {{ $monthly->verifiedBy?->name ?? 'admin' }}
                    </div>
                @endif
            @else
                <p class="text-sm text-zinc-400">No payment record for this month yet.</p>
                <p class="mt-1 text-xs text-zinc-400">Generated on the 1st of each month.</p>
            @endif
        </div>
    </div>

    {{-- Registration Fee Status --}}
    @if($reg)
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex size-8 items-center justify-center rounded-lg bg-violet-100">
                        <flux:icon name="document-check" class="size-4 text-violet-600" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-zinc-900">Registration Fee</p>
                        <p class="text-xs text-zinc-500">RM {{ number_format($reg->amount, 2) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($reg->screenshot_path)
                        <a href="{{ Storage::url($reg->screenshot_path) }}" target="_blank"
                           class="text-xs text-blue-600 hover:underline">View proof</a>
                    @endif
                    <flux:badge :color="$reg->status->color()" size="sm">{{ $reg->status->label() }}</flux:badge>
                </div>
            </div>
        </div>
    @endif

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
