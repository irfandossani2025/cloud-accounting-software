<?php

namespace Tests\Feature;

use App\Livewire\CompanyForm;
use App\Livewire\SendPanel;
use App\Mail\InvoiceMail;
use App\Mail\PaymentReminderMail;
use App\Models\AccountGroup;
use App\Models\Communication;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Services\CommunicationService;
use App\Services\InvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SendInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->travelTo(Carbon::parse('2026-06-20 09:00'));
        CompanySetting::query()->create([
            'name' => 'Test LLC', 'name_ar' => 'شركة الاختبار', 'vatin' => 'OM1100000001',
            'financial_year_start' => '2026-01-01', 'books_begin_from' => '2026-01-01',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->customer = Ledger::query()->create([
            'name' => 'Al Noor Trading', 'account_group_id' => AccountGroup::reserved('Sundry Debtors')->id,
            'is_bill_wise' => true, 'credit_days' => 30, 'email' => 'accounts@alnoor.test', 'phone' => '9123 4567',
        ]);
        config(['mail.default' => 'array']);
    }

    private function invoice(string $date, string $rate = '100'): Voucher
    {
        return app(InvoiceService::class)->save([
            'voucher_type_id' => VoucherType::query()->where('name', 'Sales')->value('id'),
            'date' => $date, 'party_ledger_id' => $this->customer->id,
            'lines' => [['ledger_id' => Ledger::query()->where('name', 'Sales - Standard Rated')->value('id'), 'description' => 'Consulting', 'rate' => $rate]],
        ]);
    }

    public function test_invoice_is_emailed_with_pdf_and_logged(): void
    {
        Mail::fake();
        $voucher = $this->invoice('2026-06-10');

        Livewire::test(SendPanel::class, ['voucherId' => $voucher->id])
            ->assertSet('email', 'accounts@alnoor.test')
            ->set('message', 'Thank you for your business.')
            ->call('sendEmail')
            ->assertHasNoErrors()
            ->assertSee('Email invoice + PDF');

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($voucher) {
            $pdf = collect($mail->attachments())->sole();
            if ($pdf->as !== 'Sales-INV-1.pdf' || $pdf->mime !== 'application/pdf') {
                return false;
            }

            return $mail->hasTo('accounts@alnoor.test') && $mail->note === 'Thank you for your business.';
        });
        $log = Communication::query()->sole();
        $this->assertSame(['email', 'invoice', 'sent', $voucher->id], [$log->channel, $log->kind, $log->status, $log->voucher_id]);

        Livewire::test(SendPanel::class, ['voucherId' => $voucher->id])->set('email', 'not-an-email')->call('sendEmail')->assertHasErrors('email');
    }

    public function test_rendered_invoice_email_and_pdf(): void
    {
        $voucher = $this->invoice('2026-06-10');
        $mail = new InvoiceMail($voucher, app(\App\Services\Documents\InvoiceDocuments::class), 'https://example.test/link', 'Pay by transfer');

        $mail->assertSeeInHtml('INV-1')->assertSeeInHtml('Pay by transfer')->assertSeeInHtml('https://example.test/link')->assertSeeInHtml('105.000');
        $this->assertStringStartsWith('%PDF', $this->get(route('vouchers.pdf', $voucher))->assertOk()->getContent());
    }

    public function test_public_links_need_a_valid_signature(): void
    {
        $voucher = $this->invoice('2026-06-10');
        $service = app(CommunicationService::class);

        $this->get($service->invoiceLink($voucher))->assertOk()->assertSee('Consulting')->assertSee('Download PDF');
        $this->get(route('public.invoice', $voucher))->assertForbidden();
        $this->get($service->statementLink($this->customer))->assertOk()->assertSee('INV-1')->assertSee('105.000');
        $this->get(route('public.statement', $this->customer))->assertForbidden();

        auth()->logout();
        $this->get($service->invoiceLink($voucher))->assertOk();
        $this->travel(CommunicationService::LINK_DAYS + 1)->days();
        $this->get($service->invoiceLink($voucher->fresh()))->assertOk(); // freshly signed
    }

    public function test_expired_link_is_rejected(): void
    {
        $link = app(CommunicationService::class)->invoiceLink($this->invoice('2026-06-10'));
        $this->travel(CommunicationService::LINK_DAYS + 1)->days();
        $this->get($link)->assertForbidden();
    }

    public function test_whatsapp_link_and_log(): void
    {
        $voucher = $this->invoice('2026-06-10');
        $url = app(CommunicationService::class)->whatsappInvoiceUrl($voucher);

        $this->assertStringStartsWith('https://wa.me/96891234567?text=', $url);
        $this->assertStringContainsString(rawurlencode('INV-1'), $url);
        $this->assertSame('96891234567', CommunicationService::whatsappNumber('+968 9123 4567'));
        $this->assertSame('96891234567', CommunicationService::whatsappNumber('0096891234567'));
        $this->assertNull(CommunicationService::whatsappNumber('123'));

        Livewire::test(SendPanel::class, ['voucherId' => $voucher->id])->call('logWhatsapp')->assertSee('WhatsApp invoice');
        $this->assertSame('opened', Communication::query()->sole()->status);
    }

    public function test_statement_is_emailed_from_the_ledger(): void
    {
        Mail::fake();
        $this->invoice('2026-06-10');

        $this->get(route('reports.ledger', $this->customer))->assertOk()->assertSee('Send statement to customer');
        Livewire::test(SendPanel::class, ['ledgerId' => $this->customer->id])->call('sendEmail')->assertHasNoErrors();

        Mail::assertSent(PaymentReminderMail::class, fn ($mail) => $mail->statement && $mail->bills->count() === 1);
        $this->assertSame('statement', Communication::query()->sole()->kind);
    }

    public function test_reminders_are_sent_once_per_stage(): void
    {
        Mail::fake();
        CompanySetting::current()->update(['reminders_enabled' => true, 'reminder_days' => '3,14,30']);
        $this->invoice('2026-05-01');  // due 31 May: 20 days overdue on 20 June -> stage 14
        $this->invoice('2026-05-18');  // due 17 June: 3 days overdue -> stage 3
        $this->invoice('2026-06-15');  // not due

        $this->artisan('reminders:send')->expectsOutputToContain('Reminders sent: 1')->assertSuccessful();
        Mail::assertSentCount(1);
        Mail::assertSent(PaymentReminderMail::class, fn ($mail) => ! $mail->statement && $mail->bills->pluck('reference')->all() === ['INV-1', 'INV-2']);
        $this->assertEqualsCanonicalizing([['INV-1', 14], ['INV-2', 3]], Communication::query()->get()->map(fn ($c) => [$c->reference, $c->stage])->all());

        // Same day again: nothing new.
        $this->artisan('reminders:send')->expectsOutputToContain('Reminders sent: 0');
        Mail::assertSentCount(1);

        // INV-1 reaches 30 days on 30 June.
        $this->travelTo(Carbon::parse('2026-06-30 09:00'));
        $this->artisan('reminders:send')->expectsOutputToContain('Reminders sent: 1');
        Mail::assertSentCount(2);
        $this->assertSame(30, Communication::query()->latest('id')->first()->stage);
    }

    public function test_reminders_respect_the_switch(): void
    {
        Mail::fake();
        $this->invoice('2026-05-01');
        $this->artisan('reminders:send')->expectsOutputToContain('switched off');
        Mail::assertNothingSent();
    }

    public function test_company_reminder_settings(): void
    {
        Livewire::test(CompanyForm::class)
            ->set('reminders_enabled', true)
            ->set('reminder_days', '30, 7,7')
            ->set('invoice_email_note', 'Bank transfer please')
            ->call('save')
            ->assertHasNoErrors();

        $company = CompanySetting::current()->fresh();
        $this->assertTrue($company->reminders_enabled);
        $this->assertSame('7,30', $company->reminder_days);

        Livewire::test(CompanyForm::class)->set('reminder_days', 'weekly')->call('save')->assertHasErrors('reminder_days');
    }

    public function test_invoice_screen_shows_send_panel_and_email_warning(): void
    {
        config(['mail.default' => 'log']);
        $voucher = $this->invoice('2026-06-10');

        $this->get(route('invoices.edit', $voucher))->assertOk()->assertSee('Send to customer')->assertSee('Email not set up yet');
        Livewire::test(SendPanel::class, ['voucherId' => $voucher->id])->call('sendEmail')->assertSee('only written to the server log');
    }
}
