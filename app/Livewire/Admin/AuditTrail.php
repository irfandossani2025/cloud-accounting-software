<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Audit trail')]
class AuditTrail extends Component
{
    use WithPagination;

    #[Url]
    public ?int $user = null;

    #[Url]
    public string $type = '';

    #[Url]
    public string $action = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $search = '';

    public ?int $expanded = null;

    public function updating(): void
    {
        $this->resetPage();
    }

    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    public function render()
    {
        $logs = ActivityLog::query()->with('user')
            ->when($this->user, fn ($q) => $q->where('user_id', $this->user))
            ->when($this->type, fn ($q) => $q->where('subject_type', $this->type))
            ->when($this->action, fn ($q) => $q->where('action', $this->action))
            ->when($this->from, fn ($q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to, fn ($q) => $q->where('created_at', '<=', $this->to.' 23:59:59'))
            ->when($this->search, fn ($q) => $q->where('description', 'like', '%'.$this->search.'%'))
            ->orderByDesc('id')
            ->paginate(50);

        return view('livewire.admin.audit-trail', [
            'logs' => $logs,
            'users' => User::query()->orderBy('name')->get(),
            'types' => ActivityLog::query()->distinct()->orderBy('subject_type')->pluck('subject_type'),
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
