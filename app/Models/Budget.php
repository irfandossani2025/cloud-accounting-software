<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from_date' => DateOnly::class, 'to_date' => DateOnly::class];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
