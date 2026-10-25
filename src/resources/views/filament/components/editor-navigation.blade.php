<div class="ct-editor-tools"
    x-data="{
        draft: null, timer: null, storageAvailable: true,
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
            this.$wire.loadPagePreview();
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
    @foreach (['datos' => 'Datos y logo', 'enlaces' => 'Enlaces', 'apariencia' => 'Apariencia', 'todo' => 'Ver todo'] as $key => $label)
        <button type="button" x-on:click="editorTab = @js($key)" x-bind:aria-pressed="editorTab === @js($key)">{{ $label }}</button>
    @endforeach
    <button type="submit" form="form" wire:loading.attr="disabled" wire:target="save,create" class="ct-editor-save">
        <span wire:loading wire:target="save,create" role="status" aria-label="Guardando">
            <x-filament::loading-indicator class="h-4 w-4" aria-hidden="true" />
        </span>
        Guardar
    </button>
    <span wire:dirty class="text-sm">Cambios sin guardar</span>
</nav>
<div x-cloak x-show="draft !== null" class="ct-draft-recovery" role="status">
    <span x-text="draft?.revision === revision ? 'Hay cambios recuperables en este dispositivo (24 h). Las imágenes deben seleccionarse de nuevo.' : 'La página cambió en el servidor. La copia del dispositivo pertenece a una revisión anterior.'"></span>
    <button type="button" x-show="draft?.revision === revision" x-on:click="recoverDraft()">Recuperar cambios</button>
    <button type="button" x-on:click="discardDraft()">Descartar copia local</button>
</div>
<p x-cloak x-show="!storageAvailable" class="text-sm">La recuperación local no está disponible. Guarda tus cambios antes de salir.</p>
</div>
