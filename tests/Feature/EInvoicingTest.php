<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountGroup;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Einvoice;
use App\Models\Ledger;
use App\Models\StockItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Services\EInvoice\EInvoiceService;
use App\Services\EInvoice\PintOmDocument;
use App\Services\EInvoice\PintOmPreflight;
use App\Services\EInvoice\PintOmXmlBuilder;
use App\Services\InvoiceService;
use App\Services\VoucherService;
use App\Support\PintOm;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EInvoicingTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $buyer;

    private Ledger $sales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);

        CompanySetting::query()->create([
            'name' => 'Muscat Trading LLC', 'vatin' => 'OM1100000001', 'cr_number' => '1234567', 'phone' => '+968 2400 0000',
            'street' => 'Way 2317, Building 192', 'additional_street' => 'Al Khuwair', 'po_box' => 'P.O. Box 192',
            'city' => 'Muscat', 'postal_code' => '112', 'einvoicing_enabled' => true,
            'financial_year_start' => '2026-01-01', 'books_begin_from' => '2026-01-01',
        ]);
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        $this->sales = Ledger::query()->where('name', 'Sales - Standard Rated')->first();
        $this->sales->update(['item_type' => 'S', 'isic_code' => '000000']);
        $this->buyer = Ledger::query()->create([
            'name' => 'Oman Buyer SAOG', 'account_group_id' => AccountGroup::reserved('Sundry Debtors')->id, 'vatin' => 'OM1100000099',
            'street' => 'Street 5', 'additional_street' => 'Falaj', 'po_box' => 'P.O. Box 55', 'city' => 'Sohar', 'postal_code' => '311',
        ]);
    }

    public function test_builds_a_pint_om_tax_invoice(): void
    {
        $voucher = $this->invoice($this->buyer, [['ledger_id' => $this->sales->id, 'description' => 'Consulting', 'quantity' => '3', 'rate' => '12.345', 'discount' => '1.5']]);
        $xml = simplexml_load_string($this->xml($voucher));
        $xml->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xml->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $value = fn (string $path) => (string) ($xml->xpath($path)[0] ?? '');

        $this->assertSame(PintOm::CUSTOMIZATION_ID, $value('cbc:CustomizationID'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value('cbc:UUID'));
        $this->assertSame('10000000000000000000', (string) $xml->xpath('cbc:InvoiceTypeCode/@name')[0]);
        $this->assertSame('1100000001', $value('cac:AccountingSupplierParty/cac:Party/cbc:EndpointID'));
        $this->assertSame('1100000099', $value('cac:AccountingCustomerParty/cac:Party/cbc:EndpointID'));
        // Lines keep baisa: 3 x 12.345 - 1.500 = 35.535; VAT 1.777 (5% of 35.535 = 1.77675).
        $this->assertSame('35.535', $value('cac:InvoiceLine/cbc:LineExtensionAmount'));
        $this->assertSame('1.777', $value('cac:InvoiceLine/cac:ItemPriceExtension/cac:TaxTotal/cbc:TaxAmount'));
        // Document totals use two decimals.
        $this->assertSame('35.54', $value('cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount'));
        $this->assertSame('1.78', $value('cac:TaxTotal/cbc:TaxAmount'));
        $this->assertSame('37.32', $value('cac:LegalMonetaryTotal/cbc:PayableAmount'));

        // The UUID is stable for the same voucher.
        $this->assertSame($value('cbc:UUID'), PintOmDocument::forVoucher($voucher->fresh())->uuid);
    }

    public function test_customer_without_vatin_gets_a_simplified_invoice(): void
    {
        $voucher = $this->invoice(Ledger::query()->where('name', 'Cash')->first(), [['ledger_id' => $this->sales->id, 'rate' => '10']]);
        $doc = PintOmDocument::forVoucher($voucher);

        $this->assertFalse($doc->isFullInvoice);
        $this->assertSame('01000000000000000000', $doc->transactionType);
        $this->assertSame(PintOm::UNKNOWN_BUYER_ENDPOINT, $doc->buyerEndpoint());
        $this->assertSame([], app(PintOmPreflight::class)->check($voucher));
    }

    public function test_preflight_explains_what_is_missing(): void
    {
        CompanySetting::query()->first()->update(['po_box' => null, 'phone' => null]);
        $this->buyer->update(['city' => null]);
        $zero = Ledger::query()->where('name', 'Sales - Zero Rated')->first();
        $item = StockItem::query()->create(['name' => 'Pump', 'unit_id' => Unit::query()->value('id'), 'item_type' => 'G', 'isic_code' => '000000']);

        $voucher = $this->invoice($this->buyer, [
            ['ledger_id' => $zero->id, 'description' => 'Dates', 'rate' => '5'],
            ['stock_item_id' => $item->id, 'ledger_id' => $this->sales->id, 'quantity' => '1', 'rate' => '5'],
        ]);
        $rules = array_column(app(PintOmPreflight::class)->check($voucher), 'rule');

        foreach (['IBR-010-OM', 'IBR-011-OM', 'IBR-019-OM', 'IBR-078-OM', 'IBR-079-OM', 'IBR-081-OM', 'CL-10-OM'] as $rule) {
            $this->assertContains($rule, $rules);
        }

        $this->expectException(ValidationException::class);
        app(EInvoiceService::class)->generate($voucher);
    }

    public function test_submitted_invoice_is_locked_until_rejected(): void
    {
        $voucher = $this->invoice($this->buyer, [['ledger_id' => $this->sales->id, 'rate' => '100']]);
        $service = app(EInvoiceService::class);

        $einvoice = $service->generate($voucher);
        $this->assertSame(hash('sha256', $einvoice->xml), $einvoice->xml_sha256);
        $service->markSubmitted($einvoice, 'SP-001', auth()->id());

        foreach ([
            fn () => app(VoucherService::class)->cancel($voucher->fresh()),
            fn () => app(InvoiceService::class)->save($this->invoiceData($this->buyer, [['ledger_id' => $this->sales->id, 'rate' => '90']]), $voucher->fresh()),
            fn () => $service->generate($voucher->fresh()),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A submitted e-invoice must be locked.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('submitted', implode(' ', $e->validator->errors()->all()));
            }
        }

        // A rejected document was never registered: correct it and generate again.
        $service->markOutcome($einvoice->fresh(), false, 'Wrong buyer');
        app(InvoiceService::class)->save($this->invoiceData($this->buyer, [['ledger_id' => $this->sales->id, 'rate' => '90']]), $voucher->fresh());
        $this->assertNull($einvoice->fresh()->xml, 'Changing the voucher discards the stale XML.');
        $this->assertSame(Einvoice::DRAFT, $service->generate($voucher->fresh())->status);
        $this->assertSame($einvoice->uuid, $voucher->fresh()->einvoice->uuid);
    }

    public function test_credit_note_references_the_original_invoice(): void
    {
        $invoice = $this->invoice($this->buyer, [['ledger_id' => $this->sales->id, 'rate' => '100']]);
        $original = app(EInvoiceService::class)->generate($invoice);

        $note = $this->invoice($this->buyer, [['ledger_id' => $this->sales->id, 'rate' => '20']], 'Credit Note', [
            'original_voucher_id' => $invoice->id, 'issuance_reason' => 'VAL',
        ]);
        $xml = $this->xml($note);

        $this->assertStringContainsString('<CreditNote', $xml);
        $this->assertStringContainsString('<cbc:CreditNoteTypeCode name="10000000000000000000">381</cbc:CreditNoteTypeCode>', $xml);
        $this->assertStringContainsString('<cbc:UUID>'.$original->uuid.'</cbc:UUID>', $xml);
        $this->assertStringContainsString('<cbc:DocumentStatusCode>VAL</cbc:DocumentStatusCode>', $xml);

        // The original must be an invoice of the same customer.
        $this->expectException(ValidationException::class);
        $this->invoice(Ledger::query()->where('name', 'Cash')->first(), [['ledger_id' => $this->sales->id, 'rate' => '5']], 'Credit Note', [
            'original_voucher_id' => $invoice->id, 'issuance_reason' => 'VAL',
        ]);
    }

    public function test_code_search_and_uuid(): void
    {
        $this->assertSame('cfbff0d1-9375-5685-968c-48ce8b15ae17', PintOm::uuid5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'example.com'));
        $this->assertNotEmpty(PintOm::search('hs', 'horses'));
        $this->assertSame('Other', PintOm::isicDescription('000000'));
        $this->getJson(route('codes.search', 'isic').'?q=petroleum')->assertOk()->assertJsonFragment(['code' => '061000']);
    }

    /**
     * Runs the official OpenPeppol Schematron over generated documents when the validator is installed
     * (see tools/pint-om/README.md).
     */
    public function test_generated_documents_pass_official_pint_om_rules(): void
    {
        $python = base_path('tools/pint-om/.venv/bin/python');
        if (! is_file($python) || ! is_dir(base_path('tools/pint-om/resources'))) {
            $this->markTestSkipped('PINT OM validator not installed (tools/pint-om/README.md).');
        }

        $zero = Ledger::query()->where('name', 'Sales - Zero Rated')->first();
        $zero->update(['item_type' => 'G', 'hs_code' => '010121100001', 'isic_code' => '000000', 'exemption_code' => 'VATZR-OM-01']);
        $exempt = Ledger::query()->where('name', 'Sales - Exempt')->first();
        $exempt->update(['item_type' => 'S', 'isic_code' => '000000', 'exemption_code' => 'VATEX-OM-04']);
        $usdBuyer = Ledger::query()->create([
            'name' => 'Duqm Importer', 'account_group_id' => AccountGroup::reserved('Sundry Debtors')->id, 'vatin' => 'OM1100000077',
            'street' => 'Road 1', 'additional_street' => 'Port', 'po_box' => 'P.O. Box 9', 'city' => 'Duqm', 'postal_code' => '700',
            'country_subdivision' => 'SEZAD', 'currency_id' => Currency::query()->where('code', 'USD')->value('id'),
        ]);

        $documents = [
            $this->invoice($this->buyer, [
                ['ledger_id' => $this->sales->id, 'description' => 'Installation', 'rate' => '100', 'quantity' => '1'],
                ['ledger_id' => $zero->id, 'description' => 'Dates', 'quantity' => '10', 'rate' => '1.234'],
                ['ledger_id' => $exempt->id, 'description' => 'Flat rent', 'rate' => '250'],
            ]),
            $this->invoice(Ledger::query()->where('name', 'Cash')->first(), [['ledger_id' => $this->sales->id, 'quantity' => '2', 'rate' => '45.250']]),
            $this->invoice($this->buyer, array_fill(0, 9, ['ledger_id' => $this->sales->id, 'description' => 'Small item', 'rate' => '0.047'])),
            $this->invoice($usdBuyer, [['ledger_id' => $this->sales->id, 'description' => 'Engineering', 'rate' => '1000']], 'Sales', [
                'currency_id' => $usdBuyer->currency_id, 'fx_rate' => '0.384497',
            ]),
        ];
        $documents[] = $this->invoice($this->buyer, [['ledger_id' => $this->sales->id, 'rate' => '20']], 'Credit Note', [
            'original_voucher_id' => $documents[0]->id, 'issuance_reason' => 'VAL',
        ]);

        $files = [];
        foreach ($documents as $i => $voucher) {
            $files[] = $file = sys_get_temp_dir()."/pint-om-test-{$i}.xml";
            file_put_contents($file, $this->xml($voucher));
        }

        $process = new Process([$python, base_path('tools/pint-om/validate.py'), ...$files]);
        $process->setTimeout(300)->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertStringNotContainsString('[fatal]', $process->getOutput());
    }

    private function invoiceData(Ledger $party, array $lines, string $type = 'Sales', array $extra = []): array
    {
        return [
            'voucher_type_id' => VoucherType::query()->where('name', $type)->value('id'),
            'date' => now()->toDateString(),
            'party_ledger_id' => $party->id,
            'lines' => $lines,
        ] + $extra;
    }

    private function invoice(Ledger $party, array $lines, string $type = 'Sales', array $extra = []): Voucher
    {
        return app(InvoiceService::class)->save($this->invoiceData($party, $lines, $type, $extra))->fresh();
    }

    private function xml(Voucher $voucher): string
    {
        return (new PintOmXmlBuilder)->build(PintOmDocument::forVoucher($voucher->fresh()));
    }
}
