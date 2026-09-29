<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HasPeriod;
use App\Models\Ledger;
use App\Services\BankReconciliationService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Bank Reconciliation')]
class BankReconciliation extends Component
{
    use HasPeriod;

    public ?int $bankId = null;

    #[Url]
    public bool $showReconciled = false;

    /** voucher_entry_id => bank date (Y-m-d or '') */
    public array $bankDates = [];

    public string $statementBalance = '';

    public string $message = '';

    public function mount(?Ledger $ledger = null): void
    {
        $this->bankId = $ledger?->id ?? Ledger::query()->whereIn('account_group_id', Ledger::bankGroupIds())->orderBy('name')->value('id');
        $this->loadDates();
    }

    public function updatedBankId(): void
    {
        $this->loadDates();
    }

    public function updatedTo(): void
    {
        $this->loadDates();
    }

    public function updatedShowReconciled(): void
    {
        $this->loadDates();
    }

    private function loadDates(): void
    {
        $this->bankDates = [];
        if (! $bank = $this->bank()) {
            return;
        }
        foreach (app(BankReconciliationService::class)->statement($bank, $this->toDate(), $this->showReconciled)['entries'] as $entry) {
            $this->bankDates[$entry->id] = (string) $entry->bank_date?->toDateString();
        }
    }

    /** Fill every empty bank date with the voucher date (quick clear for entries confirmed on the statement). */
    public function markAllOnVoucherDate(): void
    {
        foreach (app(BankReconciliationService::class)->statement($this->bank(), $this->toDate(), $this->showReconciled)['entries'] as $entry) {
            if (($this->bankDates[$entry->id] ?? '') === '') {
                $this->bankDates[$entry->id] = $entry->voucher->date->toDateString();
            }
        }
    }

    public function save(BankReconciliationService $service): void
    {
        $this->resetErrorBag();
        $this->message = '';

        try {
            $changed = $service->reconcile($this->bank(), $this->bankDates);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->message = "{$changed} bank date(s) updated.";
        $this->loadDates();
    }

    private function bank(): ?Ledger
    {
        return $this->bankId ? Ledger::query()->find($this->bankId) : null;
    }

    public function render(BankReconciliationService $service)
    {
        $bank = $this->bank();

        return view('livewire.reports.bank-reconciliation', [
            'banks' => Ledger::query()->whereIn('account_group_id', Ledger::bankGroupIds())->orderBy('name')->get(),
            'bank' => $bank,
            'statement' => $bank ? $service->statement($bank, $this->toDate(), $this->showReconciled) : null,
        ]);
    }
}
