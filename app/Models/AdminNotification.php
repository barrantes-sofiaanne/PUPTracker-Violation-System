<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdminNotification extends Model
{
    use HasFactory;

    protected $table = 'admin_notifications_tbl';

protected $primaryKey = 'admin_notification_id';
    public $timestamps = false;

    protected $fillable = [
        'message',
        'link'
    ];
}