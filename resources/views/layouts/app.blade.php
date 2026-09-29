<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $company?->name ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    @auth
        <header class="no-print bg-brand-800 text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-2">
                <a href="{{ route('gateway') }}" wire:navigate class="flex items-center gap-2 font-semibold">
                    <span class="rounded bg-white/15 px-1.5 py-0.5 text-xs">OMR</span>
                    {{ $company?->name ?? config('app.name') }}
                </a>
                <nav class="hidden items-center gap-4 text-sm md:flex">
                    <a href="{{ route('gateway') }}" wire:navigate class="hover:underline">Gateway</a>
                    <a href="{{ route('reports.day-book') }}" wire:navigate class="hover:underline">Day Book</a>
                    <a href="{{ route('reports.trial-balance') }}" wire:navigate class="hover:underline">Trial Balance</a>
                    <a href="{{ route('reports.balance-sheet') }}" wire:navigate class="hover:underline">Balance Sheet</a>
                    <a href="{{ route('reports.profit-loss') }}" wire:navigate class="hover:underline">Profit &amp; Loss</a>
                </nav>
                <div class="flex items-center gap-3 text-sm">
                    <span class="hidden text-white/70 sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded bg-white/10 px-2 py-1 hover:bg-white/20">Log out</button>
                    </form>
                </div>
            </div>
        </header>
    @endauth

    <main class="mx-auto max-w-7xl px-4 py-6">
        @if (session('status'))
            <div class="no-print mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
