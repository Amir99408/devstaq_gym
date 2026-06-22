<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 antialiased">

        @php
            try { $portalSetting = \App\Models\GymSetting::current(); }
            catch (\Throwable) { $portalSetting = null; }
            $portalMember = auth()->user()?->member;
        @endphp

        {{-- Top Navigation --}}
        <header class="sticky top-0 z-10 border-b border-blue-100 bg-gradient-to-r from-blue-600 to-blue-500 shadow-sm">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3 sm:px-6">

                {{-- Logo + Gym Name --}}
                <a href="{{ route('portal.dashboard') }}" wire:navigate class="flex items-center gap-3">
                    @if($portalSetting?->logo_path)
                        <img src="{{ Storage::url($portalSetting->logo_path) }}"
                             alt="{{ $portalSetting->gym_name }}"
                             class="size-8 rounded-lg object-contain bg-white/20 p-1 ring-1 ring-white/30" />
                    @else
                        <div class="flex size-8 items-center justify-center rounded-lg bg-white/20 ring-1 ring-white/30">
                            <x-app-logo-icon class="size-4 fill-current text-white" />
                        </div>
                    @endif
                    <div>
                        <p class="text-sm font-bold text-white">{{ $portalSetting?->gym_name ?? config('app.name') }}</p>
                        <p class="text-xs text-blue-100">Member Portal</p>
                    </div>
                </a>

                {{-- Right side: nav + member menu --}}
                <div class="flex items-center gap-1">
                    <a href="{{ route('portal.dashboard') }}" wire:navigate
                       class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors
                           {{ request()->routeIs('portal.dashboard') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('portal.payments') }}" wire:navigate
                       class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors
                           {{ request()->routeIs('portal.payments') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}">
                        Payments
                    </a>

                    {{-- Member dropdown --}}
                    <flux:dropdown position="bottom" align="end">
                        <button class="ml-2 flex items-center gap-2 rounded-lg bg-white/20 px-3 py-1.5 text-sm font-medium text-white ring-1 ring-white/30 transition hover:bg-white/30">
                            <div class="flex size-6 items-center justify-center rounded-full bg-white/30 text-xs font-bold">
                                {{ mb_substr(auth()->user()->name, 0, 1) }}
                            </div>
                            <span class="hidden sm:block">{{ auth()->user()->name }}</span>
                            <flux:icon name="chevron-down" class="size-3.5" />
                        </button>
                        <flux:menu>
                            <div class="px-3 py-2">
                                <p class="text-sm font-medium text-zinc-900">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-zinc-500">{{ auth()->user()->email }}</p>
                                @if($portalMember)
                                    <flux:badge :color="$portalMember->status->color()" size="sm" class="mt-1">
                                        {{ $portalMember->status->label() }}
                                    </flux:badge>
                                @endif
                            </div>
                            <flux:menu.separator />
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                                    Log out
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </div>
        </header>

        {{-- Page Content --}}
        <main class="mx-auto max-w-4xl px-4 py-6 sm:px-6">
            {{ $slot }}
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
