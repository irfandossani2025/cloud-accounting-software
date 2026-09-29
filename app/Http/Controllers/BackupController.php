<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Full data backup as SQL INSERT statements, for hosts without SSH. Restore by importing the file
 * into an empty database that already has the tables (run the migrations first), e.g. with phpMyAdmin.
 */
class BackupController extends Controller
{
    /** Parents before children, so the file also restores with foreign key checks on. */
    public const TABLES = [
        'users', 'company_settings', 'account_groups', 'currencies', 'exchange_rates', 'cost_centres', 'ledgers',
        'voucher_types', 'stock_groups', 'units', 'godowns', 'stock_items', 'stock_openings',
        'vouchers', 'voucher_entries', 'cost_allocations', 'invoice_lines', 'bill_allocations', 'stock_movements',
        'budgets', 'budget_lines', 'activity_logs', 'migrations',
    ];

    public function __invoke(): StreamedResponse
    {
        Audit::log('backup', 'Company', null, 'Backup downloaded');

        $filename = 'backup-'.now()->format('Y-m-d-His').'.sql';

        return response()->streamDownload(function () {
            $pdo = DB::connection()->getPdo();
            $mysql = DB::connection()->getDriverName() === 'mysql';
            $quoteId = fn (string $name) => $mysql ? "`{$name}`" : "\"{$name}\"";

            echo '-- Cloud Accounting backup '.now()->toDateTimeString()."\n";
            echo "-- Restore into a database created by the same app version (tables must exist).\n";
            if ($mysql) {
                echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
            }

            foreach (array_reverse(self::TABLES) as $table) {
                echo 'DELETE FROM '.$quoteId($table).";\n";
            }

            foreach (self::TABLES as $table) {
                echo "\n-- {$table}\n";
                DB::table($table)->orderBy($table === 'migrations' ? 'id' : 'id')->chunk(500, function ($rows) use ($table, $pdo, $quoteId) {
                    $columns = null;
                    $values = [];
                    foreach ($rows as $row) {
                        $row = (array) $row;
                        $columns ??= implode(', ', array_map($quoteId, array_keys($row)));
                        $values[] = '('.implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), $row)).')';
                    }
                    if ($values) {
                        echo 'INSERT INTO '.$quoteId($table)." ({$columns}) VALUES\n".implode(",\n", $values).";\n";
                    }
                });
            }

            if ($mysql) {
                echo "\nSET FOREIGN_KEY_CHECKS=1;\n";
            }
        }, $filename, ['Content-Type' => 'application/sql; charset=utf-8']);
    }
}
