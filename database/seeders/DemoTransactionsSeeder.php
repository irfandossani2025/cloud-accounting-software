<?php

namespace Database\Seeders;

use App\Models\AccountGroup;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\StockItem;
use App\Models\Unit;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Services\InvoiceService;
use App\Services\VoucherService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Six months of sample trading for a small IT supplies business, for demonstrating the system:
 * stock purchases, sales invoices, customer receipts (some overdue), supplier payments, rent,
 * salaries, utilities, a credit note and a post-dated cheque. Everything is posted through the
 * normal services, so reports, VAT, stock and the dashboard behave exactly as with real entries.
 *
 * Adds to the existing books and runs only once:
 *   php artisan db:seed --class=DemoTransactionsSeeder --force
 */
class DemoTransactionsSeeder extends Seeder
{
    private const MARKER = 'Oasis Hospitality SAOC';

    private Carbon $from;

    private Carbon $today;

    /** @var array<string, int> */
    private array $types;

    /** @var array<string, Ledger> */
    private array $l = [];

    /** @var array<string, StockItem> */
    private array $items = [];

    public function __construct(private InvoiceService $invoices, private VoucherService $vouchers) {}

    public function run(): void
    {
        if (Ledger::query()->where('name', self::MARKER)->exists()) {
            $this->command?->warn('Demo transactions are already loaded; nothing added.');

            return;
        }

        $company = CompanySetting::current();
        $this->today = now()->startOfDay();
        // Six months back, but never before the books begin or inside a locked period.
        $this->from = collect([
            $this->today->copy()->subMonthsNoOverflow(5)->startOfMonth(),
            $company->books_begin_from->copy(),
            $company->locked_until?->copy()->addDay(),
        ])->filter()->max();
        $this->types = VoucherType::query()->where('is_reserved', true)->pluck('id', 'name')->all();

        $this->masters();
        $this->transactions();

        $this->command?->info('Demo transactions loaded for '.$this->from->format('d M Y').' to '.$this->today->format('d M Y').': '.Voucher::query()->count().' vouchers in the books.');
    }

    private function masters(): void
    {
        $group = fn (string $name) => AccountGroup::reserved($name)->id;
        $ledger = fn (string $name, string $groupName, array $attributes = []) => $this->l[$name] = Ledger::query()->firstOrCreate(
            ['name' => $name], ['account_group_id' => $group($groupName)] + $attributes,
        );

        $ledger('Bank Muscat - Current A/c', 'Bank Accounts', ['bank_name' => 'Bank Muscat', 'opening_balance' => '25000.000']);
        $ledger("Owner's Capital", 'Capital Account', ['opening_balance' => '-25000.000']);
        $this->l['Cash'] = Ledger::query()->where('name', Ledger::CASH)->firstOrFail();
        $this->l['Sales'] = Ledger::query()->where('name', 'Sales - Standard Rated')->firstOrFail();
        $this->l['Purchases'] = Ledger::query()->where('name', 'Purchases - Standard Rated')->firstOrFail();

        foreach (['Office Rent', 'Salaries & Wages', 'Electricity & Water', 'Telephone & Internet', 'Office Supplies & Stationery', 'Fuel & Transport'] as $expense) {
            $ledger($expense, 'Indirect Expenses');
        }

        $party = fn (string $name, string $groupName, string $city, string $vatin, int $days, string $email, string $nameAr) => $ledger($name, $groupName, [
            'name_ar' => $nameAr, 'is_bill_wise' => true, 'credit_days' => $days, 'email' => $email, 'vatin' => $vatin,
            'city' => $city, 'street' => 'Way '.random_int(2100, 4800), 'po_box' => (string) random_int(100, 999),
            'postal_code' => (string) random_int(111, 133), 'address' => $city.', Sultanate of Oman', 'country_code' => 'OM',
        ]);
        // Example addresses only (example.com never receives mail); no phone numbers, so WhatsApp asks which chat to use.
        $party('Al Noor Trading LLC', 'Sundry Debtors', 'Muscat', 'OM1100234561', 30, 'accounts@alnoor.example.com', 'شركة النور للتجارة ش.م.م');
        $party('Gulf Horizon Contracting', 'Sundry Debtors', 'Sohar', 'OM1100345672', 30, 'finance@gulfhorizon.example.com', 'الأفق الخليجي للمقاولات');
        $party(self::MARKER, 'Sundry Debtors', 'Salalah', 'OM1100456783', 45, 'ap@oasishospitality.example.com', 'الواحة للضيافة ش.م.ع.م');
        $party('Muscat Retail Co.', 'Sundry Debtors', 'Muscat', 'OM1100567894', 15, 'payables@muscatretail.example.com', 'شركة مسقط للتجزئة');
        $party('Oman Office Supplies LLC', 'Sundry Creditors', 'Muscat', 'OM1100678905', 30, 'sales@omanoffice.example.com', 'عمان للوازم المكتبية');
        $party('Gulf Tech Distributors', 'Sundry Creditors', 'Muscat', 'OM1100789016', 30, 'orders@gulftech.example.com', 'الخليج للتقنية للتوزيع');

        $pcs = Unit::query()->where('symbol', 'Pcs')->value('id');
        $item = fn (string $key, string $name, string $nameAr, string $sale, string $cost, ?string $reorder) => $this->items[$key] = StockItem::query()->firstOrCreate(['name' => $name], [
            'name_ar' => $nameAr, 'unit_id' => $pcs, 'vat_category' => 'standard',
            'sales_ledger_id' => $this->l['Sales']->id, 'purchase_ledger_id' => $this->l['Purchases']->id,
            'sales_rate' => $sale, 'purchase_rate' => $cost, 'reorder_level' => $reorder,
        ]);
        $item('laptop', 'Dell Latitude 5440 Laptop', 'حاسوب محمول ديل لاتيتيود 5440', '345.000', '258.000', '5');
        $item('printer', 'HP LaserJet Pro Printer', 'طابعة إتش بي ليزر جيت برو', '98.000', '71.000', '3');
        $item('mouse', 'Logitech Wireless Mouse', 'فأرة لاسلكية لوجيتك', '6.500', '3.400', '20');
        $item('monitor', 'Dell 24" Monitor', 'شاشة ديل 24 بوصة', '72.000', '52.500', '4');
    }

