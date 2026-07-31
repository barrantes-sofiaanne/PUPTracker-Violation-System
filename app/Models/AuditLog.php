<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'module',
        'description',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static ?array $tableColumns = null;

    protected static function booted(): void
    {
        static::creating(function (self $auditLog): void {
            $attributes = $auditLog->getAttributes();

            if (self::hasTableColumn('actor_type') && empty($attributes['actor_type'])) {
                $auditLog->setAttribute('actor_type', $attributes['user_role'] ?? null);
            }

            if (self::hasTableColumn('actor_id') && !array_key_exists('actor_id', $attributes)) {
                $auditLog->setAttribute('actor_id', $attributes['user_id'] ?? null);
            }

            if (self::hasTableColumn('action') && empty($attributes['action'])) {
                $auditLog->setAttribute('action', $attributes['action_type'] ?? null);
            }

            if (self::hasTableColumn('module') && empty($attributes['module'])) {
                $auditLog->setAttribute('module', $attributes['table_affected'] ?? null);
            }

            if (self::hasTableColumn('description') && empty($attributes['description'])) {
                $auditLog->setAttribute(
                    'description',
                    $attributes['new_data'] ?? $attributes['old_data'] ?? null
                );
            }

            $attributes = $auditLog->getAttributes();

            if (self::hasTableColumn('user_role') && empty($attributes['user_role'])) {
                $auditLog->setAttribute('user_role', $attributes['actor_type'] ?? 'system');
            }

            if (self::hasTableColumn('user_id') && !array_key_exists('user_id', $attributes)) {
                $actorId = $attributes['actor_id'] ?? null;
                $auditLog->setAttribute('user_id', $actorId ?? 0);
            }

            if (self::hasTableColumn('action_type') && empty($attributes['action_type'])) {
                $auditLog->setAttribute('action_type', $attributes['action'] ?? 'System Action');
            }

            if (self::hasTableColumn('table_affected') && empty($attributes['table_affected'])) {
                $auditLog->setAttribute('table_affected', $attributes['module'] ?? 'System');
            }

            if (
                self::hasTableColumn('new_data') &&
                empty($attributes['new_data']) &&
                !empty($attributes['description'])
            ) {
                $auditLog->setAttribute('new_data', $attributes['description']);
            }
        });
    }

    protected static function hasTableColumn(string $column): bool
    {
        if (self::$tableColumns === null) {
            self::$tableColumns = Schema::getColumnListing((new self())->getTable());
        }

        return in_array($column, self::$tableColumns, true);
    }

    public function getKeyName(): string
    {
        return self::hasTableColumn('audit_log_id')
            ? 'audit_log_id'
            : 'id';
    }

    public function getActorTypeAttribute($value): ?string
    {
        return $value ?? $this->attributes['user_role'] ?? null;
    }

    public function getActorIdAttribute($value): int|string|null
    {
        return $value ?? $this->attributes['user_id'] ?? null;
    }

    public function getActionAttribute($value): ?string
    {
        return $value ?? $this->attributes['action_type'] ?? null;
    }

    public function getModuleAttribute($value): ?string
    {
        return $value ?? $this->attributes['table_affected'] ?? null;
    }

    public function getDescriptionAttribute($value): ?string
    {
        return $value
            ?? $this->attributes['new_data']
            ?? $this->attributes['old_data']
            ?? null;
    }
}
