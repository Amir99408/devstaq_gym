<?php

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\RegistrationPayment;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Registration Fees')] class extends Component {

    use WithFileUploads, WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'q')]
    public string $search = '';

    // Action state
    public ?int $actioningId = null;
    public string $adminNotes = '';
    public ?string $screenshotUrl = null;

    #[Validate('required|image|mimes:jpg,jpeg,png,webp|max:5120')]
    public $uploadFile = null;

    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingSearch(): void { $this->resetPage(); }

    #[Computed]
    public function payments()
    {
        return RegistrationPayment::query()
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->whereHas('member', fn ($q) => $q
                ->where('full_name', 'like', "%{$this->search}%")
                ->orWhere('registration_number', 'like', "%{$this->search}%")
            ))
            ->with(['member', 'memberPlan.membershipPlan', 'verifiedBy'])
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    #[Computed]
    public function statuses(): array
    {
        return PaymentStatus::cases();
    }

    // ─── Upload ───────────────────────────────────────────────────────────────

    public function openUpload(int $id): void
    {
        $this->actioningId = $id;
        $this->uploadFile = null;
        $this->resetValidation('uploadFile');
        Flux::modal('reg-upload')->show();
    }

    public function submitUpload(): void
    {
        $this->validateOnly('uploadFile');

        $path = $this->uploadFile->store('screenshots', 'public');

        RegistrationPayment::whereKey($this->actioningId)->update([
            'screenshot_path' => $path,
            'status'          => PaymentStatus::Submitted->value,
            'submitted_at'    => now(),
        ]);

        $this->uploadFile = null;
        unset($this->payments);
        Flux::modal('reg-upload')->close();
        Flux::toast(variant: 'success', text: 'Screenshot uploaded successfully.');
    }

    // ─── Verify / Reject / Waive ─────────────────────────────────────────────

    public function openReview(int $id): void
    {
        $payment = RegistrationPayment::findOrFail($id);
        $this->actioningId = $id;
        $this->adminNotes = $payment->admin_notes ?? '';
        $this->screenshotUrl = $payment->screenshot_path ? Storage::url($payment->screenshot_path) : null;
        Flux::modal('reg-review')->show();
    }

    public function verify(): void
    {
        $payment = RegistrationPayment::with('member')->findOrFail($this->actioningId);
        $payment->update([
            'status'      => PaymentStatus::Verified->value,
            'admin_notes' => $this->adminNotes ?: null,
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);
        $payment->member->update(['status' => MemberStatus::Active->value]);

        unset($this->payments);
        Flux::modal('reg-review')->close();
        Flux::toast(variant: 'success', text: 'Registration payment verified. Member is now active.');
    }

    public function reject(): void
    {
        RegistrationPayment::whereKey($this->actioningId)->update([
            'status'      => PaymentStatus::Rejected->value,
            'admin_notes' => $this->adminNotes ?: null,
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        unset($this->payments);
        Flux::modal('reg-review')->close();
        Flux::toast(variant: 'danger', text: 'Payment rejected. Member must resubmit proof.');
    }

    public function waive(int $id): void
    {
        $payment = RegistrationPayment::with('member')->findOrFail($id);
        $payment->update([
            'status'      => PaymentStatus::Waived->value,
            'admin_notes' => 'Waived by admin.',
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);
        $payment->member->update(['status' => MemberStatus::Active->value]);

        unset($this->payments);
        Flux::toast(text: 'Payment waived. Member is now active.');
    }
}; ?>

<div>
    <div class="flex flex-col gap-6 px-4 py-6 sm:px-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Registration Fees</flux:heading>
                <flux:text class="mt-1">Track and verify one-time registration payments from all members.</flux:text>
            </div>
        </div>

        {{-- Filters --}}
        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="Search by member name or registration number..."
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
            @if ($this->payments->isEmpty())
                <div class="py-20 text-center">
                    <flux:icon name="document-magnifying-glass" class="mx-auto size-10 text-zinc-300" />
                    <flux:text class="mt-3 text-sm">No registration payments found.</flux:text>
                </div>
            @else
                <flux:table>
                    <thead>
                        <flux:table.row>
                            <flux:table.column>Member</flux:table.column>
                            <flux:table.column>Plan</flux:table.column>
                            <flux:table.column>Amount</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column>Verified By</flux:table.column>
                            <flux:table.column>Actions</flux:table.column>
                        </flux:table.row>
                    </thead>
                    <tbody>
                        @foreach ($this->payments as $payment)
                            <flux:table.row>
                                <flux:table.cell>
                                    <flux:link :href="route('members.show', $payment->member)" wire:navigate class="font-medium">
                                        {{ $payment->member->full_name }}
                                    </flux:link>
                                    <p class="text-xs text-zinc-500">{{ $payment->member->registration_number }}</p>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:text class="text-sm">
                                        {{ $payment->memberPlan?->membershipPlan?->name ?? '—' }}
                                    </flux:text>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="font-medium">RM {{ number_format($payment->amount, 2) }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$payment->status->color()" size="sm">
                                        {{ $payment->status->label() }}
                                    </flux:badge>
                                    @if ($payment->screenshot_path)
                                        <a href="{{ Storage::url($payment->screenshot_path) }}" target="_blank"
                                           class="ml-1 inline-flex items-center text-xs text-blue-600 hover:underline dark:text-blue-400">
                                            <flux:icon name="photo" class="size-3.5" />
                                        </a>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:text class="text-sm">
                                        @if ($payment->verifiedBy)
                                            {{ $payment->verifiedBy->name }}
                                            <span class="block text-xs text-zinc-400">{{ $payment->verified_at?->format('d M Y') }}</span>
                                        @else
                                            —
                                        @endif
                                    </flux:text>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-1">
                                        @if ($payment->status === PaymentStatus::Submitted)
                                            <flux:button size="sm" variant="primary" wire:click="openReview({{ $payment->id }})">
                                                Review
                                            </flux:button>
                                        @elseif ($payment->status === PaymentStatus::Pending)
                                            <flux:button size="sm" wire:click="openUpload({{ $payment->id }})">
                                                Upload
                                            </flux:button>
                                            <flux:button size="sm" variant="ghost"
                                                wire:click="waive({{ $payment->id }})"
                                                wire:confirm="Waive this registration fee?">
                                                Waive
                                            </flux:button>
                                        @elseif ($payment->status === PaymentStatus::Rejected)
                                            <flux:button size="sm" wire:click="openUpload({{ $payment->id }})">
                                                Re-upload
                                            </flux:button>
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </tbody>
                </flux:table>

                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    {{ $this->payments->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Upload Modal --}}
    <flux:modal name="reg-upload" class="w-full max-w-md">
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
                    <flux:button type="button" variant="ghost" x-on:click="$flux.modal('reg-upload').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Upload</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Review Modal --}}
    <flux:modal name="reg-review" class="w-full max-w-lg">
        <div class="p-6">
            <flux:heading size="lg">Review Registration Payment</flux:heading>
            @if ($screenshotUrl)
                <div class="mt-4 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <img src="{{ $screenshotUrl }}" class="max-h-72 w-full object-contain" />
                </div>
                <a href="{{ $screenshotUrl }}" target="_blank" class="mt-1 inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                    <flux:icon name="arrow-top-right-on-square" class="size-3" /> Open full size
                </a>
            @else
                <div class="mt-4 rounded-lg bg-zinc-50 p-4 text-center text-sm text-zinc-500 dark:bg-zinc-800">No screenshot.</div>
            @endif
            <div class="mt-4">
                <flux:textarea wire:model="adminNotes" label="Admin Notes (optional)" placeholder="Reason for rejection..." rows="2" />
            </div>
            <div class="mt-5 flex items-center justify-between">
                <flux:button variant="ghost" x-on:click="$flux.modal('reg-review').close()">Cancel</flux:button>
                <div class="flex gap-2">
                    <flux:button variant="danger" wire:click="reject" wire:confirm="Reject this payment?">Reject</flux:button>
                    <flux:button variant="primary" wire:click="verify">Verify & Activate</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

</div>
