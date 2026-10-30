@php
    $compiledHtml = isset($getLivewire) ? $getLivewire()->compiledPagePreviewHtml : null;
@endphp

<div class="ct-live-preview-wrapper flex flex-col items-center w-full py-2 select-none" wire:init="loadPagePreview">
    <div class="w-full mb-4 text-center"
        x-data="{
            opening: false,
            error: '',
            async openFinalPreview() {
                if (this.opening) return;
                this.error = '';
                const preview = window.open('', '_blank');
                if (!preview) {
                    this.error = 'Permite abrir ventanas emergentes para ver la vista final.';
                    return;
                }
                preview.opener = null;
                preview.document.title = 'Preparando vista final…';
                preview.document.body.textContent = 'Preparando vista final…';
                this.opening = true;
                try {
                    const html = await this.$wire.renderFinalPagePreview();
                    if (preview.closed) return;
                    const documentPreview = new DOMParser().parseFromString(html, 'text/html');
                    for (const element of documentPreview.querySelectorAll('img[src], link[href]')) {
                        const attribute = element.tagName === 'IMG' ? 'src' : 'href';
                        element.setAttribute(attribute, new URL(element.getAttribute(attribute), window.location.href).href);
                    }
                    preview.document.open();
                    preview.document.write('<!DOCTYPE html>' + documentPreview.documentElement.outerHTML);
                    preview.document.close();
                } catch (_) {
                    preview.close();
                    this.error = 'No se pudo abrir la vista final. Inténtalo nuevamente.';
                } finally {
                    this.opening = false;
                }
            }
        }"
    >
        <x-filament::button
            type="button"
            color="gray"
            icon="heroicon-o-arrow-top-right-on-square"
            x-on:click="openFinalPreview()"
            x-bind:disabled="opening"
            class="w-full"
        >
            <span class="inline-flex items-center gap-2">
                <x-filament::loading-indicator x-cloak x-show="opening" class="h-4 w-4" aria-hidden="true" />
                Abrir vista final
            </span>
        </x-filament::button>
        <p x-cloak x-show="error" x-text="error" role="alert" class="mt-2 text-sm text-danger-600"></p>
    </div>
    <div class="relative w-full max-w-[340px] rounded-[42px] p-[10px] bg-[#1a1f26] shadow-2xl ring-1 ring-black/15 dark:ring-white/10">
        <div class="absolute top-[18px] left-1/2 -translate-x-1/2 w-24 h-4 bg-black rounded-full z-20 flex items-center justify-end pr-2.5 pointer-events-none">
            <div class="w-2.5 h-2.5 rounded-full bg-[#111827] ring-1 ring-gray-800"></div>
        </div>

        <div class="ct-preview-screen relative w-full rounded-[32px] overflow-hidden bg-white dark:bg-gray-900 shadow-inner h-[590px]"
            wire:ignore
            x-data="{ html: $wire.entangle('compiledPagePreviewHtml'), position: 0,
                updatePreview() {
                    if (this.html === null) return;
                    try { this.position = this.$refs.frame.contentWindow.scrollY; } catch (_) {}
                    this.$refs.frame.srcdoc = this.html;
                },
                onPreviewLoad() {
                    let preview;
                    try {
                        preview = this.$refs.frame.contentDocument;
                        this.$refs.frame.contentWindow.scrollTo(0, this.position);
                    } catch (_) { return; }
                    if (!preview) return;
                    preview.addEventListener('click', async (event) => {
                        const button = event.target.closest('[data-copy-text]');
                        if (!button || !button.dataset.copyText.trim()) return;
                        try {
                            await navigator.clipboard.writeText(button.dataset.copyText);
                            const label = button.querySelector('.link-label');
                            if (!label) return;
                            const originalLabel = label.textContent;
                            label.textContent = '¡Copiado!';
                            setTimeout(() => { label.textContent = originalLabel; }, 1500);
                        } catch (_) {}
                    });
                }
            }"
            x-init="$watch('html', () => updatePreview()); updatePreview()"
        >
                <p x-show="html === null" class="p-8 pt-16 text-center text-sm text-gray-600" role="status">Preparando vista previa…</p>
                <iframe
                    x-ref="frame"
                    @if ($compiledHtml !== null) srcdoc="{{ $compiledHtml }}" @endif
                    x-show="html !== null"
                    x-on:load="onPreviewLoad()"
                    class="w-full h-full border-0 rounded-[32px]"
                    sandbox="allow-same-origin"
                    title="Vista previa"
                ></iframe>
        </div>
    </div>
</div>
