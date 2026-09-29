<?php

namespace Tests\Feature;

use App\Models\AccountGroup;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\VoucherType;
use App\Services\ReportService;
use App\Services\VoucherService;
use App\Support\Money;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private VoucherService $vouchers;

    private ReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);

        CompanySetting::query()->create([
            'name' => 'Test LLC',
            'financial_year_start' => '2026-01-01',
            'books_begin_from' => '2026-01-01',
        ]);

        $this->vouchers = app(VoucherService::class);
        $this->reports = app(ReportService::class);
    }

    public function test_seeds_the_28_tally_groups_and_voucher_types(): void
    {
        $this->assertSame(28, AccountGroup::query()->count());
        $this->assertSame(15, AccountGroup::query()->whereNull('parent_id')->count());
        $this->assertSame(10, VoucherType::query()->count());
    }

    public function test_unbalanced_voucher_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->vouchers->save([
            'voucher_type_id' => $this->type('Journal'),
            'date' => '2026-02-01',
            'entries' => [
                ['ledger_id' => $this->ledger('Cash'), 'debit' => '10.000'],
                ['ledger_id' => $this->ledger('Sales - Standard Rated'), 'credit' => '9.999'],
            ],
        ]);
    }

    public function test_payment_must_credit_cash_or_bank(): void
    {
        $rent = $this->makeLedger('Rent', 'Indirect Expenses');

        $this->expectException(ValidationException::class);

        $this->vouchers->save([
            'voucher_type_id' => $this->type('Payment'),
            'date' => '2026-02-01',
            'entries' => [
                ['ledger_id' => $rent, 'debit' => '100'],
                ['ledger_id' => $this->ledger('Sales - Standard Rated'), 'credit' => '100'],
            ],
        ]);
    }

    public function test_voucher_numbers_are_sequential_per_type(): void
    {
        $first = $this->cashSale('100.000');
        $second = $this->cashSale('50.000');

        $this->assertSame('INV-1', $first->number);
        $this->assertSame('INV-2', $second->number);
    }

    public function test_reports_balance_after_trading(): void
    {
        $capital = $this->makeLedger('Owner Capital', 'Capital Account', '-1000.000');
        Ledger::query()->where('name', 'Cash')->update(['opening_balance' => '1000.000']);
        $customer = $this->makeLedger('Al Noor Trading', 'Sundry Debtors');
        $rent = $this->makeLedger('Office Rent', 'Indirect Expenses');

        // Credit sale 200.000 + 5% VAT
        $this->vouchers->save([
            'voucher_type_id' => $this->type('Sales'),
            'date' => '2026-03-01',
            'entries' => [
                ['ledger_id' => $customer, 'debit' => '210.000'],
                ['ledger_id' => $this->ledger('Sales - Standard Rated'), 'credit' => '200.000'],
                ['ledger_id' => $this->ledger('Output VAT 5%'), 'credit' => '10.000'],
            ],
        ]);

        // Pay rent 50.000
        $this->vouchers->save([
            'voucher_type_id' => $this->type('Payment'),
            'date' => '2026-03-05',
            'entries' => [
                ['ledger_id' => $rent, 'debit' => '50.000'],
                ['ledger_id' => $this->ledger('Cash'), 'credit' => '50.000'],
            ],
        ]);

        $to = Carbon::parse('2026-12-31');
        $tb = $this->reports->trialBalance(Carbon::parse('2026-01-01'), $to);
        $this->assertSame($tb['debit'], $tb['credit']);
        $this->assertSame(0, $tb['openingDifference']);

        $pl = $this->reports->profitAndLoss(Carbon::parse('2026-01-01'), $to);
        $this->assertSame(Money::toBaisa('200'), $pl['grossProfit']);
        $this->assertSame(Money::toBaisa('150'), $pl['netProfit']);

        $bs = $this->reports->balanceSheet($to);
        $this->assertSame($bs['assetTotal'], $bs['liabilityTotal']);
        $this->assertSame(Money::toBaisa('1160'), $bs['assetTotal']); // cash 950 + debtor 210

        $statement = $this->reports->ledgerStatement(Ledger::query()->find($this->ledger('Cash')), Carbon::parse('2026-01-01'), $to);
        $this->assertSame(Money::toBaisa('1000'), $statement['opening']);
        $this->assertSame(Money::toBaisa('950'), $statement['closing']);
        $this->assertNotNull($capital);
    }

    public function test_prior_year_profit_rolls_into_profit_and_loss_account(): void
    {
        $this->cashSale('100.000', '2026-06-01');

        $bs = $this->reports->balanceSheet(Carbon::parse('2027-06-30'));

        $this->assertSame(Money::toBaisa('100'), $bs['plOpening']);
        $this->assertSame(0, $bs['netProfit']);
        $this->assertSame($bs['assetTotal'], $bs['liabilityTotal']);
    }

    public function test_cancelled_voucher_has_no_effect(): void
    {
        $voucher = $this->cashSale('100.000');
        $this->vouchers->cancel($voucher);

        $pl = $this->reports->profitAndLoss(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        $this->assertSame(0, $pl['netProfit']);
    }

    public function test_vouchers_on_the_boundary_date_are_included(): void
    {
        $this->cashSale('100.000', '2026-03-15');
        $day = Carbon::parse('2026-03-15');

        $this->assertCount(1, $this->reports->dayBook($day, $day));
        $this->assertSame(Money::toBaisa('100'), $this->reports->profitAndLoss(Carbon::parse('2026-01-01'), $day)['netProfit']);
    }

    private function cashSale(string $amount, string $date = '2026-02-01')
    {
        return $this->vouchers->save([
            'voucher_type_id' => $this->type('Sales'),
            'date' => $date,
            'entries' => [
                ['ledger_id' => $this->ledger('Cash'), 'debit' => $amount],
                ['ledger_id' => $this->ledger('Sales - Standard Rated'), 'credit' => $amount],
            ],
        ]);
    }

    private function type(string $name): int
    {
        return VoucherType::query()->where('name', $name)->value('id');
    }

    private function ledger(string $name): int
    {
        return Ledger::query()->where('name', $name)->value('id');
    }

    private function makeLedger(string $name, string $group, string $opening = '0'): int
    {
        return Ledger::query()->create([
            'name' => $name,
            'account_group_id' => AccountGroup::reserved($group)->id,
            'opening_balance' => $opening,
        ])->id;
    }
}
