<?php

namespace App\Models;

use App\Enums\GroupNature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class AccountGroup extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nature' => GroupNature::class,
            'affects_gross_profit' => 'boolean',
            'is_reserved' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }

    public function isPrimary(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * IDs of this group and all of its descendants.
     *
     * @return Collection<int, int>
     */
    public function descendantAndSelfIds(): Collection
    {
        $all = static::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = collect([$this->id]);
        $queue = [$this->id];

        while ($queue) {
            $id = array_shift($queue);
            foreach ($all->get($id, []) as $child) {
                $ids->push($child->id);
                $queue[] = $child->id;
            }
        }

        return $ids;
    }

    /** Find a reserved (predefined) group by name. */
    public static function reserved(string $name): self
    {
        return static::query()->where('name', $name)->firstOrFail();
    }
}
