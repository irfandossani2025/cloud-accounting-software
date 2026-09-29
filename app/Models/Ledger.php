<?php

namespace App\Models;

use App\Enums\TaxRole;
use App\Enums\VatCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ledger extends Model
{
    public const CASH = 'Cash';

    public const PROFIT_AND_LOSS = 'Profit & Loss A/c';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:3',
            'vat_rate' => 'decimal:2',
            'vat_category' => VatCategory::class,
            'tax_role' => TaxRole::class,
            'is_bill_wise' => 'boolean',
            'is_reserved' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class, 'account_group_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(VoucherEntry::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(BillAllocation::class);
    }

    /** Ledgers under Cash-in-Hand, Bank Accounts or Bank OD A/c. */
    public function scopeCashOrBank(Builder $query): Builder
    {
        return $query->whereIn('account_group_id', static::cashBankGroupIds());
    }

    public function isCashOrBank(): bool
    {
        return in_array($this->account_group_id, static::cashBankGroupIds(), true);
    }

    /** @return array<int, int> */
    public static function cashBankGroupIds(): array
    {
        return once(function () {
            return AccountGroup::query()
                ->whereIn('name', ['Cash-in-Hand', 'Bank Accounts', 'Bank OD A/c'])
                ->get()
                ->flatMap(fn (AccountGroup $group) => $group->descendantAndSelfIds())
                ->unique()
                ->values()
                ->all();
        });
    }
}
