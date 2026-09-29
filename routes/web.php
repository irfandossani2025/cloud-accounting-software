<?php

use App\Http\Controllers\VoucherPrintController;
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

Route::middleware(['setup', 'auth'])->group(function () {
    Route::get('/', Livewire\Gateway::class)->name('gateway');

    Route::get('/company', Livewire\CompanyForm::class)->name('company.edit');

    Route::get('/groups', Livewire\Masters\GroupIndex::class)->name('groups.index');
    Route::get('/groups/create', Livewire\Masters\GroupForm::class)->name('groups.create');
    Route::get('/groups/{group}/edit', Livewire\Masters\GroupForm::class)->name('groups.edit');

    Route::get('/ledgers', Livewire\Masters\LedgerIndex::class)->name('ledgers.index');
    Route::get('/ledgers/create', Livewire\Masters\LedgerForm::class)->name('ledgers.create');
    Route::get('/ledgers/{ledger}/edit', Livewire\Masters\LedgerForm::class)->name('ledgers.edit');

    Route::get('/vouchers/create/{type}', Livewire\Vouchers\VoucherForm::class)->name('vouchers.create');
    Route::get('/vouchers/{voucher}/edit', Livewire\Vouchers\VoucherForm::class)->name('vouchers.edit');
    Route::get('/vouchers/{voucher}/print', VoucherPrintController::class)->name('vouchers.print');
    Route::get('/invoices/create/{type}', Livewire\Vouchers\InvoiceForm::class)->name('invoices.create');
    Route::get('/invoices/{voucher}/edit', Livewire\Vouchers\InvoiceForm::class)->name('invoices.edit');

    Route::get('/reports/day-book', Livewire\Reports\DayBook::class)->name('reports.day-book');
    Route::get('/reports/trial-balance', Livewire\Reports\TrialBalance::class)->name('reports.trial-balance');
    Route::get('/reports/profit-loss', Livewire\Reports\ProfitLoss::class)->name('reports.profit-loss');
    Route::get('/reports/balance-sheet', Livewire\Reports\BalanceSheet::class)->name('reports.balance-sheet');
    Route::get('/reports/outstanding/{kind}', Livewire\Reports\Outstanding::class)->whereIn('kind', ['receivables', 'payables'])->name('reports.outstanding');
    Route::get('/reports/vat-return', Livewire\Reports\VatReturn::class)->name('reports.vat-return');
    Route::get('/reports/ledger/{ledger}', Livewire\Reports\LedgerStatement::class)->name('reports.ledger');
});
