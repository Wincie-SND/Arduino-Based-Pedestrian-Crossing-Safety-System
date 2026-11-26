<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CrossingNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $team = $request->user()->currentTeam;
        abort_if($team === null, 404);

        return view('notifications.index', [
            'notifications' => $team->notifications()->latest('occurred_at')->latest('id')->paginate(15),
        ]);
    }
}
