<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViolationType extends Model
{
    protected $table = 'violation_type_tbl';

    protected $primaryKey = 'violation_type_id';

    public $timestamps = false;

    protected $fillable = [
        'violation_type',
        'violation_category_id',
        'resolution_number',
        'violation_description',
        'severity_level',
    ];

    public function category()
{
    return $this->belongsTo(
        ViolationCategory::class,
        'violation_category_id',
        'violation_category_id'
    );
}

    public function violations(): HasMany
    {
        return $this->hasMany(
            Violation::class,
            'violation_type',
            'violation_type'
        );
    
}
public function disciplinarySanctions()
{
    return $this->hasMany(
        DisciplinarySanction::class,
        'violation_type_id',
        'violation_type_id'
    );
}
}