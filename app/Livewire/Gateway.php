<?php

namespace App\Livewire;

use App\Models\CompanySetting;
use App\Models\Voucher;
use App\Services\ReportService;
use App\Support\GatewayMenu;
use Livewire\Component;

/**
 * Gateway and its sub-menus, laid out like Tally: company panel on the left, menu in the centre.
 */
class Gateway extends Component
{
    public string $menu = 'gateway';

    public function mount(string $menu = 'gateway'): void
    {
        abort_unless(isset(GatewayMenu::TITLES[$menu]), 404);

        if (in_array($menu, ['create', 'alter'], true)) {
            $this->authorize('manage-masters');
        }
        if ($menu === 'vouchers') {
            $this->authorize('enter-vouchers');
        }

        $this->menu = $menu;
    }

    public function render(ReportService $reports)
    {
        $today = now()->startOfDay();
        $fyStart = $reports->financialYearStart($today);
        $company = CompanySetting::current();

        return view('livewire.gateway', [
            'title' => GatewayMenu::TITLES[$this->menu],
            'company' => $company,
            'items' => GatewayMenu::items($this->menu, auth()->user()),
            'periodFrom' => $fyStart->max($company->books_begin_from),
            'periodTo' => $fyStart->copy()->addYear()->subDay(),
            'lastEntry' => Voucher::query()->where('is_cancelled', false)->max('date'),
            'cashBank' => $this->menu === 'gateway'
                ? $reports->ledgerBalances($fyStart, $today)->filter(fn ($row) => $row->ledger->isCashOrBank() && $row->closing !== 0)
                : collect(),
        ])->title(GatewayMenu::TITLES[$this->menu]);
    }
}
