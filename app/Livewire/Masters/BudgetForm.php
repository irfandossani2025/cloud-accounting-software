<?php

namespace App\Livewire\Masters;

use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\CostCentre;
use App\Models\Ledger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Budgets')]
class BudgetForm extends Component
{
    public ?Budget $budget = null;

    public string $name = '';

    public string $from_date = '';

    public string $to_date = '';

    /** @var list<array{account: string, cost_centre_id: int|string|null, amount: string}> account is "l:ID" or "g:ID" */
    public array $lines = [];

    public function mount(?Budget $budget = null): void
    {
        $this->budget = $budget?->exists ? $budget->load('lines') : null;

        if ($this->budget) {
            $this->name = $this->budget->name;
            $this->from_date = $this->budget->from_date->toDateString();
            $this->to_date = $this->budget->to_date->toDateString();
            $this->lines = $this->budget->lines->map(fn ($l) => [
                'account' => $l->ledger_id ? 'l:'.$l->ledger_id : 'g:'.$l->account_group_id,
                'cost_centre_id' => $l->cost_centre_id,
                'amount' => $l->amount,
            ])->all();
        } else {
            $this->from_date = now()->startOfYear()->toDateString();
            $this->to_date = now()->endOfYear()->toDateString();
        }

        $this->lines = $this->lines ?: [['account' => '', 'cost_centre_id' => null, 'amount' => '']];
    }

    public function addLine(): void
    {
        $this->lines[] = ['account' => '', 'cost_centre_id' => null, 'amount' => ''];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    public function save()
    {
        $this->validate([
            'name' => ['required', 'max:255', Rule::unique('budgets', 'name')->ignore($this->budget)],
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'lines.*.account' => ['nullable', 'regex:/^[lg]:\d+$/'],
            'lines.*.cost_centre_id' => 'nullable|exists:cost_centres,id',
            'lines.*.amount' => ['nullable', 'regex:/^\d{1,15}(\.\d{1,3})?$/'],
        ], ['lines.*.amount.regex' => 'Enter a positive amount with up to 3 decimals.']);

        $budget = DB::transaction(function () {
            $budget = $this->budget ?? new Budget;
            $budget->fill(['name' => $this->name, 'from_date' => $this->from_date, 'to_date' => $this->to_date])->save();
            $budget->lines()->delete();

            foreach ($this->lines as $line) {
                if (! $line['account'] || $line['amount'] === '') {
                    continue;
                }
                [$kind, $id] = explode(':', $line['account']);
                $budget->lines()->create([
                    'ledger_id' => $kind === 'l' ? (int) $id : null,
                    'account_group_id' => $kind === 'g' ? (int) $id : null,
                    'cost_centre_id' => $line['cost_centre_id'] ?: null,
                    'amount' => Money::toDecimal(Money::toBaisa($line['amount'])),
                ]);
            }

            return $budget;
        });

        session()->flash('status', "Budget \"{$budget->name}\" saved.");

        return $this->redirectRoute('reports.budget', $budget, navigate: true);
    }

    public function delete()
    {
        $this->budget?->delete();

        return $this->redirectRoute('budgets.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.masters.budget-form', [
            'budgets' => Budget::query()->orderByDesc('from_date')->get(),
            'groups' => AccountGroup::query()->orderBy('name')->get(),
            'ledgers' => Ledger::query()->orderBy('name')->get(),
            'costCentres' => CostCentre::query()->orderBy('name')->get(),
        ]);
    }
}
