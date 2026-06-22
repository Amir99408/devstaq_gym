<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-blue-100 bg-gradient-to-b from-blue-50 to-slate-50">

            {{-- Branded header --}}
            @php
                try { $sidebarSetting = \App\Models\GymSetting::current(); }
                catch (\Throwable) { $sidebarSetting = null; }
            @endphp
            <flux:sidebar.header class="!p-0">
                <div class="flex w-full items-center justify-between bg-gradient-to-br from-blue-600 to-blue-500 px-4 py-4">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-3">
                        @if($sidebarSetting?->logo_path)
                            <img src="{{ Storage::url($sidebarSetting->logo_path) }}"
                                 alt="{{ $sidebarSetting->gym_name }}"
                                 class="size-9 shrink-0 rounded-xl object-contain bg-white/20 p-1 ring-1 ring-white/30" />
                        @else
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/20 ring-1 ring-white/30">
                                <x-app-logo-icon class="size-5 fill-current text-white" />
                            </div>
                        @endif
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold leading-tight text-white">
                                {{ $sidebarSetting?->gym_name ?? config('app.name') }}
                            </p>
                            <p class="text-xs text-blue-100">Management System</p>
                        </div>
                    </a>
                    <flux:sidebar.collapse class="lg:hidden !text-white/80 hover:!text-white" />
                </div>
            </flux:sidebar.header>

            <flux:sidebar.nav class="px-2 py-3">

                <flux:sidebar.group :heading="__('Overview')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Members')" class="grid">
                    <flux:sidebar.item icon="users" :href="route('members.index')" :current="request()->routeIs('members.index')" wire:navigate>
                        {{ __('All Members') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-plus" :href="route('members.create')" :current="request()->routeIs('members.create')" wire:navigate>
                        {{ __('Add Member') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Payments')" class="grid">
                    <flux:sidebar.item icon="clock" :href="route('payments.pending')" :current="request()->routeIs('payments.pending')" wire:navigate>
                        {{ __('Pending') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="calendar" :href="route('payments.monthly')" :current="request()->routeIs('payments.monthly')" wire:navigate>
                        {{ __('Monthly Fees') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="document-check" :href="route('payments.registrations')" :current="request()->routeIs('payments.registrations')" wire:navigate>
                        {{ __('Registration Fees') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Setup')" class="grid">
                    <flux:sidebar.item icon="tag" :href="route('plans.index')" :current="request()->routeIs('plans.*')" wire:navigate>
                        {{ __('Membership Plans') }}
                    </flux:sidebar.item>
                    @if(auth()->user()->isOwner())
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('gym.settings')" :current="request()->routeIs('gym.settings')" wire:navigate>
                            {{ __('Gym Settings') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

            </flux:sidebar.nav>

            <flux:spacer />

            {{-- View Public Page footer card --}}
            <div class="mx-3 mb-3 overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-br from-blue-100 to-indigo-100">
                <a href="{{ route('home') }}" target="_blank"
                   class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-blue-100/50">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-500 shadow-sm">
                        <flux:icon name="arrow-top-right-on-square" class="size-4 text-white" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-blue-700">Public Page</p>
                        <p class="text-xs text-blue-400">View your gym site</p>
                    </div>
                </a>
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
