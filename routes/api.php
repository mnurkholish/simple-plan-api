<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ExtendedAuthController;
use App\Http\Controllers\Api\V1\Auth\SsoController;
use App\Http\Controllers\Api\V1\BackupController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExampleLiveController;
use App\Http\Controllers\Api\V1\ImpersonationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketHandlingController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('auth/register', [ExtendedAuthController::class, 'register'])->name('auth.register');
    Route::post('auth/forgot-password', [ExtendedAuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('auth/reset-password', [ExtendedAuthController::class, 'resetPassword'])->name('auth.reset-password');
    Route::get('auth/sso/config', [SsoController::class, 'config'])->name('auth.sso.config');
    Route::get('auth/sso/login', [SsoController::class, 'login'])->name('auth.sso.login');
    Route::get('auth/sso/callback', [SsoController::class, 'callback'])->name('auth.sso.callback');
    Route::post('auth/sso/exchange', [SsoController::class, 'exchange'])->name('auth.sso.exchange');
    Route::get('auth/email/verify/{id}/{hash}', [ExtendedAuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('auth.email.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/email/verification-notification', [ExtendedAuthController::class, 'sendVerificationEmail'])
            ->middleware('throttle:6,1')
            ->name('auth.email.verification-notification');
        Route::post('auth/confirm-password', [ExtendedAuthController::class, 'confirmPassword'])
            ->name('auth.confirm-password');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('dashboard/live-stats', [DashboardController::class, 'liveStats'])->name('dashboard.live-stats');

        Route::get('example-live', [ExampleLiveController::class, 'index'])
            ->middleware('permission:dashboard-access')
            ->name('example-live.index');
        Route::get('example-live/real-market', [ExampleLiveController::class, 'realMarketData'])
            ->middleware('permission:dashboard-access')
            ->name('example-live.market');
        Route::post('example-live/start', [ExampleLiveController::class, 'startJob'])
            ->middleware('permission:dashboard-access')
            ->name('example-live.start');
        Route::get('example-live/status/{taskId}', [ExampleLiveController::class, 'checkStatus'])
            ->middleware('permission:dashboard-access')
            ->name('example-live.status');

        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::post('tickets/{ticket}/verify', [TicketController::class, 'verify'])
            ->name('tickets.verify')
            ->missing(fn () => response()->json(['message' => 'Resource not found.'], 404));
        Route::post('tickets/{ticket}/reject', [TicketController::class, 'reject'])
            ->name('tickets.reject')
            ->missing(fn () => response()->json(['message' => 'Resource not found.'], 404));
        Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign'])
            ->name('tickets.assign')
            ->missing(fn () => response()->json(['message' => 'Resource not found.'], 404));
        Route::post('tickets/{ticket}/handlings', [TicketHandlingController::class, 'store'])
            ->name('tickets.handlings.store')
            ->missing(fn () => response()->json(['message' => 'Resource not found.'], 404));
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])
            ->name('tickets.show')
            ->missing(fn () => response()->json(['message' => 'Resource not found.'], 404));

        Route::post('impersonation/{user}/start', [ImpersonationController::class, 'start'])
            ->middleware('permission:impersonate')
            ->name('impersonation.start');
        Route::post('impersonation/stop', [ImpersonationController::class, 'stop'])
            ->name('impersonation.stop');
        Route::get('impersonation/status', [ImpersonationController::class, 'status'])
            ->name('impersonation.status');

        Route::get('backups', [BackupController::class, 'index'])
            ->middleware('permission:backups-access')
            ->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])
            ->middleware('permission:backups-create')
            ->name('backups.store');
        Route::post('backups/schedule', [BackupController::class, 'saveSchedule'])
            ->middleware('permission:backups-schedule')
            ->name('backups.schedule');
        Route::get('backups/download', [BackupController::class, 'download'])
            ->middleware('permission:backups-download')
            ->name('backups.download');
        Route::delete('backups', [BackupController::class, 'destroy'])
            ->middleware('permission:backups-delete')
            ->name('backups.destroy');

        Route::get('users/options', [UserController::class, 'options'])
            ->middleware('permission:users-create|users-update')
            ->name('users.options');
        Route::get('users', [UserController::class, 'index'])
            ->middleware('permission:users-access')
            ->name('users.index');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('permission:users-create')
            ->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])
            ->middleware('permission:users-access')
            ->name('users.show');
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users-update')
            ->name('users.update');
        Route::delete('users/{ids}', [UserController::class, 'destroy'])
            ->middleware('permission:users-delete')
            ->name('users.destroy');

        Route::get('roles/permissions', [RoleController::class, 'permissions'])
            ->middleware('permission:permissions-access|roles-create|roles-update')
            ->name('roles.permissions');
        Route::get('roles', [RoleController::class, 'index'])
            ->middleware('permission:roles-access')
            ->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])
            ->middleware('permission:roles-create')
            ->name('roles.store');
        Route::get('roles/{role}', [RoleController::class, 'show'])
            ->middleware('permission:roles-access')
            ->name('roles.show');
        Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles-update')
            ->name('roles.update');
        Route::delete('roles/{ids}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles-delete')
            ->name('roles.destroy');

        Route::get('units/users', [UnitController::class, 'users'])
            ->middleware('permission:units-update-all|units-update-owned')
            ->name('units.users');
        Route::post('units/{unit}/users', [UnitController::class, 'syncUsers'])
            ->middleware('permission:units-update-all|units-update-owned')
            ->name('units.users.sync');
        Route::get('units', [UnitController::class, 'index'])
            ->middleware('permission:units-access-all|units-access-owned')
            ->name('units.index');
        Route::post('units', [UnitController::class, 'store'])
            ->middleware('permission:units-create-all')
            ->name('units.store');
        Route::get('units/{unit}', [UnitController::class, 'show'])
            ->middleware('permission:units-access-all|units-access-owned')
            ->name('units.show');
        Route::match(['put', 'patch'], 'units/{unit}', [UnitController::class, 'update'])
            ->middleware('permission:units-update-all|units-update-owned')
            ->name('units.update');
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])
            ->middleware('permission:units-delete-all|units-delete-owned')
            ->name('units.destroy');
    });
});
