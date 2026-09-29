<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Godown extends Model
{
    use Auditable;

    public const MAIN = 'Main Location';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_reserved' => 'boolean'];
    }

    public static function main(): self
    {
        return static::query()->firstOrCreate(['name' => self::MAIN], ['is_reserved' => true]);
    }
}
