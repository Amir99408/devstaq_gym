<?php

use App\Models\GymSetting;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app'), Title('Gym Settings')] class extends Component {

    use WithFileUploads;

    public string $tab = 'gym';

    // ─── Gym Identity ─────────────────────────────────────────────────────────
    public string $gym_name = '';
    public string $tagline = '';
    public string $about = '';

    // ─── Contact ──────────────────────────────────────────────────────────────
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public string $whatsapp = '';

    // ─── Social ───────────────────────────────────────────────────────────────
    public string $facebook_url = '';
    public string $instagram_url = '';

    // ─── Branding ─────────────────────────────────────────────────────────────
    public string $primary_color = '#2563eb';
    public string $secondary_color = '#1d4ed8';

    // ─── SEO ──────────────────────────────────────────────────────────────────
    public string $meta_title = '';
    public string $meta_description = '';

    // ─── File uploads ─────────────────────────────────────────────────────────
    public $logoFile = null;
    public $heroFile = null;
    public $faviconFile = null;

    // ─── Account ──────────────────────────────────────────────────────────────
    public string $new_email = '';
    public string $email_password = '';
    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $s = GymSetting::current();

        $this->gym_name         = $s->gym_name ?? '';
        $this->tagline          = $s->tagline ?? '';
        $this->about            = $s->about ?? '';
        $this->address          = $s->address ?? '';
        $this->phone            = $s->phone ?? '';
        $this->email            = $s->email ?? '';
        $this->whatsapp         = $s->whatsapp ?? '';
        $this->facebook_url     = $s->facebook_url ?? '';
        $this->instagram_url    = $s->instagram_url ?? '';
        $this->primary_color    = $s->primary_color ?? '#2563eb';
        $this->secondary_color  = $s->secondary_color ?? '#1d4ed8';
        $this->meta_title       = $s->meta_title ?? '';
        $this->meta_description = $s->meta_description ?? '';

        $this->new_email = auth()->user()->email;
    }

    #[Computed]
    public function setting(): GymSetting
    {
        return GymSetting::current();
    }

    // ─── Save Gym Settings ────────────────────────────────────────────────────

    public function save(): void
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $this->validate([
            'gym_name'        => 'required|string|max:100',
            'tagline'         => 'nullable|string|max:200',
            'about'           => 'nullable|string|max:2000',
            'address'         => 'nullable|string|max:200',
            'phone'           => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:100',
            'whatsapp'        => 'nullable|string|max:30',
            'facebook_url'    => 'nullable|url|max:255',
            'instagram_url'   => 'nullable|url|max:255',
            'primary_color'   => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'meta_title'      => 'nullable|string|max:70',
            'meta_description'=> 'nullable|string|max:160',
            'logoFile'        => 'nullable|image|mimes:jpg,jpeg,png,svg,webp|max:2048',
            'heroFile'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'faviconFile'     => 'nullable|mimes:jpg,jpeg,png,webp|max:512',
        ]);

        $data = [
            'gym_name'         => $this->gym_name,
            'tagline'          => $this->tagline ?: null,
            'about'            => $this->about ?: null,
            'address'          => $this->address ?: null,
            'phone'            => $this->phone ?: null,
            'email'            => $this->email ?: null,
            'whatsapp'         => $this->whatsapp ?: null,
            'facebook_url'     => $this->facebook_url ?: null,
            'instagram_url'    => $this->instagram_url ?: null,
            'primary_color'    => $this->primary_color ?: '#2563eb',
            'secondary_color'  => $this->secondary_color ?: '#1d4ed8',
            'meta_title'       => $this->meta_title ?: null,
            'meta_description' => $this->meta_description ?: null,
        ];

        if ($this->logoFile) {
            $old = $this->setting->logo_path;
            $data['logo_path'] = $this->logoFile->store('gym', 'public');
            if ($old) Storage::disk('public')->delete($old);
            $this->logoFile = null;
        }

        if ($this->heroFile) {
            $old = $this->setting->hero_image_path;
            $data['hero_image_path'] = $this->heroFile->store('gym', 'public');
            if ($old) Storage::disk('public')->delete($old);
            $this->heroFile = null;
        }

        if ($this->faviconFile) {
            $old = $this->setting->favicon_path;
            $data['favicon_path'] = $this->faviconFile->store('gym', 'public');
            if ($old) Storage::disk('public')->delete($old);
            $this->faviconFile = null;
        }

        GymSetting::current()->update($data);
        unset($this->setting);

        Flux::toast(variant: 'success', text: 'Settings saved.');
    }

    public function removeLogo(): void
    {
        abort_unless(auth()->user()->isOwner(), 403);
        $s = GymSetting::first();
        if ($s?->logo_path) {
            Storage::disk('public')->delete($s->logo_path);
            $s->update(['logo_path' => null]);
            unset($this->setting);
        }
    }

    public function removeHero(): void
    {
        abort_unless(auth()->user()->isOwner(), 403);
        $s = GymSetting::first();
        if ($s?->hero_image_path) {
            Storage::disk('public')->delete($s->hero_image_path);
            $s->update(['hero_image_path' => null]);
            unset($this->setting);
        }
    }

    public function removeFavicon(): void
    {
        abort_unless(auth()->user()->isOwner(), 403);
        $s = GymSetting::first();
        if ($s?->favicon_path) {
            Storage::disk('public')->delete($s->favicon_path);
            $s->update(['favicon_path' => null]);
            unset($this->setting);
        }
    }

    // ─── Account: Update Email ────────────────────────────────────────────────

    public function updateEmail(): void
    {
        $this->validate([
            'new_email'      => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore(auth()->id())],
            'email_password' => ['required', 'current_password'],
        ]);

        auth()->user()->update(['email' => $this->new_email]);
        $this->email_password = '';

        Flux::toast(variant: 'success', text: 'Email updated successfully.');
    }

    // ─── Account: Update Password ─────────────────────────────────────────────

    public function updatePassword(): void
    {
        $this->validate([
            'current_password'         => ['required', 'current_password'],
            'new_password'             => ['required', 'min:8', 'confirmed'],
            'new_password_confirmation'=> ['required'],
        ]);

        auth()->user()->update(['password' => Hash::make($this->new_password)]);

        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';

        Flux::toast(variant: 'success', text: 'Password updated successfully.');
    }
}; ?>

