<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $company?->name ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-tally-bg text-slate-800 antialiased">
    @auth
        {{-- Top menu bar (Tally: K Company, Y Data, G Go To, E Export, P Print, F1 Help) --}}
        <header class="no-print sticky top-0 z-30">
            <div class="flex items-stretch bg-tally-top text-white">
                <a href="{{ route('gateway') }}" wire:navigate class="flex items-center gap-2 bg-black/15 px-4 text-sm font-bold tracking-wide">
                    <span class="rounded bg-tally-accent px-1.5 py-0.5 text-[10px] text-tally-top">OMR</span>
                    CLOUD ACCOUNTING
                </a>
                <nav class="flex flex-1 items-stretch overflow-x-auto text-[13px]">
                    @can('admin')
                        <a href="{{ route('company.edit') }}" wire:navigate data-shortcut="Alt+K" class="tally-topmenu"><u>K</u>: Company</a>
                        <a href="{{ route('year-end') }}" wire:navigate data-shortcut="Alt+Y" class="tally-topmenu"><u>Y</u>: Data</a>
                    @endcan
                    <button type="button" data-shortcut="Alt+G" x-data x-on:click="$dispatch('open-goto')" class="tally-topmenu"><u>G</u>: Go To</button>
                    <button type="button" data-shortcut="Alt+E" data-action="export" class="tally-topmenu"><u>E</u>: Export</button>
                    <button type="button" data-shortcut="Alt+P" data-action="print" class="tally-topmenu"><u>P</u>: Print</button>
                    <button type="button" data-shortcut="F1" x-data x-on:click="$dispatch('open-help')" class="tally-topmenu">F1: Help</button>
                </nav>
                <div class="flex items-center gap-3 px-3 text-xs">
                    @if ($company?->locked_until)
                        <span class="hidden rounded bg-white/10 px-1.5 py-0.5 md:inline" title="Books locked">🔒 {{ $company->locked_until->format('d-M-y') }}</span>
                    @endif
                    <a href="{{ route('password.edit') }}" wire:navigate class="hidden text-white/80 hover:text-white sm:inline" title="{{ auth()->user()->role->label() }}">{{ auth()->user()->name }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded bg-white/10 px-2 py-1 hover:bg-white/20" title="Quit">Q: Quit</button>
                    </form>
                </div>
            </div>
            {{-- Title strip --}}
            <div class="flex items-center justify-between border-b border-tally-line bg-tally-strip px-4 py-1 text-xs text-slate-600">
                <span class="font-semibold tracking-wide text-tally-top uppercase">{{ $title ?? 'Gateway' }}</span>
                <span class="hidden sm:inline">{{ $company?->name }} · {{ now()->format('d-M-Y') }}</span>
            </div>
        </header>
    @endauth

    <div class="flex">
        <main class="min-w-0 flex-1 px-4 py-5">
            <div class="mx-auto max-w-7xl">
                @if (session('status'))
                    <div class="no-print mb-4 flex flex-wrap items-center justify-between gap-2 rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-800">
                        <span>{{ session('status') }}</span>
                        @if (session('printVoucher'))
                            <a href="{{ route('vouchers.print', session('printVoucher')) }}" target="_blank" class="font-semibold underline" data-shortcut="Alt+Q">Print it (Alt+Q)</a>
                        @endif
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>

        @auth
            {{-- Right button bar: voucher keys plus the current screen's own shortcuts --}}
            <aside class="no-print sticky top-[4.25rem] hidden h-[calc(100vh-4.25rem)] w-40 shrink-0 overflow-y-auto border-l border-tally-line bg-tally-strip py-2 text-xs md:block">
                <div id="button-bar-page" class="space-y-px"></div>
                @can('enter-vouchers')
                    <div class="mt-2 space-y-px border-t border-tally-line pt-2">
                        @foreach ($voucherKeys as $type)
                            <a href="{{ $type->createUrl() }}" wire:navigate data-shortcut="{{ $type->base_type->shortcut() }}" class="tally-fbutton">
                                <span class="tally-fkey">{{ $type->base_type->shortcut() }}</span><span class="truncate">{{ $type->name }}</span>
                            </a>
                        @endforeach
                    </div>
                @endcan
            </aside>

            {{-- Go To (Alt+G) --}}
            <div x-data="tallyGoTo(@js($goTo))" x-on:open-goto.window="open()" x-show="visible" x-cloak
                 class="no-print fixed inset-0 z-50 flex items-start justify-center bg-black/30 pt-24" x-on:click.self="close()">
                <div class="w-full max-w-lg overflow-hidden rounded border border-tally-line bg-white shadow-xl" data-no-escape>
                    <div class="bg-tally-top px-3 py-1.5 text-xs font-semibold tracking-wide text-white uppercase">Go To</div>
                    <input x-ref="search" x-model="query" x-on:keydown="onKey($event)" class="input rounded-none border-0 border-b border-tally-line px-3 py-2 focus:ring-0" placeholder="Type a report, master or ledger name…">
                    <ul class="max-h-80 overflow-y-auto py-1 text-sm">
                        <template x-for="(item, i) in results" :key="item.url">
                            <li>
                                <a :href="item.url" wire:navigate x-on:mouseenter="current = i" x-on:click="close()"
                                   :class="current === i ? 'tally-item-active' : ''" class="tally-item">
                                    <span x-text="item.label"></span><span class="text-xs text-slate-400" x-text="item.group"></span>
                                </a>
                            </li>
                        </template>
                        <li x-show="results.length === 0" class="px-4 py-3 text-slate-400">No match.</li>
                    </ul>
                </div>
            </div>

            {{-- Help (F1) --}}
            <div x-data="{ visible: false }" x-on:open-help.window="visible = true" x-on:keydown.escape.window="visible = false" x-show="visible" x-cloak
                 class="no-print fixed inset-0 z-50 flex items-start justify-center bg-black/30 pt-16" x-on:click.self="visible = false">
                <div class="w-full max-w-2xl rounded border border-tally-line bg-white text-sm shadow-xl" data-no-escape>
                    <div class="flex justify-between bg-tally-top px-3 py-1.5 text-xs font-semibold tracking-wide text-white uppercase">
                        <span>Keyboard shortcuts</span><button x-on:click="visible = false">✕</button>
                    </div>
                    <div class="grid gap-x-8 gap-y-1 p-4 sm:grid-cols-2">
                        @foreach ([
                            'Gateway' => 'Press the underlined letter, or ↑ ↓ and Enter',
                            'Esc' => 'Back / close',
                            'Enter' => 'Next field (in forms)',
                            'Ctrl+A' => 'Accept (save)',
                            'Alt+G' => 'Go To any screen or ledger',
                            'Alt+E / Alt+P' => 'Export / Print the current report',
                            'F4 · F5 · F6 · F7' => 'Contra · Payment · Receipt · Journal',
                            'F8 · F9' => 'Sales · Purchase',
                            'Ctrl+F8 · Ctrl+F9' => 'Credit Note · Debit Note',
                            'Alt+F7 · Alt+F10' => 'Stock Journal · Physical Stock',
                            'Ctrl+H' => 'Invoice / accounting mode',
                            'Alt+N · Alt+V' => 'New line · Apply VAT 5%',
                            'Alt+F1' => 'Detailed / condensed report',
                            'Alt+C' => 'Create (on master lists)',
                        ] as $keys => $what)
                            <div class="flex justify-between gap-3 border-b border-slate-100 py-1"><span class="kbd self-center">{{ $keys }}</span><span class="text-right text-slate-600">{{ $what }}</span></div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endauth
    </div>
</body>
</html>
