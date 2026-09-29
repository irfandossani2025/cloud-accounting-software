<?php

namespace App\Models;

use App\Enums\VatCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'vat_category' => VatCategory::class,
            'sales_rate' => 'decimal:3',
            'purchase_rate' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StockGroup::class, 'stock_group_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function openings(): HasMany
    {
        return $this->hasMany(StockOpening::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function salesLedger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'sales_ledger_id');
    }

    public function purchaseLedger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'purchase_ledger_id');
    }
}
