<?php

namespace App\Livewire\Admin;

use App\Models\CompanySetting;
use App\Services\ReportService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Year-end & period lock')]
class YearEnd extends Component
{
    public string $lockDate = '';

    public function mount(): void
    {
        $this->lockDate = (string) CompanySetting::current()->locked_until?->toDateString();
    }

    public function lockUntil(?string $date = null): void
    {
        $this->authorize('admin');
        $date = $date ?? $this->lockDate;

        $this->validate(['lockDate' => 'nullable|date'], [], ['lockDate' => 'lock date']);
        if ($date && Carbon::parse($date)->gt(now())) {
            $this->addError('lockDate', 'The lock date cannot be in the future.');

            return;
        }

        CompanySetting::current()->update(['locked_until' => $date ?: null]);
        $this->lockDate = (string) $date;
        session()->flash('status', $date ? 'Books locked up to '.Carbon::parse($date)->format('d-M-Y').'.' : 'Period lock removed.');
        $this->redirectRoute('year-end', navigate: true);
    }

    public function render(ReportService $reports)
    {
        $company = CompanySetting::current();
        $years = [];
        $start = $reports->financialYearStart($company->books_begin_from);

        while ($start->lte(now())) {
            $end = $start->copy()->addYear()->subDay();
            $pl = $reports->profitAndLoss($start->copy()->max($company->books_begin_from), $end->copy()->min(now()->startOfDay()));
            $years[] = (object) [
                'start' => $start->copy(),
                'end' => $end,
                'current' => now()->between($start, $end->copy()->endOfDay()),
                'netProfit' => $pl['netProfit'],
                'locked' => $company->locked_until && $company->locked_until->gte($end),
            ];
            $start->addYear();
        }

        return view('livewire.admin.year-end', ['years' => array_reverse($years), 'company' => $company]);
    }
}
