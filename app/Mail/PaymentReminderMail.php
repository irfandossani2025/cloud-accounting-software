<?php

namespace App\Mail;

use App\Models\CompanySetting;
use App\Models\Ledger;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class PaymentReminderMail extends Mailable
{
    /** $statement: a statement of all open bills sent on request, rather than an overdue reminder. */
    public function __construct(public Ledger $customer, public Collection $bills, public string $link, public bool $statement = false) {}

    public function subjectLine(): string
    {
        return ($this->statement ? 'Statement of account from ' : 'Payment reminder from ').CompanySetting::current()->name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reminder', with: ['company' => CompanySetting::current(), 'total' => $this->bills->sum('pending')]);
    }
}
