<?php

namespace App\Database;

use Illuminate\Database\MySqlConnection;

class LegacyMariaDbConnection extends MySqlConnection
{
    protected function getDefaultSchemaGrammar()
    {
        return new LegacyMariaDbSchemaGrammar($this);
    }
}
