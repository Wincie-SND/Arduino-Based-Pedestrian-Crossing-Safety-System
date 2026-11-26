<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TeamController extends Controller
{
    /**
     * Open user management for the user's current crossing workspace.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam ?? $user->fallbackTeam() ?? $user->personalTeam();

        if ($team) {
            return to_route('teams.edit', ['team' => $team->slug]);
        }

        return view('teams.index');
    }

    /**
     * Store a newly created team.
     */
    public function store(SaveTeamRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        Gate::authorize('create', Team::class);

        $team = $createTeam->handle($request->user(), $request->validated('name'));

        return to_route('teams.edit', ['team' => $team->slug])->with('toast', __('Team created.'));
    }

    /**
     * Show the team edit page.
     */
    public function edit(Request $request, Team $team): View
    {
        Gate::authorize('addMember', $team);

        $user = $request->user();

        return view('teams.edit', [
            'team' => $team,
            'members' => $team->members()->get(),
            'permissions' => $user->toTeamPermissions($team),
        ]);
    }

    /**
     * Update the specified team.
     */
    public function update(SaveTeamRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team = DB::transaction(function () use ($request, $team) {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $team->update(['name' => $request->validated('name')]);

            return $team;
        });

        return to_route('teams.edit', ['team' => $team->slug])->with('toast', __('Team updated.'));
    }

    /**
     * Switch the user's current team.
     */
    public function switch(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()->belongsToTeam($team), 403);

        $request->user()->switchTeam($team);

        return back();
    }

    /**
     * Leave the specified team.
     */
    public function leave(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('leave', $team);

        $user = $request->user();

        DB::transaction(function () use ($team, $user): void {
            $team->memberships()
                ->where('user_id', $user->id)
                ->delete();

            if ($user->isCurrentTeam($team)) {
                $user->switchTeam($user->fallbackTeam());
            }
        });

        return to_route('teams.index')->with('toast', __('You left the team ":name"', ['name' => $team->name]));
    }

    /**
     * Delete the specified team.
     */
    public function destroy(DeleteTeamRequest $request, Team $team): RedirectResponse
    {
        $user = $request->user();
        $fallbackTeam = $user->isCurrentTeam($team)
            ? $user->fallbackTeam($team)
            : null;

        DB::transaction(function () use ($user, $team) {
            User::where('current_team_id', $team->id)
                ->where('id', '!=', $user->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchTeam(
                    $affectedUser->personalTeam() ?? $affectedUser->fallbackTeam($team),
                ));

            $team->invitations()->delete();
            $team->memberships()->delete();
            $team->delete();
        });

        if ($user->isCurrentTeam($team)) {
            $user->switchTeam($fallbackTeam);
        }

        return to_route('teams.index')->with('toast', __('Team deleted.'));
    }
}
