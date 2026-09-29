<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\BillType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => BillType::class,
            'bill_date' => DateOnly::class,
            'due_date' => DateOnly::class,
            'amount' => 'decimal:3',
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
