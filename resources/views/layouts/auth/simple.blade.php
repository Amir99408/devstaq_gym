<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gradient-to-br from-blue-50 to-slate-100 antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-4">

                {{-- Gym logo & name --}}
                @php
                    try { $setting = \App\Models\GymSetting::current(); }
                    catch (\Throwable) { $setting = null; }
                @endphp
                <a href="{{ route('home') }}" wire:navigate class="flex flex-col items-center gap-3">
                    @if($setting?->logo_path)
                        <img src="{{ Storage::url($setting->logo_path) }}"
                             alt="{{ $setting->gym_name }}"
                             class="h-14 w-auto object-contain" />
                    @else
                        <div class="flex size-14 items-center justify-center rounded-2xl bg-blue-600 shadow-md">
                            <x-app-logo-icon class="size-8 fill-current text-white" />
                        </div>
                    @endif
                    <div class="text-center">
                        <p class="text-lg font-bold text-zinc-800">{{ $setting?->gym_name ?? config('app.name') }}</p>
                        @if($setting?->tagline)
                            <p class="text-xs text-zinc-500">{{ $setting->tagline }}</p>
                        @endif
                    </div>
                </a>

                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
