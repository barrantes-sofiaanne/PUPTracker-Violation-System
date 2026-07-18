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
use App\Http\Controllers\Admin\AnnouncementsController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ViolationConfigurationController;
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

/*
|--------------------------------------------------------------------------
| Temporary Login Pages
|--------------------------------------------------------------------------
*/

Route::view('/admin/login', 'coming-soon')
    ->name('admin.login');

Route::view('/security/login', 'coming-soon')
    ->name('security.login');

/*
|--------------------------------------------------------------------------
| Student Module
|--------------------------------------------------------------------------
*/

Route::middleware('auth')
    ->prefix('student')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/dashboard',
            [StudentDashboardController::class, 'index']
        )->name('student.dashboard');

        /*
        |--------------------------------------------------------------------------
        | Record
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/record',
            [StudentRecordController::class, 'index']
        )->name('student.record');

        Route::post(
            '/request-sanction',
            [StudentRecordController::class, 'requestSanction']
        )->name('student.request-sanction');

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/notifications',
            [NotificationController::class, 'index']
        )->name('student.notifications');

        /*
        |--------------------------------------------------------------------------
        | Announcements
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/announcements',
            [AnnouncementController::class, 'index']
        )->name('student.announcements');

        /*
        |--------------------------------------------------------------------------
        | Profile
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/profile',
            [ProfileController::class, 'index']
        )->name('student.profile');

        /*
        |--------------------------------------------------------------------------
        | Settings
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/settings',
            [SettingsController::class, 'index']
        )->name('student.settings');

        Route::post(
            '/settings/change-password',
            [SettingsController::class, 'changePassword']
        )->name('student.change-password');

    });

/*
|--------------------------------------------------------------------------
| Admin Module
|--------------------------------------------------------------------------
*/

Route::middleware('auth')
    ->prefix('admin')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/dashboard',
            [AdminDashboardController::class, 'index']
        )->name('admin.dashboard');

        /*
        |--------------------------------------------------------------------------
        | Students
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/students',
            [StudentController::class, 'index']
        )->name('admin.students');

        Route::get(
            '/students/{student_number}',
            [StudentController::class, 'show']
        )->name('admin.students.show');

        /*
        |--------------------------------------------------------------------------
        | Violations
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/violations',
            [ViolationController::class, 'index']
        )->name('admin.violations.index');

        /*
        |--------------------------------------------------------------------------
        | Announcements
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/announcements',
            [AnnouncementController::class, 'index']
        )->name('admin.announcements');

        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/reports',
            [ReportController::class, 'index']
        )->name('admin.reports');
Route::get(
    '/students/{student_number}/edit',
    [StudentController::class, 'edit']
)->name('admin.students.edit');

Route::put(
    '/students/{student_number}',
    [StudentController::class, 'update']
)->name('admin.students.update');
Route::delete(
    '/students/{student_number}',
    [StudentController::class, 'destroy']
)->name('admin.students.destroy');

Route::get(
    '/violations/{student_number}',
    [ViolationController::class, 'show']
)->name('admin.violations.show');
Route::get(
    '/violations/create',
    [ViolationController::class, 'create']
)->name('admin.violations.create');

Route::post(
    '/violations',
    [ViolationController::class, 'store']
)->name('admin.violations.store');
Route::post(
    '/violations/offense-level',
    [ViolationController::class, 'getOffenseLevel']
)->name('admin.violations.offense');

Route::get(
    '/violation-configuration',
    [ViolationConfigurationController::class, 'index']
)->name('admin.violation.configuration');
Route::post(
    '/violation-configuration/category',
    [ViolationConfigurationController::class, 'storeCategory']
)->name('admin.violation.configuration.category.store');

Route::put(
    '/violation-configuration/category/{id}',
    [ViolationConfigurationController::class, 'updateCategory']
)->name('admin.violation.configuration.category.update');

Route::delete(
    '/violation-configuration/category/{id}',
    [ViolationConfigurationController::class, 'destroyCategory']
)->name('admin.violation.configuration.category.destroy');
Route::get(
    '/violations/category/{category}',
    [ViolationController::class, 'getViolationTypes']
)->name('admin.violations.types');

Route::post(
    '/violations/preview',
    [ViolationController::class, 'previewViolation']
)->name('admin.violations.preview');
Route::get(
    '/violations/search-student',
    [ViolationController::class, 'searchStudent']
)->name('admin.violations.searchStudent');
Route::prefix('admin')
    ->middleware(['auth'])
    ->group(function () {
    });
    Route::get(
    '/violations/types',
    [ViolationController::class, 'getViolationTypes']
)->name('admin.violations.types');

Route::post(
    '/violations/preview',
    [ViolationController::class, 'previewViolation']
)->name('admin.violations.preview');

Route::get(
    '/violations/search/student',
    [ViolationController::class, 'searchStudent']
)->name('admin.violations.searchStudent');

Route::get(
    '/violations/history/{student}',
    [ViolationController::class, 'studentHistory']
)->name('admin.violations.history');
    });