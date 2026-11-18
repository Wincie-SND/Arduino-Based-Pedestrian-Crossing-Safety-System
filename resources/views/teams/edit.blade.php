@extends('layouts.app')
@section('title', $permissions->canUpdateTeam ? 'Edit '.$team->name : $team->name)
@section('content')
<header class="app-heading"><h1>{{ $permissions->canUpdateTeam ? 'Team settings' : $team->name }}</h1><p>{{ $permissions->canUpdateTeam ? 'Update team information and manage membership.' : 'Team members and invitations.' }}</p></header>
<div class="content-column">
    @if ($permissions->canUpdateTeam)
        <section class="card"><h2 class="section-title">Team name</h2><form method="post" action="{{ route('teams.update', ['team' => $team->slug]) }}">@csrf @method('patch')<div class="field"><label for="name">Team name</label><input id="name" name="name" value="{{ old('name', $team->name) }}" required>@error('name')<p class="field-error">{{ $message }}</p>@enderror</div><button class="button" type="submit">Save team</button></form></section>
    @endif

    @if ($permissions->canCreateInvitation)
        <section class="card"><h2 class="section-title">Invite a member</h2><form method="post" action="{{ route('teams.invitations.store', ['team' => $team->slug]) }}" class="row-actions">@csrf<div class="field" style="flex:2;margin:0"><label for="invite-email">Email address</label><input id="invite-email" name="email" type="email" value="{{ old('email') }}" required>@error('email')<p class="field-error">{{ $message }}</p>@enderror</div><div class="field" style="flex:1;margin:0"><label for="invite-role">Role</label><select id="invite-role" name="role">@foreach ($availableRoles as $role)<option value="{{ $role['value'] }}">{{ $role['label'] }}</option>@endforeach</select></div><button class="button" type="submit">Send invitation</button></form></section>
    @endif

    <section class="card"><h2 class="section-title">Members</h2><div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead><tbody>
        @foreach ($members as $member)
            @php($memberRole = $member->pivot->role->value)
            <tr><td>{{ $member->name }}</td><td>{{ $member->email }}</td><td>
                @if ($memberRole !== 'owner' && $permissions->canUpdateMember)
                    <form method="post" action="{{ route('teams.members.update', ['team' => $team->slug, 'user' => $member->id]) }}" class="inline-form">@csrf @method('patch')<select name="role" aria-label="Role for {{ $member->name }}">@foreach ($availableRoles as $role)<option value="{{ $role['value'] }}" @selected($memberRole === $role['value'])>{{ $role['label'] }}</option>@endforeach</select><button class="button secondary small" type="submit">Save</button></form>
                @else <span class="badge">{{ ucfirst($memberRole) }}</span> @endif
            </td><td>
                @if ($memberRole !== 'owner' && $permissions->canRemoveMember)
                    <form method="post" action="{{ route('teams.members.destroy', ['team' => $team->slug, 'user' => $member->id]) }}" onsubmit="return confirm('Remove {{ addslashes($member->name) }} from the team?')">@csrf @method('delete')<button class="button danger small" type="submit">Remove</button></form>
                @else <span class="muted">—</span> @endif
            </td></tr>
        @endforeach
    </tbody></table></div></section>

    @if ($permissions->canCancelInvitation || count($invitations))
        <section class="card"><h2 class="section-title">Pending invitations</h2>@forelse ($invitations as $invitation)<div class="row-actions" style="justify-content:space-between;padding:.6rem 0;border-bottom:1px solid #e5e9ec"><span>{{ $invitation->email }} <span class="badge">{{ ucfirst($invitation->role->value) }}</span></span>@if ($permissions->canCancelInvitation)<form method="post" action="{{ route('teams.invitations.destroy', ['team' => $team->slug, 'invitation' => $invitation->code]) }}" onsubmit="return confirm('Cancel this invitation?')">@csrf @method('delete')<button class="button secondary small" type="submit">Cancel invitation</button></form>@endif</div>@empty<p class="muted">No pending invitations.</p>@endforelse</section>
    @endif

    @if ($permissions->canDeleteTeam)
        <section class="card"><h2 class="section-title">Delete team</h2><p class="muted">Deleting this team removes its membership and pending invitations.</p><form method="post" action="{{ route('teams.destroy', ['team' => $team->slug]) }}" onsubmit="return confirm('Delete {{ addslashes($team->name) }} permanently?')">@csrf @method('delete')<button class="button danger" type="submit">Delete team</button></form></section>
    @endif
</div>
<p style="margin-top:1.2rem"><a href="{{ route('teams.index') }}">← Back to teams</a></p>
@endsection
