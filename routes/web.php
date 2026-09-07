<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

// Authentication
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\StudentIdpLoginController;

// Student Controllers
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\StudentRecordController;
use App\Http\Controllers\Student\NotificationController;
use App\Http\Controllers\Student\AnnouncementController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\Student\SettingsController;

// Admin Controllers
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\ViolationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ViolationConfigurationController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SanctionController;
use App\Http\Controllers\Admin\SuperAdminController;
use App\Http\Controllers\Admin\UserManagementHistoryController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\SecurityLoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Auth\TotpSetupController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Admin\ViolationCategoryController;
use App\Http\Controllers\Admin\ViolationTypeController;

// Security Controllers
use App\Http\Controllers\Security\SecurityDashboardController;
/*


|--------------------------------------------------------------------------
| Landing Page
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('landing');
})->name('home');

Route::get('/login', function () {
    return view('index');
})->name('login');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::get('/student/login', [LoginController::class, 'showLogin'])
    ->name('student.login');

Route::post('/student/login', [LoginController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('student.login.post');

// Student IdP (OAuth2 authorization-code flow) login
Route::get('/student/idp/login', [StudentIdpLoginController::class, 'start'])
    ->name('student.idp.login');

Route::get('/student/idp/callback', [StudentIdpLoginController::class, 'callback'])
    ->name('student.idp.callback');

// Backward-compatible POST alias (no student number is required anymore).
Route::post('/student/idp/start', [StudentIdpLoginController::class, 'start'])
    ->name('student.idp.start');

// Secure student self-linking: after IDP auth with an unlinked account, the
// student verifies their local identity by entering their PUPTracker student
// number. The IDP id (from the authenticated session) is then bound to the
// verified local student. The student never types their IDP id.
Route::get('/student/idp/link', [StudentIdpLoginController::class, 'showLinkForm'])
    ->name('student.idp.link');

Route::post('/student/idp/link', [StudentIdpLoginController::class, 'linkAccount'])
    ->middleware('throttle:5,1')
    ->name('student.idp.link.post');

// "Connect / Verify your PUPTracker Account" — staged preview & final submit.
Route::get('/student/idp/link/preview', [StudentIdpLoginController::class, 'showPreview'])
    ->name('student.idp.link.preview');

Route::post('/student/idp/link/program-year', [StudentIdpLoginController::class, 'updateProgramYear'])
    ->middleware('throttle:5,1')
    ->name('student.idp.link.program-year');

Route::post('/student/idp/link/submit', [StudentIdpLoginController::class, 'submitLink'])
    ->middleware('throttle:5,1')
    ->name('student.idp.link.submit');

Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');

Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm'])
    ->name('admin.login');

Route::post('/admin/login', [AdminLoginController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('admin.login.post');

Route::post('/admin/logout', [AdminLoginController::class, 'logout'])
    ->name('admin.logout');


Route::get('/security/login', [SecurityLoginController::class, 'showLoginForm'])
    ->middleware('guest:security')
    ->name('security.login');

Route::post('/security/login', [SecurityLoginController::class, 'login'])
    ->middleware('guest:security')
    ->middleware('throttle:5,1')
    ->name('security.login.post');

Route::post('/security/logout', [SecurityLoginController::class, 'logout'])
    ->middleware('auth:security')
    ->name('security.logout');

Route::get('/mfa/verify', [MfaController::class, 'show'])
    ->name('mfa.verify.show');

Route::post('/mfa/verify', [MfaController::class, 'verify'])
    ->middleware('throttle:6,1')
    ->name('mfa.verify.submit');

Route::post('/mfa/resend', [MfaController::class, 'resend'])
    ->middleware('throttle:3,1')
    ->name('mfa.verify.resend');

Route::post('/mfa/cancel', [MfaController::class, 'cancel'])
    ->name('mfa.verify.cancel');

// Proper password reset flow (see SECURITY MODULE requirements):
//   1. Forgot Password? -> email only (no security questions)
//   2. Send reset link to the registered email (generic response, no account enumeration)
//   3. Reset link identifies the recovery attempt (token only, no email query param)
//   4. Privileged accounts (admin/security) require an OTP step before setting a new password
//   5. Password change invalidates the token and revokes existing sessions
Route::get('/password/forgot/{guard}', [ForgotPasswordController::class, 'showForgotForm'])
    ->whereIn('guard', ['student', 'admin', 'security'])
    ->name('password.request');

Route::post('/password/forgot/{guard}', [ForgotPasswordController::class, 'sendResetLink'])
    ->whereIn('guard', ['student', 'admin', 'security'])
    ->middleware('throttle:5,15')
    ->name('password.email');

Route::get('/password/reset/{guard}/{token}', [ForgotPasswordController::class, 'showResetForm'])
    ->whereIn('guard', ['student', 'admin', 'security'])
    ->name('password.reset.form');

// OTP step for privileged (admin/security) password resets.
// The in-app lock caps verification attempts at 5; the network throttle is a
// looser backstop so a user with a couple of typos isn't blocked by IP.
Route::post('/password/reset/{guard}/otp', [ForgotPasswordController::class, 'verifyOtp'])
    ->whereIn('guard', ['student', 'admin', 'security'])
    ->middleware('throttle:10,15')
    ->name('password.reset.otp');

Route::post('/password/reset/{guard}', [ForgotPasswordController::class, 'reset'])
    ->whereIn('guard', ['student', 'admin', 'security'])
    ->middleware('throttle:5,15')
    ->name('password.update');

/*
|--------------------------------------------------------------------------
| Two-Factor Authentication (TOTP) Setup
|--------------------------------------------------------------------------
*/

