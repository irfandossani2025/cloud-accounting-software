<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Voucher;

final class Audit
{
    public static function log(string $action, string $subjectType, ?int $subjectId, string $description, ?array $old = null, ?array $new = null): void
    {
        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => mb_substr($description, 0, 500),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
        ]);
    }

    /** A readable picture of a voucher for the audit trail. */
    public static function voucherSnapshot(Voucher $voucher): array
    {
        $voucher->loadMissing(['type', 'party', 'entries.ledger', 'stockMovements.item', 'invoiceLines']);

        return array_filter([
            'type' => $voucher->type->name,
            'number' => $voucher->number,
            'date' => $voucher->date->toDateString(),
            'reference' => $voucher->reference,
            'party' => $voucher->party?->name,
            'total' => $voucher->total,
            'narration' => $voucher->narration,
            'entries' => $voucher->entries->map(fn ($e) => trim($e->ledger->name.' '.
                (Money::toBaisa($e->debit) ? 'Dr '.$e->debit : 'Cr '.$e->credit)))->all(),
            'lines' => $voucher->invoiceLines->map(fn ($l) => "{$l->description} × {$l->quantity} @ {$l->rate}")->all(),
            'stock' => $voucher->stockMovements->map(fn ($m) => "{$m->item->name} {$m->quantity}")->all(),
        ], fn ($v) => $v !== null && $v !== []);
    }
}
