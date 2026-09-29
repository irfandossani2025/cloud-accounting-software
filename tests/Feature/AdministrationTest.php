<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Admin\Users;
use App\Livewire\Admin\YearEnd;
use App\Livewire\Masters\LedgerForm;
use App\Livewire\Vouchers\VoucherForm;
use App\Models\ActivityLog;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Services\VoucherService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        CompanySetting::query()->create(['name' => 'Test LLC', 'financial_year_start' => '2026-01-01', 'books_begin_from' => '2026-01-01']);
        $this->admin = User::factory()->create(['role' => Role::Admin]);
    }

    public function test_roles_limit_what_each_user_can_open(): void
    {
        $viewer = User::factory()->create(['role' => Role::Viewer]);
        $clerk = User::factory()->create(['role' => Role::DataEntry]);
        $accountant = User::factory()->create(['role' => Role::Accountant]);
        $journal = VoucherType::query()->where('name', 'Journal')->first();

        $this->actingAs($viewer)->get(route('reports.trial-balance'))->assertOk();
        $this->actingAs($viewer)->get(route('vouchers.create', $journal))->assertForbidden();
        $this->actingAs($viewer)->get(route('gateway'))->assertOk()->assertSee('Your role can view reports only.');

        $this->actingAs($clerk)->get(route('vouchers.create', $journal))->assertOk();
        $this->actingAs($clerk)->get(route('ledgers.create'))->assertForbidden();
        $this->actingAs($clerk)->get(route('reports.bank-reconciliation'))->assertForbidden();

        $this->actingAs($accountant)->get(route('ledgers.create'))->assertOk();
        $this->actingAs($accountant)->get(route('users.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('backup.download'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('users.index'))->assertOk();
    }

    public function test_data_entry_users_alter_only_their_own_vouchers_and_cannot_cancel(): void
    {
        $clerk = User::factory()->create(['role' => Role::DataEntry]);
        $mine = $this->journal('10', $clerk->id);
        $theirs = $this->journal('20', $this->admin->id);

        $this->actingAs($clerk)->get(route('vouchers.edit', $mine))->assertOk()->assertDontSee('Cancel voucher');
        $this->actingAs($clerk)->get(route('vouchers.edit', $theirs))->assertForbidden();

        Livewire::actingAs($clerk)->test(VoucherForm::class, ['voucher' => $mine])->call('cancelVoucher')->assertForbidden();
    }

    public function test_deactivated_user_is_signed_out(): void
    {
        $user = User::factory()->create(['role' => Role::Accountant, 'is_active' => false]);

        $this->actingAs($user)->get(route('gateway'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        Livewire::actingAs($this->admin)->test(Users::class)
            ->call('edit', $this->admin->id)
            ->set('role', 'viewer')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_period_lock_blocks_posting_altering_and_cancelling(): void
    {
        $this->actingAs($this->admin);
        $voucher = $this->journal('10', $this->admin->id, '2026-02-15');

        Livewire::test(YearEnd::class)->set('lockDate', '2026-03-31')->call('lockUntil');
        $this->assertSame('2026-03-31', CompanySetting::query()->first()->locked_until->toDateString());

        foreach ([
            fn () => $this->journal('5', $this->admin->id, '2026-03-31'),
            fn () => app(VoucherService::class)->cancel($voucher),
            // Moving a locked voucher into an open period is also blocked.
            fn () => app(VoucherService::class)->save($this->journalData('10', '2026-04-10'), $voucher),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the period lock to block this.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('locked up to 31-Mar-2026', $e->errors()['date'][0]);
            }
        }

        $this->assertNotNull($this->journal('5', $this->admin->id, '2026-04-01'));

        // Opening balances are fixed once the first day of the books is locked.
        Livewire::test(LedgerForm::class, ['ledger' => Ledger::query()->where('name', 'Cash')->first()])
            ->set('opening_amount', '100')
            ->call('save')
            ->assertHasErrors('opening_amount');
    }

    public function test_audit_trail_records_voucher_and_master_changes(): void
    {
        $this->actingAs($this->admin);
        $voucher = $this->journal('10', $this->admin->id);
        app(VoucherService::class)->save($this->journalData('25'), $voucher);
        app(VoucherService::class)->cancel($voucher->fresh());
        Ledger::query()->where('name', 'Cash')->first()->update(['name' => 'Cash in Hand']);

        $logs = ActivityLog::query()->where('subject_type', 'Voucher')->orderBy('id')->get();
        $this->assertSame(['created', 'altered', 'cancelled'], $logs->pluck('action')->all());
        $this->assertSame('10.000', $logs[1]->old_values['total']);
        $this->assertSame('25.000', $logs[1]->new_values['total']);
        $this->assertSame($this->admin->id, $logs[0]->user_id);

        $ledgerLog = ActivityLog::query()->where('subject_type', 'Ledger')->where('action', 'altered')->sole();
        $this->assertSame(['name' => 'Cash'], $ledgerLog->old_values);

        $this->get(route('audit.index'))->assertOk()->assertSee('Journal JV-1 altered');
    }

    public function test_backup_download_contains_the_data(): void
    {
        $this->actingAs($this->admin);
        $this->journal('10', $this->admin->id);

        $response = $this->get(route('backup.download'));
        $response->assertOk();
        $sql = $response->streamedContent();

        $this->assertStringContainsString('INSERT INTO "vouchers"', $sql);
        $this->assertStringContainsString("'Test LLC'", $sql);
        $this->assertStringContainsString('DELETE FROM "voucher_entries"', $sql);
        $this->assertTrue(ActivityLog::query()->where('action', 'backup')->exists());
    }

    private function journalData(string $amount, string $date = '2026-02-15'): array
    {
        return [
            'voucher_type_id' => VoucherType::query()->where('name', 'Journal')->value('id'),
            'date' => $date,
            'entries' => [
                ['ledger_id' => Ledger::query()->where('name', 'Cash')->value('id') ?? Ledger::query()->where('name', 'Cash in Hand')->value('id'), 'debit' => $amount],
                ['ledger_id' => Ledger::query()->where('name', 'Sales - Standard Rated')->value('id'), 'credit' => $amount],
            ],
        ];
    }

    private function journal(string $amount, int $userId, string $date = '2026-02-15'): Voucher
    {
        return app(VoucherService::class)->save($this->journalData($amount, $date), null, $userId);
    }
}
