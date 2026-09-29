<?php

namespace App\Support;

use App\Models\AccountGroup;
use App\Models\User;
use App\Models\VoucherType;

/**
 * Tally-style menus: the Gateway and its sub-menus. Each item has a hotkey letter, a target
 * (a URL or another menu) and an optional permission gate.
 */
final class GatewayMenu
{
    public const TITLES = [
        'gateway' => 'Gateway',
        'create' => 'Masters Creation',
        'alter' => 'Masters Alteration',
        'chart' => 'Chart of Accounts',
        'vouchers' => 'Voucher Types',
        'banking' => 'Banking',
        'reports' => 'Display More Reports',
        'account-books' => 'Account Books',
        'statements' => 'Statements of Accounts',
        'inventory' => 'Inventory Books',
        'admin' => 'Administration',
    ];

    /**
     * @return list<array{section?: string, label?: string, key?: string, url?: string}>
     */
    public static function items(string $menu, ?User $user): array
    {
        $can = fn (?string $gate) => $gate === null || ($user?->can($gate) ?? false);
        $sub = fn (string $name) => route('gateway.menu', $name);

        $definitions = match ($menu) {
            'gateway' => [
                ['section' => 'Masters'],
                ['label' => 'Create', 'key' => 'C', 'url' => $sub('create'), 'gate' => 'manage-masters'],
                ['label' => 'Alter', 'key' => 'A', 'url' => $sub('alter'), 'gate' => 'manage-masters'],
                ['label' => 'Chart of Accounts', 'key' => 'H', 'url' => $sub('chart')],
                ['section' => 'Transactions'],
                ['label' => 'Vouchers', 'key' => 'V', 'url' => $sub('vouchers'), 'gate' => 'enter-vouchers'],
                ['label' => 'Day Book', 'key' => 'K', 'url' => route('reports.day-book')],
                ['section' => 'Utilities'],
                ['label' => 'Banking', 'key' => 'N', 'url' => $sub('banking')],
                ['section' => 'Reports'],
                ['label' => 'Balance Sheet', 'key' => 'B', 'url' => route('reports.balance-sheet')],
                ['label' => 'Profit & Loss A/c', 'key' => 'P', 'url' => route('reports.profit-loss')],
                ['label' => 'Stock Summary', 'key' => 'S', 'url' => route('reports.stock-summary')],
                ['label' => 'Trial Balance', 'key' => 'T', 'url' => route('reports.trial-balance')],
                ['label' => 'VAT Return', 'key' => 'U', 'url' => route('reports.vat-return')],
                ['label' => 'E-Invoices (Fawtara)', 'key' => 'E', 'url' => route('einvoices.index')],
                ['label' => 'Display More Reports', 'key' => 'D', 'url' => $sub('reports')],
                ['section' => 'Company'],
                ['label' => 'Administration', 'key' => 'M', 'url' => $sub('admin')],
            ],
            'create' => [
                ['section' => 'Accounting Masters'],
                ['label' => 'Group', 'key' => 'G', 'url' => route('groups.create')],
                ['label' => 'Ledger', 'key' => 'L', 'url' => route('ledgers.create')],
                ['label' => 'Currency', 'key' => 'U', 'url' => route('currencies.index')],
                ['label' => 'Cost Centre', 'key' => 'C', 'url' => route('inventory.masters', 'cost-centres')],
                ['label' => 'Budget', 'key' => 'B', 'url' => route('budgets.index')],
                ['section' => 'Inventory Masters'],
                ['label' => 'Stock Group', 'key' => 'R', 'url' => route('inventory.masters', 'stock-groups')],
                ['label' => 'Stock Item', 'key' => 'I', 'url' => route('stock-items.create')],
                ['label' => 'Unit', 'key' => 'N', 'url' => route('inventory.masters', 'units')],
                ['label' => 'Godown', 'key' => 'O', 'url' => route('inventory.masters', 'godowns')],
            ],
            'alter', 'chart' => [
                ['section' => 'Accounting Masters'],
                ['label' => 'Groups', 'key' => 'G', 'url' => route('groups.index')],
                ['label' => 'Ledgers', 'key' => 'L', 'url' => route('ledgers.index')],
                ['label' => 'Currencies', 'key' => 'U', 'url' => route('currencies.index'), 'gate' => 'manage-masters'],
                ['label' => 'Cost Centres', 'key' => 'C', 'url' => route('inventory.masters', 'cost-centres'), 'gate' => 'manage-masters'],
                ['label' => 'Budgets', 'key' => 'B', 'url' => route('budgets.index'), 'gate' => 'manage-masters'],
                ['section' => 'Inventory Masters'],
                ['label' => 'Stock Groups', 'key' => 'R', 'url' => route('inventory.masters', 'stock-groups'), 'gate' => 'manage-masters'],
                ['label' => 'Stock Items', 'key' => 'I', 'url' => route('stock-items.index')],
                ['label' => 'Units', 'key' => 'N', 'url' => route('inventory.masters', 'units'), 'gate' => 'manage-masters'],
                ['label' => 'Godowns', 'key' => 'O', 'url' => route('inventory.masters', 'godowns'), 'gate' => 'manage-masters'],
            ],
            'vouchers' => self::voucherItems(),
            'banking' => [
                ['section' => 'Banking'],
                ['label' => 'Bank Reconciliation', 'key' => 'R', 'url' => route('reports.bank-reconciliation'), 'gate' => 'reconcile'],
                ['label' => 'Post-dated Cheques', 'key' => 'P', 'url' => route('reports.post-dated')],
            ],
            'reports' => [
                ['section' => 'Accounting'],
                ['label' => 'Trial Balance', 'key' => 'T', 'url' => route('reports.trial-balance')],
                ['label' => 'Day Book', 'key' => 'D', 'url' => route('reports.day-book')],
                ['label' => 'Account Books', 'key' => 'A', 'url' => $sub('account-books')],
                ['label' => 'Statements of Accounts', 'key' => 'S', 'url' => $sub('statements')],
                ['section' => 'Inventory'],
                ['label' => 'Inventory Books', 'key' => 'I', 'url' => $sub('inventory')],
                ['section' => 'Statutory'],
                ['label' => 'VAT Return (Oman)', 'key' => 'V', 'url' => route('reports.vat-return')],
                ['label' => 'E-Invoices (Fawtara)', 'key' => 'E', 'url' => route('einvoices.index')],
                ['section' => 'Exception'],
                ['label' => 'Forex Gain/Loss', 'key' => 'F', 'url' => route('reports.forex')],
            ],
            'account-books' => [
                ['section' => 'Account Books'],
                ['label' => 'Ledger', 'key' => 'L', 'url' => route('ledgers.index')],
                ['label' => 'Group Summary (Chart of Accounts)', 'key' => 'G', 'url' => route('groups.index')],
                ['label' => 'Cash / Bank Book(s)', 'key' => 'C', 'url' => route('ledgers.index', ['group' => AccountGroup::query()->where('name', 'Bank Accounts')->value('id')])],
            ],
            'statements' => [
                ['section' => 'Outstandings'],
                ['label' => 'Bills Receivable', 'key' => 'R', 'url' => route('reports.outstanding', 'receivables')],
                ['label' => 'Bills Payable', 'key' => 'P', 'url' => route('reports.outstanding', 'payables')],
                ['section' => 'Statistics & Analysis'],
                ['label' => 'Cost Centre Break-up', 'key' => 'C', 'url' => route('reports.cost-centres')],
                ['label' => 'Budgets vs Actuals', 'key' => 'B', 'url' => route('budgets.index'), 'gate' => 'manage-masters'],
            ],
            'inventory' => [
                ['section' => 'Inventory Books'],
                ['label' => 'Stock Summary', 'key' => 'S', 'url' => route('reports.stock-summary')],
                ['label' => 'Stock Item Register', 'key' => 'I', 'url' => route('stock-items.index')],
            ],
            'admin' => [
                ['section' => 'Company'],
                ['label' => 'Company & VAT Details', 'key' => 'C', 'url' => route('company.edit'), 'gate' => 'admin'],
                ['label' => 'Users & Roles', 'key' => 'U', 'url' => route('users.index'), 'gate' => 'admin'],
                ['label' => 'Audit Trail', 'key' => 'A', 'url' => route('audit.index'), 'gate' => 'admin'],
                ['label' => 'Year-end, Period Lock & Backup', 'key' => 'Y', 'url' => route('year-end'), 'gate' => 'admin'],
                ['label' => 'Change My Password', 'key' => 'P', 'url' => route('password.edit')],
            ],
            default => abort(404),
        };

        // Drop items the user may not open, then sections left empty.
        $visible = array_values(array_filter($definitions, fn ($item) => isset($item['section']) || $can($item['gate'] ?? null)));

        return array_values(array_filter($visible, function ($item, $i) use ($visible) {
            if (! isset($item['section'])) {
                return true;
            }
            $next = $visible[$i + 1] ?? null;

            return $next !== null && ! isset($next['section']);
        }, ARRAY_FILTER_USE_BOTH));
    }

    private static function voucherItems(): array
    {
        $items = [['section' => 'Accounting Vouchers']];
        $inventory = [['section' => 'Inventory Vouchers']];

        foreach (VoucherType::query()->where('is_active', true)->orderBy('id')->get() as $type) {
            $item = ['label' => $type->name, 'key' => $type->base_type->shortcut(), 'url' => $type->createUrl(), 'fkey' => true];
            $type->base_type->isInventoryOnly() ? $inventory[] = $item : $items[] = $item;
        }

        return array_merge($items, count($inventory) > 1 ? $inventory : []);
    }

    /** Every destination the user can open, for Go To (Alt+G). */
    public static function destinations(?User $user): array
    {
        $seen = [];
        $out = [];

        foreach (array_keys(self::TITLES) as $menu) {
            foreach (self::items($menu, $user) as $item) {
                if (isset($item['url']) && ! str_contains($item['url'], '/menu/') && ! isset($seen[$item['url']])) {
                    $seen[$item['url']] = true;
                    $out[] = ['label' => $item['label'], 'group' => self::TITLES[$menu], 'url' => $item['url']];
                }
            }
        }

        return $out;
    }
}
