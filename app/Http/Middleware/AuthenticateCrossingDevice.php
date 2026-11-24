<?php

namespace App\Http\Middleware;

use App\Models\CrossingDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCrossingDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'A device token is required.'], 401);
        }

        $device = CrossingDevice::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        if (! $device) {
            return response()->json(['message' => 'The device token is invalid.'], 401);
        }

        $request->attributes->set('crossing_device', $device);

        return $next($request);
    }
}
