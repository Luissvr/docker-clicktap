<x-filament-panels::page>
    <div class="ct-stock">
        <div class="ct-inventory-actions">
            <x-filament::button icon="heroicon-o-plus" wire:click="mountAction('entrada')">Añadir compra</x-filament::button>
            <x-filament::button color="gray" wire:click="mountAction('producto')">Añadir producto</x-filament::button>
            <x-filament::button color="gray" icon="heroicon-o-clock" wire:click="openPurchases">Historial de compras</x-filament::button>
            <x-filament::button color="gray" wire:click="mountAction('reservar')">Reservar</x-filament::button>
            <x-filament::button color="gray" wire:click="mountAction('ajuste')">Ajustar inventario</x-filament::button>
            <x-filament::button color="gray" wire:click="mountAction('precio')">Editar precio</x-filament::button>
        </div>
        <div class="ct-stock-freshness" aria-live="polite">
            <span>Consultado {{ \Illuminate\Support\Carbon::parse($readAt)->timezone('America/Santiago')->format('H:i:s') }} · Las operaciones confirman el stock actual.</span>
            <button type="button" wire:click="refreshStock" wire:loading.attr="disabled">Actualizar</button>
            <span wire:loading role="status">Actualizando…</span>
            <span wire:offline role="status">Sin conexión. Reconecta antes de registrar movimientos.</span>
        </div>
        <nav class="ct-stock-nav" aria-label="Vistas de stock">
            @foreach (['inventario' => 'Inventario', 'pedidos' => 'Pedidos y reservas', 'produccion' => 'Producción', 'historial' => 'Historial'] as $tab => $label)
                <button
                    type="button"
                    wire:click="$set('activeTab', '{{ $tab }}')"
                    @class(['ct-stock-tab', 'is-active' => $activeTab === $tab])
                    aria-current="{{ $activeTab === $tab ? 'page' : 'false' }}"
                >{{ $label }}</button>
            @endforeach
        </nav>

        <div class="ct-stock-surface">
            <div class="{{ $activeTab === 'inventario' ? '' : 'p-4 sm:p-6' }}">
                @if ($activeTab === 'inventario')
                    <section aria-label="Inventario de productos">
                        <div class="ct-stock-toolbar">
                            <div>
                                <h2>Productos</h2>
                                <p>{{ $products->count() }} {{ $products->count() === 1 ? 'producto' : 'productos' }} en inventario</p>
                            </div>
                            <label class="ct-stock-search">
                                <x-heroicon-o-magnifying-glass aria-hidden="true" />
                                <span class="sr-only">Buscar productos por nombre o SKU</span>
                                <input type="search" wire:model.live.debounce.500ms="buscar" placeholder="Buscar productos" />
                            </label>
                        </div>

                        <ul class="ct-stock-products" aria-label="Productos" wire:loading.class="opacity-60" wire:target="buscar">
                            @forelse ($products as $prod)
                                @php
                                    $free = max(0, $prod->existencia - $prod->reservado_pedidos - $prod->reservado_produccion);
                                    $isLow = $prod->minimo > 0 && $free <= $prod->minimo;
                                    $photo = match ($prod->sku) {
                                        'TAR-GOOGLE' => 'tarjeta-google.png',
                                        'NFC-CHIP' => 'chip-nfc.jpg',
                                        'ACR-BASE', 'ACR-PERSONALIZADO' => 'acrilico.png',
                                        default => null,
                                    };
                                @endphp
                                <li wire:key="stock-product-{{ $prod->id }}" class="ct-stock-product">
                                    <details class="ct-stock-details">
                                        <summary class="ct-stock-product-row">
                                            <div class="ct-stock-photo">
                                                @if ($photo)
                                                    <img src="{{ asset('images/inventory/'.$photo) }}" alt="{{ $prod->nombre }}" width="112" height="112" loading="lazy" />
                                                @else
                                                    <x-heroicon-o-cube aria-hidden="true" />
                                                @endif
                                            </div>
                                            <div class="ct-stock-product-info">
                                                <h3>{{ $prod->nombre }}</h3>
                                                <p class="ct-stock-description">{{ $prod->tipo === 'terminado' ? 'Producto terminado' : 'Insumo' }} · {{ $prod->sku }}</p>
                                                <p class="ct-stock-price">{{ $prod->precio_clp !== null ? '$'.number_format($prod->precio_clp, 0, ',', '.').' CLP' : 'Precio por definir' }}</p>
                                            </div>
                                            <div class="ct-stock-availability">
                                                <span @class(['ct-stock-quantity', 'is-empty' => $free === 0, 'is-low' => $free > 0 && $isLow])>{{ number_format($free, 0, ',', '.') }} <span>disponibles</span></span>
                                                <span class="ct-stock-status">{{ $free === 0 ? 'Sin disponibilidad' : ($isLow ? 'Stock bajo' : ($prod->vendible ? 'Para venta' : 'Para producción')) }}</span>
                                            </div>
                                            <x-heroicon-o-chevron-right class="ct-stock-chevron" aria-hidden="true" />
                                        </summary>
                                        <div class="ct-stock-product-detail">
                                            <dl>
                                                <div><dt>Existencia física</dt><dd>{{ $prod->existencia }}</dd></div>
                                                <div><dt>Reservado a pedidos</dt><dd>{{ $prod->reservado_pedidos }}</dd></div>
                                                <div><dt>Apartado para producción</dt><dd>{{ $prod->reservado_produccion }}</dd></div>
                                                <div><dt>Disponible</dt><dd>{{ $free }}</dd></div>
                                            </dl>
                                        </div>
                                    </details>
                                </li>
                            @empty
                                <li class="ct-stock-empty">
                                    <x-heroicon-o-magnifying-glass aria-hidden="true" />
                                    <h3>{{ trim($buscar) !== '' ? 'No encontramos productos' : 'Tu inventario aún está vacío' }}</h3>
                                    <p>{{ trim($buscar) !== '' ? 'Prueba con otro nombre o código de producto.' : 'Los productos aparecerán aquí cuando estén registrados.' }}</p>
                                </li>
                            @endforelse
                        </ul>
                    </section>
                @endif

                <!-- ========================================== -->
                <!-- PESTAÑA 2: PEDIDOS Y RESERVAS             -->
                <!-- ========================================== -->
                @if ($activeTab === 'pedidos')
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="w-full sm:max-w-xs">
                                <input
                                    type="search"
                                    wire:model.live.debounce.500ms="buscar"
                                    placeholder="Buscar pedido, cliente, canal..."
                                    class="w-full rounded-lg border-[#8C9681] text-sm focus:border-[#375500] focus:ring-[#375500]"
                                >
                            </div>
                            <div class="w-full sm:w-40">
                                <x-filter-select model="estado" label="Estado del pedido" :options="['todos' => 'Pedido: Todos', 'reservado' => 'Reservado', 'entregado' => 'Entregado', 'cancelado' => 'Cancelado']" />
                            </div>
                            <div class="w-full sm:w-40">
                                <x-filter-select model="estadoPago" label="Estado del pago" :options="['todos' => 'Pago: Todos', 'pagado' => 'Pagado 100%', 'parcial' => 'Abono parcial', 'pendiente' => 'Pendiente']" />
                            </div>
                        </div>

                        @if ($orders->isEmpty())
                            <div class="rounded-xl border border-dashed border-[#DCE0D5] p-8 text-center text-sm text-gray-500">
                                No hay pedidos registrados con los filtros seleccionados.
                            </div>
                        @else
                            <div class="overflow-x-auto rounded-lg border border-[#DCE0D5]">
                                <table class="ct-responsive-table w-full min-w-[820px] text-left text-sm divide-y divide-[#DCE0D5]">
                                    <thead class="bg-[#F6F7F5] text-xs font-semibold uppercase tracking-wider text-[#596052]">
                                        <tr>
                                            <th class="py-3 px-3">Fecha</th>
                                            <th class="py-3 px-3">Producto / Cantidad</th>
                                            <th class="py-3 px-3">Cliente & Canal</th>
                                            <th class="py-3 px-3 text-right">Total CLP</th>
                                            <th class="py-3 px-3 text-center">Estado Pedido</th>
                                            <th class="py-3 px-3 text-center">Estado del Pago</th>
                                            <th class="py-3 px-3 text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#DCE0D5] bg-white">
                                        @foreach ($orders as $order)
                                            <tr class="hover:bg-[#F6F7F5]/50 transition-colors">
                                                <td data-label="Fecha" class="py-3 px-3 whitespace-nowrap text-xs text-gray-600">
                                                    {{ substr($order->created_at, 0, 10) }}
                                                    @if ($order->reservado_hasta)
                                                        <span class="block text-[11px] text-amber-700">Revisión: {{ substr($order->reservado_hasta, 0, 10) }}</span>
                                                    @endif
                                                </td>
                                                <td data-label="Producto / Cantidad" class="py-3 px-3">
                                                    <span class="font-semibold text-[#101010]">{{ $order->producto_nombre }}</span>
                                                    <span class="block text-xs text-gray-500">Cantidad: <strong>{{ $order->cantidad }}</strong> × ${{ number_format($order->precio_unitario_clp, 0, ',', '.') }}</span>
                                                </td>
                                                <td data-label="Cliente &amp; Canal" class="py-3 px-3">
                                                    <span class="font-medium text-[#101010]">{{ $order->cliente ?: 'Venta directa' }}</span>
                                                    <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-700 uppercase font-mono ml-1">
                                                        {{ $order->canal }}
                                                    </span>
                                                    @if ($order->referencia_externa)
                                                        <span class="block text-xs text-gray-500">Ref: {{ $order->referencia_externa }}</span>
                                                    @endif
                                                </td>
                                                <td data-label="Total CLP" class="py-3 px-3 text-right font-semibold text-[#101010]">
                                                    ${{ number_format($order->total_clp, 0, ',', '.') }}
                                                </td>
                                                <td data-label="Estado Pedido" class="py-3 px-3 text-center">
                                                    @if ($order->estado === 'reservado')
                                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Reservado</span>
                                                    @elseif ($order->estado === 'entregado')
                                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Entregado</span>
                                                    @else
                                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">Cancelado</span>
                                                    @endif
                                                </td>
                                                <td data-label="Estado del Pago" class="py-3 px-3 text-center">
                                                    @if ($order->estado_pago === 'pagado')
                                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                                            ✓ Pagado (${{ number_format($order->pagado_clp, 0, ',', '.') }})
                                                        </span>
                                                    @elseif ($order->estado_pago === 'parcial')
                                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                                            Abono ${{ number_format($order->pagado_clp, 0, ',', '.') }} (Saldo ${{ number_format($order->saldo_pendiente_clp, 0, ',', '.') }})
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">
                                                            Pendiente ($0)
                                                        </span>
                                                    @endif
                                                </td>
                                                <td data-label="Acciones" class="py-3 px-3 text-right">
                                                    @if ($order->estado === 'reservado')
                                                        <div class="flex items-center justify-end gap-2">
                                                            @if ($order->saldo_pendiente_clp > 0)
                                                                <button
                                                                    type="button"
                                                                    wire:click="$dispatch('open-modal', { id: 'modal-abono-{{ $order->id }}' })"
                                                                    class="text-xs font-medium text-blue-700 underline hover:text-blue-900"
                                                                >
                                                                    Abonar
                                                                </button>
                                                            @endif
                                                            <button
                                                                type="button"
                                                                wire:click="deliver('{{ $order->id }}')"
                                                                wire:confirm="¿Confirmas la entrega del producto y salida definitiva del stock?"
                                                                class="rounded bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700"
                                                            >
                                                                Entregar
                                                            </button>
                                                            <button
                                                                type="button"
                                                                wire:click="cancel('{{ $order->id }}')"
                                                                wire:confirm="¿Deseas cancelar esta reserva y devolver las unidades a stock disponible?"
                                                                class="text-xs text-red-600 hover:text-red-800 underline"
                                                            >
                                                                Cancelar
                                                            </button>
                                                        </div>
                                                    @else
                                                        <span class="text-xs text-gray-400">Completado</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- ========================================== -->
                <!-- PESTAÑA 3: PRODUCCIÓN                     -->
                <!-- ========================================== -->
                @if ($activeTab === 'produccion')
                    <div class="space-y-6">
                        <!-- Estación de Ensamblaje / Receta -->
                        <div class="rounded-xl border border-[#DCE0D5] bg-[#F6F7F5] p-5">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-bold text-[#101010]">Estación de Producción de Acrílicos con NFC</h3>
                                    <p class="mt-1 text-xs text-gray-600 max-w-2xl">
                                        Fórmula de ensamblaje por unidad: <strong>1 Acrílico Base (ACR-BASE) + 1 Chip NFC apartado (NFC-CHIP) + Costo de impresión</strong> = 1 Acrílico Personalizado terminado listo para entrega.
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        wire:click="mountAction('producir')"
                                        class="rounded-lg bg-[#B6FF00] px-4 py-2 text-sm font-semibold text-[#101010] shadow-sm hover:brightness-95 transition"
                                    >
                                        ⚙️ Registrar fabricación
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="mountAction('apartarChips')"
                                        class="rounded-lg border border-[#8C9681] bg-white px-3 py-2 text-sm font-medium text-[#101010] hover:bg-gray-50 transition"
                                    >
                                        🔒 Apartar chips
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="mountAction('liberarChips')"
                                        class="rounded-lg border border-[#8C9681] bg-white px-3 py-2 text-sm font-medium text-[#101010] hover:bg-gray-50 transition"
                                    >
                                        🔓 Liberar chips
                                    </button>
                                </div>
                            </div>

                            <!-- Estado de los componentes -->
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="rounded-lg bg-white p-3 border border-[#DCE0D5]">
                                    <span class="text-xs text-gray-500 font-medium">Bases acrílicas sin NFC</span>
                                    <p class="mt-1 text-xl font-bold text-[#101010]">{{ $production['baseStock'] }} <span class="text-xs font-normal text-gray-500">uds.</span></p>
                                    <span class="text-[11px] text-gray-500">Insumo ACR-BASE</span>
                                </div>
                                <div class="rounded-lg bg-white p-3 border border-[#DCE0D5]">
                                    <span class="text-xs text-blue-700 font-medium">Chips NFC apartados</span>
                                    <p class="mt-1 text-xl font-bold text-blue-800">{{ $production['chipsApartados'] }} <span class="text-xs font-normal text-gray-500">uds.</span></p>
                                    <span class="text-[11px] text-blue-600">Apartados para acrílicos</span>
                                </div>
                                <div class="rounded-lg bg-white p-3 border border-[#DCE0D5]">
                                    <span class="text-xs text-emerald-700 font-medium">Capacidad inmediata</span>
                                    <p class="mt-1 text-xl font-bold text-emerald-800">{{ $production['capacidadEnsamblaje'] }} <span class="text-xs font-normal text-gray-500">acrílicos</span></p>
                                    <span class="text-[11px] text-emerald-600">min(bases, chips apartados)</span>
                                </div>
                                <div class="rounded-lg bg-white p-3 border border-[#DCE0D5]">
                                    <span class="text-xs text-gray-500 font-medium">Acrílicos terminados listos</span>
                                    <p class="mt-1 text-xl font-bold text-[#101010]">{{ $production['acrilicosTerminados'] }} <span class="text-xs font-normal text-gray-500">uds.</span></p>
                                    <span class="text-[11px] text-gray-500">ACR-PERSONALIZADO en stock</span>
                                </div>
                            </div>
                        </div>

                        <!-- Historial de producción -->
                        <div>
                            <h4 class="text-sm font-semibold text-[#101010] mb-2">Historial de Operaciones de Producción</h4>
                            @if ($production['movements']->isEmpty())
                                <p class="text-xs text-gray-500">No se han registrado operaciones de apartado o ensamblaje aún.</p>
                            @else
                                <div class="overflow-x-auto rounded-lg border border-[#DCE0D5]">
                                    <table class="ct-responsive-table w-full min-w-[650px] text-left text-xs divide-y divide-[#DCE0D5]">
                                        <thead class="bg-[#F6F7F5] font-semibold text-[#596052]">
                                            <tr>
                                                <th class="py-2.5 px-3">Fecha</th>
                                                <th class="py-2.5 px-3">Operación</th>
                                                <th class="py-2.5 px-3">Producto</th>
                                                <th class="py-2.5 px-3 text-right">Variación física</th>
                                                <th class="py-2.5 px-3 text-right">Apartado producción</th>
                                                <th class="py-2.5 px-3 text-right">Costo unitario / total</th>
                                                <th class="py-2.5 px-3">Detalle / Motivo</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-[#DCE0D5] bg-white">
                                            @foreach ($production['movements'] as $pm)
                                                <tr>
                                                    <td data-label="Fecha" class="py-2 px-3 whitespace-nowrap text-gray-600">{{ substr($pm->created_at, 0, 16) }}</td>
                                                    <td data-label="Operación" class="py-2 px-3 font-medium">
                                                        @if ($pm->tipo === 'produccion')
                                                            <span class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-emerald-800 font-semibold">✨ Acrílico Fabricado</span>
                                                        @elseif ($pm->tipo === 'apartado_produccion')
                                                            <span class="inline-flex items-center rounded bg-blue-50 px-2 py-0.5 text-blue-800 font-semibold">🔒 Chips Apartados</span>
                                                        @elseif ($pm->tipo === 'liberacion_produccion')
                                                            <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-gray-700">🔓 Chips Liberados</span>
                                                        @else
                                                            <span class="inline-flex items-center rounded bg-amber-50 px-2 py-0.5 text-amber-800">Consumo de Insumo</span>
                                                        @endif
                                                    </td>
                                                    <td data-label="Producto" class="py-2 px-3 font-semibold text-[#101010]">{{ $pm->producto_nombre }}</td>
                                                    <td data-label="Variación física" class="py-2 px-3 text-right font-mono">{{ $pm->cambio_existencia > 0 ? '+'.$pm->cambio_existencia : $pm->cambio_existencia }}</td>
                                                    <td data-label="Apartado producción" class="py-2 px-3 text-right font-mono text-blue-700">{{ $pm->cambio_reserva_produccion > 0 ? '+'.$pm->cambio_reserva_produccion : $pm->cambio_reserva_produccion }}</td>
                                                    <td data-label="Costo unitario / total" class="py-2 px-3 text-right font-medium">
                                                        {{ $pm->costo_clp !== null ? '$'.number_format($pm->costo_clp, 0, ',', '.') : '—' }}
                                                    </td>
                                                    <td data-label="Detalle / Motivo" class="py-2 px-3 text-gray-600">{{ $pm->motivo ?: '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- ========================================== -->
                <!-- PESTAÑA 4: HISTORIAL                      -->
                <!-- ========================================== -->
                @if ($activeTab === 'historial')
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="w-full sm:max-w-xs">
                                <input
                                    type="search"
                                    wire:model.live.debounce.500ms="buscar"
                                    placeholder="Buscar en historial..."
                                    class="w-full rounded-lg border-[#8C9681] text-sm focus:border-[#375500] focus:ring-[#375500]"
                                >
                            </div>
                            <div class="w-full sm:w-48">
                                <x-filter-select model="historialTipo" label="Tipo de movimiento de stock" :options="['todos' => 'Tipo: Todos', 'entrada' => 'Entradas / Compras', 'reserva_pedido' => 'Reservas pedidos', 'venta_entregada' => 'Ventas entregadas', 'cancelacion_reserva' => 'Cancelaciones', 'apartado_produccion' => 'Apartados producción', 'liberacion_produccion' => 'Liberaciones producción', 'produccion' => 'Acrílicos fabricados', 'consumo_produccion' => 'Consumo producción', 'ajuste_merma' => 'Mermas / Ajuste (-)', 'ajuste_positivo' => 'Recuento / Ajuste (+)']" />
                            </div>
                            <div class="w-full sm:w-40">
                                <input
                                    type="date"
                                    wire:model.live="historialDesde"
                                    class="w-full rounded-lg border-[#8C9681] text-sm"
                                    title="Desde fecha"
                                >
                            </div>
                        </div>

                        @if ($movements->isEmpty())
                            <div class="rounded-xl border border-dashed border-[#DCE0D5] p-8 text-center text-sm text-gray-500">
                                No hay movimientos de stock registrados con los filtros aplicados.
                            </div>
                        @else
                            <div class="overflow-x-auto rounded-lg border border-[#DCE0D5]">
                                <table class="ct-responsive-table w-full min-w-[760px] text-left text-xs divide-y divide-[#DCE0D5]">
                                    <thead class="bg-[#F6F7F5] font-semibold uppercase tracking-wider text-[#596052]">
                                        <tr>
                                            <th class="py-3 px-3">Fecha y Hora</th>
                                            <th class="py-3 px-3">Producto</th>
                                            <th class="py-3 px-3">Tipo de Movimiento</th>
                                            <th class="py-3 px-3 text-right">Existencia Física</th>
                                            <th class="py-3 px-3 text-right">Reserva Pedidos</th>
                                            <th class="py-3 px-3 text-right">Apartado Producción</th>
                                            <th class="py-3 px-3 text-right">Costo CLP</th>
                                            <th class="py-3 px-3">Motivo / Documento</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#DCE0D5] bg-white">
                                        @foreach ($movements as $m)
                                            <tr class="hover:bg-[#F6F7F5]/50 transition-colors">
                                                <td data-label="Fecha y Hora" class="py-2.5 px-3 whitespace-nowrap text-gray-600">
                                                    {{ substr($m->created_at, 0, 16) }}
                                                </td>
                                                <td data-label="Producto" class="py-2.5 px-3">
                                                    <span class="font-semibold text-[#101010]">{{ $m->producto_nombre }}</span>
                                                    <span class="block font-mono text-[11px] text-gray-500">{{ $m->producto_sku }}</span>
                                                </td>
                                                <td data-label="Tipo de Movimiento" class="py-2.5 px-3 font-medium">
                                                    <span class="inline-flex rounded px-2 py-0.5 text-xs font-semibold
                                                        {{ str_contains($m->tipo, 'entrada') || str_contains($m->tipo, 'positivo') ? 'bg-emerald-50 text-emerald-800' : '' }}
                                                        {{ str_contains($m->tipo, 'venta') || str_contains($m->tipo, 'produccion') ? 'bg-blue-50 text-blue-800' : '' }}
                                                        {{ str_contains($m->tipo, 'reserva') ? 'bg-amber-50 text-amber-800' : '' }}
                                                        {{ str_contains($m->tipo, 'merma') || str_contains($m->tipo, 'cancelacion') ? 'bg-red-50 text-red-800' : '' }}
                                                    ">
                                                        {{ str_replace('_', ' ', $m->tipo) }}
                                                    </span>
                                                </td>
                                                <td data-label="Existencia Física" class="py-2.5 px-3 text-right font-mono font-medium {{ $m->cambio_existencia < 0 ? 'text-red-700' : ($m->cambio_existencia > 0 ? 'text-emerald-700' : 'text-gray-400') }}">
                                                    {{ $m->cambio_existencia > 0 ? '+'.$m->cambio_existencia : ($m->cambio_existencia < 0 ? $m->cambio_existencia : '0') }}
                                                </td>
                                                <td data-label="Reserva Pedidos" class="py-2.5 px-3 text-right font-mono {{ $m->cambio_reserva_pedido != 0 ? 'text-amber-800 font-semibold' : 'text-gray-400' }}">
                                                    {{ $m->cambio_reserva_pedido > 0 ? '+'.$m->cambio_reserva_pedido : ($m->cambio_reserva_pedido < 0 ? $m->cambio_reserva_pedido : '—') }}
                                                </td>
                                                <td data-label="Apartado Producción" class="py-2.5 px-3 text-right font-mono {{ $m->cambio_reserva_produccion != 0 ? 'text-blue-800 font-semibold' : 'text-gray-400' }}">
                                                    {{ $m->cambio_reserva_produccion > 0 ? '+'.$m->cambio_reserva_produccion : ($m->cambio_reserva_produccion < 0 ? $m->cambio_reserva_produccion : '—') }}
                                                </td>
                                                <td data-label="Costo CLP" class="py-2.5 px-3 text-right font-medium text-gray-700">
                                                    {{ $m->costo_clp !== null ? '$'.number_format($m->costo_clp, 0, ',', '.') : 'Pendiente' }}
                                                </td>
                                                <td data-label="Motivo / Documento" class="py-2.5 px-3 text-gray-600">
                                                    {{ $m->motivo ?: '—' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        @if (in_array($activeTab, ['pedidos', 'historial'], true))
            <nav class="ct-inventory-pagination" aria-label="Paginación de stock">
                <x-filament::button color="gray" wire:click="$set('stockPage', {{ max(1, $stockPage - 1) }})" :disabled="$stockPage <= 1">Anterior</x-filament::button>
                <span>Página {{ $stockPage }}</span>
                <x-filament::button color="gray" wire:click="$set('stockPage', {{ $stockPage + 1 }})" :disabled="! $hasMore">Siguiente</x-filament::button>
            </nav>
        @endif
        @include('filament.components.purchase-history')
    </div>
</x-filament-panels::page>
