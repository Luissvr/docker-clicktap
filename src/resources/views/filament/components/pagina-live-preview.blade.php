@php
    $compiledHtml = isset($getLivewire) ? $getLivewire()->compiledPagePreviewHtml : null;
@endphp

<div class="ct-live-preview-wrapper flex flex-col items-center w-full py-2 select-none" x-data="{ mobilePreviewOpen: false, desktopPreview: window.matchMedia('(min-width: 1024px)').matches }" x-on:resize.window.debounce.150ms="desktopPreview = window.matchMedia('(min-width: 1024px)').matches">
    <button type="button" class="ct-mobile-preview-toggle" x-on:click="mobilePreviewOpen = !mobilePreviewOpen" x-bind:aria-expanded="String(mobilePreviewOpen)"><span>Ver vista previa</span><span aria-hidden="true" x-text="mobilePreviewOpen ? '−' : '+'"></span></button>
    <div class="ct-live-preview-content w-full flex flex-col items-center" x-show="desktopPreview || mobilePreviewOpen">
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
                Abrir vista
            </span>
        </x-filament::button>
        <p x-cloak x-show="error" x-text="error" role="alert" class="mt-2 text-sm text-danger-600"></p>
    </div>
    <div class="relative w-full max-w-[340px]">
        <div class="ct-preview-screen relative w-full overflow-hidden bg-white dark:bg-gray-900 h-[590px]"
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
                <p x-show="html === null" class="p-8 pt-16 text-center text-sm text-gray-600" role="status">Pulsa «Actualizar vista previa» para ver los cambios.</p>
                <iframe
                    x-ref="frame"
                    @if ($compiledHtml !== null) srcdoc="{{ $compiledHtml }}" @endif
                    x-show="html !== null"
                    x-on:load="onPreviewLoad()"
                    class="w-full h-full border-0"
                    sandbox="allow-same-origin"
                    title="Vista previa"
                ></iframe>
        </div>
    </div>
    </div>
</div>
