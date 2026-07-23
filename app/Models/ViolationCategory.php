<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ViolationCategory extends Model
{
    protected $table = 'violation_category_tbl';

    protected $primaryKey = 'violation_category_id';

    public $timestamps = false;

    protected $fillable = [
        'category_name',
    ];

public function violationTypes()
{
    return $this->hasMany(
        ViolationType::class,
        'violation_category_id',
        'violation_category_id'
    );
}

public function violations(): HasManyThrough
{
    return $this->hasManyThrough(
        Violation::class,
        ViolationType::class,
        'violation_category_id',
        'violation_type',
        'violation_category_id',
        'violation_type_id'
    );
}
    
}