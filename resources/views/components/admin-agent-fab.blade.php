@unless(request()->routeIs('admin.agent.index'))
    <x-agent-fab context="admin" />
@endunless