Route::middleware('auth:admin,security')
    ->group(function () {
        Route::get('/totp/setup', [App\Http\Controllers\Auth\TotpSetupController::class, 'show'])
            ->name('totp.setup');

        Route::post('/totp/verify', [App\Http\Controllers\Auth\TotpSetupController::class, 'verify'])
            ->middleware('throttle:6,1')
            ->name('totp.verify');

        Route::get('/totp/backup-codes', [App\Http\Controllers\Auth\TotpSetupController::class, 'showBackupCodes'])
            ->name('totp.backup-codes');

        Route::post('/totp/confirm-backup-codes', [App\Http\Controllers\Auth\TotpSetupController::class, 'confirmBackupCodes'])
            ->name('totp.confirm-backup-codes');

        Route::post('/totp/disable', [App\Http\Controllers\Auth\TotpSetupController::class, 'disable'])
            ->middleware('throttle:3,1')
            ->name('totp.disable');
    });

/*
|--------------------------------------------------------------------------
| Announcement Attachments (shared authorized access)
|--------------------------------------------------------------------------
|
| Announcement attachments are stored on a PRIVATE disk and are served only
| through these controlled endpoints. Any authenticated user (student,
| security, or admin) may access the attachment of a published announcement.
| Authorization is enforced server-side on every request; the file is never
| served from the public web root and there is no permanent direct
| object-storage URL.
*/

Route::middleware('auth:student,security,admin')
    ->group(function () {
        Route::get('/announcements/attachments/{attachment}', [App\Http\Controllers\AnnouncementAttachmentController::class, 'show'])
            ->name('announcements.attachments.show');

        Route::get('/announcements/attachments/{attachment}/download', [App\Http\Controllers\AnnouncementAttachmentController::class, 'download'])
            ->name('announcements.attachments.download');
    });


/*
|--------------------------------------------------------------------------
| Student Module
|--------------------------------------------------------------------------
*/

