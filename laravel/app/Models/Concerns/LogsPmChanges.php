<?php

namespace App\Models\Concerns;

use App\Models\PmChangeLog;

/**
 * Records create / update / delete of a model in the Project Management change history, with old and
 * new values per field. A model can narrow what is logged by defining pmChangeShouldLog() and sets
 * its identity with pmChangeType() / pmChangeLabel() / pmChangeJobId().
 */
trait LogsPmChanges
{
    public static function bootLogsPmChanges(): void
    {
        static::created(fn ($m) => $m->recordPmChange('created', $m->getAttributes()));
        static::updated(function ($m) {
            $changes = $m->getChanges();
            unset($changes['updated_at']);
            if ($changes) {
                $m->recordPmChange('updated', $changes, $m->getOriginal());
            }
        });
        static::deleted(fn ($m) => $m->recordPmChange('deleted', []));
    }

    abstract public function pmChangeType(): string;

    public function pmChangeLabel(): string
    {
        return (string) ($this->name ?? $this->getKey());
    }

    public function pmChangeJobId(): ?int
    {
        return null;
    }

    public function pmChangeShouldLog(): bool
    {
        return true;
    }

    /** Fields never written to the history (noise or secrets). */
    protected function pmChangeIgnored(): array
    {
        return ['updated_at', 'created_at', 'deleted_at', 'id'];
    }

    public function recordPmChange(string $action, array $new, array $old = []): void
    {
        if (! $this->pmChangeShouldLog() || ! static::pmChangeLogReady()) {
            return;
        }
        $log = PmChangeLog::create([
            'user_id' => auth()->id(),
            'entity_type' => $this->pmChangeType(),
            'entity_id' => $this->getKey(),
            'entity_label' => mb_substr($this->pmChangeLabel(), 0, 255),
            'business_job_id' => $this->pmChangeJobId(),
            'action' => $action,
        ]);
        if ($action === 'deleted') {
            return;
        }
        foreach ($new as $field => $value) {
            if (in_array($field, $this->pmChangeIgnored(), true) || ($action === 'created' && $value === null)) {
                continue;
            }
            $log->fields()->create([
                'field' => $field,
                'old_value' => $action === 'updated' ? $this->stringify($old[$field] ?? null) : null,
                'new_value' => $this->stringify($value),
            ]);
        }
    }

    /** Migrations and seeders touch these models before the history tables exist; skip logging then. */
    private static function pmChangeLogReady(): bool
    {
        static $ready = false;   // only a positive answer is cached: the table appears mid-process when migrating

        return $ready = $ready || \Illuminate\Support\Facades\Schema::hasTable('pm_change_log_fields');
    }

    private function stringify(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }

        return mb_substr(is_scalar($v) ? (string) $v : json_encode($v), 0, 2000);
    }
}
