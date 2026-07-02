<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>{{ $setting->meta_title ?: $setting->gym_name . ' — ' . ($setting->tagline ?: 'Welcome') }}</title>
    @if ($setting->meta_description)
        <meta name="description" content="{{ $setting->meta_description }}" />
    @endif

    <link rel="icon" href="/favicon.ico" sizes="any" />
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root { --brand: {{ $setting->primary_color }}; --brand-dark: {{ $setting->secondary_color }}; }
        .btn-brand { background-color: var(--brand); color: #fff; }
        .btn-brand:hover { background-color: var(--brand-dark); }
        .text-brand { color: var(--brand); }
        .border-brand { border-color: var(--brand); }
        .bg-brand { background-color: var(--brand); }
        .bg-brand-dark { background-color: var(--brand-dark); }
        html { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-white text-zinc-900 antialiased">

    {{-- ───── Navbar ───── --}}
    <nav class="fixed inset-x-0 top-0 z-50 border-b border-zinc-100 bg-white/95 backdrop-blur-sm">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">

            <div class="flex items-center gap-3">
                @if ($setting->logo_path)
                    <img src="{{ Storage::url($setting->logo_path) }}" alt="{{ $setting->gym_name }}" class="h-8 w-auto" />
                @else
                    <div class="flex size-8 items-center justify-center rounded-lg bg-brand text-white text-xs font-black">
                        {{ mb_substr($setting->gym_name, 0, 2) }}
                    </div>
                @endif
                <span class="text-lg font-bold tracking-tight text-zinc-900">{{ $setting->gym_name }}</span>
            </div>

            <div class="hidden items-center gap-8 text-sm font-medium text-zinc-600 md:flex">
                <a href="#about" class="transition-colors hover:text-brand">About</a>
                <a href="#plans" class="transition-colors hover:text-brand">Plans</a>
                @if ($setting->google_maps_embed_url)
                    <a href="#location" class="transition-colors hover:text-brand">Location</a>
                @endif
                <a href="#contact" class="transition-colors hover:text-brand">Contact</a>
            </div>

            <div class="flex items-center gap-3">
                @if ($setting->whatsapp)
                    @php $wa = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $setting->whatsapp); @endphp
                    <a href="{{ $wa }}" target="_blank"
                       class="hidden rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-green-600 sm:block">
                        WhatsApp Us
                    </a>
                @endif
                <a href="{{ route('login') }}"
                   class="text-sm font-medium text-zinc-500 transition-colors hover:text-zinc-900">
                    Login
                </a>
            </div>
        </div>
    </nav>

    {{-- ───── Hero ───── --}}
    <section id="hero" class="relative flex min-h-screen items-center justify-center overflow-hidden pt-16">

        {{-- Background: hero image or brand gradient --}}
        @if ($setting->hero_image_path)
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat"
                 style="background-image: url('{{ Storage::url($setting->hero_image_path) }}')"></div>
            <div class="absolute inset-0 bg-black/60"></div>
        @else
            <div class="absolute inset-0" style="background: linear-gradient(135deg, {{ $setting->primary_color }} 0%, {{ $setting->secondary_color }} 100%)"></div>
            {{-- Decorative geometric shapes --}}
            <div class="absolute -right-40 -top-40 size-[600px] rounded-full opacity-10" style="background: white"></div>
            <div class="absolute -bottom-40 -left-40 size-[400px] rounded-full opacity-10" style="background: white"></div>
        @endif

        <div class="relative mx-auto max-w-4xl px-4 py-24 text-center text-white sm:px-6">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-sm font-medium text-white/80 backdrop-blur-sm">
                <span class="size-2 rounded-full bg-green-400"></span>
                Now accepting new members
            </div>

            <h1 class="mt-6 text-5xl font-black leading-tight tracking-tight sm:text-6xl md:text-7xl">
                {{ $setting->gym_name }}
            </h1>

            @if ($setting->tagline)
                <p class="mt-4 text-xl text-white/80 sm:text-2xl">{{ $setting->tagline }}</p>
            @endif

            <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <a href="#plans"
                   class="inline-flex items-center gap-2 rounded-xl px-8 py-4 text-lg font-bold text-zinc-900 shadow-lg transition-all hover:shadow-xl hover:-translate-y-0.5 bg-white">
                    View Membership Plans
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </a>
                @if ($setting->whatsapp)
                    <a href="{{ $wa ?? '#' }}" target="_blank"
                       class="inline-flex items-center gap-2 rounded-xl border-2 border-white/50 bg-white/10 px-8 py-4 text-lg font-bold text-white backdrop-blur-sm transition-all hover:bg-white/20">
                        <svg class="size-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Chat on WhatsApp
                    </a>
                @endif
            </div>

            {{-- Quick stats --}}
            @if ($activeMembers > 0)
                <div class="mt-16 flex items-center justify-center gap-8 border-t border-white/20 pt-8">
                    <div class="text-center">
                        <p class="text-3xl font-black">{{ $activeMembers }}+</p>
                        <p class="text-sm text-white/60">Active Members</p>
                    </div>
                    <div class="h-8 w-px bg-white/20"></div>
                    <div class="text-center">
                        <p class="text-3xl font-black">{{ $plans->count() }}</p>
                        <p class="text-sm text-white/60">Membership Plans</p>
                    </div>
                    @if ($setting->phone)
                        <div class="hidden h-8 w-px bg-white/20 sm:block"></div>
                        <div class="hidden text-center sm:block">
                            <p class="text-3xl font-black">24/7</p>
                            <p class="text-sm text-white/60">Support</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Scroll indicator --}}
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce text-white/60">
            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </section>

    {{-- ───── About ───── --}}
    @if ($setting->about)
        <section id="about" class="bg-white py-20 sm:py-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-brand">About Us</p>
                        <h2 class="mt-2 text-4xl font-black leading-tight text-zinc-900">
                            More than just a gym
                        </h2>
                        <p class="mt-6 text-lg leading-relaxed text-zinc-600">{{ $setting->about }}</p>

                        @if ($setting->phone || $setting->address)
                            <div class="mt-8 space-y-3">
                                @if ($setting->phone)
                                    <div class="flex items-center gap-3 text-zinc-600">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100">
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                            </svg>
                                        </div>
                                        <span>{{ $setting->phone }}</span>
                                    </div>
                                @endif
                                @if ($setting->address)
                                    <div class="flex items-start gap-3 text-zinc-600">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100">
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                        </div>
                                        <span>{{ $setting->address }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        @foreach ([['🏋️', 'Premium Equipment', 'Top-of-the-line machines and free weights'], ['👥', 'Expert Trainers', 'Certified coaches to guide your journey'], ['📅', 'Flexible Hours', 'Open early to late to fit your schedule'], ['🎯', 'Real Results', 'Programs designed to get you to your goals']] as $feat)
                            <div class="rounded-2xl border border-zinc-100 bg-zinc-50 p-5">
                                <span class="text-3xl">{{ $feat[0] }}</span>
                                <p class="mt-3 font-semibold text-zinc-900">{{ $feat[1] }}</p>
                                <p class="mt-1 text-sm text-zinc-500">{{ $feat[2] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ───── Plans ───── --}}
    <section id="plans" class="bg-zinc-50 py-20 sm:py-28">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">

            <div class="text-center">
                <p class="text-sm font-semibold uppercase tracking-wider text-brand">Membership</p>
                <h2 class="mt-2 text-4xl font-black text-zinc-900">Choose your plan</h2>
                <p class="mx-auto mt-4 max-w-xl text-lg text-zinc-500">
                    Flexible plans designed for every goal and budget. Join today and start your transformation.
                </p>
            </div>

            @if ($plans->isEmpty())
                <div class="mt-12 rounded-2xl border border-dashed border-zinc-300 py-20 text-center">
                    <p class="text-zinc-400">Plans coming soon. Contact us for details.</p>
                </div>
            @else
                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($plans as $i => $plan)
                        @php $featured = ($i === 1 && $plans->count() > 1) || $plans->count() === 1; @endphp
                        <div class="relative flex flex-col overflow-hidden rounded-2xl border {{ $featured ? 'border-transparent shadow-2xl scale-105 ring-2' : 'border-zinc-200 bg-white shadow-sm' }}"
                             style="{{ $featured ? 'ring-color: var(--brand);' : '' }}">

                            @if ($featured)
                                <div class="py-2.5 text-center text-sm font-bold text-white" style="background-color: var(--brand)">
                                    Most Popular
                                </div>
                            @endif

                            <div class="flex flex-1 flex-col p-7 {{ $featured ? 'bg-white' : '' }}">
                                <div>
                                    <h3 class="text-xl font-bold text-zinc-900">{{ $plan->name }}</h3>
                                    @if ($plan->description)
                                        <p class="mt-1.5 text-sm text-zinc-500">{{ $plan->description }}</p>
                                    @endif
                                </div>

                                {{-- Pricing --}}
                                <div class="mt-6 border-t border-zinc-100 pt-6">
                                    <div class="flex items-end gap-1">
                                        <span class="text-4xl font-black text-zinc-900">
                                            RM {{ number_format($plan->monthly_fee, 0) }}
                                        </span>
                                        <span class="mb-1.5 text-zinc-400">/month</span>
                                    </div>
                                    @if ($plan->registration_fee > 0)
                                        <p class="mt-1 text-sm text-zinc-500">
                                            + RM {{ number_format($plan->registration_fee, 2) }} registration (one-time)
                                        </p>
                                    @else
                                        <p class="mt-1 text-sm text-green-600 font-medium">No registration fee</p>
                                    @endif
                                </div>

                                {{-- Features --}}
                                @if ($plan->features)
                                    <ul class="mt-6 flex-1 space-y-2.5">
                                        @foreach ($plan->features as $feature)
                                            <li class="flex items-start gap-2.5 text-sm text-zinc-600">
                                                <svg class="mt-0.5 size-4 shrink-0 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                {{ $feature }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                {{-- CTA --}}
                                <div class="mt-8">
                                    @if ($setting->whatsapp)
                                        <a href="{{ $wa ?? '#' }}"
                                           target="_blank"
                                           class="block w-full rounded-xl py-3.5 text-center text-sm font-bold transition-all
                                               {{ $featured ? 'btn-brand hover:-translate-y-0.5 hover:shadow-lg' : 'border-2 border-zinc-200 text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50' }}">
                                            Join {{ $plan->name }} Plan
                                        </a>
                                    @else
                                        <a href="#contact"
                                           class="block w-full rounded-xl py-3.5 text-center text-sm font-bold transition-all
                                               {{ $featured ? 'btn-brand hover:-translate-y-0.5 hover:shadow-lg' : 'border-2 border-zinc-200 text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50' }}">
                                            Join {{ $plan->name }} Plan
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="mt-8 text-center text-sm text-zinc-400">
                Not sure which plan is right for you?
                @if ($setting->whatsapp)
                    <a href="{{ $wa ?? '#' }}" target="_blank" class="font-medium text-brand hover:underline">Chat with us</a> — we'll help you decide.
                @else
                    <a href="#contact" class="font-medium text-brand hover:underline">Contact us</a> — we'll help you decide.
                @endif
            </p>
        </div>
    </section>

    {{-- ───── Location Map ───── --}}
    @if ($setting->google_maps_embed_url)
        <section id="location" class="bg-white py-20 sm:py-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-brand">Find Us</p>
                    <h2 class="mt-2 text-4xl font-black text-zinc-900">Our Location</h2>
                    @if ($setting->address)
                        <p class="mx-auto mt-3 max-w-xl text-lg text-zinc-500">{{ $setting->address }}</p>
                    @endif
                </div>
                <div class="mt-10 overflow-hidden rounded-2xl border border-zinc-200 shadow-lg">
                    <iframe
                        src="{{ $setting->google_maps_embed_url }}"
                        width="100%"
                        height="450"
                        style="border:0;"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        class="w-full"
                    ></iframe>
                </div>
                @if ($setting->address)
                    <div class="mt-6 flex items-center justify-center gap-2 text-zinc-500">
                        <svg class="size-4 shrink-0 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="text-sm">{{ $setting->address }}</span>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ───── Contact ───── --}}
    <section id="contact" class="py-20 text-white sm:py-28" style="background-color: var(--brand-dark)">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="grid items-center gap-12 lg:grid-cols-2">

                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-white/60">Get in touch</p>
                    <h2 class="mt-2 text-4xl font-black">Ready to start?</h2>
                    <p class="mt-4 text-lg text-white/70">
                        Contact us today to register your membership or to ask any questions.
                        We're happy to help you find the perfect plan.
                    </p>

                    <div class="mt-8 space-y-4">
                        @if ($setting->phone)
                            <a href="tel:{{ preg_replace('/\s/', '', $setting->phone) }}"
                               class="flex items-center gap-4 rounded-xl border border-white/20 bg-white/10 p-4 transition-colors hover:bg-white/20">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/20">
                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-white/50">Phone</p>
                                    <p class="font-semibold">{{ $setting->phone }}</p>
                                </div>
                            </a>
                        @endif

                        @if ($setting->email)
                            <a href="mailto:{{ $setting->email }}"
                               class="flex items-center gap-4 rounded-xl border border-white/20 bg-white/10 p-4 transition-colors hover:bg-white/20">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/20">
                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-white/50">Email</p>
                                    <p class="font-semibold">{{ $setting->email }}</p>
                                </div>
                            </a>
                        @endif

                        @if ($setting->address)
                            <div class="flex items-start gap-4 rounded-xl border border-white/20 bg-white/10 p-4">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/20">
                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-white/50">Location</p>
                                    <p class="font-semibold">{{ $setting->address }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col items-center justify-center text-center">
                    @if ($setting->whatsapp)
                        <div class="rounded-2xl border border-white/20 bg-white/10 p-10 backdrop-blur-sm">
                            <div class="mx-auto flex size-20 items-center justify-center rounded-full bg-green-500/20">
                                <svg class="size-10 text-green-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                            </div>
                            <h3 class="mt-6 text-2xl font-bold">Chat with us on WhatsApp</h3>
                            <p class="mt-2 text-white/60">Fastest way to register and get answers</p>
                            <a href="{{ $wa }}" target="_blank"
                               class="mt-6 inline-flex w-full items-center justify-center gap-3 rounded-xl bg-green-500 py-4 text-base font-bold text-white transition-colors hover:bg-green-600">
                                <svg class="size-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                Start WhatsApp Chat
                            </a>
                        </div>
                    @else
                        <div class="rounded-2xl border border-white/20 bg-white/10 p-10">
                            <p class="text-xl font-semibold">Ready to join?</p>
                            <p class="mt-2 text-white/60">Call or email us to get started.</p>
                        </div>
                    @endif

                    {{-- Social links --}}
                    @if ($setting->facebook_url || $setting->instagram_url)
                        <div class="mt-6 flex items-center gap-3">
                            @if ($setting->facebook_url)
                                <a href="{{ $setting->facebook_url }}" target="_blank"
                                   class="flex size-10 items-center justify-center rounded-lg border border-white/20 bg-white/10 text-white transition-colors hover:bg-white/20">
                                    <svg class="size-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                    </svg>
                                </a>
                            @endif
                            @if ($setting->instagram_url)
                                <a href="{{ $setting->instagram_url }}" target="_blank"
                                   class="flex size-10 items-center justify-center rounded-lg border border-white/20 bg-white/10 text-white transition-colors hover:bg-white/20">
                                    <svg class="size-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- ───── Footer ───── --}}
    <footer class="border-t border-zinc-100 bg-zinc-900 py-8 text-center">
        <p class="text-sm text-zinc-500">
            &copy; {{ now()->year }} {{ $setting->gym_name }}. All rights reserved.
        </p>
        @if ($setting->tagline)
            <p class="mt-1 text-xs text-zinc-600">{{ $setting->tagline }}</p>
        @endif
    </footer>

</body>
</html>
