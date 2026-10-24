<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PublicController;
use App\Http\Controllers\Api\V1\Staff\AnnouncementController as StaffAnnouncementController;
use App\Http\Controllers\Api\V1\Staff\CourseController as StaffCourseController;
use App\Http\Controllers\Api\V1\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Api\V1\Staff\GroupController as StaffGroupController;
use App\Http\Controllers\Api\V1\Staff\ScheduleController as StaffScheduleController;
use App\Http\Controllers\Api\V1\Staff\SettingsController as StaffSettingsController;
use App\Http\Controllers\Api\V1\Staff\StudyProgramController as StaffStudyProgramController;
use App\Http\Controllers\Api\V1\Staff\SubmissionController as StaffSubmissionController;
use App\Http\Controllers\Api\V1\Staff\TaskController as StaffTaskController;
use App\Http\Controllers\Api\V1\Staff\UserController as StaffUserController;
use App\Http\Controllers\Api\V1\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/', [PublicController::class, 'index']);
    Route::get('study-programs', [PublicController::class, 'programs']);
    Route::get('announcements', [PublicController::class, 'announcements']);
    Route::get('announcements/{id}', [PublicController::class, 'announcement']);
    Route::get('settings', [PublicController::class, 'settings']);

    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:api-login');

    Route::middleware('auth:api')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::patch('auth/profile', [AuthController::class, 'updateProfile']);
        Route::put('auth/password', [AuthController::class, 'updatePassword']);
        Route::get('auth/tokens', [AuthController::class, 'tokens']);
        Route::delete('auth/tokens/{id}', [AuthController::class, 'revokeToken']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read', [NotificationController::class, 'read']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    });

    Route::middleware(['auth:api', 'api.role:student'])->prefix('student')->group(function (): void {
        Route::get('dashboard', [StudentController::class, 'dashboard']);
        Route::get('courses', [StudentController::class, 'courses']);
        Route::get('schedules', [StudentController::class, 'schedules']);
        Route::get('tasks', [StudentController::class, 'tasks']);
        Route::get('tasks/{id}', [StudentController::class, 'task']);
        Route::post('tasks/{id}/submission', [StudentController::class, 'submitTask']);
        Route::get('groups', [StudentController::class, 'groups']);
        Route::get('groups/{id}', [StudentController::class, 'group']);
        Route::get('announcements', [StudentController::class, 'announcements']);
    });

    Route::middleware(['auth:api', 'api.role:staff'])->prefix('staff')->group(function (): void {
        Route::get('dashboard', StaffDashboardController::class);
        Route::get('students', [StaffUserController::class, 'students']);
        Route::apiResource('users', StaffUserController::class)->parameters(['users' => 'id']);
        Route::post('users/{id}/reset-password', [StaffUserController::class, 'resetPassword']);

        Route::apiResource('study-programs', StaffStudyProgramController::class)
            ->parameters(['study-programs' => 'id']);
        Route::apiResource('courses', StaffCourseController::class)->parameters(['courses' => 'id']);
        Route::apiResource('schedules', StaffScheduleController::class)->parameters(['schedules' => 'id']);
        Route::apiResource('groups', StaffGroupController::class)->parameters(['groups' => 'id']);
        Route::post('groups/{id}/members', [StaffGroupController::class, 'addMembers']);
        Route::delete('groups/{id}/members/{userId}', [StaffGroupController::class, 'removeMember']);
        Route::apiResource('tasks', StaffTaskController::class)->parameters(['tasks' => 'id']);
        Route::apiResource('submissions', StaffSubmissionController::class)->parameters(['submissions' => 'id']);
        Route::apiResource('announcements', StaffAnnouncementController::class)
            ->parameters(['announcements' => 'id']);

        Route::get('settings', [StaffSettingsController::class, 'show']);
        Route::match(['put', 'patch'], 'settings', [StaffSettingsController::class, 'update']);
    });
});
