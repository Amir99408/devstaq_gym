<?php

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\RegistrationPayment;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app'), Title('Member Profile')] class extends Component {

    use WithFileUploads;

    #[Locked]
    public int $memberId;

    #[Validate('required|image|mimes:jpg,jpeg,png,webp|max:5120')]
    public $uploadFile = null;

    #[Validate('required|image|mimes:jpg,jpeg,png,webp|max:2048')]
    public $photoFile = null;

    // Login creation
    public string $loginEmail = '';
    public string $generatedPassword = '';

    public function mount(Member $member): void
    {
        $this->memberId = $member->id;
    }

    #[Computed]
    public function member(): Member
    {
        return Member::with([
            'user',
            'currentPlan.membershipPlan',
            'registrationPayment.verifiedBy',
            'monthlyPayments' => fn ($q) => $q->orderByDesc('billing_month'),
            'monthlyPayments.verifiedBy',
            'creator',
        ])->findOrFail($this->memberId);
    }

    public function openUploadPhoto(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->photoFile = null;
        $this->resetValidation('photoFile');
        Flux::modal('photo-upload')->show();
    }

    public function uploadPhoto(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->validateOnly('photoFile');

        $member = $this->member;

        if ($member->profile_photo_path) {
            Storage::disk('public')->delete($member->profile_photo_path);
        }

        $path = $this->photoFile->store('photos', 'public');
        Member::whereKey($this->memberId)->update(['profile_photo_path' => $path]);

        $this->photoFile = null;
        unset($this->member);
        Flux::modal('photo-upload')->close();
        Flux::toast(variant: 'success', text: 'Photo updated.');
    }

    public function removePhoto(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $member = $this->member;
        if ($member->profile_photo_path) {
            Storage::disk('public')->delete($member->profile_photo_path);
            Member::whereKey($this->memberId)->update(['profile_photo_path' => null]);
            unset($this->member);
            Flux::toast(variant: 'success', text: 'Photo removed.');
        }
    }

    public function changeStatus(string $status): void
    {
        abort_unless(auth()->user()->isOwner(), 403);

        Member::whereKey($this->memberId)->update(['status' => $status]);
        unset($this->member);
        Flux::toast(variant: 'success', text: 'Status updated.');
    }

    public function openCreateLogin(): void
    {
        abort_unless(auth()->user()->isOwner(), 403);
        $this->loginEmail = $this->member->email ?? '';
        $this->generatedPassword = '';
        $this->resetValidation('loginEmail');
        Flux::modal('create-login')->show();
    }

    public function createLogin(): void
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $this->validateOnly('loginEmail', [
            'loginEmail' => 'required|email|unique:users,email',
        ]);

        $password = Str::password(12);

        $user = User::create([
            'name'              => $this->member->full_name,
            'email'             => $this->loginEmail,
            'password'          => Hash::make($password),
            'role'              => UserRole::Member,
            'email_verified_at' => now(),
        ]);

        Member::whereKey($this->memberId)->update(['user_id' => $user->id]);
        unset($this->member);

        $this->generatedPassword = $password;
        Flux::modal('create-login')->close();
        Flux::modal('login-credentials')->show();
    }

    public function resetMemberPassword(): void
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $member = $this->member;
        abort_unless($member->user_id, 404);

        $password = Str::password(12);
        User::whereKey($member->user_id)->update(['password' => Hash::make($password)]);

        $this->loginEmail = $member->user->email;
        $this->generatedPassword = $password;
        Flux::modal('login-credentials')->show();
    }

    public function revokeLogin(): void
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $member = $this->member;
        if ($member->user_id) {
            User::destroy($member->user_id);
            Member::whereKey($this->memberId)->update(['user_id' => null]);
            unset($this->member);
            Flux::toast(variant: 'success', text: 'Member login revoked.');
        }
    }

    public function openUploadReg(): void
    {
        $this->uploadFile = null;
        $this->resetValidation('uploadFile');
        Flux::modal('reg-upload-show')->show();
    }

    public function submitRegUpload(): void
    {
        $this->validateOnly('uploadFile');

        $path = $this->uploadFile->store('screenshots', 'public');

        RegistrationPayment::where('member_id', $this->memberId)->update([
            'screenshot_path' => $path,
            'status'          => PaymentStatus::Submitted->value,
            'submitted_at'    => now(),
        ]);

        $this->uploadFile = null;
        unset($this->member);
        Flux::modal('reg-upload-show')->close();
        Flux::toast(variant: 'success', text: 'Screenshot submitted for verification.');
    }

    #[Computed]
    public function availableStatuses(): array
    {
        return collect(MemberStatus::cases())
            ->reject(fn ($s) => $s === $this->member->status)
            ->values()
            ->all();
    }
}; ?>

