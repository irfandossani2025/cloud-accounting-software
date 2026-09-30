<?php

namespace App\Mail;

use App\Models\CompanySetting;
use App\Models\Voucher;
use App\Services\Documents\InvoiceDocuments;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceMail extends Mailable
{
    public function __construct(
        public Voucher $voucher,
        private InvoiceDocuments $documents,
        public string $link,
        public ?string $note = null,
    ) {}

    public function subjectLine(): string
    {
        $company = CompanySetting::current();
        [$title] = $this->documents->titles($this->voucher, $company);

        return "{$title} {$this->voucher->number} from {$company->name}";
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invoice', with: $this->documents->data($this->voucher) + ['link' => $this->link, 'note' => $this->note]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->documents->pdf($this->voucher), $this->documents->filename($this->voucher))->withMime('application/pdf'),
        ];
    }
}
