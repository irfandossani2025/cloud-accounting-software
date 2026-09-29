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

Money is always handled as integer baisa in PHP (`App\Support\Money`) and stored as `DECIMAL(18,3)`.

**Oman VAT:** 5% standard. Sales/purchase ledgers carry a VAT treatment (standard, zero-rated, exempt, reverse charge,
out of scope). VAT ledgers under *Duties & Taxes* carry a tax role. *Apply VAT* (Alt+V) on a voucher calculates the VAT
lines, including both sides of a reverse charge.

Keyboard: F4–F9 voucher types · Ctrl+A accept · Esc back · Alt+V apply VAT · Alt+N new line · Alt+F1 detailed/condensed ·
Alt+C create · Alt+L ledgers · Alt+B/P/T/D reports.

## Roadmap

- [x] **Phase 1: core books.** Groups, ledgers, 8 accounting voucher types, Trial Balance, P&L, Balance Sheet, Day Book, Ledger, installer
- [ ] **Phase 2: invoicing & VAT.** Item-invoice mode for Sales/Purchase, bilingual (Arabic/English) tax invoice print/PDF, Oman VAT return report, bill-wise outstanding (receivables/payables, ageing)
- [ ] **Phase 3: inventory.** Stock groups/items, units, godowns, stock journal, stock summary, closing stock valuation in P&L
- [ ] **Phase 4: banking & control.** Bank reconciliation, cost centres, budgets, post-dated cheques, multi-currency
- [ ] **Phase 5: admin.** Users & roles, audit trail (edit log), period lock, Excel/PDF exports, year-end, e-invoicing readiness
