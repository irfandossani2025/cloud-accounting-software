<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic payment reminders: one email per customer when a bill first reaches each overdue stage (e.g. 3, 14, 30 days).
Artisan::command('reminders:send {--dry-run : List what would be sent without sending}', function (
    \App\Services\CommunicationService $communications,
    \App\Services\OutstandingService $outstanding,
) {
    $company = \App\Models\CompanySetting::current();
    if (! $company?->reminders_enabled && ! $this->option('dry-run')) {
        $this->info('Payment reminders are switched off (Company settings).');

        return;
    }

    $days = collect(explode(',', (string) $company?->reminder_days))->map(fn ($d) => (int) trim($d))->filter(fn ($d) => $d > 0)->unique()->sort()->values();
    $debtors = \App\Models\AccountGroup::reserved('Sundry Debtors')->descendantAndSelfIds();
    $sent = 0;

    \App\Models\Ledger::query()->whereIn('account_group_id', $debtors)->where('is_bill_wise', true)->whereNotNull('email')->where('email', '!=', '')
        ->orderBy('name')->each(function (\App\Models\Ledger $customer) use ($communications, $outstanding, $days, &$sent) {
            $already = \App\Models\Communication::query()
                ->where('ledger_id', $customer->id)->where('kind', 'reminder')->where('status', 'sent')
                ->get(['reference', 'stage'])->map(fn ($c) => $c->reference.'#'.$c->stage)->flip();

            $stages = [];
            foreach ($outstanding->pendingBills($customer, now()->startOfDay()) as $bill) {
                $stage = $days->filter(fn ($d) => $d <= $bill->overdue_days)->last();
                if ($bill->pending > 0 && $stage && ! $already->has($bill->reference.'#'.$stage)) {
                    $stages[$bill->reference] = $stage;
                }
            }
            if ($stages === []) {
                return;
            }

            $this->line("{$customer->name} <{$customer->email}>: ".collect($stages)->map(fn ($s, $ref) => "{$ref} ({$s}+ days)")->implode(', '));
            if (! $this->option('dry-run')) {
                try {
                    $communications->emailReminder($customer, $stages);
                    $sent++;
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $this->error('  '.$e->getMessage());
                }
            }
        });

    $this->info("Reminders sent: {$sent}");
})->purpose('Email payment reminders for overdue customer bills');

\Illuminate\Support\Facades\Schedule::command('reminders:send')->dailyAt('08:00')->timezone('Asia/Muscat');