Route::middleware('auth:student')
    ->prefix('student')
    ->group(function () {

        Route::get('/dashboard', [StudentDashboardController::class, 'index'])
            ->name('student.dashboard');

        Route::get('/record', [StudentRecordController::class, 'index'])
            ->name('student.record');

        Route::post('/request-sanction', [StudentRecordController::class, 'requestSanction'])
            ->name('student.request-sanction');

        Route::post('/submit-proof', [StudentRecordController::class, 'submitProof'])
            ->name('student.submit-proof');

        Route::get('/sanction-records/{studentSanctionRecord}/proof', [StudentRecordController::class, 'downloadProof'])
            ->name('student.proof.download');

        Route::get('/violations/evidence/{evidence}', [StudentRecordController::class, 'downloadEvidence'])
            ->name('student.violations.evidence');

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('student.notifications');

        Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])
            ->name('student.notifications.open');

        Route::post('/notifications/status', [NotificationController::class, 'updateStatus'])
            ->name('student.notifications.status');

        Route::get('/announcements', [AnnouncementController::class, 'index'])
            ->name('student.announcements');

        Route::get('/profile', [ProfileController::class, 'index'])
            ->name('student.profile');

        Route::get('/settings', [SettingsController::class, 'index'])
            ->name('student.settings');
    });

/*
|--------------------------------------------------------------------------
| Admin Module
|--------------------------------------------------------------------------
*/

