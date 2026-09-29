<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\InstrumentType;
use App\Enums\VatCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:3',
            'credit' => 'decimal:3',
            'vat_category' => VatCategory::class,
            'instrument_type' => InstrumentType::class,
            'instrument_date' => DateOnly::class,
            'bank_date' => DateOnly::class,
            'fx_amount' => 'decimal:3',
            'fx_rate' => 'decimal:6',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function costAllocations(): HasMany
    {
        return $this->hasMany(CostAllocation::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
