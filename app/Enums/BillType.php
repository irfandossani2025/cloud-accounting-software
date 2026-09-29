<?php

namespace App\Enums;

enum BillType: string
{
    case NewRef = 'new_ref';
    case AgainstRef = 'against_ref';
    case Advance = 'advance';
    case OnAccount = 'on_account';

    public function label(): string
    {
        return match ($this) {
            self::NewRef => 'New Ref',
            self::AgainstRef => 'Agst Ref',
            self::Advance => 'Advance',
            self::OnAccount => 'On Account',
        };
    }
}
