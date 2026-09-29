<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Voucher extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'reference_date' => DateOnly::class,
            'due_date' => DateOnly::class,
            'is_invoice' => 'boolean',
            'fx_rate' => 'decimal:6',
            'total' => 'decimal:3',
            'is_cancelled' => 'boolean',
        ];
    }

    public function editUrl(): string
    {
        return match (true) {
            $this->is_invoice => route('invoices.edit', $this),
            $this->type->base_type->isInventoryOnly() => route('inventory-vouchers.edit', $this),
            default => route('vouchers.edit', $this),
        };
    }

    public function einvoice(): HasOne
    {
        return $this->hasOne(Einvoice::class);
    }

    /** For credit notes: the invoice being corrected. */
    public function originalVoucher(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_voucher_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(VoucherType::class, 'voucher_type_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'party_ledger_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(VoucherEntry::class)->orderBy('sort_order');
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(BillAllocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
