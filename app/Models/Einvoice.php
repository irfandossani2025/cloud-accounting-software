<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Einvoice extends Model
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /** Submitted or accepted documents are final: correct them with a credit note. */
    public function isLocked(): bool
    {
        return in_array($this->status, [self::SUBMITTED, self::ACCEPTED], true);
    }
}
