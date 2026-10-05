<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViolationEditHistory extends Model
{
    protected $table = 'violation_edit_histories';

    protected $fillable = [
        'violation_id',
        'admin_id',

        'old_violation_type_id',
        'old_violation_date',
        'old_description',

        'new_violation_type_id',
        'new_violation_date',
        'new_description',

        'reason',
    ];

    protected $casts = [
        'old_violation_date' => 'datetime',
        'new_violation_date' => 'datetime',
    ];

    public function violation(): BelongsTo
    {
        return $this->belongsTo(
            Violation::class,
            'violation_id',
            'violation_id'
        );
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(
            Admin::class,
            'admin_id',
            'id'
        );
    }

    public function oldViolationType(): BelongsTo
    {
        return $this->belongsTo(
            ViolationType::class,
            'old_violation_type_id',
            'violation_type_id'
        );
    }

    public function newViolationType(): BelongsTo
    {
        return $this->belongsTo(
            ViolationType::class,
            'new_violation_type_id',
            'violation_type_id'
        );
    }
}