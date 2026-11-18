<nav class="settings-nav" aria-label="Settings">
    <a href="{{ route('profile.edit') }}" @class(['active' => $active === 'profile'])>Profile</a>
    <a href="{{ route('security.edit') }}" @class(['active' => $active === 'security'])>Security</a>
    <a href="{{ route('appearance.edit') }}" @class(['active' => $active === 'appearance'])>Appearance</a>
    <a href="{{ route('teams.index') }}" @class(['active' => $active === 'teams'])>Teams</a>
</nav>
