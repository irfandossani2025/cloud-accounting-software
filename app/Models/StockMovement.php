<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'quantity' => 'decimal:3',
            'rate' => 'decimal:3',
            'value' => 'decimal:3',
            'affects_cost' => 'boolean',
            'is_transfer' => 'boolean',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function godown(): BelongsTo
    {
        return $this->belongsTo(Godown::class);
    }
}
