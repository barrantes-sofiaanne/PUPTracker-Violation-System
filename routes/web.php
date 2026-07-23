<?php

use Illuminate\Support\Facades\Route;

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
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\SecurityLoginController;
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
    return view('index');
})->name('home');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::get('/student/login', [LoginController::class, 'showLogin'])
    ->name('student.login');

Route::post('/student/login', [LoginController::class, 'login'])
    ->name('student.login.post');

Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');

Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm'])
    ->name('admin.login');

Route::post('/admin/login', [AdminLoginController::class, 'login'])
    ->name('admin.login.post');

Route::post('/admin/logout', [AdminLoginController::class, 'logout'])
    ->name('admin.logout');


Route::get('/security/login', [SecurityLoginController::class, 'showLoginForm'])
    ->middleware('guest:security')
    ->name('security.login');

Route::post('/security/login', [SecurityLoginController::class, 'login'])
    ->middleware('guest:security')
    ->name('security.login.post');

Route::post('/security/logout', [SecurityLoginController::class, 'logout'])
    ->middleware('auth:security')
    ->name('security.logout');

   

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

        Route::get('/students/{student_number}/edit', [StudentController::class, 'edit'])
            ->name('admin.students.edit');

        Route::put('/students/{student_number}', [StudentController::class, 'update'])
            ->name('admin.students.update');

        Route::delete('/students/{student_number}', [StudentController::class, 'destroy'])
            ->name('admin.students.destroy');

        Route::get('/students/{student_number}', [StudentController::class, 'show'])
            ->name('admin.students.show');

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

        Route::get('/settings', [AdminSettingsController::class, 'index'])
            ->name('admin.settings');
    });

    Route::middleware('auth:security')
    ->prefix('security')
    ->group(function () {

        Route::get('/dashboard', [SecurityDashboardController::class, 'index'])
            ->name('security.dashboard');

        // Student violations routes
        Route::get('/violations/students', [App\Http\Controllers\Security\StudentViolationController::class, 'index'])
            ->name('security.violations.students');

        Route::get('/violations/student/{student_number}', [App\Http\Controllers\Security\StudentViolationController::class, 'show'])
            ->name('security.violations.show');

        // Search routes
        Route::get('/search/student', [App\Http\Controllers\Security\StudentViolationController::class, 'search'])
            ->name('security.search.student');

    });

    