    private function transactions(): void
    {
        $customers = ['Al Noor Trading LLC', 'Gulf Horizon Contracting', self::MARKER, 'Muscat Retail Co.'];

        // --- Stock purchases (supplier bill number => lines) ---
        $this->purchase(0, 3, 'Oman Office Supplies LLC', 'OOS-1041', [['laptop', 24], ['printer', 10], ['mouse', 100], ['monitor', 16]]);
        $this->purchase(2, 4, 'Gulf Tech Distributors', 'GTD-2207', [['laptop', 22], ['mouse', 70], ['monitor', 14]]);
        $this->purchaseDaysAgo(20, 'Gulf Tech Distributors', 'GTD-2291', [['laptop', 20], ['printer', 8]]); // due within 14 days

        // --- Supplier payments ---
        $this->pay(1, 6, 'Oman Office Supplies LLC', [['OOS-1041', null]], 'cheque', '000412');
        $this->pay(3, 8, 'Gulf Tech Distributors', [['GTD-2207', '4000.000']], 'transfer', 'TRF-88213');

        // --- Sales: a growing business, three or four invoices a month ---
        $plan = [
            [[0, 12, 0, [['laptop', 3], ['mouse', 6]]], [0, 20, 2, [['laptop', 2], ['monitor', 2]], 'Office network setup', '540.000'], [0, 26, 3, [['printer', 1], ['mouse', 10]]]],
            [[1, 5, 1, [['laptop', 2], ['printer', 1]]], [1, 14, 0, [['monitor', 3], ['mouse', 8]]], [1, 23, 2, [['laptop', 3]], 'Network setup and installation', '420.000']],
            [[2, 7, 3, [['mouse', 12], ['printer', 2]]], [2, 16, 1, [['laptop', 4], ['monitor', 2]]], [2, 27, 0, [['laptop', 2]], 'Annual IT support contract', '1200.000']],
            [[3, 4, 2, [['laptop', 3], ['printer', 1]]], [3, 13, 1, [['monitor', 3], ['mouse', 10]], 'Server maintenance (quarterly)', '480.000'], [3, 21, 3, [['laptop', 2], ['mouse', 8]]], [3, 28, 0, [['printer', 2]]]],
            [[4, 6, 1, [['laptop', 3]], 'Site survey and cabling', '650.000'], [4, 15, 2, [['laptop', 2], ['monitor', 2]]], [4, 24, 3, [['mouse', 15], ['printer', 1]]]],
            [[5, 3, 0, [['laptop', 3], ['monitor', 2]]], [5, 11, 2, [['laptop', 2]], 'Wi-Fi upgrade', '380.000'], [5, 17, 1, [['mouse', 12]]], [5, 24, 3, [['laptop', 1], ['printer', 1]]]],
        ];
        // Monthly managed-IT retainers.
        foreach (range(0, 5) as $m) {
            $plan[$m][] = [$m, 2, 0, [], 'Managed IT services - monthly retainer', '850.000'];
            $plan[$m][] = [$m, 2, 2, [], 'Managed IT services and helpdesk - monthly retainer', '1250.000'];
        }
        $sales = [];
        foreach (collect($plan)->map(fn ($invoices) => collect($invoices)->sortBy(1)->values()->all())->all() as $monthInvoices) {
            foreach ($monthInvoices as $row) {
                [$m, $day, $c, $lines] = $row;
                if ($voucher = $this->sale($m, $day, $customers[$c], $lines, $row[4] ?? null, $row[5] ?? null)) {
                    $sales[] = $voucher;
                }
            }
        }

        // --- Customer receipts: older invoices are paid; Gulf Horizon falls behind, Muscat Retail pays part. ---
        foreach ($sales as $invoice) {
            $party = $invoice->party->name;
            $paidOn = $invoice->date->copy()->addDays(match ($party) { self::MARKER => 40, 'Muscat Retail Co.' => 14, default => 26 });
            $late = $party === 'Gulf Horizon Contracting' && $invoice->date->gte($this->today->copy()->subDays(95));
            if ($late || $paidOn->gte($this->today->copy()->subDays(3))) {
                continue;
            }
            $amount = $party === 'Muscat Retail Co.' && $invoice->date->gte($this->today->copy()->subDays(50))
                ? number_format(round((float) $invoice->total / 2, 3), 3, '.', '') : $invoice->total;
            $this->receipt($paidOn, $invoice, $amount);
        }

        // A post-dated cheque from Oasis for its latest open invoice, due in a few days.
        $open = collect($sales)->last(fn ($v) => $v->party->name === self::MARKER && ! $v->date->lte($this->today->copy()->subDays(43)));
        if ($open) {
            $this->receipt($this->today->copy()->addDays(4), $open, $open->total, ['instrument_type' => 'cheque', 'instrument_no' => '118452', 'instrument_date' => $this->today->copy()->addDays(4)->toDateString()]);
        }

        // Credit note: Muscat Retail returns one printer.
        $return = collect($sales)->first(fn ($v) => $v->party->name === 'Muscat Retail Co.' && $v->invoiceLines->contains('stock_item_id', $this->items['printer']->id));
        if ($return && $this->inRange($return->date->copy()->addDays(6))) {
            $this->invoices->save([
                'voucher_type_id' => $this->types['Credit Note'], 'date' => $return->date->copy()->addDays(6)->toDateString(),
                'party_ledger_id' => $return->party_ledger_id, 'original_voucher_id' => $return->id, 'issuance_reason' => 'Goods returned (damaged in transit)',
                'lines' => [$this->itemLine('printer', 1, 'sales')],
                'narration' => 'Printer returned, damaged in transit',
            ]);
        }

        // --- Monthly running costs ---
        for ($m = 0; $m <= 5; $m++) {
            $this->expense($m, 1, 'Office Rent', '380.000', 'Office rent, Al Khuwair', 'bank');
            $this->expense($m, 9, 'Telephone & Internet', (string) (58 + $m * 1.5), 'Omantel business line and fibre', 'bank');
            $this->expense($m, 12, 'Electricity & Water', (string) (96 + [0, 18, 41, 63, 58, 34][$m % 6]), 'Nama electricity and water', 'bank');
            $this->expense($m, 18, 'Fuel & Transport', (string) (35 + $m * 4), 'Fuel for delivery van', 'cash');
            $this->expense($m, 22, 'Office Supplies & Stationery', (string) (14.75 + $m * 2), 'Stationery and printer paper', 'cash');
            $this->expense($m, 27, 'Salaries & Wages', $m < 2 ? '1650.000' : '2100.000', 'Salaries for the month (2 staff'.($m < 2 ? ')' : ', plus 1 new technician)'), 'bank');
            if ($date = $this->date($m, 15)) {
                $this->vouchers->save([
                    'voucher_type_id' => $this->types['Contra'], 'date' => $date,
                    'entries' => [['ledger_id' => $this->l['Cash']->id, 'debit' => '150.000'], ['ledger_id' => $this->l['Bank Muscat - Current A/c']->id, 'credit' => '150.000']],
                    'narration' => 'Cash withdrawn for petty expenses',
                ]);
            }
        }
    }

