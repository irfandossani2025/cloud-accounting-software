<?php

namespace App\Livewire\Masters;

use App\Enums\TaxRole;
use App\Enums\VatCategory;
use App\Models\AccountGroup;
use App\Models\Ledger;
use App\Support\Money;
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

    public function mount(?Ledger $ledger = null): void
    {
        $this->ledger = $ledger?->exists ? $ledger : null;

        if (! $this->ledger) {
            return;
        }

        foreach (['name', 'name_ar', 'alias', 'address', 'vatin', 'cr_number', 'phone', 'email', 'bank_name', 'bank_account_no', 'iban'] as $field) {
            $this->{$field} = (string) $this->ledger->{$field};
        }

        $this->account_group_id = $this->ledger->account_group_id;
        $this->is_bill_wise = $this->ledger->is_bill_wise;
        $this->is_active = $this->ledger->is_active;
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
            'vatin' => 'nullable|string|max:30',
        ], ['opening_amount.regex' => 'Enter an amount with up to 3 decimals.']);

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
            'vat_category' => $this->vat_category ?: null,
            'tax_role' => $this->tax_role ?: null,
            'vat_rate' => $this->tax_role ? VatCategory::STANDARD_RATE : null,
            'address' => $this->address ?: null,
            'vatin' => $this->vatin ?: null,
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

        $this->ledger ? $this->ledger->update($attributes) : Ledger::query()->create($attributes);

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
        ])->title($this->ledger ? 'Alter ledger' : 'Create ledger');
    }
}
