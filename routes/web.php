<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\VoucherPrintController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/setup', Livewire\Setup::class)->name('setup');

Route::middleware('guest')->group(function () {
    Route::get('/login', Livewire\Auth\Login::class)->name('login');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::middleware(['setup', 'auth', EnsureUserIsActive::class])->group(function () {
    // Everyone signed in: gateway, reports, printing, own password.
    Route::get('/', Livewire\Gateway::class)->name('gateway');
    Route::get('/menu/{menu}', Livewire\Gateway::class)->name('gateway.menu');
    Route::get('/account/password', Livewire\Admin\ChangePassword::class)->name('password.edit');
    Route::get('/vouchers/{voucher}/print', VoucherPrintController::class)->name('vouchers.print');

    Route::get('/reports/day-book', Livewire\Reports\DayBook::class)->name('reports.day-book');
    Route::get('/reports/trial-balance', Livewire\Reports\TrialBalance::class)->name('reports.trial-balance');
    Route::get('/reports/profit-loss', Livewire\Reports\ProfitLoss::class)->name('reports.profit-loss');
    Route::get('/reports/balance-sheet', Livewire\Reports\BalanceSheet::class)->name('reports.balance-sheet');
    Route::get('/reports/outstanding/{kind}', Livewire\Reports\Outstanding::class)->whereIn('kind', ['receivables', 'payables'])->name('reports.outstanding');
    Route::get('/reports/vat-return', Livewire\Reports\VatReturn::class)->name('reports.vat-return');
    Route::get('/reports/stock-summary', Livewire\Reports\StockSummary::class)->name('reports.stock-summary');
    Route::get('/reports/stock-item/{item}', Livewire\Reports\StockItemRegister::class)->name('reports.stock-item');
    Route::get('/reports/post-dated', Livewire\Reports\PostDated::class)->name('reports.post-dated');
    Route::get('/reports/cost-centres', Livewire\Reports\CostCentres::class)->name('reports.cost-centres');
    Route::get('/reports/budget/{budget}', Livewire\Reports\BudgetVariance::class)->name('reports.budget');
    Route::get('/reports/forex', Livewire\Reports\ForexPosition::class)->name('reports.forex');
    Route::get('/reports/ledger/{ledger}', Livewire\Reports\LedgerStatement::class)->name('reports.ledger');
    Route::get('/ledgers', Livewire\Masters\LedgerIndex::class)->name('ledgers.index');
    Route::get('/groups', Livewire\Masters\GroupIndex::class)->name('groups.index');
    Route::get('/stock-items', Livewire\Inventory\StockItemIndex::class)->name('stock-items.index');

    // Voucher entry. Altering is checked per voucher (data entry users may alter only their own).
    Route::middleware('can:enter-vouchers')->group(function () {
        Route::get('/vouchers/create/{type}', Livewire\Vouchers\VoucherForm::class)->name('vouchers.create');
        Route::get('/vouchers/{voucher}/edit', Livewire\Vouchers\VoucherForm::class)->name('vouchers.edit');
        Route::get('/invoices/create/{type}', Livewire\Vouchers\InvoiceForm::class)->name('invoices.create');
        Route::get('/invoices/{voucher}/edit', Livewire\Vouchers\InvoiceForm::class)->name('invoices.edit');
        Route::get('/inventory-vouchers/create/{type}', Livewire\Inventory\InventoryVoucherForm::class)->name('inventory-vouchers.create');
        Route::get('/inventory-vouchers/{voucher}/edit', Livewire\Inventory\InventoryVoucherForm::class)->name('inventory-vouchers.edit');
    });

    // Masters and accounting control.
    Route::middleware('can:manage-masters')->group(function () {
        Route::get('/groups/create', Livewire\Masters\GroupForm::class)->name('groups.create');
        Route::get('/groups/{group}/edit', Livewire\Masters\GroupForm::class)->name('groups.edit');
        Route::get('/ledgers/create', Livewire\Masters\LedgerForm::class)->name('ledgers.create');
        Route::get('/ledgers/{ledger}/edit', Livewire\Masters\LedgerForm::class)->name('ledgers.edit');
        Route::get('/inventory/{kind}', Livewire\Inventory\SimpleMaster::class)->whereIn('kind', ['stock-groups', 'units', 'godowns', 'cost-centres'])->name('inventory.masters');
        Route::get('/stock-items/create', Livewire\Inventory\StockItemForm::class)->name('stock-items.create');
        Route::get('/stock-items/{item}/edit', Livewire\Inventory\StockItemForm::class)->name('stock-items.edit');
        Route::get('/currencies', Livewire\Masters\CurrencyRates::class)->name('currencies.index');
        Route::get('/budgets', Livewire\Masters\BudgetForm::class)->name('budgets.index');
        Route::get('/budgets/{budget}', Livewire\Masters\BudgetForm::class)->name('budgets.edit');
    });

    Route::get('/reports/bank-reconciliation/{ledger?}', Livewire\Reports\BankReconciliation::class)->middleware('can:reconcile')->name('reports.bank-reconciliation');

    // Administration.
    Route::middleware('can:admin')->group(function () {
        Route::get('/company', Livewire\CompanyForm::class)->name('company.edit');
        Route::get('/users', Livewire\Admin\Users::class)->name('users.index');
        Route::get('/audit', Livewire\Admin\AuditTrail::class)->name('audit.index');
        Route::get('/year-end', Livewire\Admin\YearEnd::class)->name('year-end');
        Route::get('/backup', BackupController::class)->name('backup.download');
    });
});
