<div x-data="{ open: false, adding: false }" class="ct-link-picker">
    <button type="button" class="ct-link-picker-trigger" x-on:click="open = !open" x-bind:aria-expanded="open" aria-controls="ct-link-types">Agregar enlace</button>
    <div id="ct-link-types" x-cloak x-show="open" x-transition.opacity.duration.150ms class="ct-link-types" role="group" aria-label="Tipo de enlace">
        @foreach (\App\Support\LinkTypeHelper::options() as $type => $label)
            <button type="button" x-bind:disabled="adding" x-on:click="adding = true; open = false; $wire.addLinkOfType(@js($type)).then((key) => { $nextTick(() => { const item = [...document.querySelectorAll('.ct-page-editor-grid [x-sortable-item]')].find((element) => element.getAttribute('x-sortable-item') === key); item?.dispatchEvent(new Event('expand')); item?.querySelector('input:not([type=hidden])')?.focus({ preventScroll: true }); }); }).finally(() => { adding = false })" class="ct-link-type">
                {!! \App\Support\LinkTypeHelper::iconSvg($type, 'w-6 h-6') !!}
                <span>{{ $label }}</span>
            </button>
        @endforeach
    </div>
    <div x-cloak x-show="adding" class="ct-link-adding" role="status" aria-live="polite">
        <span>Preparando campos del enlace…</span>
        <span class="ct-link-adding-line"></span>
        <span class="ct-link-adding-line"></span>
    </div>
</div>
