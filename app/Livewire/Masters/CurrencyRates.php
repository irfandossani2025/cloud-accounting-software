<?php

namespace App\Livewire\Masters;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Currencies')]
class CurrencyRates extends Component
{
    #[Url]
    public ?int $currency = null;

    public string $code = '';

    public string $name = '';

    public string $symbol = '';

    public string $decimals = '2';

    public string $rateDate = '';

    public string $rate = '';

    public function mount(): void
    {
        $this->currency ??= Currency::query()->orderBy('code')->value('id');
        $this->rateDate = now()->toDateString();
    }

    public function addCurrency(): void
    {
        $this->code = strtoupper(trim($this->code));
        $this->validate([
            'code' => ['required', 'size:3', 'alpha', Rule::unique('currencies', 'code'), 'not_in:OMR'],
            'name' => 'required|max:100',
            'symbol' => 'nullable|max:10',
            'decimals' => 'required|integer|min:0|max:3',
        ], ['code.not_in' => 'OMR is the base currency.']);

        $this->currency = Currency::query()->create([
            'code' => $this->code, 'name' => $this->name, 'symbol' => $this->symbol ?: null, 'decimals' => (int) $this->decimals,
        ])->id;
        $this->reset('code', 'name', 'symbol');
    }

    public function saveRate(): void
    {
        $this->validate([
            'currency' => 'required|exists:currencies,id',
            'rateDate' => 'required|date',
            'rate' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,12}(\.\d{1,6})?$/'],
        ], ['rate.regex' => 'Up to 6 decimals.']);

        ExchangeRate::query()->updateOrCreate(
            ['currency_id' => $this->currency, 'date' => $this->rateDate],
            ['rate' => $this->rate],
        );
        $this->reset('rate');
    }

    public function deleteRate(int $id): void
    {
        ExchangeRate::query()->where('currency_id', $this->currency)->whereKey($id)->delete();
    }

    public function render()
    {
        $selected = $this->currency ? Currency::query()->find($this->currency) : null;

        return view('livewire.masters.currency-rates', [
            'currencies' => Currency::query()->orderBy('code')->get(),
            'selected' => $selected,
            'rates' => $selected?->rates()->limit(60)->get() ?? collect(),
        ]);
    }
}
