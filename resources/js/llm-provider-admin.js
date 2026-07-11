/** Text AI provider cards — Agent settings (Alpine) */
export function registerLlmProviderAdmin(Alpine) {
    Alpine.data('llmProviderSettings', (config = {}) => ({
        providers: config.providers ?? [],
        saveUrl: config.saveUrl ?? '',
        testUrl: config.testUrl ?? '',
        csrf: config.csrf ?? '',
        agentName: config.agentName ?? 'ShipNest AI',
        agentLogoUrl: config.agentLogoUrl ?? null,
        modalOpen: false,
        draft: {},
        saving: false,
        testingId: null,
        error: '',
        success: '',

        openModal(provider) {
            this.draft = { ...provider, api_key: '' };
            if (! this.draft.model || ! (this.draft.models || {})[this.draft.model]) {
                this.draft.model = this.draft.default_model;
            }
            if (! this.draft.vision_model || ! (this.draft.vision_models || {})[this.draft.vision_model]) {
                this.draft.vision_model = this.draft.default_vision_model;
            }
            this.error = '';
            this.success = '';
            this.modalOpen = true;
        },

        closeModal() {
            this.modalOpen = false;
        },

        async saveProvider() {
            this.saving = true;
            this.error = '';
            this.success = '';
            try {
                const res = await fetch(this.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        provider: this.draft.id,
                        api_key: this.draft.api_key || null,
                        model: this.draft.model || null,
                        vision_model: this.draft.vision_model || null,
                        base_url: this.draft.base_url || null,
                        enabled: this.draft.enabled,
                    }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Save failed');
                window.location.reload();
            } catch (e) {
                this.error = e.message;
            } finally {
                this.saving = false;
            }
        },

        async toggleEnable(providerId, enabled) {
            try {
                const res = await fetch(this.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ provider: providerId, enabled }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Update failed');
                window.location.reload();
            } catch (e) {
                alert(e.message);
            }
        },

        async testProvider(providerId) {
            this.testingId = providerId;
            try {
                const res = await fetch(this.testUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ provider: providerId }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Test failed');
                window.location.reload();
            } catch (e) {
                alert(e.message);
            } finally {
                this.testingId = null;
            }
        },
    }));
}
