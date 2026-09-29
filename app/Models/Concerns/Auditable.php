<?php

namespace App\Models\Concerns;

use App\Support\Audit;
use Illuminate\Support\Arr;

/** Records creation, changes and deletion of master records in the audit trail. */
trait Auditable
{
    protected static array $auditIgnore = ['created_at', 'updated_at', 'password', 'remember_token'];

    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->audit('created', null, $model->auditValues($model->getAttributes())));

        static::updated(function ($model) {
            $changes = $model->auditValues($model->getChanges());
            if ($changes) {
                $model->audit('altered', Arr::only($model->auditValues($model->getOriginal()), array_keys($changes)), $changes);
            }
        });

        static::deleted(fn ($model) => $model->audit('deleted', $model->auditValues($model->getAttributes()), null));
    }

    private function auditValues(array $values): array
    {
        $values = Arr::except($values, static::$auditIgnore);

        return array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : ($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v), $values);
    }

    private function audit(string $action, ?array $old, ?array $new): void
    {
        $label = class_basename($this);
        $name = $this->name ?? $this->code ?? $this->symbol ?? $this->email ?? '#'.$this->getKey();

        Audit::log($action, $label, $this->getKey(), "{$label} \"{$name}\" {$action}", $old, $new);
    }
}
