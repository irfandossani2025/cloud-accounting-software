<?php

namespace App\Livewire\Masters;

use App\Enums\TaxRole;
use App\Enums\VatCategory;
use App\Models\AccountGroup;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Ledger;
use App\Services\VoucherService;
use App\Support\Money;
use App\Support\PeriodLock;
use App\Support\PintOm;
use Illuminate\Validation\Rule;
use Livewire\Component;

class LedgerForm extends Component
{
    public ?Ledger $ledger = null;

    public string $name = '';

    public string $name_ar = '';

    public string $alias = '';

    public ?int $account_group_id = null;

    public string $opening_amount = '';

    public string $opening_side = 'Dr';

    public bool $is_bill_wise = false;

    public bool $is_active = true;

    public bool $cost_centres_applicable = false;

    public ?int $currency_id = null;

    public string $opening_fx_amount = '';

    public string $vat_category = '';

    public string $tax_role = '';

    public string $address = '';

    public string $vatin = '';

    public string $cr_number = '';

    public string $phone = '';

    public string $email = '';

    public string $credit_days = '';

    public string $bank_name = '';

    public string $bank_account_no = '';

    public string $iban = '';

    public string $street = '';

    public string $additional_street = '';

    public string $po_box = '';

    public string $city = '';

    public string $postal_code = '';

    public string $country_subdivision = '';

    public string $party_id_scheme = '';

    public string $party_id = '';

    public string $item_type = '';

    public string $hs_code = '';

    public string $isic_code = '';

    public string $exemption_code = '';

    public string $country_code = 'OM';

    public function mount(?Ledger $ledger = null): void
    {
        $this->ledger = $ledger?->exists ? $ledger : null;

        if (! $this->ledger) {
            return;
        }

        foreach (['name', 'name_ar', 'alias', 'address', 'vatin', 'cr_number', 'phone', 'email', 'bank_name', 'bank_account_no', 'iban',
            'street', 'additional_street', 'po_box', 'city', 'postal_code', 'country_code', 'country_subdivision', 'party_id_scheme', 'party_id',
            'item_type', 'hs_code', 'isic_code', 'exemption_code'] as $field) {
            $this->{$field} = (string) $this->ledger->{$field};
        }

        $this->account_group_id = $this->ledger->account_group_id;
        $this->is_bill_wise = $this->ledger->is_bill_wise;
        $this->is_active = $this->ledger->is_active;
        $this->cost_centres_applicable = $this->ledger->cost_centres_applicable;
        $this->currency_id = $this->ledger->currency_id;
        $fxOpening = Money::toBaisa($this->ledger->opening_fx_balance ?? 0);
        $this->opening_fx_amount = $fxOpening ? Money::toDecimal(abs($fxOpening)) : '';
        $this->vat_category = $this->ledger->vat_category?->value ?? '';
        $this->tax_role = $this->ledger->tax_role?->value ?? '';
        $this->credit_days = (string) $this->ledger->credit_days;

        $opening = Money::toBaisa($this->ledger->opening_balance);
        $this->opening_amount = $opening ? Money::toDecimal(abs($opening)) : '';
        $this->opening_side = $opening < 0 ? 'Cr' : 'Dr';
    }