    private function purchase(int $month, int $day, string $supplier, string $billNo, array $lines): void
    {
        if ($date = $this->date($month, $day)) {
            $this->purchaseOn($date, $supplier, $billNo, $lines);
        }
    }

    private function purchaseDaysAgo(int $days, string $supplier, string $billNo, array $lines): void
    {
        $date = $this->today->copy()->subDays($days);
        if ($this->inRange($date)) {
            $this->purchaseOn($date->toDateString(), $supplier, $billNo, $lines);
        }
    }

    private function purchaseOn(string $date, string $supplier, string $billNo, array $lines): void
    {
        $this->invoices->save([
            'voucher_type_id' => $this->types['Purchase'], 'date' => $date, 'party_ledger_id' => $this->l[$supplier]->id,
            'reference' => $billNo, 'reference_date' => $date,
            'lines' => array_map(fn ($l) => $this->itemLine($l[0], $l[1], 'purchase'), $lines),
            'narration' => 'Stock purchase, supplier bill '.$billNo,
        ]);
    }

    private function sale(int $month, int $day, string $customer, array $lines, ?string $service, ?string $serviceAmount): ?Voucher
    {
        $date = $this->date($month, $day);
        if (! $date) {
            return null;
        }
        $rows = array_map(fn ($l) => $this->itemLine($l[0], $l[1] * 2, 'sales'), $lines);
        if ($service) {
            $rows[] = ['ledger_id' => $this->l['Sales']->id, 'description' => $service, 'quantity' => '1', 'unit' => 'Nos', 'rate' => $serviceAmount];
        }

        return $this->invoices->save([
            'voucher_type_id' => $this->types['Sales'], 'date' => $date, 'party_ledger_id' => $this->l[$customer]->id, 'lines' => $rows,
        ])->load(['party', 'invoiceLines']);
    }

