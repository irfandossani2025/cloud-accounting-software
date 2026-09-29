<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Godown extends Model
{
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
