@extends('layouts.dashboard')
@section('title', 'User Management')
@section('content')
<header class="dashboard-intro">
    <div><p class="eyebrow">Access management</p><h1>User management</h1><p class="dashboard-summary">Manage authorized accounts for the monitored crossing.</p></div>
</header>
<section class="dashboard-panel user-management-panel empty-workspace-panel">
    <header class="panel-heading"><div><h2>No monitoring location assigned</h2><p>Contact your workspace administrator about crossing access.</p></div></header>
</section>
@endsection
