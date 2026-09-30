<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountGroup;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\StockItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\VoucherType;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use App\Support\Money;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_figures(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $this->travelTo(Carbon::parse('2026-06-20 10:00'));
        CompanySetting::query()->create(['name' => 'Test', 'financial_year_start' => '2026-01-01', 'books_begin_from' => '2026-01-01']);
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        $customer = Ledger::query()->create(['name' => 'Customer', 'account_group_id' => AccountGroup::reserved('Sundry Debtors')->id, 'is_bill_wise' => true, 'credit_days' => 30]);
        $supplier = Ledger::query()->create(['name' => 'Supplier', 'account_group_id' => AccountGroup::reserved('Sundry Creditors')->id, 'is_bill_wise' => true, 'credit_days' => 10]);
        $sales = Ledger::query()->where('name', 'Sales - Standard Rated')->value('id');
        $purchases = Ledger::query()->where('name', 'Purchases - Standard Rated')->value('id');
        $invoice = fn (string $type, Ledger $party, string $date, string $rate, int $ledger) => app(InvoiceService::class)->save([
            'voucher_type_id' => VoucherType::query()->where('name', $type)->value('id'),
            'date' => $date, 'party_ledger_id' => $party->id, 'lines' => [['ledger_id' => $ledger, 'rate' => $rate]],
        ]);

        $invoice('Sales', $customer, '2026-04-10', '100', $sales);   // due 10 May: overdue on 20 June
        $invoice('Sales', $customer, '2026-05-15', '200', $sales);   // due 14 June: overdue; also in "same days last month"
        $invoice('Sales', $customer, '2026-06-05', '300', $sales);   // this month
        $invoice('Purchase', $supplier, '2026-06-18', '50', $purchases); // due 28 June: within 14 days

        StockItem::query()->create(['name' => 'Widget', 'unit_id' => Unit::query()->value('id'), 'reorder_level' => '5']);

        $d = app(DashboardService::class)->summary(Carbon::parse('2026-06-20'));

        $this->assertSame(Money::toBaisa('630'), $d['receivables']);          // 105 + 210 + 315
        $this->assertSame(Money::toBaisa('315'), $d['receivablesOverdue']);   // April (due 10 May) and May (due 14 June) invoices
        $this->assertSame(Money::toBaisa('52.5'), $d['payables']);
        $this->assertCount(1, $d['payablesDueSoon']);
        $this->assertSame(Money::toBaisa('300'), $d['salesThisMonth']);
        $this->assertSame(Money::toBaisa('200'), $d['salesLastMonthToDate']);
        $this->assertSame(Money::toBaisa('27.5'), $d['vatDue']);              // Q2: output 30 - input 2.5
        $this->assertSame('Customer', $d['topOverdue'][0]->ledger->name);
        $this->assertCount(1, $d['lowStock']);

        $june = collect($d['monthly'])->first(fn ($m) => $m['month']->format('Y-m') === '2026-06');
        $this->assertCount(12, $d['monthly']);
        $this->assertSame(Money::toBaisa('300'), $june['sales']);
        $this->assertSame(Money::toBaisa('50'), $june['expenses']);

        $this->get(route('dashboard'))->assertOk()->assertSeeText('Sales and expenses')->assertSeeText('50% vs same days last month');
        $this->get(route('gateway'))->assertOk()->assertSeeText('315.000 overdue');
    }
}
