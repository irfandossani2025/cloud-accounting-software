<?php

namespace Tests\Feature;

use App\Models\AccountGroup;
use App\Models\CompanySetting;
use App\Models\Godown;
use App\Models\Ledger;
use App\Models\StockItem;
use App\Models\Unit;
use App\Models\VoucherType;
use App\Services\InventoryVoucherService;
use App\Services\InvoiceService;
use App\Services\ReportService;
use App\Services\StockService;
use App\Services\VoucherService;
use App\Support\Money;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private StockItem $chair;

    private Ledger $customer;

    private Ledger $supplier;

    private Godown $main;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        CompanySetting::query()->create(['name' => 'Test LLC', 'financial_year_start' => '2026-01-01', 'books_begin_from' => '2026-01-01']);

        $this->main = Godown::main();
        $this->chair = StockItem::query()->create([
            'name' => 'Office Chair',
            'unit_id' => Unit::query()->where('symbol', 'Pcs')->value('id'),
            'sales_ledger_id' => $this->ledger('Sales - Standard Rated'),
            'purchase_ledger_id' => $this->ledger('Purchases - Standard Rated'),
        ]);
        // Opening: 10 @ 4.000 = 40.000, funded by capital.
        $this->chair->openings()->create(['godown_id' => $this->main->id, 'quantity' => '10', 'rate' => '4', 'value' => '40']);
        Ledger::query()->create(['name' => 'Capital', 'account_group_id' => AccountGroup::reserved('Capital Account')->id, 'opening_balance' => '-40']);

        $this->customer = Ledger::query()->create(['name' => 'Customer', 'account_group_id' => AccountGroup::reserved('Sundry Debtors')->id]);
        $this->supplier = Ledger::query()->create(['name' => 'Supplier', 'account_group_id' => AccountGroup::reserved('Sundry Creditors')->id]);
    }

    public function test_average_cost_and_financial_statements(): void
    {
        $this->invoice('Purchase', $this->supplier, '10', '5');   // +10 @ 5.000 -> avg (40+50)/20 = 4.500
        $this->invoice('Sales', $this->customer, '12', '8');      // -12

        $position = app(StockService::class)->positions(Carbon::parse('2026-12-31'))[$this->chair->id];
        $this->assertSame(8000, $position->qty);
        $this->assertSame(4500, $position->rate);
        $this->assertSame(36000, $position->value);

        $reports = app(ReportService::class);
        $pl = $reports->profitAndLoss(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        $this->assertSame(40000, $pl['openingStock']);
        $this->assertSame(36000, $pl['closingStock']);
        $this->assertSame(Money::toBaisa('42'), $pl['grossProfit']); // 96 + 36 - 50 - 40

        $bs = $reports->balanceSheet(Carbon::parse('2026-12-31'));
        $this->assertSame(0, $bs['openingDifference']);
        $this->assertSame($bs['assetTotal'], $bs['liabilityTotal']);

        $tb = $reports->trialBalance(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        $this->assertSame($tb['debit'], $tb['credit']);

        // Next year: last year's profit, including the stock change, is carried in the P&L A/c.
        $next = $reports->balanceSheet(Carbon::parse('2027-03-31'));
        $this->assertSame(Money::toBaisa('42'), $next['plOpening']);
        $this->assertSame(0, $next['netProfit']);
        $this->assertSame($next['assetTotal'], $next['liabilityTotal']);
        $tb2 = $reports->trialBalance(Carbon::parse('2027-01-01'), Carbon::parse('2027-03-31'));
        $this->assertSame(36000, $tb2['openingStock']);
        $this->assertSame($tb2['debit'], $tb2['credit']);
    }

    public function test_purchase_return_reduces_the_cost_pool(): void
    {
        $this->invoice('Purchase', $this->supplier, '10', '5');
        $this->invoice('Debit Note', $this->supplier, '10', '5');

        $position = app(StockService::class)->positions(Carbon::parse('2026-12-31'))[$this->chair->id];
        $this->assertSame(10000, $position->qty);
        $this->assertSame(4000, $position->rate);
    }

    public function test_stock_journal_transfer_keeps_average_cost(): void
    {
        $shop = Godown::query()->create(['name' => 'Shop']);

        app(InventoryVoucherService::class)->saveStockJournal([
            'voucher_type_id' => $this->type('Stock Journal'),
            'date' => '2026-02-01',
            'source' => [['stock_item_id' => $this->chair->id, 'godown_id' => $this->main->id, 'quantity' => '4']],
            'destination' => [['stock_item_id' => $this->chair->id, 'godown_id' => $shop->id, 'quantity' => '4']],
        ]);

        $stock = app(StockService::class);
        $date = Carbon::parse('2026-02-01');
        $this->assertSame(6000, $stock->available($this->chair, $date, $this->main->id));
        $this->assertSame(4000, $stock->available($this->chair, $date, $shop->id));
        $this->assertSame(40000, $stock->closingValue($date));
        $this->assertSame(16000, $stock->positions($date, $shop->id)[$this->chair->id]->value);

        // Company-wide, a transfer is neither an inward nor an outward.
        $row = $stock->summary(Carbon::parse('2026-01-01'), $date)->firstWhere('item.id', $this->chair->id);
        $this->assertSame(0, $row->inQty);
        $this->assertSame(0, $row->outQty);
        $this->assertSame(4000, $stock->summary(Carbon::parse('2026-01-01'), $date, $shop->id)->first()->inQty);
    }

    public function test_production_consumes_raw_material(): void
    {
        $desk = StockItem::query()->create(['name' => 'Desk Set', 'unit_id' => Unit::query()->where('symbol', 'Nos')->value('id')]);

        app(InventoryVoucherService::class)->saveStockJournal([
            'voucher_type_id' => $this->type('Stock Journal'),
            'date' => '2026-02-01',
            'source' => [['stock_item_id' => $this->chair->id, 'quantity' => '2']],
            'destination' => [['stock_item_id' => $desk->id, 'quantity' => '1', 'rate' => '8']],
        ]);

        $positions = app(StockService::class)->positions(Carbon::parse('2026-02-01'));
        $this->assertSame(8000, $positions[$this->chair->id]->qty);
        $this->assertSame(8000, $positions[$desk->id]->value);
        $this->assertSame(40000, app(StockService::class)->closingValue(Carbon::parse('2026-02-01')));
    }

    public function test_physical_stock_records_the_difference(): void
    {
        $service = app(InventoryVoucherService::class);
        $data = [
            'voucher_type_id' => $this->type('Physical Stock'),
            'date' => '2026-03-01',
            'lines' => [['stock_item_id' => $this->chair->id, 'godown_id' => $this->main->id, 'quantity' => '7']],
        ];

        $voucher = $service->savePhysicalStock($data);
        $this->assertSame(7000, app(StockService::class)->available($this->chair, Carbon::parse('2026-03-01')));

        // Altering the count recalculates against the book quantity without this voucher.
        $data['lines'][0]['quantity'] = '9';
        $service->savePhysicalStock($data, $voucher);
        $this->assertSame(9000, app(StockService::class)->available($this->chair, Carbon::parse('2026-03-01')));
    }

    public function test_cancelled_invoice_releases_stock(): void
    {
        $sale = $this->invoice('Sales', $this->customer, '3', '8');
        app(VoucherService::class)->cancel($sale);

        $this->assertSame(10000, app(StockService::class)->available($this->chair, Carbon::parse('2026-12-31')));
    }

    private function invoice(string $type, Ledger $party, string $qty, string $rate)
    {
        return app(InvoiceService::class)->save([
            'voucher_type_id' => $this->type($type),
            'date' => '2026-03-01',
            'party_ledger_id' => $party->id,
            'lines' => [['stock_item_id' => $this->chair->id, 'quantity' => $qty, 'rate' => $rate, 'vat_category' => 'zero_rated']],
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
}
