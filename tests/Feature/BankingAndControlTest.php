<?php

namespace Tests\Feature;

use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\CompanySetting;
use App\Models\CostCentre;
use App\Models\Currency;
use App\Models\Ledger;
use App\Models\VoucherType;
use App\Services\BankReconciliationService;
use App\Services\BudgetService;
use App\Services\CostCentreReportService;
use App\Services\ForexService;
use App\Services\InvoiceService;
use App\Services\VoucherService;
use App\Support\Money;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BankingAndControlTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $bank;

    private Ledger $rent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        CompanySetting::query()->create(['name' => 'Test LLC', 'financial_year_start' => '2026-01-01', 'books_begin_from' => '2026-01-01']);

        $this->bank = Ledger::query()->create(['name' => 'Bank Muscat', 'account_group_id' => AccountGroup::reserved('Bank Accounts')->id, 'opening_balance' => '1000']);
        Ledger::query()->create(['name' => 'Capital', 'account_group_id' => AccountGroup::reserved('Capital Account')->id, 'opening_balance' => '-1000']);
        $this->rent = Ledger::query()->create(['name' => 'Rent', 'account_group_id' => AccountGroup::reserved('Indirect Expenses')->id, 'cost_centres_applicable' => true]);
    }

    public function test_bank_reconciliation_statement(): void
    {
        $cheque = $this->payment('300', '2026-03-10', ['instrument_type' => 'cheque', 'instrument_no' => '000123', 'instrument_date' => '2026-03-10']);
        $this->payment('100', '2026-03-12');

        $service = app(BankReconciliationService::class);
        $asOf = Carbon::parse('2026-03-31');

        $brs = $service->statement($this->bank, $asOf);
        $this->assertSame(Money::toBaisa('600'), $brs['booksBalance']);
        $this->assertSame(Money::toBaisa('1000'), $brs['bankBalance']);
        $this->assertCount(2, $brs['entries']);
        $this->assertSame('000123', $brs['entries'][0]->instrument_no);

        $bankEntry = $cheque->entries->firstWhere('ledger_id', $this->bank->id);
        $service->reconcile($this->bank, [$bankEntry->id => '2026-03-15']);

        $brs = $service->statement($this->bank, $asOf);
        $this->assertCount(1, $brs['entries']);
        $this->assertSame(Money::toBaisa('700'), $brs['bankBalance']);

        // Cleared after the statement date: still outstanding on 14 March.
        $this->assertCount(2, $service->statement($this->bank, Carbon::parse('2026-03-14'))['entries']);

        // Editing the voucher keeps the bank date.
        app(VoucherService::class)->save([
            'voucher_type_id' => $cheque->voucher_type_id, 'date' => '2026-03-10',
            'narration' => 'March rent',
            'entries' => [
                ['ledger_id' => $this->rent->id, 'debit' => '300'],
                ['ledger_id' => $this->bank->id, 'credit' => '300', 'instrument_no' => '000123'],
            ],
        ], $cheque);
        $newEntry = $cheque->fresh()->entries->firstWhere('ledger_id', $this->bank->id);
        $this->assertSame('2026-03-15', $newEntry->bank_date->toDateString());

        try {
            $service->reconcile($this->bank, [$newEntry->id => '2026-03-01']);
            $this->fail('A bank date before the voucher date must be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('before the voucher date', $e->errors()["bankDates.{$newEntry->id}"][0]);
        }
    }

    public function test_post_dated_cheques_do_not_affect_todays_books(): void
    {
        $this->payment('250', '2026-06-30', ['instrument_type' => 'cheque', 'instrument_no' => '555']);

        $pdc = app(BankReconciliationService::class)->postDated(Carbon::parse('2026-06-01'));
        $this->assertCount(1, $pdc);

        $brs = app(BankReconciliationService::class)->statement($this->bank, Carbon::parse('2026-06-01'));
        $this->assertSame(Money::toBaisa('1000'), $brs['booksBalance']);
    }

    public function test_cost_centre_allocation_and_report(): void
    {
        [$muscat, $sohar] = [CostCentre::query()->create(['name' => 'Muscat branch']), CostCentre::query()->create(['name' => 'Sohar branch'])];

        $this->payment('300', '2026-03-10', [], [
            ['cost_centre_id' => $muscat->id, 'amount' => '200'],
            ['cost_centre_id' => $sohar->id, 'amount' => '60'],
        ]);

        $report = app(CostCentreReportService::class)->report(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        $this->assertSame(Money::toBaisa('200'), $report['centres']->firstWhere('centre.id', $muscat->id)->expenses);
        $this->assertSame(Money::toBaisa('60'), $report['centres']->firstWhere('centre.id', $sohar->id)->expenses);
        $this->assertSame(Money::toBaisa('40'), $report['unallocated']->expenses);

        $this->expectException(ValidationException::class);
        $this->payment('100', '2026-03-10', [], [['cost_centre_id' => $muscat->id, 'amount' => '150']]);
    }

    public function test_budget_variance(): void
    {
        $this->payment('300', '2026-03-10');
        $budget = Budget::query()->create(['name' => 'FY2026', 'from_date' => '2026-01-01', 'to_date' => '2026-12-31']);
        $budget->lines()->create(['ledger_id' => $this->rent->id, 'amount' => '1200']);
        $budget->lines()->create(['account_group_id' => AccountGroup::reserved('Sales Accounts')->id, 'amount' => '5000']);

        $rows = app(BudgetService::class)->variance($budget)['rows'];
        $this->assertSame(Money::toBaisa('300'), $rows[0]->actual);
        $this->assertSame(Money::toBaisa('-900'), $rows[0]->variance);
        $this->assertEquals(25.0, $rows[0]->percent);
        $this->assertSame(0, $rows[1]->actual);
    }

    public function test_foreign_currency_invoice_and_revaluation(): void
    {
        $usd = Currency::query()->where('code', 'USD')->first();
        $customer = Ledger::query()->create([
            'name' => 'US Client', 'account_group_id' => AccountGroup::reserved('Sundry Debtors')->id, 'currency_id' => $usd->id,
        ]);

        // USD 1,000 standard rated at 0.385: OMR 385.000 + VAT 19.250 (5% of the OMR value).
        $invoice = app(InvoiceService::class)->save([
            'voucher_type_id' => VoucherType::query()->where('name', 'Sales')->value('id'),
            'date' => '2026-03-01',
            'party_ledger_id' => $customer->id,
            'currency_id' => $usd->id,
            'fx_rate' => '0.385',
            'lines' => [['ledger_id' => Ledger::query()->where('name', 'Sales - Standard Rated')->value('id'), 'rate' => '1000']],
        ]);

        $entries = $invoice->entries->keyBy('ledger_id');
        $this->assertSame('404.250', $entries[$customer->id]->debit);
        $this->assertSame('1050.000', $entries[$customer->id]->fx_amount);
        $this->assertSame('19.250', $entries[Ledger::query()->where('name', 'Output VAT 5%')->value('id')]->credit);

        // Rate moves to 0.384497 (seeded peg): USD 1,050 is now OMR 403.722, a loss of 0.528.
        $position = app(ForexService::class)->position(Carbon::parse('2026-03-31'));
        $row = $position['rows']->firstWhere('ledger.id', $customer->id);
        $this->assertSame(1050000, $row->fxBalance);
        $this->assertSame(Money::toBaisa('403.722'), $row->revalued);
        $this->assertSame(Money::toBaisa('-0.528'), $row->gain);

        app(VoucherService::class)->save([
            'voucher_type_id' => VoucherType::query()->where('name', 'Journal')->value('id'),
            'date' => '2026-03-31',
            'entries' => app(ForexService::class)->revaluationEntries(Carbon::parse('2026-03-31')),
        ]);
        $this->assertSame(0, app(ForexService::class)->position(Carbon::parse('2026-03-31'))['rows']->firstWhere('ledger.id', $customer->id)->gain);
    }

    private function payment(string $amount, string $date, array $bankExtras = [], ?array $costCentres = null)
    {
        return app(VoucherService::class)->save([
            'voucher_type_id' => VoucherType::query()->where('name', 'Payment')->value('id'),
            'date' => $date,
            'entries' => [
                ['ledger_id' => $this->rent->id, 'debit' => $amount] + ($costCentres ? ['cost_centres' => $costCentres] : []),
                ['ledger_id' => $this->bank->id, 'credit' => $amount] + $bankExtras,
            ],
        ])->load('entries');
    }
}
