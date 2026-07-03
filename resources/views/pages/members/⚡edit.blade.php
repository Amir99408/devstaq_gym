<?php

use App\Models\Member;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app'), Title('Edit Member')] class extends Component {

    #[Locked]
    public int $memberId;

    public string $full_name = '';
    public string $phone = '';
    public string $email = '';
    public string $date_of_birth = '';
    public string $address = '';
    public string $emergency_contact_name = '';
    public string $emergency_contact_phone = '';
    public string $notes = '';

    public function mount(Member $member): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->memberId              = $member->id;
        $this->full_name             = $member->full_name;
        $this->phone                 = $member->phone;
        $this->email                 = $member->email ?? '';
        $this->date_of_birth         = $member->date_of_birth?->format('Y-m-d') ?? '';
        $this->address               = $member->address ?? '';
        $this->emergency_contact_name  = $member->emergency_contact_name ?? '';
        $this->emergency_contact_phone = $member->emergency_contact_phone ?? '';
        $this->notes                 = $member->notes ?? '';
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->validate([
            'full_name'                => 'required|string|max:255',
            'phone'                    => 'required|string|max:30|unique:members,phone,' . $this->memberId,
            'email'                    => 'nullable|email|max:255',
            'date_of_birth'            => 'nullable|date|before:today',
            'address'                  => 'nullable|string|max:500',
            'emergency_contact_name'   => 'nullable|string|max:255',
            'emergency_contact_phone'  => 'nullable|string|max:30',
            'notes'                    => 'nullable|string|max:1000',
        ]);

        Member::whereKey($this->memberId)->update([
            'full_name'                => $this->full_name,
            'phone'                    => $this->phone,
            'email'                    => $this->email ?: null,
            'date_of_birth'            => $this->date_of_birth ?: null,
            'address'                  => $this->address ?: null,
            'emergency_contact_name'   => $this->emergency_contact_name ?: null,
            'emergency_contact_phone'  => $this->emergency_contact_phone ?: null,
            'notes'                    => $this->notes ?: null,
        ]);

        Flux::toast(variant: 'success', text: 'Member information updated.');
        $this->redirect(route('members.show', $this->memberId), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-3xl px-6 py-6 sm:px-8">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-3">
        <flux:button icon="arrow-left" variant="ghost" :href="route('members.show', $this->memberId)" wire:navigate size="sm" />
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">Edit Member</h1>
            <p class="mt-0.5 text-sm font-medium text-zinc-500">Update personal and contact information.</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Personal Information --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-4 text-base font-bold text-zinc-900 dark:text-white">Personal Information</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <flux:input
                        wire:model="full_name"
                        label="Full Name"
                        placeholder="Ahmad bin Abdullah"
                        required
                    />
                    @error('full_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <flux:input
                        wire:model="phone"
                        label="Phone Number"
                        placeholder="+60 11-1234 5678"
                        type="tel"
                        required
                    />
                    @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <flux:input
                        wire:model="email"
                        label="Email Address"
                        placeholder="ahmad@example.com"
                        type="email"
                    />
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <flux:input
                        wire:model="date_of_birth"
                        label="Date of Birth"
                        type="date"
                        :max="now()->subYears(10)->toDateString()"
                    />
                    @error('date_of_birth') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <flux:input wire:model="address" label="Address" placeholder="Full address" />
                    @error('address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Emergency Contact --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-1 text-base font-bold text-zinc-900 dark:text-white">Emergency Contact</h3>
            <p class="mb-4 text-sm text-zinc-500">Optional but recommended.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:input wire:model="emergency_contact_name" label="Contact Name" placeholder="Contact person name" />
                    @error('emergency_contact_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <flux:input wire:model="emergency_contact_phone" label="Contact Phone" placeholder="+60 11-9999 8888" type="tel" />
                    @error('emergency_contact_phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Notes --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:textarea
                wire:model="notes"
                label="Internal Notes"
                placeholder="Any notes about this member (visible to staff only)..."
                rows="3"
            />
            @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" :href="route('members.show', $this->memberId)" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                <span wire:loading.remove>Save Changes</span>
                <span wire:loading>Saving...</span>
            </flux:button>
        </div>

    </form>
</div>
