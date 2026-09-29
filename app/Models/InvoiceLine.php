<?php

namespace App\Models;

use App\Enums\VatCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'rate' => 'decimal:3',
            'discount' => 'decimal:3',
            'amount' => 'decimal:3',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:3',
            'vat_category' => VatCategory::class,
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
