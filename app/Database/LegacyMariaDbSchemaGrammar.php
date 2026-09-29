<?php

namespace App\Database;

use Illuminate\Database\Schema\Grammars\MySqlGrammar;

/**
 * Schema grammar for MariaDB 10.1, which lacks information_schema.columns.generation_expression
 * (added in 10.2.5). Laravel 12 selects that column when introspecting tables.
 */
class LegacyMariaDbSchemaGrammar extends MySqlGrammar
{
    public function compileColumns($schema, $table)
    {
        return sprintf(
            'select column_name as `name`, data_type as `type_name`, column_type as `type`, '
            .'collation_name as `collation`, is_nullable as `nullable`, '
            .'column_default as `default`, column_comment as `comment`, '
            .'null as `expression`, extra as `extra` '
            .'from information_schema.columns where table_schema = %s and table_name = %s '
            .'order by ordinal_position asc',
            $schema ? $this->quoteString($schema) : 'schema()',
            $this->quoteString($table)
        );
    }
}
