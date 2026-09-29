<?php

namespace App\Services\EInvoice;

use App\Enums\VoucherBaseType;
use App\Models\Voucher;
use App\Support\PintOm;
use Illuminate\Support\Carbon;

/**
 * Checks a voucher against the PINT OM rules that depend on data entered in this app, and explains
 * each problem in plain language with where to fix it. The service provider runs the full official
 * validation on submission; this catches the common problems first.
 */
final class PintOmPreflight
{
    /**
     * @return list<array{rule: string, message: string, fix: ?string}>
     */
    public function check(Voucher $voucher): array
    {
        $voucher->loadMissing(['type', 'party']);
        $problems = [];
        $add = function (string $rule, string $message, ?string $fix = null) use (&$problems) {
            $problems[] = compact('rule', 'message', 'fix');
        };

        if (! in_array($voucher->type->base_type, [VoucherBaseType::Sales, VoucherBaseType::CreditNote], true)) {
            $add('TYPE', 'Only Sales invoices and Credit Notes are e-invoiced.');

            return $problems;
        }
        if (! $voucher->is_invoice) {
            $add('TYPE', 'This voucher was entered in accounting mode. Enter sales in invoice mode (items and quantities) so they can be e-invoiced.');

            return $problems;
        }
        if ($voucher->is_cancelled) {
            $add('TYPE', 'This voucher is cancelled.');

            return $problems;
        }

        $doc = PintOmDocument::forVoucher($voucher);
        $company = $doc->company;
        $companyFix = route('company.edit');

        if (! $company->einvoicing_enabled) {
            $add('SETUP', 'E-invoicing is not switched on for the company.', $companyFix);
        }
        if (! PintOm::validVatin($company->vatin)) {
            $add('IBR-003-OM', 'The company VATIN must be "OM" followed by 10 digits.', $companyFix);
        }
        foreach (['street' => 'street / building', 'additional_street' => 'area', 'po_box' => 'P.O. Box', 'city' => 'city', 'postal_code' => 'postal code'] as $field => $label) {
            if (blank($company->{$field})) {
                $add('IBR-010-OM', "The company address is missing its {$label}.", $companyFix);
            }
        }
        if (blank($company->phone)) {
            $add('IBR-011-OM', 'The company phone number is required.', $companyFix);
        }

        $buyer = $doc->buyer;
        $buyerFix = $buyer && ! $buyer->isCashOrBank() ? route('ledgers.edit', $buyer) : null;
        if ($buyer && filled($buyer->vatin) && ! PintOm::validVatin($buyer->vatin)) {
            $add('IBR-003-OM', "The VATIN of {$buyer->name} must be \"OM\" followed by 10 digits.", $buyerFix);
        }
        if ($doc->isFullInvoice) {
            foreach (['street' => 'street / building', 'additional_street' => 'area', 'po_box' => 'P.O. Box', 'city' => 'city', 'postal_code' => 'postal code'] as $field => $label) {
                if (blank($buyer->{$field})) {
                    $add('IBR-019-OM', "Full tax invoice: the address of {$buyer->name} is missing its {$label}.", $buyerFix);
                }
            }
        }

        if (Carbon::parse($voucher->date)->startOfDay()->gt(now('Asia/Muscat')->startOfDay())) {
            $add('IBR-171-OM', 'An e-invoice cannot be issued with a future date.');
        }
        if ($voucher->currency_id && ! $doc->fxRate) {
            $add('IBR-004-OM', 'Foreign-currency invoices need the exchange rate.');
        }

        if ($doc->isCreditNote) {
            if (! $voucher->original_voucher_id) {
                $add('IBR-032-OM', 'Select the original invoice this credit note corrects.');
            }
            if (! isset(PintOm::ISSUANCE_REASONS[$voucher->issuance_reason ?? ''])) {
                $add('IBR-023-OM', 'Select the reason for the credit note.');
            }
        }

        if (! $doc->lines) {
            $add('LINES', 'The invoice has no lines.');
        }

        foreach ($doc->lines as $line) {
            $label = "Line {$line['id']} ({$line['name']})";
            $fix = $line['line']->stock_item_id ? route('stock-items.edit', $line['line']->stock_item_id) : route('ledgers.edit', $line['line']->ledger_id);
            $where = $line['line']->stock_item_id ? 'stock item' : 'sales ledger';

            if ($doc->isFullInvoice) {
                if (! isset(PintOm::ITEM_TYPES[$line['item_type'] ?? ''])) {
                    $add('IBR-078-OM', "{$label}: set Goods or Services on the {$where}.", $fix);
                }
                if ($line['item_type'] === 'G' && (! preg_match('/^\d{12}$/', (string) $line['hs_code']) || ! PintOm::hsDescription($line['hs_code']))) {
                    $add('IBR-079-OM', "{$label}: goods need a valid 12-digit Oman HS code on the {$where}.", $fix);
                }
                if (! PintOm::isicDescription((string) $line['isic_code'])) {
                    $add('IBR-081-OM', "{$label}: set the industry (ISIC) code on the {$where}. Use 000000 if none applies.", $fix);
                }
            }

            if ($line['category'] === 'Z' && ! isset(PintOm::ZERO_RATING[$line['exemption'] ?? ''])) {
                $add('CL-10-OM', "{$label}: zero-rated supplies need a zero-rating reason on the {$where}.", $fix);
            }
            if ($line['category'] === 'E' && ! isset(PintOm::EXEMPTION[$line['exemption'] ?? ''])) {
                $add('CL-05-OM', "{$label}: exempt supplies need an exemption reason on the {$where}.", $fix);
            }
        }

        // One message per problem, even when several lines share the same ledger.
        return array_values(array_unique($problems, SORT_REGULAR));
    }
}
