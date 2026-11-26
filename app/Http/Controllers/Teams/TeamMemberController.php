<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\StoreTeamMemberRequest;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TeamMemberController extends Controller
{
    public function store(StoreTeamMemberRequest $request, Team $team): RedirectResponse
    {
        DB::transaction(function () use ($request, $team): void {
            $user = User::create([
                ...$request->validated(),
                'current_team_id' => $team->id,
            ]);

            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Enforcer,
            ]);
        });

        return to_route('teams.edit', ['team' => $team->slug])->with('toast', __('Enforcer account created.'));
    }

    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $team);

        $membership = $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        abort_if($membership->role === TeamRole::Admin, 403, __('The team Admin role cannot be changed.'));

        $membership->update(['role' => TeamRole::from($request->validated('role'))]);

        return to_route('teams.edit', ['team' => $team->slug])->with('toast', __('Member role updated.'));
    }

    /**
     * Remove the specified team member.
     */
    public function destroy(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $team);

        abort_if($user->ownsTeam($team), 403, __('The team Admin cannot be removed.'));

        DB::transaction(function () use ($team, $user): void {
            $team->memberships()
                ->where('user_id', $user->id)
                ->delete();

            if ($user->isCurrentTeam($team)) {
                $user->switchTeam($user->personalTeam() ?? $user->fallbackTeam());
            }
        });

        return to_route('teams.edit', ['team' => $team->slug])->with('toast', __('Member removed.'));
    }
}