<div>
    <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6">

        {{-- Back nav --}}
        <div class="mb-6 flex items-center gap-3">
            <flux:button icon="arrow-left" variant="ghost" :href="route('members.index')" wire:navigate size="sm" />
            <flux:text class="text-sm text-zinc-500">Back to Members</flux:text>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Left: Member Card --}}
            <div class="lg:col-span-1">
                <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">

                    {{-- Avatar + Name --}}
                    <div class="flex flex-col items-center text-center">
                        <div class="relative">
                            @if($this->member->profile_photo_path)
                                <img src="{{ Storage::url($this->member->profile_photo_path) }}"
                                     alt="{{ $this->member->full_name }}"
                                     class="size-24 rounded-full object-cover ring-4 ring-white shadow-md" />
                            @else
                                <div class="flex size-24 items-center justify-center rounded-full bg-blue-100 text-3xl font-bold text-blue-600 ring-4 ring-white shadow-md">
                                    {{ mb_substr($this->member->full_name, 0, 1) }}
                                </div>
                            @endif
                            @if(auth()->user()->isAdmin())
                                <button wire:click="openUploadPhoto"
                                    class="absolute bottom-0 right-0 flex size-7 items-center justify-center rounded-full bg-blue-600 shadow-md ring-2 ring-white transition-colors hover:bg-blue-700">
                                    <flux:icon name="camera" class="size-3.5 text-white" />
                                </button>
                            @endif
                        </div>
                        @if($this->member->profile_photo_path && auth()->user()->isAdmin())
                            <button wire:click="removePhoto"
                                wire:confirm="Remove this member's photo?"
                                class="mt-1 text-xs text-zinc-400 hover:text-red-500 transition-colors">
                                Remove photo
                            </button>
                        @endif
                        <flux:heading class="mt-3" size="lg">{{ $this->member->full_name }}</flux:heading>
                        <flux:text class="mt-0.5 text-sm">{{ $this->member->registration_number }}</flux:text>
                        <div class="mt-2">
                            <flux:badge :color="$this->member->status->color()">
                                {{ $this->member->status->label() }}
                            </flux:badge>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <div class="flex items-center gap-2 text-sm">
                            <flux:icon name="phone" class="size-4 shrink-0 text-zinc-400" />
                            <span class="text-zinc-700 dark:text-zinc-300">{{ $this->member->phone }}</span>
                        </div>
                        @if ($this->member->email)
                            <div class="flex items-center gap-2 text-sm">
                                <flux:icon name="envelope" class="size-4 shrink-0 text-zinc-400" />
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $this->member->email }}</span>
                            </div>
                        @endif
                        @if ($this->member->date_of_birth)
                            <div class="flex items-center gap-2 text-sm">
                                <flux:icon name="cake" class="size-4 shrink-0 text-zinc-400" />
                                <span class="text-zinc-700 dark:text-zinc-300">
                                    {{ $this->member->date_of_birth->format('d M Y') }}
                                    ({{ $this->member->age() }} yrs)
                                </span>
                            </div>
                        @endif
                        @if ($this->member->address)
                            <div class="flex items-start gap-2 text-sm">
                                <flux:icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $this->member->address }}</span>
                            </div>
                        @endif
                        @if ($this->member->creator)
                            <div class="flex items-center gap-2 text-sm">
                                <flux:icon name="user-circle" class="size-4 shrink-0 text-zinc-400" />
                                <span class="text-zinc-500">Added by {{ $this->member->creator->name }}</span>
                            </div>
                        @endif
                        <div class="flex items-center gap-2 text-sm">
                            <flux:icon name="calendar" class="size-4 shrink-0 text-zinc-400" />
                            <span class="text-zinc-500">Joined {{ $this->member->created_at->format('d M Y') }}</span>
                        </div>
                    </div>

                    {{-- Emergency Contact --}}
                    @if ($this->member->emergency_contact_name)
                        <div class="mt-4 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                            <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-500">Emergency Contact</flux:text>
                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $this->member->emergency_contact_name }}</p>
                            @if ($this->member->emergency_contact_phone)
                                <p class="text-sm text-zinc-500">{{ $this->member->emergency_contact_phone }}</p>
                            @endif
                        </div>
                    @endif

                    {{-- Change Status --}}
                    @if ($this->availableStatuses)
                        <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                            <flux:text class="mb-2 text-xs font-medium uppercase tracking-wider text-zinc-500">Change Status</flux:text>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->availableStatuses as $status)
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        :color="$status->color()"
                                        wire:click="changeStatus('{{ $status->value }}')"
                                        wire:confirm="Set status to {{ $status->label() }}?"
                                    >
                                        → {{ $status->label() }}
                                    </flux:button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Member Login Access --}}
                    @if (auth()->user()->isOwner())
                        <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                            <flux:text class="mb-2 text-xs font-medium uppercase tracking-wider text-zinc-500">Portal Access</flux:text>
                            @if ($this->member->hasLogin())
                                <div class="mb-2 flex items-center gap-2 rounded-lg bg-green-50 px-3 py-2">
                                    <flux:icon name="check-circle" class="size-4 shrink-0 text-green-500" />
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-medium text-green-700">Login active</p>
                                        <p class="truncate text-xs text-green-600">{{ $this->member->user->email }}</p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="resetMemberPassword"
                                        wire:confirm="Generate a new password for this member?">
                                        Reset Password
                                    </flux:button>
                                    <flux:button size="sm" variant="ghost" color="red"
                                        wire:click="revokeLogin"
                                        wire:confirm="Revoke this member's login? They will no longer be able to access the portal.">
                                        Revoke
                                    </flux:button>
                                </div>
                            @else
                                <p class="mb-2 text-xs text-zinc-400">No portal login yet.</p>
                                <flux:button size="sm" variant="primary" wire:click="openCreateLogin">
                                    Create Login
                                </flux:button>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Notes --}}
                @if ($this->member->notes)
                    <div class="mt-4 rounded-xl border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-950">
                        <flux:text class="text-xs font-medium uppercase tracking-wider text-yellow-700 dark:text-yellow-400">Notes</flux:text>
                        <p class="mt-1 text-sm text-yellow-800 dark:text-yellow-300">{{ $this->member->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Right: Plan + Payment History --}}
            <div class="space-y-6 lg:col-span-2">

                {{-- Current Plan --}}
                @if ($this->member->currentPlan)
                    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="base" class="mb-4">Current Membership Plan</flux:heading>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-lg font-semibold text-zinc-900 dark:text-white">
                                    {{ $this->member->currentPlan->membershipPlan->name }}
                                </p>
                                <flux:text class="text-sm">
                                    Started {{ $this->member->currentPlan->start_date->format('d M Y') }}
                                </flux:text>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-zinc-500">Monthly Fee</p>
                                <p class="text-xl font-bold text-zinc-900 dark:text-white">
                                    RM {{ number_format($this->member->currentPlan->monthly_fee_amount, 2) }}
                                </p>
                            </div>
                        </div>

                        {{-- Registration Payment --}}
                        @if ($this->member->registrationPayment)
                            @php $reg = $this->member->registrationPayment; @endphp
                            <div class="mt-4 rounded-lg bg-zinc-50 px-4 py-3 dark:bg-zinc-800">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <flux:icon name="document-check" class="size-5 text-zinc-400" />
                                        <div>
                                            <p class="text-sm font-medium text-zinc-900 dark:text-white">Registration Fee</p>
                                            <p class="text-xs text-zinc-500">
                                                RM {{ number_format($reg->amount, 2) }}
                                                @if ($reg->verified_at) · Verified {{ $reg->verified_at->format('d M Y') }} @endif
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if ($reg->screenshot_path)
                                            <a href="{{ Storage::url($reg->screenshot_path) }}" target="_blank"
                                               class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400">
                                                <flux:icon name="photo" class="size-3.5" /> View proof
                                            </a>
                                        @endif
                                        <flux:badge :color="$reg->status->color()" size="sm">
                                            {{ $reg->status->label() }}
                                        </flux:badge>
                                    </div>
                                </div>
                                @if (in_array($reg->status, [App\Enums\PaymentStatus::Pending, App\Enums\PaymentStatus::Rejected]))
                                    <div class="mt-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                        <flux:button size="sm" wire:click="openUploadReg">
                                            Upload Payment Screenshot
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Payment History --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4 flex items-center justify-between">
                        <flux:heading size="base">Payment History</flux:heading>
                        <flux:text class="text-sm text-zinc-500">
                            {{ $this->member->monthlyPayments->count() }} month(s)
                        </flux:text>
                    </div>

                    @if ($this->member->monthlyPayments->isEmpty())
                        <div class="flex flex-col items-center py-10">
                            <flux:icon name="banknotes" class="size-10 text-zinc-300" />
                            <flux:text class="mt-3 text-sm">No monthly payment records yet.</flux:text>
                        </div>
                    @else
                        <div class="space-y-2">
                            @foreach ($this->member->monthlyPayments as $payment)
                                <div class="flex items-center justify-between rounded-lg border border-zinc-100 px-4 py-3 dark:border-zinc-800">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full
                                            {{ $payment->status->isPaid() ? 'bg-green-100 dark:bg-green-900' : 'bg-zinc-100 dark:bg-zinc-800' }}">
                                            <flux:icon
                                                :name="$payment->status->isPaid() ? 'check' : 'clock'"
                                                class="size-5 {{ $payment->status->isPaid() ? 'text-green-600 dark:text-green-400' : 'text-zinc-400' }}"
                                            />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-zinc-900 dark:text-white">
                                                {{ $payment->billing_month->format('F Y') }}
                                            </p>
                                            <p class="text-xs text-zinc-500">
                                                RM {{ number_format($payment->amount_due, 2) }}
                                                @if ($payment->verified_at)
                                                    · Verified by {{ $payment->verifiedBy?->name ?? '—' }}
                                                @elseif ($payment->submitted_at)
                                                    · Screenshot submitted
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    <flux:badge :color="$payment->status->color()" size="sm">
                                        {{ $payment->status->label() }}
                                    </flux:badge>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Photo upload modal --}}
    <flux:modal name="photo-upload" class="w-full max-w-md">
        <div class="p-6">
            <flux:heading size="lg">Upload Member Photo</flux:heading>
            <flux:text class="mt-1 text-sm">JPG, PNG, or WebP — max 2MB.</flux:text>
            <form wire:submit="uploadPhoto" class="mt-4 space-y-4">
                <input type="file" wire:model="photoFile" accept="image/*"
                    class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200" />
                @error('photoFile') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @if($photoFile)
                    <img src="{{ $photoFile->temporaryUrl() }}" class="mx-auto size-28 rounded-full object-cover ring-4 ring-blue-100" />
                @endif
                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" x-on:click="$flux.modal('photo-upload').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove>Upload Photo</span>
                        <span wire:loading>Uploading...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Create Login modal --}}
    <flux:modal name="create-login" class="w-full max-w-md">
        <div class="p-6">
            <flux:heading size="lg">Create Member Login</flux:heading>
            <flux:text class="mt-1 text-sm">A secure password will be generated. Share it with the member.</flux:text>
            <form wire:submit="createLogin" class="mt-5 space-y-4">
                <flux:input
                    wire:model="loginEmail"
                    label="Email Address"
                    type="email"
                    placeholder="member@example.com"
                    required
                />
                @error('loginEmail') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" x-on:click="$flux.modal('create-login').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Generate & Create</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Show generated credentials modal --}}
    <flux:modal name="login-credentials" class="w-full max-w-md">
        <div class="p-6">
            <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-green-100">
                <flux:icon name="key" class="size-6 text-green-600" />
            </div>
            <flux:heading size="lg">Login Created!</flux:heading>
            <flux:text class="mt-1 text-sm">Share these credentials with the member. The password will not be shown again.</flux:text>
            <div class="mt-4 space-y-3">
                <div class="rounded-lg bg-zinc-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">Email</p>
                    <p class="mt-1 font-mono text-sm font-semibold text-zinc-900">{{ $loginEmail }}</p>
                </div>
                <div class="rounded-lg bg-blue-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wider text-blue-500">Password (shown once)</p>
                    <p class="mt-1 font-mono text-lg font-bold tracking-widest text-blue-700">{{ $generatedPassword }}</p>
                </div>
                <p class="text-xs text-zinc-400">Member should change their password after first login via the portal settings.</p>
            </div>
            <div class="mt-5 flex justify-end">
                <flux:button variant="primary" x-on:click="$flux.modal('login-credentials').close()">Done</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Registration screenshot upload modal --}}
    <flux:modal name="reg-upload-show" class="w-full max-w-md">
        <div class="p-6">
            <flux:heading size="lg">Upload Registration Payment Screenshot</flux:heading>
            <flux:text class="mt-1">JPG, PNG, or WebP — max 5MB.</flux:text>
            <form wire:submit="submitRegUpload" class="mt-4 space-y-4">
                <input type="file" wire:model="uploadFile" accept="image/*"
                    class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-300" />
                @error('uploadFile') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @if ($uploadFile)
                    <img src="{{ $uploadFile->temporaryUrl() }}" class="max-h-40 w-full rounded object-contain" />
                @endif
                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" x-on:click="$flux.modal('reg-upload-show').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Submit Screenshot</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

</div>
