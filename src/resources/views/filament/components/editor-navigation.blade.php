<div class="ct-editor-tools"
    x-data="{
        draft: null, timer: null, storageAvailable: true, recovered: false,
        key: 'clicktap:draft:' + @js((string) auth()->id()) + ':' + window.location.pathname,
        revision: @js($getLivewire()->loadedRevision ?? 0),
        init() {
            try {
                const saved = JSON.parse(localStorage.getItem(this.key) || 'null');
                if (saved && saved.expires > Date.now() && saved.data && typeof saved.data === 'object') this.draft = saved;
                else localStorage.removeItem(this.key);
            } catch (_) { this.storageAvailable = false; }
            this.$watch('$wire.data', () => {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.keepDraft(), 600);
            });
            this.$watch('$wire.savedDataHash', () => this.discardDraft());
        },
        keepDraft() {
            if (!this.storageAvailable) return;
            try {
                const data = JSON.parse(JSON.stringify(this.$wire.data));
                delete data.logo_path;
                const draft = { data, revision: this.revision, expires: Date.now() + 86400000 };
                localStorage.setItem(this.key, JSON.stringify(draft));
            } catch (_) { this.storageAvailable = false; }
        },
        recoverDraft() {
            if (!this.draft || this.draft.revision !== this.revision) return;
            const allowed = Object.keys(this.$wire.data);
            for (const [key, value] of Object.entries(this.draft.data)) {
                if (allowed.includes(key) && key !== 'logo_path' && key !== 'revision') this.$wire.$set('data.' + key, value, false);
            }
            this.draft = null;
            this.recovered = true;
            setTimeout(() => this.recovered = false, 6000);
        },
        discardDraft() {
            clearTimeout(this.timer);
            try { localStorage.removeItem(this.key); } catch (_) {}
            this.draft = null;
            this.revision = this.$wire.loadedRevision || 0;
        },
        destroy() { clearTimeout(this.timer); }
    }"
>
<nav class="ct-editor-navigation" aria-label="Secciones del editor">
    @foreach (['datos' => 'Datos', 'logo' => 'Logo', 'enlaces' => 'Enlaces', 'apariencia' => 'Apariencia'] as $key => $label)
        <button type="button" x-on:click="editorTab = editorTab === @js($key) && !desktopEditor ? null : @js($key)" x-bind:aria-current="desktopEditor && editorTab === @js($key) ? 'step' : null" x-bind:aria-expanded="!desktopEditor ? String(editorTab === @js($key)) : null" x-bind:aria-pressed="desktopEditor ? editorTab === @js($key) : null" x-bind:class="{ 'is-active': editorTab === @js($key) }"><span>{{ $label }}</span><svg class="ct-editor-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
    @endforeach
</nav>
<template x-teleport=".fi-page .fi-header">
    <div x-cloak x-show="draft !== null || recovered" class="ct-draft-recovery" role="status">
        <span x-show="recovered">Borrador recuperado. Selecciona el logo nuevamente.</span>
        <template x-if="draft !== null">
            <span class="ct-draft-actions">
                <span x-text="draft?.revision === revision ? 'Borrador recuperable · 24 h' : 'Borrador de una versión anterior'"></span>
                <button type="button" x-show="draft?.revision === revision" x-on:click="recoverDraft()">Recuperar</button>
                <button type="button" x-on:click="discardDraft()">Descartar</button>
            </span>
        </template>
    </div>
</template>
</div>
