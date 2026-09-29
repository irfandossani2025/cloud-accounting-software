# Cloud Accounting

Web-based accounting for a single company in Oman, modelled on TallyPrime. It uses OMR with 3 decimals (baisa) and Oman VAT.

**Stack:** Laravel 12 · Livewire 4 · Tailwind 4 · PHP 8.2 · MariaDB 10.1 (production) / SQLite (local)

## Local development

```bash
composer install
npm install
cp .env.example .env    # then set DB_CONNECTION=sqlite, APP_ENV=local, APP_DEBUG=true
php artisan key:generate
php artisan migrate --seed --seeder=DemoSeeder   # login: admin@example.test / password
npm run build
php artisan serve
```

Tests: `php artisan test`

## How it works

| Concept (Tally) | Here |
|---|---|
| Groups: 15 primary + 13 sub-groups | `account_groups`, seeded by `ChartOfAccountsSeeder` |
| Ledgers | `ledgers`; opening balance is signed (+Dr / −Cr) |
| Voucher types F4–F9, Ctrl+F8/F9 | `voucher_types`, with the same keyboard shortcuts |
| Vouchers | `vouchers` + `voucher_entries` (debit/credit). Posted only through `App\Services\VoucherService`, which enforces Dr = Cr and the Contra/Payment/Receipt rules |
| Reports | `App\Services\ReportService`: Trial Balance, P&L, Balance Sheet, Day Book, Ledger |
| Invoice mode (Ctrl+H toggles) | `App\Services\InvoiceService` prices lines (qty × rate − discount), calculates VAT per line and generates the double entry; lines are kept in `invoice_lines` for printing |
| Bill-wise details | `bill_allocations` (New Ref / Agst Ref / Advance / On Account); `App\Services\OutstandingService` builds Bills Receivable/Payable with ageing |
| Inventory | `stock_items`, `stock_openings`, `stock_movements`. `App\Services\StockService` values stock at weighted average cost; `InventoryVoucherService` posts Stock Journal (Alt+F7) and Physical Stock (Alt+F10). Opening/closing stock feed the P&L, Balance Sheet and Trial Balance |
| Banking & control | `BankReconciliationService` (bank dates on bank entries, preserved on edit; post-dated cheques = receipts/payments dated in the future), `CostCentreReportService` (`cost_allocations`), `BudgetService` (`budgets`, `budget_lines`), `ForexService` (`currencies`, `exchange_rates`, `fx_amount` on entries, revaluation journal to Forex Gain/Loss) |
| Administration | Roles in `App\Enums\Role` with gates in `AppServiceProvider` (route middleware `can:`, re-checked on every Livewire request); `App\Support\Audit` + `Auditable` trait → `activity_logs`; `App\Support\PeriodLock` enforced in the voucher services; `BackupController` streams an SQL backup |
| VAT return | `App\Services\VatReturnService`: supplies and purchases by VAT treatment, output/input VAT and reverse charge, net payable |

Money is always handled as integer baisa in PHP (`App\Support\Money`) and stored as `DECIMAL(18,3)`.

**Oman VAT:** 5% standard. Sales/purchase ledgers carry a VAT treatment (standard, zero-rated, exempt, reverse charge,
out of scope). VAT ledgers under *Duties & Taxes* carry a tax role. *Apply VAT* (Alt+V) on a voucher calculates the VAT
lines, including both sides of a reverse charge.

Keyboard: F4–F9 voucher types · Ctrl+H invoice/accounting mode · Ctrl+A accept · Alt+Q print last saved · Esc back · Alt+V apply VAT · Alt+N new line · Alt+F1 detailed/condensed ·
Alt+C create · Alt+L ledgers · Alt+B/P/T/D/R/Y/X/S reports · Alt+I stock items · Alt+K bank reconciliation. (Shortcuts use the real Ctrl key, so Cmd+A still selects text on a Mac.)

## Roles

| Role | Can |
|---|---|
| Administrator | Everything: users, company settings, audit trail, period lock / year-end, backup |
| Accountant | All vouchers and masters, cancel vouchers, bank reconciliation, budgets, currencies, forex revaluation |
| Data entry | Enter vouchers; alter only vouchers they created; no masters, no cancellation |
| Viewer | View, print and export reports |

## Roadmap

- [x] **Phase 1: core books.** Groups, ledgers, 8 accounting voucher types, Trial Balance, P&L, Balance Sheet, Day Book, Ledger, installer
- [x] **Phase 2: invoicing & VAT.** Item-invoice mode for Sales/Purchase/Credit/Debit Notes, bilingual (Arabic/English) tax invoice print, Oman VAT return working, bill-wise outstanding with ageing and receipt/payment allocation
- [x] **Phase 3: inventory.** Stock groups/items, units, godowns, opening stock, items on invoices, stock journal (transfers, production), physical stock, stock summary, item register, weighted-average closing stock in P&L and Balance Sheet
- [x] **Phase 4: banking & control.** Bank reconciliation (instrument details, bank dates, statement matching), post-dated cheques, cost centres, budgets vs actual, multi-currency (rates, foreign-currency invoices with VAT in OMR, forex revaluation)
- [x] **Phase 5: admin.** Users & roles, audit trail, period lock (incl. opening balances), Excel (CSV) export of every report, print/PDF, year-end close, SQL backup download
- [ ] **Next (not started):** Oman e-invoicing (Fawtara) once the OTA technical specification applies to the client, delivery/receipt notes, batch & expiry, FIFO valuation
