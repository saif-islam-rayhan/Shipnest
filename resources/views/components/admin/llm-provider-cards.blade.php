@props([
    'providers' => [],
    'useLiveLlm' => false,
    'agentName' => 'ShipNest AI',
    'agentLogoUrl' => null,
])

@php
    $badge = function (string $type, string $provider) use ($providers) {
        $p = collect($providers)->firstWhere('id', $provider);
        if (! $p) {
            return ['class' => 'llm-badge-muted', 'label' => '—'];
        }

        return match ($type) {
            'status' => $p['active']
                ? ['class' => 'llm-badge-active', 'label' => 'Active']
                : ['class' => 'llm-badge-muted', 'label' => 'Inactive'],
            'test' => match ($p['test_status'] ?? 'not_tested') {
                'passed' => ['class' => 'llm-badge-pass', 'label' => 'Test passed'],
                'failed' => ['class' => 'llm-badge-fail', 'label' => 'Test failed'],
                default => ['class' => 'llm-badge-muted', 'label' => 'Not tested'],
            },
            default => ['class' => 'llm-badge-muted', 'label' => '—'],
        };
    };
@endphp

<form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" class="settings-card max-w-6xl agent-identity-card mb-6">
    @csrf
    @method('PUT')
    <input type="hidden" name="group" value="agent">

    <div class="agent-identity-head">
        <div class="agent-identity-preview">
            @if($agentLogoUrl)
                <img src="{{ $agentLogoUrl }}" alt="{{ $agentName }}" class="agent-identity-logo">
            @else
                <span class="agent-identity-logo-fallback" aria-hidden="true">
                    <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                </span>
            @endif
            <div>
                <h3 class="agent-identity-title">Agent Identity</h3>
                <p class="agent-identity-subtitle">Name and logo shown in chat widget, sidebar, and responses.</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
        <div>
            <label class="form-label">Agent Name</label>
            <input name="agent_name" value="{{ $agentName }}" class="input-field" placeholder="ShipNest AI">
        </div>
        <div>
            <label class="form-label">Agent Logo</label>
            <input type="file" name="agent_logo" accept="image/*" class="text-sm mt-1 block w-full">
            @if($agentLogoUrl)
                <p class="text-xs text-gray-500 mt-2">Current logo shown in preview above.</p>
            @endif
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm mt-4">
        <input type="checkbox" name="use_live_llm" value="1" @checked($useLiveLlm)>
        Enable live LLM for AI Chat
    </label>

    <button type="submit" class="btn-primary mt-4">Save identity</button>
</form>

