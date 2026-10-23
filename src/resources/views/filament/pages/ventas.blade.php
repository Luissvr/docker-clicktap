<x-filament-panels::page>
    @if ($sale)
        @php
            $isDraft = $sale->estado === 'borrador';
        @endphp
        <section class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#DCE0D5] bg-white p-4 sm:p-6">
                <div>
                    <p class="text-sm text-[#596052]">{{ $isDraft ? 'Venta en preparación' : 'Venta confirmada' }}</p>
                    <h2 class="mt-1 text-xl font-semibold text-[#101010]">{{ $sale->cliente_nombre ?: 'Cliente pendiente' }}</h2>
                    <p class="mt-1 text-sm text-[#596052]">Creada {{ \Illuminate\Support\Carbon::parse($sale->created_at)->timezone('America/Santiago')->format('d/m/Y H:i') }}</p>
                </div>
                <a href="{{ \App\Filament\Pages\Ventas::getUrl() }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-[#DCE0D5] bg-white px-4 text-sm font-medium text-[#101010] hover:bg-[#F6F7F5]">
                    <x-heroicon-o-arrow-left class="h-4 w-4" aria-hidden="true" /> Volver a ventas
                </a>
            </div>

            <section class="rounded-xl border border-[#DCE0D5] bg-white p-4 sm:p-6">
                <h3 class="font-semibold text-[#101010]">Página del cliente</h3>
                @if ($pageId)
                    <p class="mt-2 text-sm text-[#596052]">{{ $pageName }}</p>
                    <a href="{{ \App\Filament\Resources\PaginaResource::getUrl('edit', ['record' => $pageId]) }}" wire:navigate class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-lg border border-[#DCE0D5] px-4 text-sm font-medium text-[#101010] hover:bg-[#F6F7F5]">
                        <x-heroicon-o-pencil-square class="h-4 w-4" aria-hidden="true" /> Editar página
                    </a>
                @elseif ($isDraft)
                    <p class="mt-2 text-sm text-[#596052]">Comienza la visita creando la página. Al guardarla volverás a esta misma venta.</p>
                    <a href="{{ \App\Filament\Resources\PaginaResource::getUrl('create', ['venta_id' => $sale->id]) }}" wire:navigate class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-lg bg-[#B6FF00] px-4 text-sm font-semibold text-[#101010] hover:bg-[#A0F700]">
                        <x-heroicon-o-plus class="h-4 w-4" aria-hidden="true" /> Crear página
                    </a>
                @else
                    <p class="mt-2 text-sm text-[#596052]">Esta venta se confirmó sin una página asociada.</p>
                @endif
            </section>

            <div @if ($isDraft) x-data="{
                google: $wire.entangle('data.tarjeta_google'),
                modo: $wire.entangle('data.forma_cobro'),
                cantidad: $wire.entangle('data.cantidad_personalizada'),
                totalPersonalizado: $wire.entangle('data.precio_recibido_clp'),
                get unidades() { return !this.google ? 0 : this.modo === 'una' ? 1 : this.modo === 'dos' ? 2 : (Number(this.cantidad) || 0) },
                get total() { return !this.google ? 0 : this.modo === 'una' ? 15000 : this.modo === 'dos' ? 20000 : (Number(this.totalPersonalizado) || 0) },
                format(value) { return new Intl.NumberFormat('es-CL').format(value) }
            }" @endif class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(16rem,0.7fr)]">
                <div class="rounded-xl border border-[#DCE0D5] bg-white p-4 sm:p-6">
                    <h3 class="mb-4 text-base font-semibold text-[#101010]">Datos de la venta</h3>
                    @if ($isDraft)
                        {{ $this->form }}
                    @else
                        <p class="text-sm text-[#596052]">Esta venta quedó confirmada y el stock ya fue descontado.</p>
                        <p class="mt-2 text-sm text-[#596052]">Cliente: {{ $sale->cliente_nombre ?: 'Sin nombre' }}</p>
                        @if ($sale->local_nombre)
                            <p class="mt-1 text-sm text-[#596052]">Local: {{ $sale->local_nombre }}</p>
                        @endif
                    @endif
                </div>

                <aside class="space-y-4">
                    <section class="rounded-xl border border-[#DCE0D5] bg-white p-4 sm:p-6">
                        <h3 class="font-semibold text-[#101010]">Resumen</h3>
                        @if ($isDraft)
                            <p class="mt-2 text-sm text-[#596052]">Tarjeta Google · <span x-text="unidades"></span> <span x-text="unidades === 1 ? 'unidad' : 'unidades'"></span></p>
                            <p class="mt-1 text-xl font-semibold text-[#101010]">$<span x-text="format(total)"></span> CLP</p>
                        @else
                            <p class="mt-2 text-sm text-[#596052]">Tarjeta Google · {{ (int) $sale->cantidad_tarjetas }} {{ (int) $sale->cantidad_tarjetas === 1 ? 'unidad' : 'unidades' }}</p>
                            <p class="mt-1 text-xl font-semibold text-[#101010]">${{ number_format($sale->total_clp, 0, ',', '.') }} CLP</p>
                        @endif
                        <p class="mt-2 text-xs text-[#596052]">Total recibido por la cantidad seleccionada.</p>
                    </section>
                </aside>
            </div>

            @if ($isDraft)
                <div class="space-y-3">
                    <x-filament::button wire:click="saveDraft" color="gray" icon="heroicon-o-bookmark" class="w-full">Guardar borrador</x-filament::button>
                    <x-filament::button wire:click="confirmSale" icon="heroicon-o-check" class="w-full">Confirmar venta</x-filament::button>
                    <x-filament::button wire:confirm="¿Eliminar este borrador de venta? La página se conservará." wire:click="deleteDraft" color="danger" icon="heroicon-o-trash" class="w-full">Eliminar borrador</x-filament::button>
                </div>
            @endif
        </section>
    @else
        <section class="grid gap-8">
            <div class="flex flex-col gap-3 rounded-xl border border-[#DCE0D5] bg-white p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                <div>
                    <h2 class="text-lg font-semibold text-[#101010]">Ventas de terreno</h2>
                    <p class="mt-1 text-sm text-[#596052]">Prepara una página, guarda la oportunidad y retoma la misma venta cuando el cliente decida.</p>
                </div>
                <x-filament::button wire:click="createSale" icon="heroicon-o-plus" class="min-h-11">Crear venta</x-filament::button>
            </div>

            @if ($sales->isEmpty())
                <div class="rounded-xl border border-dashed border-[#DCE0D5] bg-white p-8 text-center">
                    <x-heroicon-o-banknotes class="mx-auto h-8 w-8 text-[#596052]" aria-hidden="true" />
                    <h3 class="mt-3 font-semibold text-[#101010]">Aún no hay ventas</h3>
                    <p class="mt-1 text-sm text-[#596052]">Crea un borrador antes de comenzar la visita.</p>
                </div>
            @else
                <ul class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($sales as $saleRow)
                        <li wire:key="venta-{{ $saleRow->id }}" class="rounded-xl border border-[#DCE0D5] bg-white p-4 sm:p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-[#101010]">{{ $saleRow->cliente_nombre ?: 'Cliente pendiente' }}</p>
                                    <p class="mt-1 truncate text-sm text-[#596052]">{{ $saleRow->local_nombre ?: 'Local pendiente' }}</p>
                                    <p class="mt-1 truncate text-xs text-[#596052]">{{ $saleRow->pagina_nombre ?: 'Página pendiente' }}</p>
                                </div>
                                <span @class(['shrink-0 rounded-full px-3 py-1 text-xs font-medium', 'bg-[#F1F5E9] text-[#375500]' => $saleRow->estado === 'confirmada', 'bg-[#FFF7DB] text-[#854D0E]' => $saleRow->estado === 'borrador'])>{{ $saleRow->estado === 'confirmada' ? 'Confirmada' : 'Borrador' }}</span>
                            </div>
                            <div class="mt-4 flex items-end justify-between gap-3">
                                <div>
                                    <p class="text-xs text-[#596052]">{{ \Illuminate\Support\Carbon::parse($saleRow->created_at)->timezone('America/Santiago')->format('d/m/Y · H:i') }}</p>
                                    @if ($saleRow->total_clp > 0)
                                        <p class="mt-1 font-semibold text-[#101010]">${{ number_format($saleRow->total_clp, 0, ',', '.') }} CLP</p>
                                    @endif
                                </div>
                                <a href="{{ \App\Filament\Pages\Ventas::getUrl(['venta' => $saleRow->id]) }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg {{ $saleRow->estado === 'borrador' ? 'bg-[#B6FF00] text-[#101010] hover:bg-[#A0F700]' : 'border border-[#DCE0D5] text-[#101010] hover:bg-[#F6F7F5]' }} px-4 text-sm font-semibold">
                                    {{ $saleRow->estado === 'borrador' ? 'Continuar venta' : 'Ver venta' }}
                                    <x-heroicon-o-arrow-right class="h-4 w-4" aria-hidden="true" />
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</x-filament-panels::page>
