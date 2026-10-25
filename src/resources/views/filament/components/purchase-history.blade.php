<x-filament::modal id="purchase-history" width="5xl">
    <x-slot name="heading">Historial de compras</x-slot>
    <x-slot name="description">Cada compra registra mercadería y su costo. Los pagos se registran por separado en caja.</x-slot>
    @if ($purchaseHistory !== null)
        <label class="block">
            <span class="text-sm font-medium">Buscar producto, proveedor o comprobante</span>
            <input type="search" wire:model.live.debounce.500ms="purchaseSearch" class="ct-native-filter mt-2" placeholder="Nombre, SKU o referencia" />
        </label>
        <div class="ct-purchases-list" wire:loading.class="opacity-60">
            @forelse ($purchaseHistory['purchases'] as $purchase)
                <article class="ct-purchase" wire:key="purchase-{{ $purchase->id }}">
                    <div>
                        <h3>{{ $purchase->producto_nombre }} · {{ $purchase->cantidad }} unidades</h3>
                        <p>{{ substr($purchase->fecha, 0, 10) }} · {{ $purchase->proveedor ?: 'Proveedor sin especificar' }}</p>
                        <p>{{ $purchase->producto_sku }} · Comprobante: {{ $purchase->documento ?: 'Sin referencia' }}</p>
                        <p>Registrado por {{ $purchase->responsable ?: 'Usuario no disponible' }}</p>
                    </div>
                    <dl>
                        <div><dt>Costo total</dt><dd>{{ $purchase->costo_total_clp === null ? 'Por confirmar' : '$'.number_format($purchase->costo_total_clp, 0, ',', '.') }}</dd></div>
                        <div><dt>Pagado</dt><dd>${{ number_format($purchase->pagado_clp, 0, ',', '.') }}</dd></div>
                        <div><dt>Saldo pendiente</dt><dd>{{ $purchase->costo_total_clp === null ? 'Por confirmar' : '$'.number_format(max(0, $purchase->costo_total_clp - $purchase->pagado_clp), 0, ',', '.') }}</dd></div>
                    </dl>
                    @if ($purchase->costo_total_clp !== null && $purchase->pagado_clp < $purchase->costo_total_clp)
                        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'purchase-history' })" wire:click="mountAction('pagarCompra', { compra: '{{ $purchase->id }}' })">Registrar pago</x-filament::button>
                    @endif
                </article>
            @empty
                <p class="py-6 text-sm">No hay compras que coincidan con la búsqueda.</p>
            @endforelse
        </div>
        <nav class="ct-inventory-pagination" aria-label="Paginación de compras">
            <x-filament::button color="gray" wire:click="$set('purchasePage', {{ max(1, $purchasePage - 1) }})" :disabled="$purchasePage <= 1">Anterior</x-filament::button>
            <span>Página {{ $purchasePage }}</span>
            <x-filament::button color="gray" wire:click="$set('purchasePage', {{ $purchasePage + 1 }})" :disabled="! $purchaseHistory['hasMore']">Siguiente</x-filament::button>
        </nav>
    @endif
</x-filament::modal>