    private function itemLine(string $key, int $qty, string $side): array
    {
        $item = $this->items[$key];

        return [
            'ledger_id' => $side === 'sales' ? $this->l['Sales']->id : $this->l['Purchases']->id,
            'stock_item_id' => $item->id, 'description' => $item->name, 'description_ar' => $item->name_ar,
            'quantity' => (string) $qty, 'unit' => 'Pcs', 'rate' => $side === 'sales' ? $item->sales_rate : $item->purchase_rate,
        ];
    }

    private function receipt(Carbon $date, Voucher $invoice, string $amount, array $instrument = []): void
    {
        if ($date->lt($this->from)) {
            return;
        }
        $this->vouchers->save([
            'voucher_type_id' => $this->types['Receipt'], 'date' => $date->toDateString(),
            'entries' => [
                ['ledger_id' => $this->l['Bank Muscat - Current A/c']->id, 'debit' => $amount] + $instrument,
                ['ledger_id' => $invoice->party_ledger_id, 'credit' => $amount, 'bills' => [['type' => 'against_ref', 'reference' => $invoice->number, 'amount' => $amount]]],
            ],
            'narration' => ($instrument ? 'Post-dated cheque' : 'Payment received').' against '.$invoice->number,
        ]);
    }

    /** @param  list<array{0: string, 1: ?string}>  $bills  reference => amount (null = the full bill) */
    private function pay(int $month, int $day, string $supplier, array $bills, string $mode, string $instrumentNo): void
    {
        $date = $this->date($month, $day);
        if (! $date) {
            return;
        }
        $allocations = [];
        foreach ($bills as [$reference, $amount]) {
            $bill = Voucher::query()->where('reference', $reference)->where('party_ledger_id', $this->l[$supplier]->id)->first();
            if ($bill) {
                $allocations[] = ['type' => 'against_ref', 'reference' => $reference, 'amount' => $amount ?? $bill->total];
            }
        }
        if (! $allocations) {
            return;
        }
        $total = number_format(array_sum(array_map(fn ($a) => (float) $a['amount'], $allocations)), 3, '.', '');

        $this->vouchers->save([
            'voucher_type_id' => $this->types['Payment'], 'date' => $date,
            'entries' => [
                ['ledger_id' => $this->l[$supplier]->id, 'debit' => $total, 'bills' => $allocations],
                ['ledger_id' => $this->l['Bank Muscat - Current A/c']->id, 'credit' => $total, 'instrument_type' => $mode, 'instrument_no' => $instrumentNo, 'instrument_date' => $date],
            ],
            'narration' => 'Payment to '.$supplier,
        ]);
    }

    private function expense(int $month, int $day, string $ledger, string $amount, string $narration, string $from): void
    {
        if (! $date = $this->date($month, $day)) {
            return;
        }
        $amount = number_format((float) $amount, 3, '.', '');
        $this->vouchers->save([
            'voucher_type_id' => $this->types['Payment'], 'date' => $date,
            'entries' => [
                ['ledger_id' => $this->l[$ledger]->id, 'debit' => $amount],
                ['ledger_id' => ($from === 'cash' ? $this->l['Cash'] : $this->l['Bank Muscat - Current A/c'])->id, 'credit' => $amount],
            ],
            'narration' => $narration,
        ]);
    }

    /** Date in the Nth demo month, or null when it falls outside the demo period (future or before the books). */
    private function date(int $month, int $day): ?string
    {
        $start = $this->today->copy()->subMonthsNoOverflow(5)->startOfMonth()->addMonthsNoOverflow($month);
        $date = $start->copy()->day(min($day, $start->daysInMonth));

        return $this->inRange($date) ? $date->toDateString() : null;
    }

    private function inRange(Carbon $date): bool
    {
        return $date->gte($this->from) && $date->lte($this->today);
    }
}