<div class="settings-card llm-providers-shell"
     x-data="llmProviderSettings({
        providers: @js($providers),
        saveUrl: @js(route('admin.settings.llm-provider.update')),
        testUrl: @js(route('admin.settings.llm-provider.test')),
        csrf: @js(csrf_token()),
        agentName: @js($agentName),
        agentLogoUrl: @js($agentLogoUrl),
     })">

    <div class="llm-providers-head">
        <div>
            <h3 class="llm-providers-title">Text AI Providers</h3>
            <p class="llm-providers-subtitle">Configure, test, and enable Text AI providers for AI Chat.</p>
        </div>
        @unless($useLiveLlm)
            <p class="llm-providers-alert">Live LLM is off — enable it in Agent Identity above.</p>
        @endunless
    </div>

    <div class="llm-providers-grid">
        @foreach($providers as $provider)
            @php
                $status = $badge('status', $provider['id']);
                $test = $badge('test', $provider['id']);
            @endphp
            <article class="llm-provider-card">
                <header class="llm-provider-head">
                    <span class="llm-provider-icon" style="background: {{ $provider['icon_bg'] }}">{{ $provider['icon'] }}</span>
                    <div class="llm-provider-head-text">
                        <div class="llm-provider-title-row">
                            <h4 class="llm-provider-name">{{ $provider['label'] }}</h4>
                            <span class="llm-provider-tagline">{{ $provider['tagline'] }}</span>
                        </div>
                    </div>
                </header>

                <dl class="llm-provider-meta">
                    <div class="llm-provider-meta-row">
                        <dt>Type</dt>
                        <dd><code>{{ $provider['id'] }}</code></dd>
                    </div>
                    <div class="llm-provider-meta-row">
                        <dt>Configured</dt>
                        <dd>{{ $provider['configured'] ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="llm-provider-meta-row">
                        <dt>Status</dt>
                        <dd><span class="llm-badge {{ $status['class'] }}">{{ $status['label'] }}</span></dd>
                    </div>
                    <div class="llm-provider-meta-row">
                        <dt>Test</dt>
                        <dd><span class="llm-badge {{ $test['class'] }}">{{ $test['label'] }}</span></dd>
                    </div>
                </dl>

                @if($provider['configured'] && $provider['model'])
                    <div class="llm-provider-extra">
                        <p><span class="font-medium text-gray-700">Model:</span> {{ $provider['model_label'] ?? $provider['model'] }}</p>
                        @if($provider['last_tested_human'])
                            <p><span class="font-medium text-gray-700">Last tested:</span> {{ $provider['last_tested_human'] }}</p>
                        @endif
                    </div>
                @endif

                <label class="llm-provider-enable">
                    <input type="checkbox"
                           @if($provider['enabled']) checked @endif
                           @change="toggleEnable('{{ $provider['id'] }}', $event.target.checked)">
                    <span>Enable provider</span>
                </label>

                <div class="llm-provider-actions">
                    <button type="button" class="llm-btn-configure" @click="openModal(@js($provider))">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Configure
                    </button>
                    @if($provider['configured'])
                        <button type="button"
                                class="llm-btn-test"
                                :disabled="testingId === '{{ $provider['id'] }}'"
                                @click="testProvider('{{ $provider['id'] }}')">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                            <span x-text="testingId === '{{ $provider['id'] }}' ? 'Testing...' : 'Test'"></span>
                        </button>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    <div x-show="modalOpen" x-cloak class="llm-modal-backdrop" @keydown.escape.window="closeModal()">
        <div class="llm-modal" @click.outside="closeModal()">
            <div class="flex items-start justify-between gap-3 mb-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2.5 mb-1">
                        <template x-if="agentLogoUrl">
                            <img :src="agentLogoUrl" alt="" class="agent-identity-logo agent-identity-logo--sm shrink-0" aria-hidden="true">
                        </template>
                        <template x-if="!agentLogoUrl">
                            <span class="agent-identity-logo-fallback agent-identity-logo-fallback--sm shrink-0" aria-hidden="true">
                                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                            </span>
                        </template>
                        <span class="text-xs font-medium text-gray-500 truncate" x-text="agentName"></span>
                    </div>
                    <h4 class="font-semibold text-gray-900" x-text="draft.label ? `Configure ${draft.label}` : 'Configure provider'"></h4>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-700 text-xl leading-none shrink-0" @click="closeModal()">×</button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="form-label">API Key</label>
                    <input type="password" x-model="draft.api_key" class="input-field" :placeholder="draft.has_api_key ? '•••••••• (saved — leave blank to keep)' : 'Enter API key'" autocomplete="new-password">
                </div>
                <div>
                    <label class="form-label">Chat Model</label>
                    <select x-model="draft.model" class="input-field">
                        <template x-for="(label, modelId) in (draft.models || {})" :key="modelId">
                            <option :value="modelId" x-text="label"></option>
                        </template>
                    </select>
                </div>
                <template x-if="draft.supports_vision && Object.keys(draft.vision_models || {}).length">
                    <div>
                        <label class="form-label">Vision Model</label>
                        <select x-model="draft.vision_model" class="input-field">
                            <template x-for="(label, modelId) in (draft.vision_models || {})" :key="modelId">
                                <option :value="modelId" x-text="label"></option>
                            </template>
                        </select>
                    </div>
                </template>
                <template x-if="draft.requires_base_url">
                    <div>
                        <label class="form-label">Base URL</label>
                        <input type="url" x-model="draft.base_url" class="input-field" :placeholder="draft.default_base_url">
                    </div>
                </template>
            </div>

            <p class="text-xs text-red-600 mt-3" x-show="error" x-text="error"></p>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn-outline" @click="closeModal()">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="saveProvider()">
                    <span x-text="saving ? 'Saving...' : 'Save'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
