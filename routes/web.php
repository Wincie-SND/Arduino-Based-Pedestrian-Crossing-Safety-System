<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
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
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('violations', [ViolationReportController::class, 'index'])->name('violations.index');
        Route::get('violations/export', [ViolationReportController::class, 'export'])->name('violations.export');
        Route::get('violations/{incident}', [ViolationReportController::class, 'show'])->whereNumber('incident')->name('violations.show');
        Route::patch('violations/{incident}/review', [ViolationReportController::class, 'review'])->whereNumber('incident')->name('violations.review');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