    public function save()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('ledgers', 'name')->ignore($this->ledger)],
            'name_ar' => 'nullable|string|max:255',
            'alias' => 'nullable|string|max:100',
            'account_group_id' => 'required|exists:account_groups,id',
            'opening_amount' => ['nullable', 'regex:/^\d{1,15}(\.\d{1,3})?$/'],
            'opening_side' => 'in:Dr,Cr',
            'vat_category' => ['nullable', Rule::enum(VatCategory::class)],
            'tax_role' => ['nullable', Rule::enum(TaxRole::class)],
            'email' => 'nullable|email|max:255',
            'credit_days' => 'nullable|integer|min:0|max:3650',
            'vatin' => ['nullable', 'regex:/^OM\d{10}$/'],
            'country_code' => 'required|size:2|alpha',
            'country_subdivision' => ['nullable', Rule::in(array_keys(PintOm::SUBDIVISIONS))],
            'party_id_scheme' => ['nullable', 'required_with:party_id', Rule::in(array_keys(PintOm::PARTY_ID_SCHEMES))],
            'party_id' => 'nullable|string|max:50',
            'item_type' => ['nullable', Rule::in(array_keys(PintOm::ITEM_TYPES))],
            'hs_code' => ['nullable', 'digits:12', fn ($a, $v, $fail) => $v && ! PintOm::hsDescription($v) ? $fail('Not in the official Oman HS code list.') : null],
            'isic_code' => ['nullable', 'digits:6', fn ($a, $v, $fail) => $v && ! PintOm::isicDescription($v) ? $fail('Not in the official ISIC code list.') : null],
            'exemption_code' => ['nullable', Rule::in(array_merge(array_keys(PintOm::ZERO_RATING), array_keys(PintOm::EXEMPTION)))],
            'currency_id' => 'nullable|exists:currencies,id',
            'opening_fx_amount' => ['nullable', 'regex:/^\d{1,15}(\.\d{1,3})?$/'],
        ], ['vatin.regex' => 'The VATIN must be "OM" followed by 10 digits.', 'opening_amount.regex' => 'Enter an amount with up to 3 decimals.']);

        $group = AccountGroup::query()->findOrFail($this->account_group_id);
        $opening = Money::toBaisa($this->opening_amount ?: 0);

        if ($opening !== 0 && $group->nature->isRevenue()) {
            $this->addError('opening_amount', 'Income and expense ledgers cannot have an opening balance.');

            return;
        }

        $attributes = [
            'name' => $this->name,
            'name_ar' => $this->name_ar ?: null,
            'alias' => $this->alias ?: null,
            'account_group_id' => $group->id,
            'opening_balance' => Money::toDecimal($this->opening_side === 'Cr' ? -$opening : $opening),
            'is_bill_wise' => $this->is_bill_wise,
            'is_active' => $this->is_active,
            'cost_centres_applicable' => $group->nature->isRevenue() && $this->cost_centres_applicable,
            'currency_id' => $group->nature->isRevenue() ? null : ($this->currency_id ?: null),
            'opening_fx_balance' => ! $group->nature->isRevenue() && $this->currency_id && $this->opening_fx_amount !== ''
                ? Money::toDecimal(($this->opening_side === 'Cr' ? -1 : 1) * Money::toBaisa($this->opening_fx_amount))
                : null,
            'vat_category' => $this->vat_category ?: null,
            'tax_role' => $this->tax_role ?: null,
            'vat_rate' => $this->tax_role ? VatCategory::STANDARD_RATE : null,
            'address' => $this->address ?: null,
            'vatin' => $this->vatin ?: null,
            'street' => $this->street ?: null,
            'additional_street' => $this->additional_street ?: null,
            'po_box' => $this->po_box ?: null,
            'city' => $this->city ?: null,
            'postal_code' => $this->postal_code ?: null,
            'country_code' => strtoupper($this->country_code ?: 'OM'),
            'country_subdivision' => $this->country_subdivision ?: null,
            'party_id_scheme' => $this->party_id ? $this->party_id_scheme : null,
            'party_id' => $this->party_id ?: null,
            'item_type' => $group->nature->isRevenue() ? ($this->item_type ?: null) : null,
            'hs_code' => $group->nature->isRevenue() ? ($this->hs_code ?: null) : null,
            'isic_code' => $group->nature->isRevenue() ? ($this->isic_code ?: null) : null,
            'exemption_code' => $group->nature->isRevenue() ? ($this->exemption_code ?: null) : null,
            'cr_number' => $this->cr_number ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'credit_days' => $this->credit_days !== '' ? (int) $this->credit_days : null,
            'bank_name' => $this->bank_name ?: null,
            'bank_account_no' => $this->bank_account_no ?: null,
            'iban' => $this->iban ?: null,
        ];

        if ($this->ledger?->is_reserved) {
            unset($attributes['account_group_id']);
        }

        // Opening balances belong to the first day of the books; keep them fixed once that is locked.
        $openingChanged = Money::toBaisa($attributes['opening_balance']) !== Money::toBaisa($this->ledger?->opening_balance ?? 0)
            || Money::toBaisa($attributes['opening_fx_balance'] ?? 0) !== Money::toBaisa($this->ledger?->opening_fx_balance ?? 0);
        if ($openingChanged && PeriodLock::isLocked(CompanySetting::current()?->books_begin_from)) {
            $this->addError('opening_amount', 'Opening balances cannot change: the books are locked from their beginning.');

            return;
        }

        $ledger = $this->ledger ?? new Ledger;
        $ledger->fill($attributes)->save();
        app(VoucherService::class)->syncOpeningBill($ledger);

        session()->flash('status', "Ledger \"{$this->name}\" saved.");

        return $this->redirectRoute('ledgers.index', navigate: true);
    }

    public function delete()
    {
        abort_if(! $this->ledger || $this->ledger->is_reserved, 403);

        if ($this->ledger->entries()->exists()) {
            $this->addError('name', 'This ledger has vouchers and cannot be deleted. Mark it inactive instead.');

            return;
        }

        $this->ledger->delete();
        session()->flash('status', 'Ledger deleted.');

        return $this->redirectRoute('ledgers.index', navigate: true);
    }

    public function render()
    {
        $group = $this->account_group_id ? AccountGroup::query()->find($this->account_group_id) : null;
        $ancestry = [];
        for ($g = $group; $g; $g = $g->parent_id ? AccountGroup::query()->find($g->parent_id) : null) {
            $ancestry[] = $g->name;
        }

        return view('livewire.masters.ledger-form', [
            'groups' => AccountGroup::query()->orderBy('name')->get(),
            'isParty' => (bool) array_intersect($ancestry, ['Sundry Debtors', 'Sundry Creditors']),
            'isBank' => (bool) array_intersect($ancestry, ['Bank Accounts', 'Bank OD A/c']),
            'isTax' => in_array('Duties & Taxes', $ancestry, true),
            'isRevenue' => $group?->nature->isRevenue() ?? false,
            'currencies' => Currency::query()->orderBy('code')->get(),
        ])->title($this->ledger ? 'Alter ledger' : 'Create ledger');
    }
}
