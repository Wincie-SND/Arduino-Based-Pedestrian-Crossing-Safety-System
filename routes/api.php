<?php

use App\Http\Controllers\Api\CrossingDeviceApiController;
use App\Http\Middleware\AuthenticateCrossingDevice;
use Illuminate\Support\Facades\Route;

Route::prefix('device')->middleware(AuthenticateCrossingDevice::class)->group(function (): void {
    Route::post('heartbeat', [CrossingDeviceApiController::class, 'heartbeat'])->name('api.device.heartbeat');
    Route::post('incidents', [CrossingDeviceApiController::class, 'incident'])->name('api.device.incidents.store');
});
