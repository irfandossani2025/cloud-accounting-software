<?php

namespace App\Livewire;

use App\Models\CompanySetting;
use App\Support\PintOm;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Company')]
class CompanyForm extends Component
{
    public string $name = '';

    public string $name_ar = '';

    public string $address = '';

    public string $address_ar = '';

    public string $vatin = '';

    public string $cr_number = '';

    public string $phone = '';

    public string $email = '';

    public string $financial_year_start = '';

    public string $books_begin_from = '';

    public bool $vat_registered = true;

    public string $street = '';

    public string $additional_street = '';

    public string $po_box = '';

    public string $city = '';

    public string $postal_code = '';

    public string $country_subdivision = 'MO';

    public bool $einvoicing_enabled = false;

    public bool $reminders_enabled = false;

    public string $reminder_days = '3,14,30';

    public string $invoice_email_note = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $company = CompanySetting::current();

        foreach (['name', 'name_ar', 'address', 'address_ar', 'vatin', 'cr_number', 'phone', 'email', 'street', 'additional_street', 'po_box', 'city', 'postal_code', 'country_subdivision'] as $field) {
            $this->{$field} = (string) $company->{$field};
        }

        $this->financial_year_start = $company->financial_year_start->toDateString();
        $this->books_begin_from = $company->books_begin_from->toDateString();
        $this->vat_registered = $company->vat_registered;
        $this->einvoicing_enabled = $company->einvoicing_enabled;
        $this->reminders_enabled = $company->reminders_enabled;
        $this->reminder_days = (string) $company->reminder_days;
        $this->invoice_email_note = (string) $company->invoice_email_note;
    }

    public function save()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'address_ar' => 'nullable|string|max:1000',
            // Oman VATIN: "OM" + 10 digits (PINT OM IBR-003-OM); required once e-invoicing is on.
            'vatin' => [$this->einvoicing_enabled ? 'required' : 'nullable', 'regex:/^OM\d{10}$/'],
            'cr_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'financial_year_start' => 'required|date',
            'books_begin_from' => 'required|date|after_or_equal:financial_year_start',
            'vat_registered' => 'boolean',
            'street' => 'nullable|string|max:255',
            'additional_street' => 'nullable|string|max:255',
            'po_box' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_subdivision' => ['required', Rule::in(array_keys(PintOm::SUBDIVISIONS))],
            'einvoicing_enabled' => 'boolean',
            'reminders_enabled' => 'boolean',
            'reminder_days' => ['required', 'regex:/^\s*\d{1,3}(\s*,\s*\d{1,3})*\s*$/'],
            'invoice_email_note' => 'nullable|string|max:2000',
        ], [
            'vatin.regex' => 'The VATIN must be "OM" followed by 10 digits, e.g. OM1100012345.',
            'reminder_days.regex' => 'Enter days overdue separated by commas, e.g. 3,14,30.',
        ]);
        $data['reminder_days'] = collect(explode(',', $data['reminder_days']))->map(fn ($d) => (int) trim($d))->filter()->unique()->sort()->implode(',');

        CompanySetting::current()->update(array_map(fn ($v) => $v === '' ? null : $v, $data));
        session()->flash('status', 'Company details saved.');

        return $this->redirectRoute('gateway', navigate: true);
    }
}
