<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\DashboardService;
use App\Services\ReportService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\DemoTransactionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DemoTransactionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_balanced_and_loads_once(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-30 10:00'));
        CompanySetting::query()->create(['name' => 'Demo', 'financial_year_start' => '2026-01-01', 'books_begin_from' => '2026-01-01']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->seed(DemoTransactionsSeeder::class);
        $count = Voucher::query()->count();
        $this->assertGreaterThan(80, $count);

        $this->assertSame(0, app(ReportService::class)->openingDifference());

        $d = app(DashboardService::class)->summary(Carbon::parse('2026-09-30'));
        $this->assertGreaterThan(0, $d['receivablesOverdue']);
        $this->assertGreaterThan(0, $d['cashTotal']);
        $this->assertNotEmpty($d['payablesDueSoon']);
        $this->assertNotEmpty($d['postDated']);
        $this->assertNotEmpty($d['lowStock']);
        $this->assertGreaterThan(0, $d['netProfitYtd']);

        $this->seed(DemoTransactionsSeeder::class);
        $this->assertSame($count, Voucher::query()->count());

        // An interrupted run (here: everything after the receipts missing) is completed, not duplicated.
        $firstMissing = Voucher::query()->whereHas('type', fn ($q) => $q->where('name', 'Credit Note'))->value('id');
        \Illuminate\Support\Facades\DB::table('vouchers')->where('id', '>=', $firstMissing)->delete();
        $this->assertLessThan($count, Voucher::query()->count());
        $this->seed(DemoTransactionsSeeder::class);
        $this->assertSame($count, Voucher::query()->count());
        $this->assertSame('CAN', Voucher::query()->whereNotNull('original_voucher_id')->sole()->issuance_reason);
        $this->assertSame(0, app(ReportService::class)->openingDifference());

        foreach (['dashboard', 'gateway', 'reports.day-book', 'reports.trial-balance', 'reports.profit-loss', 'reports.balance-sheet', 'reports.vat-return', 'reports.stock-summary'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }
}
