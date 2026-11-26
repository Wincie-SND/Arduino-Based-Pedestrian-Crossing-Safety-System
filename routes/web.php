<?php

use App\Http\Controllers\CrossingCameraController;
use App\Http\Controllers\CrossingNotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ViolationReportController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = request()->user();

    if ($user === null) {
        return to_route('login');
    }

    $team = $user->currentTeam ?? $user->personalTeam();

    return $team
        ? to_route('dashboard', ['current_team' => $team->slug])
        : to_route('teams.index');
})->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard/device-status', [DashboardController::class, 'deviceStatuses'])->name('dashboard.device-status');
        Route::get('dashboard/camera/incidents', [CrossingCameraController::class, 'index'])->name('dashboard.camera.incidents');
        Route::post('dashboard/camera/incidents/{incident}/evidence', [CrossingCameraController::class, 'store'])->whereNumber('incident')->name('dashboard.camera.evidence.store');
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('notifications', [CrossingNotificationController::class, 'index'])->name('notifications.index');
        Route::get('violations', [ViolationReportController::class, 'index'])->name('violations.index');
        Route::get('violations/export', [ViolationReportController::class, 'export'])->name('violations.export');
        Route::get('violations/{incident}', [ViolationReportController::class, 'show'])->whereNumber('incident')->name('violations.show');
        Route::get('violations/{incident}/evidence', [ViolationReportController::class, 'evidence'])->whereNumber('incident')->name('violations.evidence');
        Route::patch('violations/{incident}/review', [ViolationReportController::class, 'review'])->whereNumber('incident')->name('violations.review');
    });

require __DIR__.'/settings.php';