Route::middleware('auth:admin')
    ->prefix('admin')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('admin.dashboard');

        Route::get('/dashboard/chart-data', [AdminDashboardController::class, 'chartData'])
            ->name('admin.dashboard.chart-data');
        Route::get(
            '/violations/history',
            [ViolationController::class, 'history']
        )->name('admin.violations.history');
        Route::get(
            '/violations/{violation}/edit',
            [ViolationController::class, 'edit']
        )->name('admin.violations.edit');

        Route::put(
            '/violations/{violation}',
            [ViolationController::class, 'update']
        )->name('admin.violations.update');

        Route::delete(
            '/violations/{violation}',
            [ViolationController::class, 'destroy']
        )->name('admin.violations.destroy');

        Route::resource(
            'violation-types',
            ViolationTypeController::class,
            [
                'as' => 'admin'
            ]
        )->except(['create', 'edit']);

        Route::get(
            '/violation-types/category/{category}',
            [ViolationTypeController::class, 'categoryTypes']
        )->name('admin.violation-types.category');

        Route::resource(
            'disciplinary-sanctions',
            App\Http\Controllers\Admin\DisciplinarySanctionController::class,
            [
                'as' => 'admin'
            ]
        )->except(['create', 'edit']);
        /*
        |--------------------------------------------------------------------------
        | Students
        |--------------------------------------------------------------------------
        */

        Route::get('/students', [StudentController::class, 'index'])
            ->name('admin.students');

        Route::post('/students', [StudentController::class, 'store'])
            ->name('admin.students.store');

        Route::get('/students/{student_number}/edit', [StudentController::class, 'edit'])
            ->name('admin.students.edit');

        Route::put('/students/{student_number}', [StudentController::class, 'update'])
            ->name('admin.students.update');

        Route::delete('/students/{student_number}', [StudentController::class, 'destroy'])
            ->name('admin.students.destroy');

        Route::get('/students/{student_number}', [StudentController::class, 'show'])
            ->name('admin.students.show');

        Route::post('/students/{student_number}/link-idp', [StudentController::class, 'linkIdpAccount'])
            ->name('admin.students.link-idp');

        Route::post('/students/{student_number}/unlink-idp', [StudentController::class, 'unlinkIdpAccount'])
            ->name('admin.students.unlink-idp');

        Route::post('/users/admins', [StudentController::class, 'storeAdmin'])
            ->name('admin.users.admins.store');

        Route::put('/users/admins/{admin}', [StudentController::class, 'updateAdmin'])
            ->name('admin.users.admins.update');

        Route::delete('/users/admins/{admin}', [StudentController::class, 'destroyAdmin'])
            ->name('admin.users.admins.destroy');

        Route::post('/users/security', [StudentController::class, 'storeSecurity'])
            ->name('admin.users.security.store');

        Route::put('/users/security/{security}', [StudentController::class, 'updateSecurity'])
            ->name('admin.users.security.update');

        Route::delete('/users/security/{security}', [StudentController::class, 'destroySecurity'])
            ->name('admin.users.security.destroy');

        Route::post('/users/import', [StudentController::class, 'importAccounts'])
            ->name('admin.users.import');

        Route::get('/users/import/template', [StudentController::class, 'downloadImportTemplate'])
            ->name('admin.users.import.template');

        /*
        |--------------------------------------------------------------------------
        | Violations
        |--------------------------------------------------------------------------
        */

        Route::get('/violations', [ViolationController::class, 'index'])
            ->name('admin.violations.index');

        Route::get('/violations/create', [ViolationController::class, 'create'])
            ->name('admin.violations.create');

        Route::post('/violations', [ViolationController::class, 'store'])
            ->name('admin.violations.store');

        Route::get('/violations/types', [ViolationController::class, 'getViolationTypes'])
            ->name('admin.violations.types');

        Route::get('/violations/search/student', [ViolationController::class, 'searchStudent'])
            ->name('admin.violations.searchStudent');

        Route::post('/violations/preview', [ViolationController::class, 'previewViolation'])
            ->name('admin.violations.previewViolation');

        Route::post('/violations/offense-level', [ViolationController::class, 'getOffenseLevel'])
            ->name('admin.violations.offense');

        /*
        |--------------------------------------------------------------------------
        | Violation Confirmation Queue (Security → Admin)
        |--------------------------------------------------------------------------
        */

        Route::get('/violations/confirmations', [ViolationController::class, 'confirmations'])
            ->name('admin.violations.confirmations');

        Route::post('/violations/confirm/{violation}', [ViolationController::class, 'confirmViolation'])
            ->name('admin.violations.confirm');

        Route::post('/violations/deny/{violation}', [ViolationController::class, 'denyViolation'])
            ->name('admin.violations.deny');

        Route::get('/violations/evidence/{evidence}', [ViolationController::class, 'downloadEvidence'])
            ->name('admin.violations.evidence');

        Route::get('/violations/evidence/{evidence}/events', [ViolationController::class, 'evidenceEvents'])
            ->name('admin.violations.evidence.events');

        Route::get('/sanctions', [SanctionController::class, 'index'])
            ->name('admin.sanctions.index');

        Route::get('/sanctions/requests/{sanctionRequest}', [SanctionController::class, 'showRequest'])
            ->name('admin.sanctions.requests.show');

        Route::post('/sanctions/requests/{sanctionRequest}/approve', [SanctionController::class, 'approveRequest'])
            ->name('admin.sanctions.requests.approve');

        Route::post('/sanctions/requests/{sanctionRequest}/decline', [SanctionController::class, 'declineRequest'])
            ->name('admin.sanctions.requests.decline');

        Route::post('/sanctions/records/{studentSanctionRecord}/complete', [SanctionController::class, 'acceptProof'])
            ->name('admin.sanctions.records.complete');

        Route::post('/sanctions/records/{studentSanctionRecord}/reject-proof', [SanctionController::class, 'rejectProof'])
            ->name('admin.sanctions.records.reject-proof');

        Route::post('/sanctions/records/{studentSanctionRecord}/revert', [SanctionController::class, 'revertRecordToPending'])
            ->name('admin.sanctions.records.revert');

        Route::get('/sanctions/records/{studentSanctionRecord}/proof', [SanctionController::class, 'downloadProof'])
            ->name('admin.sanctions.proof.download');

        Route::get(
            '/violations/student/{student_number}',
            [ViolationController::class, 'show']
        )->name('admin.violations.studentHistory');

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        | Keep this LAST because it catches ANY value after /violations/
        |--------------------------------------------------------------------------
        */

        Route::get('/violations/{student_number}', [ViolationController::class, 'show'])
            ->name('admin.violations.show');

        /*
        |--------------------------------------------------------------------------
        | Violation Categories
        |--------------------------------------------------------------------------
        */

        Route::prefix('violation-categories')
            ->name('admin.violation-categories.')
            ->group(function () {

                Route::get('/', [ViolationCategoryController::class, 'index'])
                    ->name('index');

                Route::post('/', [ViolationCategoryController::class, 'store'])
                    ->name('store');

                Route::get('/{category}', [ViolationCategoryController::class, 'show'])
                    ->name('show');

                Route::put('/{category}', [ViolationCategoryController::class, 'update'])
                    ->name('update');

                Route::delete('/{category}', [ViolationCategoryController::class, 'destroy'])
                    ->name('destroy');

            });
        /*
        |--------------------------------------------------------------------------
        | Violation Configuration
        |--------------------------------------------------------------------------
        */

        Route::get('/violation-configuration', [ViolationConfigurationController::class, 'index'])
            ->name('admin.violations.configuration');

        Route::post('/violation-configuration/category', [ViolationConfigurationController::class, 'storeCategory'])
            ->name('admin.violations.configuration.category.store');

        Route::put('/violation-configuration/category/{id}', [ViolationConfigurationController::class, 'updateCategory'])
            ->name('admin.violations.configuration.category.update');

        Route::delete('/violation-configuration/category/{id}', [ViolationConfigurationController::class, 'destroyCategory'])
            ->name('admin.violations.configuration.category.destroy');

        /*
        |--------------------------------------------------------------------------
        | Announcements
        |--------------------------------------------------------------------------
        */

        Route::get('/announcements', [AdminAnnouncementController::class, 'index'])
            ->name('admin.announcements');

        Route::get('/announcements/create', [AdminAnnouncementController::class, 'create'])
            ->name('admin.announcements.create');

        Route::post('/announcements', [AdminAnnouncementController::class, 'store'])
            ->name('admin.announcements.store');

        // Secure announcement attachments (private storage, authorized access).
        // These MUST be declared before the wildcard {announcement} show route
        // below so "attachments" is not treated as an announcement id.
        Route::post('/announcements/{announcement}/attachments', [AdminAnnouncementController::class, 'storeAttachment'])
            ->name('admin.announcements.attachments.store');

        Route::delete('/announcements/attachments/{attachment}', [AdminAnnouncementController::class, 'destroyAttachment'])
            ->name('admin.announcements.attachments.destroy');

        Route::get('/announcements/{announcement}', [AdminAnnouncementController::class, 'show'])
            ->name('admin.announcements.show');

        Route::get('/announcements/{announcement}/edit', [AdminAnnouncementController::class, 'edit'])
            ->name('admin.announcements.edit');

        Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'update'])
            ->name('admin.announcements.update');

        Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])
            ->name('admin.announcements.destroy');

        Route::get('/notifications', [AdminNotificationController::class, 'index'])
            ->name('admin.notifications');

        Route::get('/notifications/unread-count', [AdminNotificationController::class, 'unreadCount'])
            ->name('admin.notifications.unread-count');

        Route::get('/notifications/{notification}/open', [AdminNotificationController::class, 'open'])
            ->name('admin.notifications.open');

        Route::post('/notifications/{notification}/read', [AdminNotificationController::class, 'markAsRead'])
            ->name('admin.notifications.read');

        Route::post('/notifications/{notification}/unread', [AdminNotificationController::class, 'markAsUnread'])
            ->name('admin.notifications.unread');

        Route::post('/notifications/mark-all-read', [AdminNotificationController::class, 'markAllRead'])
            ->name('admin.notifications.mark-all-read');

        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */

        Route::get('/reports', [ReportController::class, 'index'])
            ->name('admin.reports');

        Route::post('/reports/filter', [ReportController::class, 'filter'])
            ->name('admin.reports.filter');

        Route::post('/reports/assistant', [ReportController::class, 'assistant'])
            ->name('admin.reports.assistant');

        Route::get('/reports/export', [ReportController::class, 'export'])
            ->name('admin.reports.export');

        Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf'])
            ->name('admin.reports.export-pdf');

        Route::get('/reports/print', [ReportController::class, 'print'])
            ->name('admin.reports.print');

        Route::get('/settings', [AdminSettingsController::class, 'index'])
            ->name('admin.settings');

        Route::get('/profile', [App\Http\Controllers\Admin\AdminProfileController::class, 'index'])
            ->name('admin.profile');

        /*
        |--------------------------------------------------------------------------
        | Program Sanction Summary Report
        |--------------------------------------------------------------------------
        */

        Route::get('/program-report', [App\Http\Controllers\Admin\ProgramReportController::class, 'index'])
            ->name('admin.program-report.index');

        Route::post('/program-report', [App\Http\Controllers\Admin\ProgramReportController::class, 'generate'])
            ->name('admin.program-report.generate');

        Route::post('/program-report/preview-graduating', [App\Http\Controllers\Admin\ProgramReportController::class, 'previewGraduating'])
            ->name('admin.program-report.preview-graduating');

        Route::get('/program-report/search-students', [App\Http\Controllers\Admin\ProgramReportController::class, 'searchStudents'])
            ->name('admin.program-report.search-students');

        Route::post('/program-report/{reportId}/ai-summary', [App\Http\Controllers\Admin\ProgramReportController::class, 'generateAi'])
            ->whereNumber('reportId')
            ->name('admin.program-report.ai-summary');

        Route::get('/program-report/{reportId}', [App\Http\Controllers\Admin\ProgramReportController::class, 'show'])
            ->whereNumber('reportId')
            ->name('admin.program-report.show');

        Route::get('/program-report/{reportId}/pdf', [App\Http\Controllers\Admin\ProgramReportController::class, 'pdf'])
            ->whereNumber('reportId')
            ->name('admin.program-report.pdf');

        Route::get('/program-report/history/all', [App\Http\Controllers\Admin\ProgramReportController::class, 'history'])
            ->name('admin.program-report.history');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->name('admin.audit-logs');

        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])
            ->name('admin.audit-logs.export');

        Route::get('/user-management-history', [UserManagementHistoryController::class, 'index'])
            ->name('admin.user-management-history');

        Route::prefix('super-admin')
            ->name('admin.super-admin.')
            ->middleware('super.admin')
            ->group(function () {
                Route::get('/', [SuperAdminController::class, 'dashboard'])
                    ->name('dashboard');

                Route::get('/audit-trail', function () {
                    return redirect()->route('admin.audit-logs');
                })->name('audit-trail');

                /*
                |--------------------------------------------------------------------------
                | Evidence Security Events (Super Admin)
                |--------------------------------------------------------------------------
                */

                Route::get('/evidence-security-events', [SuperAdminController::class, 'evidenceSecurityEvents'])
                    ->name('evidence-security-events');

                /*
                |--------------------------------------------------------------------------
                | System Logs (Super Admin) — reads the Laravel application log
                |--------------------------------------------------------------------------
                */

                Route::get('/system-logs', [SuperAdminController::class, 'systemLogs'])
                    ->name('system-logs');

                Route::get('/maintenance-configuration', [SuperAdminController::class, 'maintenanceConfiguration'])
                    ->name('maintenance');

                Route::get('/audit-control-plan', [SuperAdminController::class, 'auditControlPlan'])
                    ->name('audit-control-plan');

                Route::post('/maintenance-configuration', [SuperAdminController::class, 'updateMaintenanceConfiguration'])
                    ->name('maintenance.update');

                /*
                |--------------------------------------------------------------------------
                | Quarterly Maintenance Records (Risk No. 6)
                |--------------------------------------------------------------------------
                */

                Route::get('/maintenance-records', [App\Http\Controllers\Admin\MaintenanceRecordController::class, 'index'])
                    ->name('maintenance-records.index');

                Route::get('/maintenance-records/create', [App\Http\Controllers\Admin\MaintenanceRecordController::class, 'create'])
                    ->name('maintenance-records.create');

                Route::post('/maintenance-records', [App\Http\Controllers\Admin\MaintenanceRecordController::class, 'store'])
                    ->name('maintenance-records.store');

                Route::get('/maintenance-records/{maintenanceId}', [App\Http\Controllers\Admin\MaintenanceRecordController::class, 'show'])
                    ->whereNumber('maintenanceId')
                    ->name('maintenance-records.show');

                Route::get('/maintenance-records/{maintenanceId}/edit', [App\Http\Controllers\Admin\MaintenanceRecordController::class, 'edit'])
                    ->whereNumber('maintenanceId')
                    ->name('maintenance-records.edit');

                Route::put('/maintenance-records/{maintenanceId}', [App\Http\Controllers\Admin\MaintenanceRecordController::class, 'update'])
                    ->whereNumber('maintenanceId')
                    ->name('maintenance-records.update');

                Route::delete('/maintenance-records/{maintenanceId}', [App\Http\Controllers\Admin\MaintenanceRecordController::class, 'destroy'])
                    ->whereNumber('maintenanceId')
                    ->name('maintenance-records.destroy');

                /*
                |--------------------------------------------------------------------------
                | Email Incidents / System Logs (Risk No. 7)
                |--------------------------------------------------------------------------
                */

                Route::get('/email-incidents', [App\Http\Controllers\Admin\EmailIncidentController::class, 'index'])
                    ->name('email-incidents.index');

                Route::get('/email-incidents/{incidentId}', [App\Http\Controllers\Admin\EmailIncidentController::class, 'show'])
                    ->whereNumber('incidentId')
                    ->name('email-incidents.show');

                Route::post('/email-incidents/{incidentId}/acknowledge', [App\Http\Controllers\Admin\EmailIncidentController::class, 'acknowledge'])
                    ->whereNumber('incidentId')
                    ->name('email-incidents.acknowledge');

                Route::post('/email-incidents/{incidentId}/resolve', [App\Http\Controllers\Admin\EmailIncidentController::class, 'resolve'])
                    ->whereNumber('incidentId')
                    ->name('email-incidents.resolve');

                Route::post('/email-incidents/simulate-failure', [App\Http\Controllers\Admin\EmailIncidentController::class, 'simulateFailure'])
                    ->name('email-incidents.simulate-failure');

                /*
                |--------------------------------------------------------------------------
                | Backup History (Risk No. 3 — Railway → MyDumper → Cloudflare R2)
                |--------------------------------------------------------------------------
                */

                Route::get('/backups', [App\Http\Controllers\Admin\BackupHistoryController::class, 'index'])
                    ->name('backups.index');

                Route::get('/backups/{backup}', [App\Http\Controllers\Admin\BackupHistoryController::class, 'show'])
                    ->name('backups.show');
            });
    });

