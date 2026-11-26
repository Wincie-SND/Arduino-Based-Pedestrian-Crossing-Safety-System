@props(['site'])

<div {{ $attributes->class('monitoring-site-card') }}>
    <img class="monitor-site-pin" src="{{ asset('pictures/locationpin.png') }}" alt="" width="24" height="24">
    <div class="monitoring-site-copy">
        <small>Designated monitoring site</small>
        <strong>{{ $site ?? 'Crossing location' }}</strong>
    </div>
</div>
