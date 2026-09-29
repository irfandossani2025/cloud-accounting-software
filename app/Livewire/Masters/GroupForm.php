<?php

namespace App\Livewire\Masters;

use App\Models\AccountGroup;
use Illuminate\Validation\Rule;
use Livewire\Component;

class GroupForm extends Component
{
    public ?AccountGroup $group = null;

    public string $name = '';

    public string $name_ar = '';

    public ?int $parent_id = null;

    public function mount(?AccountGroup $group = null): void
    {
        $this->group = $group?->exists ? $group->load('parent') : null;

        if ($this->group) {
            $this->fill($this->group->only(['name', 'parent_id']));
            $this->name_ar = (string) $this->group->name_ar;
        }
    }

    public function save()
    {
        $reserved = $this->group?->is_reserved;

        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('account_groups', 'name')->ignore($this->group)],
            'name_ar' => 'nullable|string|max:255',
            'parent_id' => [$reserved ? 'nullable' : 'required', 'exists:account_groups,id'],
        ], ['parent_id.required' => 'Choose the group this belongs under.']);

        if ($reserved) {
            // Predefined groups can be renamed but not moved.
            $this->group->update(['name' => $this->name, 'name_ar' => $this->name_ar ?: null]);
        } else {
            $parent = AccountGroup::query()->findOrFail($this->parent_id);

            if ($this->group && $parent->descendantAndSelfIds()->contains($this->group->id)) {
                $this->addError('parent_id', 'A group cannot be placed under itself.');

                return;
            }

            // Sub-groups inherit nature and gross-profit behaviour from their parent.
            $attributes = [
                'name' => $this->name,
                'name_ar' => $this->name_ar ?: null,
                'parent_id' => $parent->id,
                'nature' => $parent->nature,
                'affects_gross_profit' => $parent->affects_gross_profit,
            ];

            $this->group ? $this->group->update($attributes) : AccountGroup::query()->create($attributes + ['sort_order' => 100]);
        }

        session()->flash('status', "Group \"{$this->name}\" saved.");

        return $this->redirectRoute('groups.index', navigate: true);
    }

    public function delete()
    {
        abort_if(! $this->group || $this->group->is_reserved, 403);

        if ($this->group->children()->exists() || $this->group->ledgers()->exists()) {
            $this->addError('name', 'This group has sub-groups or ledgers and cannot be deleted.');

            return;
        }

        $this->group->delete();
        session()->flash('status', 'Group deleted.');

        return $this->redirectRoute('groups.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.masters.group-form', [
            'parents' => AccountGroup::query()->when($this->group, fn ($q) => $q->whereKeyNot($this->group->id))->orderBy('name')->get(),
        ])->title($this->group ? 'Alter group' : 'Create group');
    }
}
