@php
    $subtitle = $subtitle ?? null;
    $actionUrl = $actionUrl ?? null;
    $actionLabel = $actionLabel ?? null;
@endphp
<div class="admin-page-header">
    <div>
        @hasSection('breadcrumb')
            <nav class="admin-breadcrumb">@yield('breadcrumb')</nav>
        @else
            <nav class="admin-breadcrumb">
                <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                <span>/</span>
                <span class="text-gray-600">@yield('page-title', 'Admin')</span>
            </nav>
        @endif
        <h1 class="admin-page-title">@yield('page-title', 'Admin')</h1>
        @if($subtitle)
            <p class="admin-page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if($actionUrl && $actionLabel)
        <a href="{{ $actionUrl }}" class="btn-primary whitespace-nowrap">{{ $actionLabel }}</a>
    @elseif(isset($actions))
        {{ $actions }}
    @endif
</div>
