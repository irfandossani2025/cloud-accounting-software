<section class="card mt-4 p-4" id="send">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold">{{ $voucher ? 'Send to customer' : 'Send statement to customer' }}</h2>
        @unless ($emailConfigured)
            <span class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800" title="Add the SMTP settings to the server's .env file (MAIL_MAILER=smtp and the MAIL_* values).">Email not set up yet</span>
        @endunless
    </div>

    @can('enter-vouchers')
        <form wire:submit="sendEmail" data-enter-submits class="grid gap-2 md:grid-cols-[minmax(0,18rem)_1fr_auto] md:items-start">
            <div>
                <label class="tally-caption" for="send-email">Email to</label>
                <input id="send-email" type="email" wire:model="email" class="input w-full" placeholder="customer@example.com">
            </div>
            @if ($voucher)
                <div>
                    <label class="tally-caption" for="send-message">Message (optional)</label>
                    <input id="send-message" wire:model="message" class="input w-full" placeholder="Added above the invoice details">
                </div>
            @else
                <div class="hidden md:block"></div>
            @endif
            <div class="md:pt-4">
                <button class="btn-primary w-full" wire:loading.attr="disabled" wire:target="sendEmail">
                    <span wire:loading.remove wire:target="sendEmail">{{ $voucher ? 'Email invoice + PDF' : 'Email statement' }}</span>
                    <span wire:loading wire:target="sendEmail">Sending…</span>
                </button>
            </div>
        </form>
        @error('email') <p class="error mt-1 text-sm">{{ $message }}</p> @enderror
        @if ($status)<p class="mt-1 text-sm {{ $emailConfigured ? 'text-green-700' : 'text-amber-700' }}">{{ $status }}</p>@endif

        <div class="mt-3 flex flex-wrap gap-2" x-data="{ copied: false }">
            <a href="{{ $whatsapp }}" target="_blank" rel="noopener" wire:click="logWhatsapp" class="btn-secondary">WhatsApp</a>
            <button type="button" class="btn-secondary" x-on:click="navigator.clipboard.writeText(@js($link)); copied = true; setTimeout(() => copied = false, 2000)">
                <span x-show="!copied">Copy customer link</span><span x-show="copied" x-cloak>Link copied ✓</span>
            </button>
            @if ($voucher)
                <a href="{{ route('vouchers.pdf', $voucher) }}" class="btn-secondary">Download PDF</a>
            @endif
            <a href="{{ $link }}" target="_blank" rel="noopener" class="btn-secondary">Preview what the customer sees</a>
        </div>
        <p class="mt-1 text-xs text-slate-500">
            @unless ($hasNumber) {{ $party?->name }} has no mobile number: WhatsApp will ask you to choose the chat. @endunless
            The customer link works without a login for {{ \App\Services\CommunicationService::LINK_DAYS }} days.
        </p>
    @endcan

    @if ($history->isNotEmpty())
        <h3 class="mt-4 mb-1 text-sm font-semibold">History</h3>
        <table class="w-full text-sm">
            @foreach ($history as $row)
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="py-1 pr-2 whitespace-nowrap text-slate-500" title="{{ $row->created_at->format('d-M-Y H:i') }}">{{ $row->created_at->diffForHumans() }}</td>
                    <td class="py-1 pr-2">{{ $row->channel === 'whatsapp' ? 'WhatsApp' : 'Email' }} {{ $row->kind }}@if ($row->reference) {{ $row->reference }}@endif @if ($row->stage) ({{ $row->stage }}+ days overdue)@endif</td>
                    <td class="py-1 pr-2 text-slate-600">{{ $row->recipient }}</td>
                    <td class="py-1 pr-2 text-xs {{ $row->status === 'failed' ? 'text-red-700' : 'text-slate-500' }}" title="{{ $row->error }}">{{ $row->status === 'opened' ? 'opened' : $row->status }}</td>
                    <td class="py-1 text-xs text-slate-500">{{ $row->user?->name ?? 'Automatic' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</section>
