<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViolationCategory extends Model
{
    protected $table = 'violation_category_tbl';

    protected $primaryKey = 'violation_category_id';

    public $timestamps = false;

    protected $fillable = [
        'category_name',
    ];

    public function violationTypes(): HasMany
    {
        return $this->hasMany(
            ViolationType::class,
            'violation_category_id',
            'violation_category_id'
        );
    }
    
}