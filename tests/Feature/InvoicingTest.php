<?php

namespace Tests\Feature;

use App\Enums\BillType;
use App\Models\AccountGroup;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\VoucherType;
use App\Services\InvoiceService;
use App\Services\OutstandingService;
use App\Services\ReportService;
use App\Services\VatReturnService;
use App\Services\VoucherService;
use App\Support\Money;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoicingTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $customer;

    private Ledger $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        CompanySetting::query()->create(['name' => 'Test LLC', 'financial_year_start' => '2026-01-01', 'books_begin_from' => '2026-01-01']);

        $this->customer = Ledger::query()->create([
            'name' => 'Al Noor Trading', 'account_group_id' => AccountGroup::reserved('Sundry Debtors')->id,
            'is_bill_wise' => true, 'credit_days' => 30,
        ]);
        $this->supplier = Ledger::query()->create([
            'name' => 'Overseas Supplier', 'account_group_id' => AccountGroup::reserved('Sundry Creditors')->id, 'is_bill_wise' => true,
        ]);
    }

    public function test_sales_invoice_prices_lines_and_posts_vat(): void
    {
        $voucher = $this->invoice('Sales', $this->customer, [
            ['ledger_id' => $this->ledger('Sales - Standard Rated'), 'description' => 'Widget', 'quantity' => '3', 'rate' => '12.345'],
            ['ledger_id' => $this->ledger('Sales - Zero Rated'), 'description' => 'Export item', 'quantity' => '1', 'rate' => '50', 'discount' => '5'],
        ]);

        // 3 x 12.345 = 37.035; VAT 5% = 1.852 (1.85175 rounded); zero-rated 45.000
        $this->assertTrue($voucher->is_invoice);
        $this->assertSame('83.887', $voucher->total);
        $this->assertSame('2026-03-31', $voucher->due_date->toDateString());
        $this->assertSame('1.852', $voucher->invoiceLines[0]->vat_amount);
        $this->assertSame('0.000', $voucher->invoiceLines[1]->vat_amount);

        $entries = $voucher->entries->keyBy('ledger_id');
        $this->assertSame('83.887', $entries[$this->customer->id]->debit);
        $this->assertSame('1.852', $entries[$this->ledger('Output VAT 5%')]->credit);

        $bill = $voucher->bills()->sole();
        $this->assertSame(BillType::NewRef, $bill->type);
        $this->assertSame('INV-1', $bill->reference);
    }

    public function test_imports_under_reverse_charge_self_assess_vat(): void
    {
        $voucher = $this->invoice('Purchase', $this->supplier, [
            ['ledger_id' => $this->ledger('Purchases - Imports (Reverse Charge)'), 'description' => 'Consulting', 'quantity' => '1', 'rate' => '1000'],
        ], ['reference' => 'SUP-778']);

        $entries = $voucher->entries->keyBy('ledger_id');
        $this->assertSame('1000.000', $entries[$this->supplier->id]->credit, 'Supplier is owed the net amount only');
        $this->assertSame('50.000', $entries[$this->ledger('Input VAT - Reverse Charge')]->debit);
        $this->assertSame('50.000', $entries[$this->ledger('Output VAT - Reverse Charge')]->credit);
        $this->assertSame('SUP-778', $voucher->bills()->sole()->reference);
    }

    public function test_credit_note_reverses_sales_and_vat(): void
    {
        $this->invoice('Sales', $this->customer, [['ledger_id' => $this->ledger('Sales - Standard Rated'), 'rate' => '200']]);
        $note = $this->invoice('Credit Note', $this->customer, [['ledger_id' => $this->ledger('Sales - Standard Rated'), 'rate' => '20']]);

        $entries = $note->entries->keyBy('ledger_id');
        $this->assertSame('21.000', $entries[$this->customer->id]->credit);
        $this->assertSame('1.000', $entries[$this->ledger('Output VAT 5%')]->debit);

        $vat = app(VatReturnService::class)->report(Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'));
        $this->assertSame(Money::toBaisa('180'), $vat['supplies']['standard']);
        $this->assertSame(Money::toBaisa('9'), $vat['netPayable']);
        $this->assertSame($vat['expectedOutputVat'], $vat['tax']['output_vat']);
    }

    public function test_receipt_against_bill_clears_outstanding(): void
    {
        $this->invoice('Sales', $this->customer, [['ledger_id' => $this->ledger('Sales - Standard Rated'), 'rate' => '100']]);  // INV-1 105.000
        $this->invoice('Sales', $this->customer, [['ledger_id' => $this->ledger('Sales - Standard Rated'), 'rate' => '200']]);  // INV-2 210.000

        app(VoucherService::class)->save([
            'voucher_type_id' => $this->type('Receipt'),
            'date' => '2026-04-10',
            'entries' => [
                ['ledger_id' => $this->ledger('Cash'), 'debit' => '155'],
                ['ledger_id' => $this->customer->id, 'credit' => '155', 'bills' => [
                    ['type' => 'against_ref', 'reference' => 'INV-1', 'amount' => '105'],
                    ['type' => 'against_ref', 'reference' => 'INV-2', 'amount' => '50'],
                ]],
            ],
        ]);

        $bills = app(OutstandingService::class)->pendingBills($this->customer, Carbon::parse('2026-04-30'));
        $this->assertCount(1, $bills);
        $this->assertSame('INV-2', $bills[0]->reference);
        $this->assertSame(Money::toBaisa('160'), $bills[0]->pending);

        $report = app(OutstandingService::class)->report('receivables', Carbon::parse('2026-04-30'));
        $this->assertSame(Money::toBaisa('160'), $report['total']);
        $this->assertSame(Money::toBaisa('160'), $report['ageing']['31-60']);
    }

    public function test_bill_allocation_must_match_amount(): void
    {
        $this->expectException(ValidationException::class);

        app(VoucherService::class)->save([
            'voucher_type_id' => $this->type('Receipt'),
            'date' => '2026-04-10',
            'entries' => [
                ['ledger_id' => $this->ledger('Cash'), 'debit' => '100'],
                ['ledger_id' => $this->customer->id, 'credit' => '100', 'bills' => [
                    ['type' => 'against_ref', 'reference' => 'INV-1', 'amount' => '90'],
                ]],
            ],
        ]);
    }

    public function test_opening_bill_and_editing_an_invoice(): void
    {
        $this->customer->update(['opening_balance' => '40.000']);
        app(VoucherService::class)->syncOpeningBill($this->customer);

        $voucher = $this->invoice('Sales', $this->customer, [['ledger_id' => $this->ledger('Sales - Standard Rated'), 'rate' => '100']]);
        app(InvoiceService::class)->save([
            'voucher_type_id' => $this->type('Sales'), 'date' => '2026-03-01', 'party_ledger_id' => $this->customer->id,
            'lines' => [['ledger_id' => $this->ledger('Sales - Standard Rated'), 'rate' => '10']],
        ], $voucher);

        $this->assertSame('INV-1', $voucher->fresh()->number);
        $this->assertCount(1, $voucher->fresh()->invoiceLines);

        $report = app(OutstandingService::class)->report('receivables', Carbon::parse('2026-03-01'));
        $this->assertSame(Money::toBaisa('50.5'), $report['total']);
        $this->assertSame(Money::toBaisa('50.5'), app(ReportService::class)->ledgerBalances(Carbon::parse('2026-01-01'), Carbon::parse('2026-03-01'))[$this->customer->id]->closing);
    }

    private function invoice(string $type, Ledger $party, array $lines, array $extra = [])
    {
        return app(InvoiceService::class)->save([
            'voucher_type_id' => $this->type($type),
            'date' => '2026-03-01',
            'party_ledger_id' => $party->id,
            'lines' => $lines,
        ] + $extra)->load('invoiceLines', 'entries');
    }

    private function type(string $name): int
    {
        return VoucherType::query()->where('name', $name)->value('id');
    }

    private function ledger(string $name): int
    {
        return Ledger::query()->where('name', $name)->value('id');
    }
}
