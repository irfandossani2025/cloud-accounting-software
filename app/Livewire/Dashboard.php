<?php

namespace App\Livewire;

use App\Services\DashboardService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(DashboardService $dashboard)
    {
        return view('livewire.dashboard', $dashboard->summary(now()->startOfDay()));
    }
}
