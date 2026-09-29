<?php

namespace App\Livewire\Reports\Concerns;

use App\Services\ReportService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

trait HasPeriod
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public bool $detailed = false;

    public function mountHasPeriod(): void
    {
        $today = now()->startOfDay();
        $this->to = $this->to ?: $today->toDateString();
        $this->from = $this->from ?: app(ReportService::class)->financialYearStart(Carbon::parse($this->to))->toDateString();
    }

    public function toggleDetailed(): void
    {
        $this->detailed = ! $this->detailed;
    }

    protected function fromDate(): Carbon
    {
        return rescue(fn () => Carbon::parse($this->from)->startOfDay(), now()->startOfYear(), false);
    }

    protected function toDate(): Carbon
    {
        return rescue(fn () => Carbon::parse($this->to)->startOfDay(), now()->startOfDay(), false);
    }
}
