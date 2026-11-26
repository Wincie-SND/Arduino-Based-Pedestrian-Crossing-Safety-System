@extends('layouts.dashboard')
@section('title', 'User Management')
@section('content')
@use('App\Enums\TeamRole')
<header class="dashboard-intro">
    <div><p class="eyebrow">Access management</p><h1>User management</h1><p class="dashboard-summary">Manage authorized accounts for {{ $team->name }}.</p></div>
</header>
<div class="content-column user-management-content">
    @if ($permissions->canAddMember)
        <section class="dashboard-panel user-management-panel">
            <header class="panel-heading"><h2>Create an Enforcer account</h2></header>
            <form method="post" action="{{ route('teams.members.store', ['team' => $team->slug]) }}" class="user-create-form">
                @csrf
                <div class="field">
                    <label for="enforcer-name">Full name</label>
                    <input id="enforcer-name" name="name" value="{{ old('name') }}" autocomplete="section-enforcer name" required>
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="enforcer-email">Email address</label>
                    <input id="enforcer-email" name="email" type="email" value="{{ old('email') }}" autocomplete="section-enforcer email" required>
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="enforcer-password">Initial password</label>
                    <input id="enforcer-password" name="password" type="password" autocomplete="section-enforcer new-password" required>
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="enforcer-password-confirmation">Confirm password</label>
                    <input id="enforcer-password-confirmation" name="password_confirmation" type="password" autocomplete="section-enforcer new-password" required>
                </div>
                <div><button class="button" type="submit">Create account</button></div>
            </form>
        </section>
    @endif

    <section class="dashboard-panel user-management-panel"><header class="panel-heading"><div><h2>Authorized users</h2><p>Accounts with access to this monitoring workspace.</p></div><span class="badge">{{ $members->count() }} users</span></header><div class="table-wrap"><table class="user-management-table"><thead><tr><th>Name</th><th>Email</th><th>Access role</th><th>Actions</th></tr></thead><tbody>
        @foreach ($members as $member)
            @php($memberRole = $member->pivot->role)
            <tr class="user-member-row"><td data-label="Name">{{ $member->name }}</td><td data-label="Email">{{ $member->email }}</td><td data-label="Access role">
                <span class="badge">{{ $memberRole->label() }}</span>
            </td><td data-label="Actions">
                @if ($memberRole !== TeamRole::Admin && $permissions->canRemoveMember)
                    <form method="post" action="{{ route('teams.members.destroy', ['team' => $team->slug, 'user' => $member->id]) }}" onsubmit="return confirm('Remove {{ addslashes($member->name) }} from this workspace?')">@csrf @method('delete')<button class="button danger small" type="submit">Revoke access</button></form>
                @else <span class="muted">—</span> @endif
            </td></tr>
        @endforeach
    </tbody></table></div></section>

</div>
@endsection
