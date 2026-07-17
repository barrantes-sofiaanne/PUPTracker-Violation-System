<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinarySanction extends Model
{
    protected $table = 'disciplinary_sanctions';

    protected $primaryKey = 'disciplinary_sanction_id';

    public $timestamps = false;

    protected $fillable = [
        'violation_type_id',
        'offense_level',
        'disciplinary_sanction',
    ];

    public function violationType(): BelongsTo
    {
        return $this->belongsTo(
            ViolationType::class,
            'violation_type_id',
            'violation_type_id'
        );
    }
}