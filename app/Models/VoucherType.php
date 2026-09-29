<?php

namespace App\Models;

use App\Enums\VoucherBaseType;
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

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}
