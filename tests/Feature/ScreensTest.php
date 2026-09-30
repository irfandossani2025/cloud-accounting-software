<?php

namespace Tests\Feature;

use App\Livewire\Inventory\InventoryVoucherForm;
use App\Livewire\Vouchers\InvoiceForm;
use App\Livewire\Vouchers\VoucherForm;
use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\StockItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Services\StockService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ScreensTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        CompanySetting::query()->create([
            'name' => 'Test LLC', 'name_ar' => 'شركة الاختبار', 'vatin' => 'OM1100000001',
            'financial_year_start' => '2026-01-01', 'books_begin_from' => '2026-01-01',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->customer = Ledger::query()->create([
            'name' => 'Al Noor Trading', 'account_group_id' => AccountGroup::reserved('Sundry Debtors')->id, 'is_bill_wise' => true,
        ]);
    }

    public function test_invoice_form_saves_and_prints_a_bilingual_tax_invoice(): void
    {
        $sales = VoucherType::query()->where('name', 'Sales')->first();
        $ledger = Ledger::query()->where('name', 'Sales - Standard Rated')->first();

        Livewire::test(InvoiceForm::class, ['type' => $sales])
            ->set('date', '2026-03-01')
            ->set('party_ledger_id', $this->customer->id)
            ->set('lines.0.ledger_id', (string) $ledger->id)
            ->assertSet('lines.0.vat_category', 'standard')
            ->set('lines.0.description', 'Office chairs')
            ->set('lines.0.quantity', '4')
            ->set('lines.0.rate', '25.500')
            ->call('save')
            ->assertHasNoErrors();

        $voucher = Voucher::query()->sole();
        $this->assertSame('107.100', $voucher->total); // 102.000 + 5.100

        $this->get(route('vouchers.print', $voucher))
            ->assertOk()
            ->assertSee('Tax Invoice')
            ->assertSee('فاتورة ضريبية', false)
            ->assertSee('OM1100000001')
            ->assertSee('Office chairs')
            ->assertSee('107.100')
            ->assertSee('One Hundred Seven Omani Rials and One Hundred Baisa Only');

        // Accounting-mode edit redirects to invoice mode.
        Livewire::test(VoucherForm::class, ['voucher' => $voucher])->assertRedirect(route('invoices.edit', $voucher));
    }

    public function test_receipt_allocates_bills_fifo(): void
    {
        $sales = VoucherType::query()->where('name', 'Sales')->first();
        $ledger = Ledger::query()->where('name', 'Sales - Standard Rated')->first();
        foreach (['100', '200'] as $rate) {
            Livewire::test(InvoiceForm::class, ['type' => $sales])
                ->set('date', '2026-03-01')->set('party_ledger_id', $this->customer->id)
                ->set('lines.0.ledger_id', (string) $ledger->id)->set('lines.0.rate', $rate)
                ->call('save')->assertHasNoErrors();
        }

        $receipt = VoucherType::query()->where('name', 'Receipt')->first();
        Livewire::test(VoucherForm::class, ['type' => $receipt])
            ->set('date', '2026-04-01')
            ->set('rows.0.ledger_id', (string) $this->customer->id)
            ->set('rows.0.amount', '150')
            ->call('allocateBills', 0)
            ->assertSet('rows.0.bills.0.reference', 'INV-1')
            ->assertSet('rows.0.bills.0.amount', '105.000')
            ->assertSet('rows.0.bills.1.reference', 'INV-2')
            ->assertSet('rows.0.bills.1.amount', '45.000')
            ->set('rows.1.ledger_id', (string) Ledger::query()->where('name', 'Cash')->value('id'))
            ->call('save')
            ->assertHasNoErrors();

        $this->get(route('reports.outstanding', 'receivables').'?to=2026-04-30')
            ->assertOk()
            ->assertSee('INV-2')
            ->assertDontSee('INV-1')
            ->assertSee('165.000');
    }

    public function test_invoice_line_autofills_from_stock_item_and_moves_stock(): void
    {
        $item = StockItem::query()->create([
            'name' => 'Office Chair', 'name_ar' => 'كرسي مكتب',
            'unit_id' => Unit::query()->where('symbol', 'Pcs')->value('id'),
            'sales_ledger_id' => Ledger::query()->where('name', 'Sales - Standard Rated')->value('id'),
            'sales_rate' => '25.500', 'vat_category' => 'standard',
        ]);

        Livewire::test(InvoiceForm::class, ['type' => VoucherType::query()->where('name', 'Sales')->first()])
            ->set('date', '2026-03-01')
            ->set('party_ledger_id', $this->customer->id)
            ->set('lines.0.stock_item_id', (string) $item->id)
            ->assertSet('lines.0.description', 'Office Chair')
            ->assertSet('lines.0.description_ar', 'كرسي مكتب')
            ->assertSet('lines.0.unit', 'Pcs')
            ->assertSet('lines.0.rate', '25.500')
            ->assertSet('lines.0.vat_category', 'standard')
            ->set('lines.0.quantity', '2')
            ->assertSee('goes negative')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(-2000, app(StockService::class)->available($item, Carbon::parse('2026-03-01')));
    }

    public function test_inventory_voucher_screens(): void
    {
        $item = StockItem::query()->create(['name' => 'Widget', 'unit_id' => Unit::query()->value('id')]);
        $journal = VoucherType::query()->where('name', 'Physical Stock')->first();

        Livewire::test(InventoryVoucherForm::class, ['type' => $journal])
            ->set('date', '2026-03-01')
            ->set('source.0.stock_item_id', (string) $item->id)
            ->set('source.0.quantity', '5')
            ->call('save')
            ->assertHasNoErrors();

        $voucher = Voucher::query()->sole();
        $this->get(route('reports.day-book', ['from' => '2026-03-01', 'to' => '2026-03-01']))->assertOk()->assertSee('PS-1');

        Livewire::test(InventoryVoucherForm::class, ['voucher' => $voucher])
            ->assertSet('source.0.quantity', '5');

        $this->get(route('reports.stock-summary', ['from' => '2026-01-01', 'to' => '2026-12-31']))->assertOk()->assertSee('Widget');
        $this->get(route('reports.stock-item', $item))->assertOk();
        $this->get(route('stock-items.index'))->assertOk()->assertSee('Widget');
        $this->get(route('stock-items.edit', $item))->assertOk();
        foreach (['stock-groups', 'units', 'godowns'] as $kind) {
            $this->get(route('inventory.masters', $kind))->assertOk();
        }
    }

    public function test_banking_and_control_pages_render(): void
    {
        $bank = Ledger::query()->create(['name' => 'Bank Muscat', 'account_group_id' => AccountGroup::reserved('Bank Accounts')->id]);
        $budget = Budget::query()->create(['name' => 'FY', 'from_date' => '2026-01-01', 'to_date' => '2026-12-31']);
        $budget->lines()->create(['account_group_id' => AccountGroup::reserved('Indirect Expenses')->id, 'amount' => '100']);

        $this->get(route('reports.bank-reconciliation'))->assertOk()->assertSee('Bank Muscat');
        $this->get(route('reports.bank-reconciliation', $bank))->assertOk();
        $this->get(route('reports.post-dated'))->assertOk();
        $this->get(route('reports.cost-centres'))->assertOk();
        $this->get(route('reports.forex'))->assertOk();
        $this->get(route('reports.budget', $budget))->assertOk()->assertSee('Indirect Expenses');
        $this->get(route('budgets.index'))->assertOk();
        $this->get(route('budgets.edit', $budget))->assertOk();
        $this->get(route('currencies.index'))->assertOk()->assertSee('USD');
        $this->get(route('inventory.masters', 'cost-centres'))->assertOk();

        // Voucher line details appear for a bank ledger.
        Livewire::test(VoucherForm::class, ['type' => VoucherType::query()->where('name', 'Payment')->first()])
            ->set('rows.1.ledger_id', (string) $bank->id)
            ->assertSee('Instrument');
    }

    public function test_cancel_buttons_call_cancel_instead_of_submitting_the_form(): void
    {
        $sales = VoucherType::query()->where('name', 'Sales')->first();
        $ledger = Ledger::query()->where('name', 'Sales - Standard Rated')->first();
        Livewire::test(InvoiceForm::class, ['type' => $sales])
            ->set('date', '2026-03-01')->set('party_ledger_id', $this->customer->id)
            ->set('lines.0.ledger_id', (string) $ledger->id)->set('lines.0.rate', '10')
            ->call('save')->assertHasNoErrors();
        $invoice = Voucher::query()->sole();

        $journal = app(\App\Services\VoucherService::class)->save([
            'voucher_type_id' => VoucherType::query()->where('name', 'Journal')->value('id'),
            'date' => '2026-03-01',
            'entries' => [
                ['ledger_id' => Ledger::query()->where('name', 'Cash')->value('id'), 'debit' => '5'],
                ['ledger_id' => $ledger->id, 'credit' => '5'],
            ],
        ]);
        $item = \App\Models\StockItem::query()->create(['name' => 'Widget', 'unit_id' => \App\Models\Unit::query()->value('id')]);
        $count = app(\App\Services\InventoryVoucherService::class)->savePhysicalStock([
            'voucher_type_id' => VoucherType::query()->where('name', 'Physical Stock')->value('id'),
            'date' => '2026-03-01',
            'lines' => [['stock_item_id' => $item->id, 'quantity' => '1']],
        ]);

        // A malformed type attribute makes the browser treat the button as "submit", which saves instead of cancelling.
        foreach ([route('invoices.edit', $invoice), route('vouchers.edit', $journal), route('inventory-vouchers.edit', $count)] as $url) {
            $this->get($url)->assertOk()->assertSee('<button type="button" wire:click="cancelVoucher"', false);
        }
    }

    public function test_report_pages_render(): void
    {
        foreach (['gateway', 'reports.day-book', 'reports.trial-balance', 'reports.profit-loss', 'reports.balance-sheet', 'reports.vat-return', 'ledgers.index', 'groups.index', 'company.edit'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('reports.outstanding', 'payables'))->assertOk();
        $this->get(route('reports.ledger', $this->customer))->assertOk();
    }
}
