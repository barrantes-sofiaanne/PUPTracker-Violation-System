<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores a quarterly maintenance record (Risk No. 6 evidence layer).
 *
 * This is a documented record of maintenance performed by the IT Administrator.
 * It never stores credentials.
 */
class MaintenanceRecord extends Model
{
    protected $table = 'maintenance_records';

    protected $primaryKey = 'maintenance_id';

    protected $fillable = [
        'maintenance_date',
        'maintenance_type',
        'description',
        'performed_by_admin_id',
        'dependency_status',
        'php_runtime_status',
        'server_status',
        'testing_status',
        'overall_status',
        'notes',
        'updates_identified',
        'updates_applied',
        'tests_performed',
        'test_results',
        'problems_found',
        'corrective_actions',
        'checklist_json',
    ];

    protected $casts = [
        'maintenance_date' => 'date',
        'checklist_json' => 'array',
        'performed_by_admin_id' => 'integer',
    ];

    /**
     * Supported maintenance types (documented requirement #3).
     */
    public const TYPES = [
        'Dependency Update',
        'Framework Security Patch',
        'PHP/Runtime Check',
        'Server/Container OS Maintenance',
        'Database Maintenance',
        'Security Review',
        'Post-Update Stability Test',
        'Other',
    ];

    /**
     * Supported overall statuses (documented requirement #5).
     */
    public const STATUSES = [
        'Completed',
        'Completed with Issues',
        'Failed',
        'Follow-up Required',
    ];

    /**
     * Checklist labels (documented requirement #4).
     */
    public const CHECKLIST_ITEMS = [
        'composer_dependencies' => 'Composer dependencies checked',
        'laravel_framework' => 'Laravel/framework checked',
        'php_version' => 'PHP version checked',
        'server_packages' => 'Server/container packages checked',
        'security_patches' => 'Security patches checked',
        'database_connectivity' => 'Database connectivity checked',
        'application_tests' => 'Application tests passed',
        'login_tested' => 'Login tested',
        'email_tested' => 'Email tested',
        'critical_functions' => 'Critical system functions tested',
    ];

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'performed_by_admin_id', 'id');
    }
}
