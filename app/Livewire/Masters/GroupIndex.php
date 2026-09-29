<?php

namespace App\Livewire\Masters;

use App\Models\AccountGroup;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Groups')]
class GroupIndex extends Component
{
    public function render()
    {
        $groups = AccountGroup::query()->withCount('ledgers')->orderBy('sort_order')->orderBy('name')->get();
        $byParent = $groups->groupBy(fn ($g) => $g->parent_id ?? 0);

        // Flatten into display order with depth for indentation.
        $rows = [];
        $walk = function ($parentId, $depth) use (&$walk, &$rows, $byParent) {
            foreach ($byParent->get($parentId, []) as $group) {
                $rows[] = ['group' => $group, 'depth' => $depth];
                $walk($group->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return view('livewire.masters.group-index', ['rows' => $rows]);
    }
}
