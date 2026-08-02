<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

// Authentication
use App\Http\Controllers\Auth\LoginController;

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
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Admin\ViolationCategoryController;
use App\Http\Controllers\Admin\ViolationTypeController;

// Security Controllers
use App\Http\Controllers\Security\SecurityDashboardController;
use App\Http\Controllers\SecurityContactController;
/*


|--------------------------------------------------------------------------
| Landing Page
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('index');
})->name('home');

/*
|--------------------------------------------------------------------------
| Security Contact & Incident Reporting
|--------------------------------------------------------------------------
*/

Route::get('/security/report', [SecurityContactController::class, 'show'])
    ->name('security.report');

Route::post('/security/report', [SecurityContactController::class, 'submit'])
    ->middleware('throttle:5,60')
    ->name('security.report.submit');

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

// IDP login temporarily disabled due to missing database columns
// Route::post('/student/idp/start', 'App\\Http\\Controllers\\Auth\\StudentIdpLoginController@start')
//     ->middleware('throttle:5,1')
//     ->name('student.idp.start');

// Route::get('/student/idp/login', 'App\\Http\\Controllers\\Auth\\StudentIdpLoginController@redirect')
//     ->name('student.idp.login');

// Route::get('/student/idp/callback', 'App\\Http\\Controllers\\Auth\\StudentIdpLoginController@callback')
//     ->name('student.idp.callback');

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

Route::get('/password/forgot/{guard}', [ForgotPasswordController::class, 'showForgotForm'])
    ->whereIn('guard', ['student', 'admin', 'security'])
    ->name('password.request');

Route::post('/password/forgot/{guard}', [ForgotPasswordController::class, 'sendResetLink'])
    ->whereIn('guard', ['student', 'admin', 'security'])
    ->middleware('throttle:3,1')
    ->name('password.email');

Route::get('/password/reset/{guard}/{token}', [ForgotPasswordController::class, 'showResetForm'])
    ->whereIn('guard', ['student', 'admin', 'security'])
    ->name('password.reset.form');

Route::post('/password/reset/{guard}', [ForgotPasswordController::class, 'reset'])
    ->whereIn('guard', ['student', 'admin', 'security'])
    ->middleware('throttle:5,1')
    ->name('password.update');

   

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

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('student.notifications');

        Route::post('/notifications/status', [NotificationController::class, 'updateStatus'])
            ->name('student.notifications.status');

        Route::get('/announcements', [AnnouncementController::class, 'index'])
            ->name('student.announcements');

        Route::get('/profile', [ProfileController::class, 'index'])
            ->name('student.profile');

        Route::get('/settings', [SettingsController::class, 'index'])
            ->name('student.settings');

        Route::post('/settings/change-password', [SettingsController::class, 'changePassword'])
            ->name('student.change-password');
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
        Route::get('/violations/history', [ViolationController::class, 'history']
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

        Route::get('/sanctions', [SanctionController::class, 'index'])
            ->name('admin.sanctions.index');

        Route::post('/sanctions/requests/{sanctionRequest}/approve', [SanctionController::class, 'approveRequest'])
            ->name('admin.sanctions.requests.approve');

        Route::post('/sanctions/requests/{sanctionRequest}/decline', [SanctionController::class, 'declineRequest'])
            ->name('admin.sanctions.requests.decline');

        Route::post('/sanctions/records/{studentSanctionRecord}/complete', [SanctionController::class, 'markRecordCompleted'])
            ->name('admin.sanctions.records.complete');

        Route::post('/sanctions/records/{studentSanctionRecord}/revert', [SanctionController::class, 'revertRecordToPending'])
            ->name('admin.sanctions.records.revert');

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        | Keep this LAST because it catches ANY value after /violations/
        |--------------------------------------------------------------------------
        */

        Route::get('/violations/{student_number}', [ViolationController::class, 'show'])
            ->name('admin.violations.show');
        Route::get(
    '/violations/student/{student_number}',
    [ViolationController::class, 'show']
)->name('admin.violations.studentHistory');

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

        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->name('admin.audit-logs');

        Route::get('/user-management-history', [UserManagementHistoryController::class, 'index'])
            ->name('admin.user-management-history');

        Route::prefix('super-admin')
            ->name('admin.super-admin.')
            ->middleware('super.admin')
            ->group(function () {
                Route::get('/', [SuperAdminController::class, 'dashboard'])
                    ->name('dashboard');

                Route::get('/audit-trail', [SuperAdminController::class, 'auditTrail'])
                    ->name('audit-trail');

                Route::get('/maintenance-configuration', [SuperAdminController::class, 'maintenanceConfiguration'])
                    ->name('maintenance');

                Route::get('/audit-control-plan', [SuperAdminController::class, 'auditControlPlan'])
                    ->name('audit-control-plan');

                Route::post('/maintenance-configuration', [SuperAdminController::class, 'updateMaintenanceConfiguration'])
                    ->name('maintenance.update');
            });
    });

    Route::middleware('auth:security')
    ->prefix('security')
    ->group(function () {

        Route::get('/dashboard', [SecurityDashboardController::class, 'index'])
            ->name('security.dashboard');

        // Student violations routes
        Route::get('/violations/students', [App\Http\Controllers\Security\StudentViolationController::class, 'index'])
            ->name('security.violations.students');

        Route::get('/violations/report', [App\Http\Controllers\Security\StudentViolationController::class, 'report'])
            ->name('security.violations.report');

        Route::get('/violations/student/{student_number}', [App\Http\Controllers\Security\StudentViolationController::class, 'show'])
            ->name('security.violations.show');

        Route::post('/violations/store', [App\Http\Controllers\Security\StudentViolationController::class, 'store'])
            ->name('security.violations.store');

        Route::get('/violations/types', [App\Http\Controllers\Security\StudentViolationController::class, 'getViolationTypes'])
            ->name('security.violations.types');

        Route::post('/violations/preview', [App\Http\Controllers\Security\StudentViolationController::class, 'previewViolation'])
            ->name('security.violations.preview');

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

    });

// TEMPORARY DEBUG ROUTE - DELETE AFTER USE
Route::get('/debug/reset-admin-password', [App\Http\Controllers\Admin\DebugPasswordResetController::class, 'resetAdminPassword'])
    ->withoutMiddleware(['web'])
    ->name('debug.reset-admin-password');

    Route::get('/mailgun-direct-test', function () {

    $response = Http::withBasicAuth('api', env('MAILGUN_SECRET'))
        ->asForm()
        ->post(
            'https://' . env('MAILGUN_ENDPOINT', 'api.mailgun.net') . '/v3/' . env('MAILGUN_DOMAIN') . '/messages',
            [
                'from'    => 'PUPTracker <' . env('MAIL_FROM_ADDRESS') . '>',
                'to'      => 'sabarrantes2911@gmail.com',
                'subject' => 'Direct Mailgun Test',
                'text'    => 'Testing Mailgun API directly from Laravel.',
            ]
        );

    return response()->json([
        'status' => $response->status(),
        'body'   => $response->body(),
    ]);
});