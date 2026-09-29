<?php

namespace App\Models;

use App\Enums\VoucherBaseType;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'base_type' => VoucherBaseType::class,
            'is_reserved' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Entry screen for a new voucher of this type (invoice mode for sales/purchase types). */
    public function createUrl(): string
    {
        return match (true) {
            $this->base_type->isInventoryOnly() => route('inventory-vouchers.create', $this),
            in_array($this->base_type, InvoiceService::INVOICE_TYPES, true) => route('invoices.create', $this),
            default => route('vouchers.create', $this),
        };
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}
