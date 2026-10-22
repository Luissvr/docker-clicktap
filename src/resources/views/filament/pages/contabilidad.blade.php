<x-filament-panels::page>
    <div class="space-y-4">
        <!-- Subtítulo general -->
        <p class="text-sm text-gray-600">
            Control financiero, flujo de caja, compras y rentabilidad de gestión en CLP. El registro de compras alimenta inventario y deudas en un solo paso.
        </p>

        <!-- KPI Cards Superiores -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <!-- CAJA DISPONIBLE -->
            <div class="rounded-xl border border-[#DCE0D5] bg-white p-4 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Caja disponible</span>
                <div class="mt-2 text-2xl font-bold {{ $cash < 0 ? 'text-red-700' : 'text-[#101010]' }}">
                    ${{ number_format($cash, 0, ',', '.') }}
                </div>
                <span class="mt-1 block text-[11px] text-gray-500">Saldo real acumulado</span>
            </div>

            <!-- VALOR INVENTARIO -->
            <div class="rounded-xl border border-[#DCE0D5] bg-white p-4 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Invertido en stock</span>
                <div class="mt-2 text-2xl font-bold text-[#101010]">
                    ${{ number_format($inventoryValue, 0, ',', '.') }}
                </div>
                <span class="mt-1 block text-[11px] {{ $unknownLots > 0 ? 'text-amber-700 font-medium' : 'text-gray-500' }}">
                    {{ $unknownLots > 0 ? $unknownLots.' lote(s) con costo pendiente' : 'Al costo conocido' }}
                </span>
            </div>

            <!-- POR COBRAR -->
            <div class="rounded-xl border border-[#DCE0D5] bg-white p-4 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Por cobrar</span>
                <div class="mt-2 text-2xl font-bold text-blue-700">
                    ${{ number_format($receivables, 0, ',', '.') }}
                </div>
                <span class="mt-1 block text-[11px] text-gray-500">Deudas de clientes</span>
            </div>

            <!-- POR PAGAR -->
            <div class="rounded-xl border border-[#DCE0D5] bg-white p-4 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Por pagar</span>
                <div class="mt-2 text-2xl font-bold text-amber-700">
                    ${{ number_format($payables, 0, ',', '.') }}
                </div>
                <span class="mt-1 block text-[11px] text-gray-500">Deuda con proveedores</span>
            </div>

            <!-- VENTAS ENTREGADAS -->
            <div class="rounded-xl border border-[#DCE0D5] bg-white p-4 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Ventas período</span>
                <div class="mt-2 text-2xl font-bold text-emerald-800">
                    ${{ number_format($revenue, 0, ',', '.') }}
                </div>
                <span class="mt-1 block text-[11px] text-gray-500">Entregas efectivas</span>
            </div>

            <!-- RESULTADO DE GESTIÓN -->
            <div class="rounded-xl border border-[#DCE0D5] bg-white p-4 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Resultado gestión</span>
                <div class="mt-2 text-2xl font-bold text-[#101010]">
                    @if ($unknownCosts > 0)
                        <span class="text-sm font-semibold text-amber-800">Costo pendiente</span>
                    @else
                        ${{ number_format($revenue - $knownCost - $expenses, 0, ',', '.') }}
                    @endif
                </div>
                <span class="mt-1 block text-[11px] text-gray-500">Ventas - Costo - Gastos</span>
            </div>
        </div>

        <!-- CONTENEDOR PRINCIPAL CON PESTAÑAS -->
        <div class="rounded-xl border border-[#DCE0D5] bg-white shadow-sm overflow-hidden">
            <!-- Barra de Pestañas -->
            <div class="border-b border-[#DCE0D5] bg-white px-5 pt-2">
                <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                    <!-- Pestaña 1: Resumen -->
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'resumen')"
                        class="whitespace-nowrap py-3 px-2 text-sm font-medium transition-colors border-b-[3px] {{ $activeTab === 'resumen' ? 'border-[#B6FF00] font-semibold text-[#101010]' : 'border-transparent text-[#596052] hover:text-[#101010]' }}"
                    >
                        📊 Resumen ejecutivo
                    </button>

                    <!-- Pestaña 2: Movimientos -->
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'movimientos')"
                        class="whitespace-nowrap py-3 px-2 text-sm font-medium transition-colors border-b-[3px] {{ $activeTab === 'movimientos' ? 'border-[#B6FF00] font-semibold text-[#101010]' : 'border-transparent text-[#596052] hover:text-[#101010]' }}"
                    >
                        💵 Movimientos de caja
                        <span class="ml-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ $entries->count() }}</span>
                    </button>

                    <!-- Pestaña 3: Compras y costos -->
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'compras')"
                        class="whitespace-nowrap py-3 px-2 text-sm font-medium transition-colors border-b-[3px] {{ $activeTab === 'compras' ? 'border-[#B6FF00] font-semibold text-[#101010]' : 'border-transparent text-[#596052] hover:text-[#101010]' }}"
                    >
                        🛒 Compras y costos
                        <span class="ml-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ $purchases->count() }}</span>
                    </button>

                    <!-- Pestaña 4: Proyección -->
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'proyeccion')"
                        class="whitespace-nowrap py-3 px-2 text-sm font-medium transition-colors border-b-[3px] {{ $activeTab === 'proyeccion' ? 'border-[#B6FF00] font-semibold text-[#101010]' : 'border-transparent text-[#596052] hover:text-[#101010]' }}"
                    >
                        📈 Proyección de inventario
                    </button>
                </nav>
            </div>

            <!-- CONTENIDO DE LAS PESTAÑAS -->
            <div class="p-6">
                <!-- ========================================== -->
                <!-- PESTAÑA 1: RESUMEN EJECUTIVO              -->
                <!-- ========================================== -->
                @if ($activeTab === 'resumen')
                    <div class="space-y-6">
                        <!-- Filtro de fechas -->
                        <div class="flex flex-wrap items-center gap-3 bg-[#F6F7F5] p-3 rounded-lg border border-[#DCE0D5]">
                            <span class="text-xs font-semibold text-gray-600">Filtrar período:</span>
                            <div class="flex items-center gap-2">
                                <label class="text-xs text-gray-500">Desde:</label>
                                <input type="date" wire:model.live="desde" class="rounded border-[#8C9681] text-xs py-1 px-2">
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-xs text-gray-500">Hasta:</label>
                                <input type="date" wire:model.live="hasta" class="rounded border-[#8C9681] text-xs py-1 px-2">
                            </div>
                            @if ($desde || $hasta)
                                <button type="button" wire:click="$set('desde', null); $set('hasta', null);" class="text-xs text-blue-700 underline font-medium">Limpiar filtro</button>
                            @endif
                        </div>

                        <!-- Las 5 Preguntas Concretas del Negocio -->
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-600 mb-3">Respuestas a las Preguntas Clave del Negocio</h3>
                            <div class="grid gap-4 md:grid-cols-2">
                                <!-- P1: Dinero disponible -->
                                <div class="rounded-xl border border-[#DCE0D5] p-4 bg-white">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">1</span>
                                        <h4 class="text-sm font-semibold text-[#101010]">¿Cuánto dinero tenemos realmente disponible?</h4>
                                    </div>
                                    <div class="mt-3 text-2xl font-bold {{ $cash < 0 ? 'text-red-700' : 'text-emerald-800' }}">
                                        ${{ number_format($cash, 0, ',', '.') }} CLP
                                    </div>
                                    <p class="mt-1 text-xs text-gray-600">
                                        Saldo neto de caja registrado. Cobros recibidos del período: <strong>${{ number_format($collections, 0, ',', '.') }}</strong>, Gastos operativos: <strong>${{ number_format($expenses, 0, ',', '.') }}</strong>.
                                    </p>
                                </div>

                                <!-- P2: Invertido en inventario -->
                                <div class="rounded-xl border border-[#DCE0D5] p-4 bg-white">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-800">2</span>
                                        <h4 class="text-sm font-semibold text-[#101010]">¿Cuánto está invertido en inventario?</h4>
                                    </div>
                                    <div class="mt-3 text-2xl font-bold text-[#101010]">
                                        ${{ number_format($inventoryValue, 0, ',', '.') }} CLP
                                    </div>
                                    <p class="mt-1 text-xs text-gray-600">
                                        Valoración de lotes físicos en stock al costo de adquisición documentado.
                                        @if ($unknownLots > 0)
                                            <span class="block text-amber-700 font-medium">⚠️ {{ $unknownLots }} lote(s) tienen costo pendiente de conciliar.</span>
                                        @endif
                                    </p>
                                </div>

                                <!-- P3: Deudas y créditos -->
                                <div class="rounded-xl border border-[#DCE0D5] p-4 bg-white">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-800">3</span>
                                        <h4 class="text-sm font-semibold text-[#101010]">¿Cuánto nos deben y cuánto debemos?</h4>
                                    </div>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <div>
                                            <span class="text-xs text-gray-500">Nos deben (Clientes):</span>
                                            <div class="text-lg font-bold text-blue-700">${{ number_format($receivables, 0, ',', '.') }}</div>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500">Debemos (Proveedores):</span>
                                            <div class="text-lg font-bold text-amber-700">${{ number_format($payables, 0, ',', '.') }}</div>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-600">
                                        Posición neta por cobrar: <strong>${{ number_format($receivables - $payables, 0, ',', '.') }} CLP</strong>.
                                    </p>
                                </div>

                                <!-- P5: Qué necesitamos para completar -->
                                <div class="rounded-xl border border-[#DCE0D5] p-4 bg-white">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-purple-100 text-xs font-bold text-purple-800">5</span>
                                        <h4 class="text-sm font-semibold text-[#101010]">¿Cuánto necesitamos para completar y vender?</h4>
                                    </div>
                                    <div class="mt-3 text-lg font-bold text-[#101010]">
                                        {{ $plannedAcrylics }} acrílicos en capacidad de producción
                                    </div>
                                    <p class="mt-1 text-xs text-gray-600">
                                        Costo estimado de impresión para fabricar esos {{ $plannedAcrylics }} acrílicos:
                                        <strong>${{ number_format($plannedAcrylics * ($simCostoImpresion ?? 2000), 0, ',', '.') }} CLP</strong>
                                        (a ${{ number_format($simCostoImpresion ?? 2000, 0, ',', '.') }}/u).
                                    </p>
                                    @if ($stockAlerts->isNotEmpty())
                                        <div class="mt-2 text-xs text-red-700 font-medium">
                                            Alerta de reposición en: {{ $stockAlerts->pluck('nombre')->join(', ') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- P4: RENTABILIDAD Y MARGEN POR PRODUCTO -->
                        <div class="rounded-xl border border-[#DCE0D5] bg-white p-5">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <span class="flex h-6 w-6 inline-flex items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800 mr-2">4</span>
                                    <h4 class="text-sm font-bold text-[#101010] inline">¿Cuánto ganamos por tarjeta, acrílico y chip?</h4>
                                </div>
                                <span class="text-xs text-gray-500">Basado en ventas entregadas del período</span>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-[#DCE0D5]">
                                <table class="w-full min-w-[620px] text-left text-xs divide-y divide-[#DCE0D5]">
                                    <thead class="bg-[#F6F7F5] font-semibold text-[#596052]">
                                        <tr>
                                            <th class="py-2.5 px-3">Artículo</th>
                                            <th class="py-2.5 px-3 text-right">Precio unitario sugerido</th>
                                            <th class="py-2.5 px-3 text-right">Uds. vendidas</th>
                                            <th class="py-2.5 px-3 text-right">Ingresos ventas</th>
                                            <th class="py-2.5 px-3 text-right">Costo unidades vendidas</th>
                                            <th class="py-2.5 px-3 text-right">Margen bruto</th>
                                            <th class="py-2.5 px-3 text-right">Margen unitario</th>
                                            <th class="py-2.5 px-3 text-right">Margen %</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#DCE0D5] bg-white">
                                        @foreach ($productMargins as $pm)
                                            <tr>
                                                <td class="py-2.5 px-3 font-semibold text-[#101010]">
                                                    {{ $pm->nombre }}
                                                    <span class="block font-mono text-[11px] text-gray-500">{{ $pm->sku }}</span>
                                                </td>
                                                <td class="py-2.5 px-3 text-right">
                                                    {{ $pm->precio_sugerido !== null ? '$'.number_format($pm->precio_sugerido, 0, ',', '.') : 'Pendiente' }}
                                                </td>
                                                <td class="py-2.5 px-3 text-right font-medium">{{ $pm->unidades_vendidas }}</td>
                                                <td class="py-2.5 px-3 text-right font-medium">${{ number_format($pm->ingresos, 0, ',', '.') }}</td>
                                                <td class="py-2.5 px-3 text-right text-gray-600">${{ number_format($pm->costo, 0, ',', '.') }}</td>
                                                <td class="py-2.5 px-3 text-right font-bold text-emerald-800">
                                                    @if ($pm->tiene_costos_pendientes)
                                                        <span class="text-amber-700">Costos pendientes</span>
                                                    @elseif ($pm->margen_bruto !== null)
                                                        ${{ number_format($pm->margen_bruto, 0, ',', '.') }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="py-2.5 px-3 text-right font-medium text-emerald-700">
                                                    {{ $pm->margen_unitario !== null ? '$'.number_format($pm->margen_unitario, 0, ',', '.') : '—' }}
                                                </td>
                                                <td class="py-2.5 px-3 text-right font-semibold">
                                                    {{ $pm->margen_porcentaje !== null ? $pm->margen_porcentaje.'%' : '—' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ACLARACIÓN CONTABLE DE MONTOS INFORMADOS -->
                        <div class="rounded-xl border border-[#DCE0D5] bg-[#EEF1E8] p-5">
                            <h4 class="text-sm font-bold text-[#101010] mb-2">Tratamiento Contable de los Fondos Informados (Norma IAS 2)</h4>
                            <div class="grid gap-3 sm:grid-cols-3 text-xs">
                                <div class="bg-white rounded-lg p-3 border border-[#DCE0D5]">
                                    <span class="font-bold text-emerald-800">$25.000 Iniciales</span>
                                    <p class="mt-1 text-gray-600">Aporte de capital inicial del socio. Ingreso patrimonial a caja registrado una sola vez.</p>
                                </div>
                                <div class="bg-white rounded-lg p-3 border border-[#DCE0D5]">
                                    <span class="font-bold text-blue-800">$89.000 Retorno / Reinversión</span>
                                    <p class="mt-1 text-gray-600">Cobro de ventas de la 1ª etapa reinvertido en compra de inventario. NO es aporte nuevo ni utilidad neta.</p>
                                </div>
                                <div class="bg-white rounded-lg p-3 border border-[#DCE0D5]">
                                    <span class="font-bold text-amber-800">$3.000 Publicidad</span>
                                    <p class="mt-1 text-gray-600">Gasto comercial operativo identificado por separado para calcular costo de adquisición de clientes.</p>
                                </div>
                            </div>
                            <p class="mt-3 text-[11px] text-[#596052]">
                                ⚠️ <strong>Regla:</strong> $117.000 no representa el capital aportado ni el saldo de caja, ya que sumar aporte y retorno reinvertido duplicaría conceptos. Comprar inventario reduce caja y aumenta activos; el costo se reconoce al vender según IAS 2.
                            </p>
                        </div>
                    </div>
                @endif

                <!-- ========================================== -->
                <!-- PESTAÑA 2: MOVIMIENTOS DE CAJA            -->
                <!-- ========================================== -->
                @if ($activeTab === 'movimientos')
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="w-48">
                                <x-filter-select model="tipoMovimiento" label="Tipo de movimiento de caja" :options="['todos' => 'Tipo: Todos', 'aporte' => 'Aportes de capital (+)', 'cobro_venta' => 'Cobros de ventas (+)', 'gasto' => 'Gastos operativos (-)', 'pago_compra' => 'Pagos de compras (-)', 'pago_produccion' => 'Pagos de producción (-)', 'retiro' => 'Retiros (-)', 'reembolso' => 'Reembolsos (-)']" />
                            </div>
                            <div class="w-40">
                                <input type="date" wire:model.live="desde" class="w-full rounded-lg border-[#8C9681] text-sm" title="Desde fecha">
                            </div>
                            <div class="w-40">
                                <input type="date" wire:model.live="hasta" class="w-full rounded-lg border-[#8C9681] text-sm" title="Hasta fecha">
                            </div>
                        </div>

                        @if ($entries->isEmpty())
                            <div class="rounded-xl border border-dashed border-[#DCE0D5] p-8 text-center text-sm text-gray-500">
                                No hay movimientos de caja registrados en este período.
                            </div>
                        @else
                            <div class="overflow-x-auto rounded-lg border border-[#DCE0D5]">
                                <table class="w-full min-w-[700px] text-left text-xs divide-y divide-[#DCE0D5]">
                                    <thead class="bg-[#F6F7F5] font-semibold uppercase tracking-wider text-[#596052]">
                                        <tr>
                                            <th class="py-3 px-3">Fecha</th>
                                            <th class="py-3 px-3">Tipo</th>
                                            <th class="py-3 px-3">Categoría</th>
                                            <th class="py-3 px-3">Descripción</th>
                                            <th class="py-3 px-3">Comprobante</th>
                                            <th class="py-3 px-3 text-right">Monto CLP</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#DCE0D5] bg-white">
                                        @foreach ($entries as $e)
                                            <tr class="hover:bg-[#F6F7F5]/50 transition-colors">
                                                <td class="py-2.5 px-3 whitespace-nowrap text-gray-600">{{ $e->fecha }}</td>
                                                <td class="py-2.5 px-3">
                                                    <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium
                                                        {{ $e->monto_clp > 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800' }}">
                                                        {{ str_replace('_', ' ', $e->tipo) }}
                                                    </span>
                                                </td>
                                                <td class="py-2.5 px-3 text-gray-700 font-medium">{{ ucfirst($e->categoria) }}</td>
                                                <td class="py-2.5 px-3 text-[#101010]">{{ $e->descripcion }}</td>
                                                <td class="py-2.5 px-3 text-gray-500">{{ $e->documento ?: '—' }}</td>
                                                <td class="py-2.5 px-3 text-right font-bold {{ $e->monto_clp > 0 ? 'text-emerald-800' : 'text-red-700' }}">
                                                    {{ $e->monto_clp > 0 ? '+' : '' }}${{ number_format($e->monto_clp, 0, ',', '.') }}
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
                <!-- PESTAÑA 3: COMPRAS Y COSTOS               -->
                <!-- ========================================== -->
                @if ($activeTab === 'compras')
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-gray-600">
                                Las compras registradas generan lotes de stock con costo de adquisición y crean deudas con proveedores hasta su pago en caja.
                            </p>
                        </div>

                        @if ($purchases->isEmpty())
                            <div class="rounded-xl border border-dashed border-[#DCE0D5] p-8 text-center text-sm text-gray-500">
                                No hay compras de inventario registradas aún. Regístralas desde «Stock de tarjetas» o con la acción superior.
                            </div>
                        @else
                            <div class="overflow-x-auto rounded-lg border border-[#DCE0D5]">
                                <table class="w-full min-w-[760px] text-left text-xs divide-y divide-[#DCE0D5]">
                                    <thead class="bg-[#F6F7F5] font-semibold uppercase tracking-wider text-[#596052]">
                                        <tr>
                                            <th class="py-3 px-3">Fecha</th>
                                            <th class="py-3 px-3">Proveedor & Ref</th>
                                            <th class="py-3 px-3">Producto / Cantidad</th>
                                            <th class="py-3 px-3 text-right">Costo Total CLP</th>
                                            <th class="py-3 px-3 text-right">Costo Unitario</th>
                                            <th class="py-3 px-3 text-right">Pagado CLP</th>
                                            <th class="py-3 px-3 text-right">Saldo Deudor</th>
                                            <th class="py-3 px-3 text-right">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#DCE0D5] bg-white">
                                        @foreach ($purchases as $p)
                                            @php
                                                $saldo = $p->costo_total_clp !== null ? max(0, $p->costo_total_clp - $p->pagado_clp) : null;
                                                $unitCost = ($p->costo_total_clp !== null && $p->cantidad > 0) ? intdiv($p->costo_total_clp, $p->cantidad) : null;
                                            @endphp
                                            <tr class="hover:bg-[#F6F7F5]/50 transition-colors">
                                                <td class="py-2.5 px-3 text-gray-600 whitespace-nowrap">{{ $p->fecha }}</td>
                                                <td class="py-2.5 px-3">
                                                    <span class="font-semibold text-[#101010]">{{ $p->proveedor ?: 'Sin proveedor informado' }}</span>
                                                    @if ($p->documento)
                                                        <span class="block text-[11px] text-gray-500">Doc: {{ $p->documento }}</span>
                                                    @endif
                                                </td>
                                                <td class="py-2.5 px-3">
                                                    <span class="font-semibold text-[#101010]">{{ $p->producto_nombre }}</span>
                                                    <span class="block text-gray-500 font-mono text-[11px]">{{ $p->cantidad }} unidades</span>
                                                </td>
                                                <td class="py-2.5 px-3 text-right font-medium text-[#101010]">
                                                    {{ $p->costo_total_clp !== null ? '$'.number_format($p->costo_total_clp, 0, ',', '.') : 'Pendiente' }}
                                                </td>
                                                <td class="py-2.5 px-3 text-right text-gray-600">
                                                    {{ $unitCost !== null ? '$'.number_format($unitCost, 0, ',', '.') : '—' }}
                                                </td>
                                                <td class="py-2.5 px-3 text-right font-medium text-emerald-800">
                                                    ${{ number_format($p->pagado_clp, 0, ',', '.') }}
                                                </td>
                                                <td class="py-2.5 px-3 text-right">
                                                    @if ($saldo === null)
                                                        <span class="text-amber-700">Por definir</span>
                                                    @elseif ($saldo > 0)
                                                        <span class="font-bold text-red-700">${{ number_format($saldo, 0, ',', '.') }}</span>
                                                    @else
                                                        <span class="text-xs text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded font-medium">Liquidado</span>
                                                    @endif
                                                </td>
                                                <td class="py-2.5 px-3 text-right">
                                                    @if ($saldo !== null && $saldo > 0)
                                                        <button
                                                            type="button"
                                                            wire:click="pagarSaldo('{{ $p->id }}', {{ $saldo }})"
                                                            wire:confirm="¿Confirmas registrar el pago total de ${{ number_format($saldo, 0, ',', '.') }} CLP para liquidar esta compra?"
                                                            class="rounded bg-blue-600 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-700"
                                                        >
                                                            Pagar ${{ number_format($saldo, 0, ',', '.') }}
                                                        </button>
                                                    @else
                                                        <span class="text-gray-400">—</span>
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
                <!-- PESTAÑA 4: PROYECCIÓN                     -->
                <!-- ========================================== -->
                @if ($activeTab === 'proyeccion')
                    <div class="space-y-6">
                        @php
                            $bySku = $allProducts->keyBy('sku');
                            $cards = $bySku->get('TAR-GOOGLE');
                            $chips = $bySku->get('NFC-CHIP');
                            $base = $bySku->get('ACR-BASE');
                            $finished = $bySku->get('ACR-PERSONALIZADO');

                            $freeCards = $cards ? max(0, $cards->existencia - $cards->reservado_pedidos - $cards->reservado_produccion) : 0;
                            $freeChips = $chips ? max(0, $chips->existencia - $chips->reservado_pedidos - $chips->reservado_produccion) : 0;
                            $freeFinished = $finished ? max(0, $finished->existencia - $finished->reservado_pedidos - $finished->reservado_produccion) : 0;
                            $acrylicsCapacity = ($base && $chips) ? min(max(0, $base->existencia - $base->reservado_pedidos), $chips->reservado_produccion) : 0;

                            $priceCard = $simPrecioTarjeta ?? ($cards?->precio_clp ?? 0);
                            $priceChip = $simPrecioChip ?? ($chips?->precio_clp ?? 0);
                            $priceAcrylic = $simPrecioAcrilico ?? ($finished?->precio_clp ?? 0);
                            $printCostUnit = $simCostoImpresion ?? 2000;

                            $potCards = $freeCards * $priceCard;
                            $potChips = $freeChips * $priceChip;
                            $potAcrylics = ($freeFinished + $acrylicsCapacity) * $priceAcrylic;
                            $totalPotentialRevenue = $potCards + $potChips + $potAcrylics;

                            $totalPrintCost = $acrylicsCapacity * $printCostUnit;
                            $netPotentialBeforeExpenses = $totalPotentialRevenue - $totalPrintCost;
                        @endphp

                        <!-- Introducción y fórmula acordada -->
                        <div class="rounded-xl border border-[#DCE0D5] bg-[#F6F7F5] p-5">
                            <h3 class="text-base font-bold text-[#101010]">Modelo de Proyección Comercial</h3>
                            <p class="mt-1 text-xs text-gray-600 max-w-3xl">
                                Fórmula acordada en el plan:
                                <code class="font-mono bg-white px-2 py-0.5 rounded border border-[#DCE0D5] text-[#101010]">
                                    Ingreso potencial = 50 × precio_tarjeta + 20 × precio_acrílico + 30 × precio_chip
                                </code>
                                <br>
                                Supuestos: las tarjetas Google ya incorporan NFC; los 20 chips NFC están apartados para ensamblar los 20 acrílicos sin NFC; y los 30 chips restantes se venden de forma individual. Ningún chip se cuenta dos veces.
                            </p>
                        </div>

                        <!-- Simulador en Vivo de Precios Sugeridos -->
                        <div class="rounded-xl border border-[#DCE0D5] p-5 bg-white">
                            <h4 class="text-sm font-semibold text-[#101010] mb-3">Ajustar Precios para Simulación en Tiempo Real</h4>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <label class="text-xs text-gray-500 font-medium">Precio Tarjeta Google ($CLP)</label>
                                    <input type="number" wire:model.live.debounce.300ms="simPrecioTarjeta" class="mt-1 w-full rounded border-[#8C9681] text-sm">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 font-medium">Precio Acrílico Terminado ($CLP)</label>
                                    <input type="number" wire:model.live.debounce.300ms="simPrecioAcrilico" class="mt-1 w-full rounded border-[#8C9681] text-sm">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 font-medium">Precio Chip NFC Suelto ($CLP)</label>
                                    <input type="number" wire:model.live.debounce.300ms="simPrecioChip" class="mt-1 w-full rounded border-[#8C9681] text-sm">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 font-medium">Costo Impresión / Acrílico ($CLP)</label>
                                    <input type="number" wire:model.live.debounce.300ms="simCostoImpresion" class="mt-1 w-full rounded border-[#8C9681] text-sm">
                                </div>
                            </div>
                        </div>

                        <!-- Tabla de desglose de la proyección -->
                        <div class="overflow-x-auto rounded-lg border border-[#DCE0D5]">
                            <table class="w-full min-w-[650px] text-left text-xs divide-y divide-[#DCE0D5]">
                                <thead class="bg-[#F6F7F5] font-semibold text-[#596052]">
                                    <tr>
                                        <th class="py-2.5 px-3">Línea de Producto</th>
                                        <th class="py-2.5 px-3 text-right">Uds. Disponibles / Planificadas</th>
                                        <th class="py-2.5 px-3 text-right">Precio Simulado</th>
                                        <th class="py-2.5 px-3 text-right">Ingreso Potencial Bruto</th>
                                        <th class="py-2.5 px-3 text-right">Costo Impresión a Descontar</th>
                                        <th class="py-2.5 px-3 text-right">Contribución Estimada</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#DCE0D5] bg-white">
                                    <tr>
                                        <td class="py-2.5 px-3 font-semibold text-[#101010]">Tarjetas Google (con NFC)</td>
                                        <td class="py-2.5 px-3 text-right font-medium">{{ $freeCards }} uds.</td>
                                        <td class="py-2.5 px-3 text-right">${{ number_format($priceCard, 0, ',', '.') }}</td>
                                        <td class="py-2.5 px-3 text-right font-bold text-emerald-800">${{ number_format($potCards, 0, ',', '.') }}</td>
                                        <td class="py-2.5 px-3 text-right text-gray-400">$0</td>
                                        <td class="py-2.5 px-3 text-right font-semibold text-emerald-800">${{ number_format($potCards, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5 px-3 font-semibold text-[#101010]">Acrílicos Terminados (Base + Chip + Impresión)</td>
                                        <td class="py-2.5 px-3 text-right font-medium">{{ $freeFinished + $acrylicsCapacity }} uds. ({{ $freeFinished }} listos + {{ $acrylicsCapacity }} por fabricar)</td>
                                        <td class="py-2.5 px-3 text-right">${{ number_format($priceAcrylic, 0, ',', '.') }}</td>
                                        <td class="py-2.5 px-3 text-right font-bold text-emerald-800">${{ number_format($potAcrylics, 0, ',', '.') }}</td>
                                        <td class="py-2.5 px-3 text-right text-amber-700 font-medium">-${{ number_format($totalPrintCost, 0, ',', '.') }}</td>
                                        <td class="py-2.5 px-3 text-right font-semibold text-emerald-800">${{ number_format($potAcrylics - $totalPrintCost, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5 px-3 font-semibold text-[#101010]">Chips NFC Sueltos (Venta individual)</td>
                                        <td class="py-2.5 px-3 text-right font-medium">{{ $freeChips }} uds. libres</td>
                                        <td class="py-2.5 px-3 text-right">${{ number_format($priceChip, 0, ',', '.') }}</td>
                                        <td class="py-2.5 px-3 text-right font-bold text-emerald-800">${{ number_format($potChips, 0, ',', '.') }}</td>
                                        <td class="py-2.5 px-3 text-right text-gray-400">$0</td>
                                        <td class="py-2.5 px-3 text-right font-semibold text-emerald-800">${{ number_format($potChips, 0, ',', '.') }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-[#F6F7F5] font-bold text-[#101010]">
                                    <tr>
                                        <td class="py-3 px-3">TOTAL POTENCIAL PROYECTADO</td>
                                        <td class="py-3 px-3 text-right">{{ $freeCards + $freeFinished + $acrylicsCapacity + $freeChips }} uds.</td>
                                        <td class="py-3 px-3 text-right">—</td>
                                        <td class="py-3 px-3 text-right text-emerald-900">${{ number_format($totalPotentialRevenue, 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right text-amber-800">-${{ number_format($totalPrintCost, 0, ',', '.') }}</td>
                                        <td class="py-3 px-3 text-right text-emerald-900 text-sm font-extrabold">${{ number_format($netPotentialBeforeExpenses, 0, ',', '.') }} CLP</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="rounded-lg bg-[#EEF1E8] p-4 text-xs text-[#596052]">
                            ⚠️ <strong>Nota de rigor financiero:</strong> El ingreso potencial refleja el valor de realización del inventario a precios sugeridos. No garantiza demanda ni representa utilidad neta, ya que aún deben descontarse comisiones de canal (ej. Mercado Libre ~13-15%), costos de despacho, empaque y gastos comerciales de publicidad.
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
