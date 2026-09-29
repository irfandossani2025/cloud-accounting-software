<?php

namespace Tests\Feature;

use App\Livewire\Vouchers\VoucherForm;
use App\Models\AccountGroup;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherType;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VoucherFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        CompanySetting::query()->create(['name' => 'Test LLC', 'financial_year_start' => '2026-01-01', 'books_begin_from' => '2026-01-01']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_sales_invoice_with_vat_is_filled_and_saved(): void
    {
        $customer = Ledger::query()->create(['name' => 'Al Noor Trading', 'account_group_id' => AccountGroup::reserved('Sundry Debtors')->id]);
        $sales = Ledger::query()->where('name', 'Sales - Standard Rated')->first();
        $type = VoucherType::query()->where('name', 'Sales')->first();

        Livewire::test(VoucherForm::class, ['type' => $type])
            ->set('rows.0.ledger_id', (string) $customer->id)
            ->set('rows.1.ledger_id', (string) $sales->id)
            ->set('rows.1.amount', '200')
            ->call('applyVat')
            ->assertHasNoErrors()
            ->assertSet('rows.0.amount', '210.000')
            ->assertSet('rows.2.amount', '10.000')
            ->call('save')
            ->assertHasNoErrors();

        $voucher = Voucher::query()->with('entries')->sole();
        $this->assertSame('INV-1', $voucher->number);
        $this->assertSame('210.000', $voucher->total);
        $this->assertSame($customer->id, $voucher->party_ledger_id);
        $this->assertCount(3, $voucher->entries);
    }

    public function test_reverse_charge_import_adds_both_vat_lines(): void
    {
        $supplier = Ledger::query()->create(['name' => 'Overseas Supplier', 'account_group_id' => AccountGroup::reserved('Sundry Creditors')->id]);
        $imports = Ledger::query()->where('name', 'Purchases - Imports (Reverse Charge)')->first();
        $type = VoucherType::query()->where('name', 'Purchase')->first();

        Livewire::test(VoucherForm::class, ['type' => $type])
            ->set('rows.0.ledger_id', (string) $supplier->id)
            ->set('rows.1.ledger_id', (string) $imports->id)
            ->set('rows.1.amount', '1000')
            ->call('applyVat')
            ->assertHasNoErrors()
            ->assertSet('rows.0.amount', '1000.000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertCount(4, Voucher::query()->sole()->entries);
    }
}
