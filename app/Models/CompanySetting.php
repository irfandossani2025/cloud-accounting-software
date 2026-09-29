<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'financial_year_start' => DateOnly::class,
            'books_begin_from' => DateOnly::class,
            'locked_until' => DateOnly::class,
            'vat_registered' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        return once(fn () => static::query()->first());
    }
}
