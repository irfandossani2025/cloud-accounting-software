<?php

namespace App\Services;

use App\Mail\InvoiceMail;
use App\Mail\PaymentReminderMail;
use App\Models\Communication;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Services\Documents\InvoiceDocuments;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sends invoices, statements and payment reminders to customers, and logs every attempt.
 */
class CommunicationService
{
    /** Shared links stay valid this long. */
    public const LINK_DAYS = 90;

    public function __construct(private InvoiceDocuments $documents, private OutstandingService $outstanding) {}

    /** Whether real email is configured (the default "log" mailer only writes to the log file). */
    public static function emailConfigured(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }

    public function invoiceLink(Voucher $voucher): string
    {
        return URL::temporarySignedRoute('public.invoice', now()->addDays(self::LINK_DAYS), ['voucher' => $voucher->id]);
    }

    public function statementLink(Ledger $ledger): string
    {
        return URL::temporarySignedRoute('public.statement', now()->addDays(self::LINK_DAYS), ['ledger' => $ledger->id]);
    }

    public function emailInvoice(Voucher $voucher, string $to, ?string $message, ?int $userId): Communication
    {
        $this->validateEmail($to);
        abort_unless($voucher->is_invoice && ! $voucher->is_cancelled, 422);

        $mail = new InvoiceMail($voucher, $this->documents, $this->invoiceLink($voucher), $message ?: CompanySetting::current()->invoice_email_note);

        return $this->send($mail, $to, [
            'voucher_id' => $voucher->id, 'ledger_id' => $voucher->party_ledger_id, 'kind' => 'invoice', 'user_id' => $userId,
        ]);
    }

    /**
     * Emails the customer's overdue bills. $stages maps bill reference => overdue stage reached.
     *
     * @param  array<string, int>  $stages
     */
    public function emailReminder(Ledger $customer, array $stages, ?int $userId = null): array
    {
        $this->validateEmail((string) $customer->email);
        $bills = $this->outstanding->pendingBills($customer)->filter(fn ($b) => $b->pending > 0 && $b->overdue_days > 0)->values();

        $mail = new PaymentReminderMail($customer, $bills, $this->statementLink($customer));
        $logs = [];
        foreach ($stages ?: ['' => null] as $reference => $stage) {
            $logs[] = $this->send($mail, $customer->email, [
                'ledger_id' => $customer->id, 'kind' => 'reminder', 'reference' => $reference ?: null, 'stage' => $stage, 'user_id' => $userId,
            ], sendMail: $logs === []);
        }

        return $logs;
    }

    public function emailStatement(Ledger $customer, string $to, ?int $userId): Communication
    {
        $this->validateEmail($to);
        $bills = $customer->is_bill_wise
            ? $this->outstanding->pendingBills($customer)->filter(fn ($b) => $b->pending > 0)->values()
            : collect();

        return $this->send(new PaymentReminderMail($customer, $bills, $this->statementLink($customer), statement: true), $to, [
            'ledger_id' => $customer->id, 'kind' => 'statement', 'user_id' => $userId,
        ]);
    }

    /**
     * WhatsApp "click to chat" link with a prepared message. No API account is needed: WhatsApp
     * opens on the user's phone or computer with the text ready to send.
     */
    public function whatsappInvoiceUrl(Voucher $voucher): ?string
    {
        $company = CompanySetting::current();
        $total = ($voucher->currency?->code ?? 'OMR').' '.Money::format($voucher->total);
        $text = "Dear {$voucher->party?->name},\n{$company->name} has sent you {$voucher->type->name} {$voucher->number} dated {$voucher->date->format('d M Y')} for {$total}."
            .($voucher->due_date ? " Due on {$voucher->due_date->format('d M Y')}." : '')
            ."\nView or download: ".$this->invoiceLink($voucher);

        return $this->whatsappUrl($voucher->party?->phone, $text);
    }

    public function whatsappStatementUrl(Ledger $customer): ?string
    {
        $company = CompanySetting::current();
        $text = "Dear {$customer->name},\nPlease find your account statement from {$company->name}: ".$this->statementLink($customer);

        return $this->whatsappUrl($customer->phone, $text);
    }

    public function logWhatsapp(?Voucher $voucher, ?Ledger $ledger, string $kind, ?int $userId): void
    {
        Communication::query()->create([
            'voucher_id' => $voucher?->id, 'ledger_id' => $ledger?->id ?? $voucher?->party_ledger_id, 'channel' => 'whatsapp',
            'kind' => $kind, 'recipient' => ($ledger ?? $voucher?->party)?->phone, 'status' => 'opened', 'user_id' => $userId,
        ]);
    }

    /** International number for wa.me: digits only, Omani 8-digit numbers get the 968 prefix. */
    public static function whatsappNumber(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $digits = preg_replace('/^00/', '', $digits);

        return match (true) {
            strlen($digits) === 8 => '968'.$digits,
            strlen($digits) >= 10 => $digits,
            default => null,
        };
    }

    private function whatsappUrl(?string $phone, string $text): ?string
    {
        $number = self::whatsappNumber($phone);

        // Without a number WhatsApp asks the user to pick the chat.
        return 'https://wa.me/'.($number ?? '').'?text='.rawurlencode($text);
    }

    private function send($mail, string $to, array $log, bool $sendMail = true): Communication
    {
        $attributes = $log + ['channel' => 'email', 'recipient' => $to, 'subject' => $mail->subjectLine()];

        try {
            if ($sendMail) {
                Mail::to($to)->send($mail);
            }

            return Communication::query()->create($attributes + ['status' => 'sent']);
        } catch (Throwable $e) {
            report($e);
            Communication::query()->create($attributes + ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);

            throw ValidationException::withMessages(['email' => 'The email could not be sent: '.$e->getMessage()]);
        }
    }

    private function validateEmail(string $to): void
    {
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Enter a valid email address.']);
        }
    }

    /** Human "3 days ago" style age for the log view. */
    public static function ago(Carbon $at): string
    {
        return $at->diffForHumans();
    }
}
