<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

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
