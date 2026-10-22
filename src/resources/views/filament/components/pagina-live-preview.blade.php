@php
    $compiledHtml = isset($getLivewire) ? $getLivewire()->compiledPagePreviewHtml : null;
@endphp

<div class="ct-live-preview-wrapper flex flex-col items-center w-full py-2 select-none">
    <div class="relative w-full max-w-[340px] rounded-[42px] p-[10px] bg-[#1a1f26] shadow-2xl ring-1 ring-black/15 dark:ring-white/10">
        <div class="absolute top-[18px] left-1/2 -translate-x-1/2 w-24 h-4 bg-black rounded-full z-20 flex items-center justify-end pr-2.5 pointer-events-none">
            <div class="w-2.5 h-2.5 rounded-full bg-[#111827] ring-1 ring-gray-800"></div>
        </div>

        <div class="relative w-full rounded-[32px] overflow-hidden bg-white dark:bg-gray-900 shadow-inner h-[590px]">
            @if ($compiledHtml !== null)
                <iframe
                    wire:key="pagina-preview-{{ sha1($compiledHtml) }}"
                    srcdoc="{{ $compiledHtml }}"
                    class="w-full h-full border-0 rounded-[32px]"
                    sandbox="allow-scripts allow-same-origin"
                    title="Vista previa"
                ></iframe>
            @endif
        </div>
    </div>
</div>
