<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Currency extends Model
{
    protected $guarded = ['id'];

    public function rates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class)->orderByDesc('date');
    }

    /** OMR per unit on or before $date, as a decimal string, or null when no rate is set. */
    public function rateOn(Carbon $date): ?string
    {
        return $this->rates()->where('date', '<=', $date->toDateString())->value('rate');
    }
}
