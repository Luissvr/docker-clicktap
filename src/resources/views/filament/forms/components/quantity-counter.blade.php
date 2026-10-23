<div class="flex min-h-11 items-center justify-between overflow-hidden rounded-lg border border-[#DCE0D5] bg-white" role="group" aria-label="Cantidad de tarjetas">
    <button
        type="button"
        x-on:click="cantidad = Math.max(1, (Number(cantidad) || 1) - 1)"
        class="inline-flex h-11 w-12 items-center justify-center border-r border-[#DCE0D5] text-3xl font-semibold leading-none text-[#101010] hover:bg-[#F6F7F5] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#375500]"
        aria-label="Disminuir cantidad"
    >
        <span aria-hidden="true">−</span>
    </button>
    <output class="min-w-12 text-center text-sm font-semibold tabular-nums text-[#101010]" aria-live="polite" x-text="Number(cantidad) || 1"></output>
    <button
        type="button"
        x-on:click="cantidad = Math.min(2147483647, (Number(cantidad) || 0) + 1)"
        class="inline-flex h-11 w-12 items-center justify-center border-l border-[#DCE0D5] text-3xl font-semibold leading-none text-[#101010] hover:bg-[#F6F7F5] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#375500]"
        aria-label="Aumentar cantidad"
    >
        <span aria-hidden="true">+</span>
    </button>
</div>
