<?php

namespace App\Support;

use App\Models\CompanySetting;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Books are closed on and before the company's lock date. */
final class PeriodLock
{
    public static function lockedUntil(): ?Carbon
    {
        return CompanySetting::current()?->locked_until;
    }

    public static function isLocked(Carbon|string|null $date): bool
    {
        $lock = self::lockedUntil();

        return $lock !== null && $date !== null && Carbon::parse($date)->lte($lock);
    }

    /** @throws ValidationException */
    public static function assertOpen(Carbon|string|null ...$dates): void
    {
        foreach ($dates as $date) {
            if (self::isLocked($date)) {
                throw ValidationException::withMessages([
                    'date' => 'The books are locked up to '.self::lockedUntil()->format('d-M-Y').'. Ask an administrator to change the lock date.',
                ]);
            }
        }
    }
}
