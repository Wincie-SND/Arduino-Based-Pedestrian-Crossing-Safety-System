@extends('layouts.dashboard')
@section('title', 'User Management')
@section('content')
<header class="dashboard-intro">
    <div><p class="eyebrow">Access management</p><h1>User management</h1><p class="dashboard-summary">Manage authorized accounts for {{ $team->name }}.</p></div>
</header>
<div class="content-column user-management-content">
    @if ($permissions->canCreateInvitation)
        <section class="dashboard-panel user-management-panel"><header class="panel-heading"><div><h2>Invite an authorized user</h2><p>Send an invitation to grant access to this monitoring workspace.</p></div></header><form method="post" action="{{ route('teams.invitations.store', ['team' => $team->slug]) }}" class="row-actions user-invite-form">@csrf<div class="field"><label for="invite-email">Email address</label><input id="invite-email" name="email" type="email" value="{{ old('email') }}" required>@error('email')<p class="field-error">{{ $message }}</p>@enderror</div><div class="field"><label for="invite-role">Access role</label><select id="invite-role" name="role">@foreach ($availableRoles as $role)<option value="{{ $role['value'] }}">{{ $role['label'] }}</option>@endforeach</select></div><button class="button" type="submit">Send invitation</button></form></section>
    @endif

    <section class="dashboard-panel user-management-panel"><header class="panel-heading"><div><h2>Authorized users</h2><p>Accounts with access to this monitoring workspace.</p></div><span class="badge">{{ $members->count() }} users</span></header><div class="table-wrap"><table class="user-management-table"><thead><tr><th>Name</th><th>Email</th><th>Access role</th><th>Actions</th></tr></thead><tbody>
        @foreach ($members as $member)
            @php($memberRole = $member->pivot->role->value)
            <tr><td>{{ $member->name }}</td><td>{{ $member->email }}</td><td>
                @if ($memberRole !== 'owner' && $permissions->canUpdateMember)
                    <form method="post" action="{{ route('teams.members.update', ['team' => $team->slug, 'user' => $member->id]) }}" class="inline-form user-role-form">@csrf @method('patch')<select name="role" aria-label="Role for {{ $member->name }}">@foreach ($availableRoles as $role)<option value="{{ $role['value'] }}" @selected($memberRole === $role['value'])>{{ $role['label'] }}</option>@endforeach</select><button class="button secondary small" type="submit">Save</button></form>
                @else <span class="badge">{{ ucfirst($memberRole) }}</span> @endif
            </td><td>
                @if ($memberRole !== 'owner' && $permissions->canRemoveMember)
                    <form method="post" action="{{ route('teams.members.destroy', ['team' => $team->slug, 'user' => $member->id]) }}" onsubmit="return confirm('Remove {{ addslashes($member->name) }} from this workspace?')">@csrf @method('delete')<button class="button danger small" type="submit">Revoke access</button></form>
                @else <span class="muted">—</span> @endif
            </td></tr>
        @endforeach
    </tbody></table></div></section>

    @if ($permissions->canCancelInvitation || count($invitations))
        <section class="dashboard-panel user-management-panel"><header class="panel-heading"><div><h2>Pending invitations</h2><p>Invitations waiting for a response.</p></div></header>@forelse ($invitations as $invitation)<div class="invitation-row"><div><strong>{{ $invitation->email }}</strong><span>{{ ucfirst($invitation->role->value) }} access</span></div>@if ($permissions->canCancelInvitation)<form method="post" action="{{ route('teams.invitations.destroy', ['team' => $team->slug, 'invitation' => $invitation->code]) }}" onsubmit="return confirm('Cancel this invitation?')">@csrf @method('delete')<button class="button secondary small" type="submit">Cancel invitation</button></form>@endif</div>@empty<p class="muted">No pending invitations.</p>@endforelse</section>
    @endif

</div>
@endsection
