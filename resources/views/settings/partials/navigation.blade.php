<nav class="settings-nav" aria-label="Settings">
    <a href="{{ route('profile.edit') }}" @class(['active' => $active === 'profile']) @if ($active === 'profile') aria-current="page" @endif>Profile</a>
    <a href="{{ route('security.edit') }}" @class(['active' => $active === 'security']) @if ($active === 'security') aria-current="page" @endif>Security</a>
</nav>
