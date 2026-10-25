@php
    $compiledHtml = isset($getLivewire) ? $getLivewire()->compiledPagePreviewHtml : null;
@endphp

<div class="ct-live-preview-wrapper flex flex-col items-center w-full py-2 select-none" wire:init="loadPagePreview">
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
