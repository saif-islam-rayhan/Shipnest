@php
    use Illuminate\Support\Facades\Storage;

    $agentLogoUrl = ! empty($agent['agent_logo'])
        ? Storage::disk('public')->url($agent['agent_logo'])
        : null;
@endphp

<x-admin.llm-provider-cards
    :providers="$llmProviderCards"
    :use-live-llm="($agent['use_live_llm'] ?? '0') === '1'"
    :agent-name="$agent['agent_name'] ?? 'ShipNest AI'"
    :agent-logo-url="$agentLogoUrl"
/>

<details class="settings-card max-w-6xl mt-4 group">
    <summary class="cursor-pointer list-none font-semibold text-gray-800 flex items-center justify-between py-1">
        <span>Web search &amp; Google AI Mode</span>
        <span class="text-xs text-gray-400 group-open:rotate-180 transition-transform">▼</span>
    </summary>
    <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-4 pt-4 border-t mt-3">@csrf @method('PUT')
        <input type="hidden" name="group" value="agent">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Search Backend</label>
                <select name="search_backend" class="input-field">
                    <option value="duckduckgo" @selected(($agent['search_backend'] ?? 'duckduckgo') === 'duckduckgo')>DuckDuckGo (free)</option>
                    <option value="searxng" @selected(($agent['search_backend'] ?? '') === 'searxng')>SearXNG</option>
                    <option value="tavily" @selected(($agent['search_backend'] ?? '') === 'tavily')>Tavily</option>
                    <option value="serpapi" @selected(($agent['search_backend'] ?? '') === 'serpapi')>SerpAPI</option>
                    <option value="free" @selected(($agent['search_backend'] ?? '') === 'free')>Free (DuckDuckGo / SearXNG)</option>
                </select>
            </div>
            <div>
                <label class="form-label">SearXNG URL</label>
                <input name="searxng_url" value="{{ $agent['searxng_url'] ?? '' }}" class="input-field" placeholder="https://searx.example.com">
            </div>
            <div>
                <label class="form-label">Tavily API Key</label>
                <input name="tavily_api_key" type="password" class="input-field" placeholder="{{ $pwd('tavily_api_key') ?: 'Tavily API key' }}" autocomplete="new-password">
            </div>
            <div>
                <label class="form-label">SerpAPI Key</label>
                <input name="serpapi_key" type="password" class="input-field" placeholder="{{ $pwd('serpapi_key') ?: 'SerpAPI key' }}" autocomplete="new-password">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="use_google_ai_mode" value="1" @checked(($agent['use_google_ai_mode'] ?? '1') === '1')>
            Enable Google AI Mode (requires SerpAPI)
        </label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Country (gl)</label>
                <input name="google_ai_mode_gl" value="{{ $agent['google_ai_mode_gl'] ?? 'bd' }}" class="input-field">
            </div>
            <div>
                <label class="form-label">Language (hl)</label>
                <input name="google_ai_mode_hl" value="{{ $agent['google_ai_mode_hl'] ?? 'en' }}" class="input-field">
            </div>
            <div class="md:col-span-2">
                <label class="form-label">Location</label>
                <input name="google_ai_mode_location" value="{{ $agent['google_ai_mode_location'] ?? 'Dhaka, Bangladesh' }}" class="input-field">
            </div>
        </div>
        <button class="btn-primary">Save search settings</button>
    </form>
</details>
