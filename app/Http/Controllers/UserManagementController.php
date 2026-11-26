<?php

namespace App\Http\Controllers;

use App\Enums\TeamRole;
use App\Http\Requests\Users\StoreEnforcerRequest;
use App\Http\Requests\Users\UpdateWorkspaceUserRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UserManagementController extends Controller
{
    public function index(Team $currentTeam): View
    {
        Gate::authorize('addMember', $currentTeam);

        return view('users.index', [
            'workspace' => $currentTeam,
            'members' => $currentTeam->members()->get(),
        ]);
    }

    public function store(StoreEnforcerRequest $request, Team $currentTeam): RedirectResponse
    {
        DB::transaction(function () use ($request, $currentTeam): void {
            $user = User::create([
                ...$request->validated(),
                'current_team_id' => $currentTeam->id,
            ]);

            $currentTeam->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Enforcer,
            ]);
        });

        return to_route('users.index', ['current_team' => $currentTeam->slug])->with('toast', __('Enforcer account created.'));
    }

    /**
     * Update the specified team member's role.
     */
    public function update(UpdateWorkspaceUserRequest $request, Team $currentTeam, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $currentTeam);

        $membership = $currentTeam->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        abort_if($membership->role === TeamRole::Admin, 403, __('The team Admin role cannot be changed.'));

        $membership->update(['role' => TeamRole::from($request->validated('role'))]);

        return to_route('users.index', ['current_team' => $currentTeam->slug])->with('toast', __('Member role updated.'));
    }

    /**
     * Remove the specified team member.
     */
    public function destroy(Team $currentTeam, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $currentTeam);

        abort_if($user->ownsTeam($currentTeam), 403, __('The workspace Admin cannot be removed.'));

        DB::transaction(function () use ($currentTeam, $user): void {
            $currentTeam->memberships()
                ->where('user_id', $user->id)
                ->delete();

            if ($user->isCurrentTeam($currentTeam)) {
                $user->switchTeam($user->personalTeam() ?? $user->fallbackTeam());
            }
        });

        return to_route('users.index', ['current_team' => $currentTeam->slug])->with('toast', __('Member removed.'));
    }
}
