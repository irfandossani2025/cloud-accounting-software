<?php

namespace App\Services\EInvoice;

use App\Models\CompanySetting;
use App\Models\Einvoice;
use App\Models\Voucher;
use App\Support\Audit;
use App\Support\PintOm;
use Illuminate\Validation\ValidationException;

/**
 * Oman e-invoicing lifecycle: generate the PINT OM XML, record submission to the accredited service
 * provider, and the outcome. Submitted documents are final and must be corrected with a credit note.
 */
class EInvoiceService
{
    public function __construct(private PintOmPreflight $preflight, private PintOmXmlBuilder $builder) {}

    public function generate(Voucher $voucher): Einvoice
    {
        $existing = $voucher->einvoice;
        if ($existing?->isLocked()) {
            throw ValidationException::withMessages(['einvoice' => 'This e-invoice has already been submitted and cannot be regenerated.']);
        }

        $problems = $this->preflight->check($voucher);
        if ($problems) {
            throw ValidationException::withMessages(['einvoice' => array_column($problems, 'message')]);
        }

        $company = CompanySetting::current();
        if (! $company->einvoice_seller_uuid) {
            $company->update(['einvoice_seller_uuid' => PintOm::sellerUuid($company->vatin)]);
        }

        $doc = PintOmDocument::forVoucher($voucher->fresh());
        $xml = $this->builder->build($doc);

        $einvoice = Einvoice::query()->updateOrCreate(['voucher_id' => $voucher->id], [
            'uuid' => $doc->uuid,
            'transaction_type' => $doc->transactionType,
            'status' => Einvoice::DRAFT,
            'xml' => $xml,
            'xml_sha256' => hash('sha256', $xml),
            'provider' => $company->einvoice_provider,
            'message' => null,
            'generated_at' => now(),
        ]);

        Audit::log('generated', 'Einvoice', $einvoice->id, "E-invoice for {$voucher->number} generated", null, ['uuid' => $doc->uuid, 'sha256' => $einvoice->xml_sha256]);

        return $einvoice;
    }

    /** Recorded after uploading the XML to the service provider (manual provider). */
    public function markSubmitted(Einvoice $einvoice, ?string $reference, ?int $userId): void
    {
        if ($einvoice->status !== Einvoice::DRAFT || ! $einvoice->xml) {
            throw ValidationException::withMessages(['einvoice' => 'Generate the e-invoice before marking it as submitted.']);
        }

        $einvoice->update(['status' => Einvoice::SUBMITTED, 'provider_reference' => $reference ?: null, 'submitted_at' => now(), 'submitted_by' => $userId]);
        Audit::log('submitted', 'Einvoice', $einvoice->id, "E-invoice for {$einvoice->voucher->number} submitted", null, ['reference' => $reference]);
    }

    public function markOutcome(Einvoice $einvoice, bool $accepted, ?string $message): void
    {
        if ($einvoice->status !== Einvoice::SUBMITTED) {
            throw ValidationException::withMessages(['einvoice' => 'Only submitted e-invoices can be marked accepted or rejected.']);
        }

        // A rejected document was never registered: it can be corrected and generated again.
        $einvoice->update(['status' => $accepted ? Einvoice::ACCEPTED : Einvoice::REJECTED, 'message' => $message ?: null]);
        Audit::log($accepted ? 'accepted' : 'rejected', 'Einvoice', $einvoice->id,
            "E-invoice for {$einvoice->voucher->number} ".($accepted ? 'accepted' : 'rejected'), null, ['message' => $message]);
    }

    /** Called before a voucher is altered or cancelled. */
    public static function assertEditable(?Voucher $voucher): void
    {
        if ($voucher?->einvoice?->isLocked()) {
            throw ValidationException::withMessages([
                'date' => "{$voucher->number} has been submitted as an e-invoice and can no longer be altered or cancelled. Issue a credit note instead.",
            ]);
        }
    }

    /** After an allowed change, a previously generated (unsubmitted) XML no longer matches the voucher. */
    public static function invalidate(Voucher $voucher): void
    {
        $voucher->einvoice()->whereIn('status', [Einvoice::DRAFT, Einvoice::REJECTED])
            ->update(['xml' => null, 'xml_sha256' => null, 'generated_at' => null, 'status' => Einvoice::DRAFT]);
    }
}
