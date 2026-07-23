<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::latest('created_at')->paginate(15);

        return view('admin.audit-logs.index', compact('logs'));
    }
}