// Backup report webhook (called by the Railway backup job — token-protected,
// NOT gated by the super.admin middleware so the cron/script can POST results).
Route::post('/admin/super-admin/backups/report', [App\Http\Controllers\Admin\BackupHistoryController::class, 'report'])
    ->middleware('web')
    ->name('admin.super-admin.backups.report');

Route::middleware('auth:security')
    ->prefix('security')
    ->group(function () {

        Route::get('/dashboard', [SecurityDashboardController::class, 'index'])
            ->name('security.dashboard');

        Route::get('/dashboard/chart-data', [SecurityDashboardController::class, 'chartData'])
            ->name('security.dashboard.chart-data');

        // Student violations routes
        Route::get('/violations/students', [App\Http\Controllers\Security\StudentViolationController::class, 'index'])
            ->name('security.violations.students');

        Route::get('/violations/report', [App\Http\Controllers\Security\StudentViolationController::class, 'report'])
            ->name('security.violations.report');

        Route::get('/violations/submitted', [App\Http\Controllers\Security\StudentViolationController::class, 'submitted'])
            ->name('security.violations.submitted');

        Route::get('/violations/student/{student_number}', [App\Http\Controllers\Security\StudentViolationController::class, 'show'])
            ->name('security.violations.show');

        Route::post('/violations/store', [App\Http\Controllers\Security\StudentViolationController::class, 'store'])
            ->name('security.violations.store');

        Route::get('/violations/types', [App\Http\Controllers\Security\StudentViolationController::class, 'getViolationTypes'])
            ->name('security.violations.types');

        Route::post('/violations/preview', [App\Http\Controllers\Security\StudentViolationController::class, 'previewViolation'])
            ->name('security.violations.preview');

        Route::get('/violations/evidence/{evidence}', [App\Http\Controllers\Security\StudentViolationController::class, 'downloadEvidence'])
            ->name('security.violations.evidence');

        Route::post('/evidence/security-event', [App\Http\Controllers\Security\StudentViolationController::class, 'logSecurityEvent'])
            ->name('security.violations.evidence-security-event');

        // Search routes
        Route::get('/search/student', [App\Http\Controllers\Security\StudentViolationController::class, 'search'])
            ->name('security.search.student');

        Route::get('/sanction-requests', [App\Http\Controllers\Security\SanctionRequestController::class, 'index'])
            ->name('security.sanction-requests');

        Route::post('/sanction-requests/{sanctionRequest}/approve', [App\Http\Controllers\Security\SanctionRequestController::class, 'approve'])
            ->name('security.sanction-requests.approve');

        Route::post('/sanction-requests/{sanctionRequest}/decline', [App\Http\Controllers\Security\SanctionRequestController::class, 'decline'])
            ->name('security.sanction-requests.decline');

        Route::get('/announcements', [App\Http\Controllers\Security\AnnouncementController::class, 'index'])
            ->name('security.announcements');

        Route::get('/notifications', [App\Http\Controllers\Security\NotificationController::class, 'index'])
            ->name('security.notifications');

        Route::get('/notifications/unread-count', [App\Http\Controllers\Security\NotificationController::class, 'unreadCount'])
            ->name('security.notifications.unread-count');

        Route::get('/notifications/{notification}/open', [App\Http\Controllers\Security\NotificationController::class, 'open'])
            ->name('security.notifications.open');

        Route::post('/notifications/{notification}/read', [App\Http\Controllers\Security\NotificationController::class, 'markAsRead'])
            ->name('security.notifications.read');

        Route::post('/notifications/mark-all-read', [App\Http\Controllers\Security\NotificationController::class, 'markAllRead'])
            ->name('security.notifications.mark-all-read');

        Route::get('/profile', [App\Http\Controllers\Security\SecurityProfileController::class, 'index'])
            ->name('security.profile');

    });

// TEMPORARY DEBUG ROUTE - DELETE AFTER USE
// Route::get('/debug/reset-admin-password', [App\Http\Controllers\Admin\DebugPasswordResetController::class, 'resetAdminPassword'])
//     ->withoutMiddleware(['web'])
//     ->name('debug.reset-admin-password');



Route::get('/mailgun-direct-test', function () {

    $response = Http::withBasicAuth(
        'api',
        config('services.mailgun.secret')
    )
        ->asForm()
        ->post(
            'https://' .
            config('services.mailgun.endpoint') .
            '/v3/' .
            config('services.mailgun.domain') .
            '/messages',
            [
                'from' => 'PUP Tracker <' . config('mail.from.address') . '>',
                'to' => 'sabarrantes2911@gmail.com',
                'subject' => 'Railway Mailgun Test',
                'text' => 'Hello from Railway and Mailgun!',
            ]
        );

    return response()->json([
        'status' => $response->status(),
        'body' => $response->body(),
    ]);
});