<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Settings</flux:heading>
            <flux:text class="mt-1 text-zinc-500">Manage your gym and account settings.</flux:text>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-6 flex gap-1 rounded-xl border border-zinc-200 bg-zinc-100 p-1">
        <button wire:click="$set('tab', 'gym')" type="button"
                class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all
                    {{ $tab === 'gym' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-700' }}">
            <flux:icon name="building-storefront" class="size-4" />
            Gym Settings
        </button>
        <button wire:click="$set('tab', 'account')" type="button"
                class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all
                    {{ $tab === 'account' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-700' }}">
            <flux:icon name="user-circle" class="size-4" />
            Account
        </button>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- Tab: Gym Settings                                                       --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}

    @if($tab === 'gym')
    <form wire:submit="save" class="space-y-6">

        {{-- ─── Identity ─── --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6">
            <flux:heading size="base" class="mb-5">Gym Identity</flux:heading>
            <div class="space-y-4">

                <flux:field>
                    <flux:label>Gym Name <span class="text-red-500 ml-0.5">*</span></flux:label>
                    <flux:input wire:model="gym_name" placeholder="PowerFit Gym" />
                    <flux:error name="gym_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Tagline</flux:label>
                    <flux:input wire:model="tagline" placeholder="Train Hard. Live Fit." />
                    <flux:description>Short motto shown in the hero section.</flux:description>
                    <flux:error name="tagline" />
                </flux:field>

                <flux:field>
                    <flux:label>About</flux:label>
                    <flux:textarea wire:model="about" rows="4" placeholder="Tell visitors about your gym, facilities, and mission..." />
                    <flux:error name="about" />
                </flux:field>
            </div>
        </div>

        {{-- ─── Branding / Images ─── --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6">
            <flux:heading size="base" class="mb-5">Branding & Images</flux:heading>
            <div class="space-y-6">

                {{-- Brand Colors --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Primary Color</flux:label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model.live="primary_color" value="{{ $primary_color }}"
                                class="size-10 cursor-pointer rounded border border-zinc-300 p-0.5" />
                            <flux:input wire:model.live="primary_color" class="font-mono uppercase" maxlength="7" />
                        </div>
                        <flux:error name="primary_color" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Secondary / Dark Color</flux:label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model.live="secondary_color" value="{{ $secondary_color }}"
                                class="size-10 cursor-pointer rounded border border-zinc-300 p-0.5" />
                            <flux:input wire:model.live="secondary_color" class="font-mono uppercase" maxlength="7" />
                        </div>
                        <flux:error name="secondary_color" />
                    </flux:field>
                </div>

                {{-- Color Preview --}}
                <div class="flex gap-3">
                    <div class="flex-1 rounded-lg py-3 text-center text-sm font-semibold text-white shadow-sm transition-all"
                         style="background-color: {{ $primary_color }}">Primary</div>
                    <div class="flex-1 rounded-lg py-3 text-center text-sm font-semibold text-white shadow-sm transition-all"
                         style="background-color: {{ $secondary_color }}">Secondary</div>
                </div>

                {{-- Logo --}}
                <flux:field>
                    <flux:label>Gym Logo</flux:label>
                    @if ($this->setting->logo_path && ! $logoFile)
                        <div class="mb-3 flex items-center gap-4">
                            <img src="{{ Storage::url($this->setting->logo_path) }}" alt="Logo" class="h-16 w-auto rounded-lg border border-zinc-200 object-contain p-1" />
                            <flux:button size="sm" variant="ghost" wire:click="removeLogo" wire:confirm="Remove logo?">Remove</flux:button>
                        </div>
                    @endif
                    @if ($logoFile)
                        <div class="mb-3">
                            <img src="{{ $logoFile->temporaryUrl() }}" class="h-16 w-auto rounded-lg border border-zinc-200 object-contain p-1" />
                        </div>
                    @endif
                    <input type="file" wire:model="logoFile" accept="image/*"
                        class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200" />
                    <flux:description>PNG or SVG recommended. Max 2MB.</flux:description>
                    <flux:error name="logoFile" />
                </flux:field>

                {{-- Favicon --}}
                <flux:field>
                    <flux:label>Favicon</flux:label>
                    @if ($this->setting->favicon_path && ! $faviconFile)
                        <div class="mb-3 flex items-center gap-4">
                            <img src="{{ Storage::url($this->setting->favicon_path) }}" alt="Favicon" class="size-10 rounded border border-zinc-200 object-contain p-0.5" />
                            <flux:button size="sm" variant="ghost" wire:click="removeFavicon" wire:confirm="Remove favicon?">Remove</flux:button>
                        </div>
                    @endif
                    @if ($faviconFile)
                        <div class="mb-3">
                            <img src="{{ $faviconFile->temporaryUrl() }}" class="size-10 rounded border border-zinc-200 object-contain p-0.5" />
                        </div>
                    @endif
                    <input type="file" wire:model="faviconFile" accept="image/png,image/jpeg,image/webp"
                        class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200" />
                    <flux:description>PNG recommended, 32×32 or 64×64 px. Max 512KB. JPG and WebP also accepted.</flux:description>
                    <flux:error name="faviconFile" />
                </flux:field>

                {{-- Hero Image --}}
                <flux:field>
                    <flux:label>Hero / Banner Image</flux:label>
                    @if ($this->setting->hero_image_path && ! $heroFile)
                        <div class="mb-3">
                            <img src="{{ Storage::url($this->setting->hero_image_path) }}" alt="Hero" class="max-h-36 w-full rounded-lg border border-zinc-200 object-cover" />
                            <flux:button size="sm" variant="ghost" wire:click="removeHero" wire:confirm="Remove hero image?" class="mt-2">Remove</flux:button>
                        </div>
                    @endif
                    @if ($heroFile)
                        <div class="mb-3">
                            <img src="{{ $heroFile->temporaryUrl() }}" class="max-h-36 w-full rounded-lg border border-zinc-200 object-cover" />
                        </div>
                    @endif
                    <input type="file" wire:model="heroFile" accept="image/*"
                        class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200" />
                    <flux:description>Wide landscape image. Max 10MB.</flux:description>
                    <flux:error name="heroFile" />
                </flux:field>
            </div>
        </div>

        {{-- ─── Contact ─── --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6">
            <flux:heading size="base" class="mb-5">Contact Details</flux:heading>
            <div class="space-y-4">

                <flux:field>
                    <flux:label>Address</flux:label>
                    <flux:textarea wire:model="address" rows="2" placeholder="123 Jalan Gym, Kuala Lumpur" />
                    <flux:error name="address" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Phone</flux:label>
                        <flux:input wire:model="phone" type="tel" placeholder="+60 12-345 6789" />
                        <flux:error name="phone" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Email</flux:label>
                        <flux:input wire:model="email" type="email" placeholder="info@gym.com" />
                        <flux:error name="email" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>WhatsApp Number</flux:label>
                    <flux:input wire:model="whatsapp" placeholder="60123456789" />
                    <flux:description>Digits only with country code — e.g. 60123456789.</flux:description>
                    <flux:error name="whatsapp" />
                </flux:field>
            </div>
        </div>

        {{-- ─── Social ─── --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6">
            <flux:heading size="base" class="mb-5">Social Media</flux:heading>
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Facebook URL</flux:label>
                    <flux:input wire:model="facebook_url" type="url" placeholder="https://facebook.com/yourgym" />
                    <flux:error name="facebook_url" />
                </flux:field>
                <flux:field>
                    <flux:label>Instagram URL</flux:label>
                    <flux:input wire:model="instagram_url" type="url" placeholder="https://instagram.com/yourgym" />
                    <flux:error name="instagram_url" />
                </flux:field>
            </div>
        </div>

        {{-- ─── SEO ─── --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6">
            <flux:heading size="base" class="mb-1">SEO / Meta</flux:heading>
            <flux:text class="mb-5 text-sm text-zinc-500">Controls how your gym appears in Google search results.</flux:text>
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Page Title</flux:label>
                    <flux:input wire:model="meta_title" placeholder="PowerFit Gym — Kuala Lumpur's Premier Gym" maxlength="70" />
                    <flux:description>Keep under 60 characters for best results.</flux:description>
                    <flux:error name="meta_title" />
                </flux:field>
                <flux:field>
                    <flux:label>Meta Description</flux:label>
                    <flux:textarea wire:model="meta_description" rows="2" placeholder="Join PowerFit Gym — the best gym in Kuala Lumpur." maxlength="160" />
                    <flux:description>Keep under 155 characters.</flux:description>
                    <flux:error name="meta_description" />
                </flux:field>
            </div>
        </div>

        {{-- Save --}}
        <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-white px-6 py-4">
            <flux:text class="text-sm text-zinc-500">Changes are applied immediately.</flux:text>
            <flux:button type="submit" variant="primary" icon="check">Save Settings</flux:button>
        </div>

    </form>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- Tab: Account                                                            --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}

    @if($tab === 'account')
    <div class="space-y-6">

        {{-- Current account info --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6">
            <div class="flex items-center gap-4">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-blue-100">
                    <flux:icon name="user" class="size-7 text-blue-600" />
                </div>
                <div>
                    <p class="text-base font-semibold text-zinc-900">{{ auth()->user()->name }}</p>
                    <p class="text-sm text-zinc-500">{{ auth()->user()->email }}</p>
                    <flux:badge size="sm" color="blue" class="mt-1">{{ auth()->user()->role->label() }}</flux:badge>
                </div>
            </div>
        </div>

        {{-- Change Email --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6">
            <flux:heading size="base" class="mb-1">Change Email Address</flux:heading>
            <flux:text class="mb-5 text-sm text-zinc-500">Update the email address used to log in.</flux:text>

            <form wire:submit="updateEmail" class="space-y-4">
                <flux:field>
                    <flux:label>New Email Address</flux:label>
                    <flux:input wire:model="new_email" type="email" required autocomplete="email" />
                    <flux:error name="new_email" />
                </flux:field>
                <flux:field>
                    <flux:label>Confirm with Current Password</flux:label>
                    <flux:input wire:model="email_password" type="password" required autocomplete="current-password" viewable />
                    <flux:error name="email_password" />
                </flux:field>
                <flux:button type="submit" variant="primary" icon="envelope">Update Email</flux:button>
            </form>
        </div>

        {{-- Change Password --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6">
            <flux:heading size="base" class="mb-1">Change Password</flux:heading>
            <flux:text class="mb-5 text-sm text-zinc-500">Use a strong password you don't use elsewhere.</flux:text>

            <form wire:submit="updatePassword" class="space-y-4">
                <flux:field>
                    <flux:label>Current Password</flux:label>
                    <flux:input wire:model="current_password" type="password" required autocomplete="current-password" viewable />
                    <flux:error name="current_password" />
                </flux:field>
                <flux:field>
                    <flux:label>New Password</flux:label>
                    <flux:input wire:model="new_password" type="password" required autocomplete="new-password" viewable />
                    <flux:error name="new_password" />
                </flux:field>
                <flux:field>
                    <flux:label>Confirm New Password</flux:label>
                    <flux:input wire:model="new_password_confirmation" type="password" required autocomplete="new-password" viewable />
                    <flux:error name="new_password_confirmation" />
                </flux:field>
                <flux:button type="submit" variant="primary" icon="lock-closed">Update Password</flux:button>
            </form>
        </div>

    </div>
    @endif

</div>
