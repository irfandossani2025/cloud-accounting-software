<?php

namespace App\Livewire;

use App\Models\Communication;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Services\CommunicationService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Send to customer" panel: email with PDF, WhatsApp, shareable link, and the history of what was sent.
 * Used on a saved sales invoice ($voucherId) or a customer's ledger statement ($ledgerId).
 */
class SendPanel extends Component
{
    #[Locked]
    public ?int $voucherId = null;

    #[Locked]
    public ?int $ledgerId = null;

    public string $email = '';

    public string $message = '';

    public string $status = '';

    public function mount(?int $voucherId = null, ?int $ledgerId = null): void
    {
        $this->voucherId = $voucherId;
        $this->ledgerId = $voucherId ? null : $ledgerId;
        $this->email = (string) $this->party()?->email;
    }

    public function sendEmail(CommunicationService $communications): void
    {
        $this->authorize('enter-vouchers');
        $this->resetErrorBag();
        $this->status = '';

        try {
            $voucher = $this->voucher();
            $voucher
                ? $communications->emailInvoice($voucher, trim($this->email), $this->message ?: null, auth()->id())
                : $communications->emailStatement($this->party(), trim($this->email), auth()->id());
            $this->status = CommunicationService::emailConfigured()
                ? 'Email sent to '.trim($this->email).'.'
                : 'Recorded, but email is not set up yet: the message was only written to the server log.';
        } catch (ValidationException $e) {
            $this->addError('email', $e->validator->errors()->first());
        }
    }

    /** Logs the WhatsApp share; the browser then opens the prepared wa.me link. */
    public function logWhatsapp(CommunicationService $communications): void
    {
        $this->authorize('enter-vouchers');
        $voucher = $this->voucher();
        $communications->logWhatsapp($voucher, $voucher ? null : $this->party(), $voucher ? 'invoice' : 'statement', auth()->id());
    }

    private function voucher(): ?Voucher
    {
        return $this->voucherId ? Voucher::query()->with(['party', 'type', 'currency'])->findOrFail($this->voucherId) : null;
    }

    private function party(): ?Ledger
    {
        return $this->voucherId ? $this->voucher()->party : Ledger::query()->find($this->ledgerId);
    }

    public function render(CommunicationService $communications)
    {
        $voucher = $this->voucher();
        $party = $this->party();

        return view('livewire.send-panel', [
            'voucher' => $voucher,
            'party' => $party,
            'link' => $voucher ? $communications->invoiceLink($voucher) : $communications->statementLink($party),
            'whatsapp' => $voucher ? $communications->whatsappInvoiceUrl($voucher) : $communications->whatsappStatementUrl($party),
            'hasNumber' => CommunicationService::whatsappNumber($party?->phone) !== null,
            'emailConfigured' => CommunicationService::emailConfigured(),
            'history' => Communication::query()->with('user')
                ->when($voucher, fn ($q) => $q->where('voucher_id', $voucher->id), fn ($q) => $q->where('ledger_id', $party->id))
                ->latest('id')->limit(10)->get(),
        ]);
    }
}
