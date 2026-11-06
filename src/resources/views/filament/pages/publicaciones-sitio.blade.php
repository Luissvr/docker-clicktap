<x-filament-panels::page>
    <div class="ct-pages-workspace space-y-6" x-data="{
        albumFormOpen: false,
        albumsOpen: false,
        albumSearch: '',
        lastPicked: null,
        rejectOpen: false,
        bulkRejectLimit: 25,
        get selected() { return this.$wire.selectedPages ?? []; },
        boxes() { return [...this.$root.querySelectorAll('input[data-select-page]')]; },
        boxFor(id) { return this.boxes().find((box) => box.value === id); },
        nameOf(id) { return this.boxFor(id)?.dataset.name ?? id; },
        get canActOnDrafts() { return this.selected.length > 0 && this.selected.every((id) => this.boxFor(id)?.dataset.draft === '1'); },
        get canRejectSelected() { return this.selected.length > 0 && this.selected.length <= this.bulkRejectLimit && this.selected.every((id) => this.boxFor(id)?.dataset.rejectable === '1'); },
        selectVisible() { this.$wire.selectedPages = this.boxes().map((box) => box.value); },
        clearSelection() { this.$wire.selectedPages = []; this.rejectOpen = false; },
        rangeClick(event) {
            const input = event.target;
            const id = input.value;
            if (event.shiftKey && this.lastPicked && this.lastPicked !== id) {
                const boxes = [...input.closest('.ct-pages-workspace').querySelectorAll('input[data-select-page]')];
                const from = boxes.findIndex((box) => box.value === this.lastPicked);
                const to = boxes.findIndex((box) => box.value === id);
                if (from > -1 && to > -1) {
                    const next = new Set(this.$wire.selectedPages ?? []);
                    boxes.slice(Math.min(from, to), Math.max(from, to) + 1).forEach((box) => input.checked ? next.add(box.value) : next.delete(box.value));
                    this.$wire.selectedPages = [...next];
                }
            }
            this.lastPicked = id;
        }
    }" x-on:album-created.window="albumFormOpen = false" x-on:keydown.escape.window="albumsOpen = false">
        <div class="ct-status-cards" aria-label="Resumen de páginas">
            <button type="button" wire:click="toggleFilter('sold')" aria-pressed="{{ $filter === 'sold' ? 'true' : 'false' }}" @class(['rounded-xl border border-[#BBD69B] bg-[#F2F9E9] p-4 text-left', 'ring-2 ring-[#5B8F00] ring-offset-2' => $filter === 'sold'])>
                <span class="block text-xs font-semibold uppercase tracking-wide text-[#375500]">Vendidas</span>
                <strong class="mt-1 block text-2xl text-[#101010]">{{ $counts['sold'] }}</strong>
            </button>
            <button type="button" wire:click="toggleFilter('for_sale')" aria-pressed="{{ $filter === 'for_sale' ? 'true' : 'false' }}" @class(['rounded-xl border border-[#EACB77] bg-[#FFF8DE] p-4 text-left', 'ring-2 ring-[#D59A00] ring-offset-2' => $filter === 'for_sale'])>
                <span class="block text-xs font-semibold uppercase tracking-wide text-[#854D0E]">Por vender</span>
                <strong class="mt-1 block text-2xl text-[#101010]">{{ $counts['for_sale'] }}</strong>
            </button>
            <button type="button" wire:click="toggleFilter('rejected')" aria-pressed="{{ $filter === 'rejected' ? 'true' : 'false' }}" @class(['rounded-xl border border-[#E9B4B4] bg-[#FFF1F1] p-4 text-left', 'ring-2 ring-[#B70000] ring-offset-2' => $filter === 'rejected'])>
                <span class="block text-xs font-semibold uppercase tracking-wide text-[#B70000]">Rechazadas</span>
                <strong class="mt-1 block text-2xl text-[#101010]">{{ $counts['rejected'] }}</strong>
            </button>
            <button type="button" wire:click="toggleFilter('trash')" aria-pressed="{{ $filter === 'trash' ? 'true' : 'false' }}" @disabled(! $trashAvailable) @class(['rounded-xl border border-[#DCE0D5] bg-white p-4 text-left disabled:cursor-not-allowed disabled:opacity-50', 'ring-2 ring-[#596052] ring-offset-2' => $filter === 'trash'])>
                <span class="block text-xs font-semibold uppercase tracking-wide text-[#596052]">Papelera</span>
                <strong class="mt-1 block text-2xl text-[#101010]">{{ $counts['trash'] }}</strong>
            </button>
        </div>

        <div class="ct-create-row">
            <a href="{{ \App\Filament\Resources\PaginaResource::getUrl('create') }}" wire:navigate class="ct-create-card ct-create-card-primary">
                <span class="ct-create-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M10 4.5v11M4.5 10h11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
                <span class="ct-create-text"><strong>Crear página</strong><small>Prepara una nueva página para tu ruta</small></span>
            </a>
            @if ($albumsAvailable)
                <div class="ct-create-card ct-create-card-split">
                    <button type="button" class="ct-create-main" x-on:click="albumFormOpen = ! albumFormOpen" x-bind:aria-expanded="albumFormOpen.toString()" aria-controls="ct-album-create-panel">
                        <span class="ct-create-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M3.5 6.5a2 2 0 0 1 2-2h3l1.5 1.8h4.5a2 2 0 0 1 2 2v5.7a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2V6.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></span>
                        <span class="ct-create-text"><strong>Crear álbum</strong><small>Agrupa páginas por día o sector</small></span>
                    </button>
                    <button type="button" class="ct-create-side" x-on:click="albumsOpen = true; $nextTick(() => $refs.albumSearch?.focus())" aria-haspopup="dialog">Ver álbumes</button>
                </div>
            @else
                <div class="ct-create-card is-disabled" role="status">
                    <span class="ct-create-text"><strong>Crear álbum</strong><small>Pendiente de actualizar el catálogo</small></span>
                </div>
            @endif
        </div>
        @if ($albumsAvailable)
            <section id="ct-album-create-panel" x-cloak x-show="albumFormOpen" style="display:none" class="ct-albums" aria-label="Crear álbum">
                <form wire:submit="createAlbum" class="ct-album-form !border-t-0 !pt-0">
                    <label>Nombre del álbum<input type="text" wire:model="albumName" maxlength="120" placeholder="Ruta Concepción centro" required></label>
                    <label>Fecha opcional<input type="date" wire:model="albumDate"></label>
                    <label>Sector opcional<input type="text" wire:model="albumSector" maxlength="120" placeholder="Centro"></label>
                    <button type="submit" wire:loading.attr="disabled" wire:target="createAlbum">Crear álbum</button>
                </form>
                @error('albumName') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            </section>
        @endif

        <section class="ct-pages-filters" aria-label="Filtros de páginas">
            <div class="ct-pages-list-controls">
                <div class="ct-pages-search-control">
                    <label for="publication-search" class="ct-field-label">Buscar página o local</label>
                    <input id="publication-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Nombre, local o ruta" class="ct-field-input" autocomplete="off" />
                </div>
                <div class="ct-field">
                    <span class="ct-field-label">Mostrar páginas</span>
                    <x-filter-select model="perPage" label="Mostrar páginas" :options="[10 => '10 por vista', 25 => '25 por vista', 50 => '50 por vista', 100 => '100 por vista']" />
                </div>
                <div class="ct-field">
                    <span class="ct-field-label">Ordenar por</span>
                    <x-filter-select model="sort" label="Ordenar por" :options="['recently_updated' => 'Actualización reciente', 'oldest_updated' => 'Actualización antigua', 'recently_created' => 'Creación reciente', 'oldest_created' => 'Creación antigua', 'name_asc' => 'Nombre A–Z', 'name_desc' => 'Nombre Z–A']" />
                </div>
            </div>

            @if ($albumsAvailable && $albumFilter !== '')
                @php $activeAlbum = $albums->firstWhere('id', $albumFilter); @endphp
                <div class="ct-pages-filters-albums">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="ct-album-chip is-active">Álbum: {{ $activeAlbum?->nombre ?? 'seleccionado' }}</span>
                        <button type="button" wire:click="$set('albumFilter', '')" class="ct-pages-clear">Quitar filtro de álbum</button>
                        <button type="button" wire:click="startEditingAlbum" class="ct-album-delete !mt-0 !text-[#375500]">Editar álbum</button>
                        <button type="button" wire:click="deleteAlbum" wire:confirm="¿Eliminar este álbum? Sus páginas se conservarán." class="ct-album-delete !mt-0">Eliminar álbum</button>
                    </div>
                    @if ($editingAlbumId === $albumFilter)
                        <form wire:submit="saveAlbum" class="ct-album-form mt-3">
                            <label>Nombre del álbum<input type="text" wire:model="editingAlbumName" maxlength="120" required></label>
                            <label>Fecha opcional<input type="date" wire:model="editingAlbumDate"></label>
                            <label>Sector opcional<input type="text" wire:model="editingAlbumSector" maxlength="120"></label>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveAlbum">Guardar álbum</button>
                            <button type="button" wire:click="$set('editingAlbumId', '')" class="!bg-white !border !border-[#DCE0D5]">Cancelar</button>
                        </form>
                        @error('editingAlbumName') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                    @endif
                </div>
            @endif

            <div class="ct-pages-filters-foot">
                <span role="status">{{ $pages->total() }} {{ $pages->total() === 1 ? 'resultado' : 'resultados' }}</span>
                @if ($search !== '' || $filter !== 'all' || $albumFilter !== '' || $sort !== 'recently_updated' || $perPage !== 25)
                    <button type="button" wire:click="clearFilters" class="ct-pages-clear">Limpiar filtros</button>
                @endif
            </div>
        </section>
        @if ($albumsAvailable)
            <div x-cloak x-show="albumsOpen" style="display:none" class="ct-albums-modal-backdrop" x-on:click.self="albumsOpen = false" role="presentation">
                <section class="ct-albums-modal" role="dialog" aria-modal="true" aria-labelledby="ct-albums-modal-title">
                    <div class="ct-albums-modal-head">
                        <h2 id="ct-albums-modal-title">Álbumes</h2>
                        <button type="button" x-on:click="albumsOpen = false" aria-label="Cerrar" class="ct-status-close">✕</button>
                    </div>
                    <label for="ct-album-search" class="ct-field-label">Buscar álbum</label>
                    <input id="ct-album-search" x-ref="albumSearch" x-model="albumSearch" type="search" class="ct-field-input" placeholder="Nombre o sector" autocomplete="off">
                    <ul class="ct-albums-modal-list">
                        <li x-show="'todas las páginas'.includes(albumSearch.toLowerCase())">
                            <button type="button" wire:click="$set('albumFilter', '')" x-on:click="albumsOpen = false" @class(['ct-albums-modal-item', 'is-active' => $albumFilter === ''])><strong>Todas las páginas</strong></button>
                        </li>
                        @foreach ($albums as $album)
                            <li x-show="@js(mb_strtolower($album->nombre.' '.($album->sector ?? ''))).includes(albumSearch.toLowerCase())">
                                <button type="button" wire:click="$set('albumFilter', '{{ $album->id }}')" x-on:click="albumsOpen = false" @class(['ct-albums-modal-item', 'is-active' => $albumFilter === $album->id])>
                                    <strong>{{ $album->nombre }}</strong>
                                    <small>{{ collect([$album->sector, $album->fecha_ruta?->format('d/m/Y')])->filter()->implode(' · ') }}</small>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    @if ($albums->isEmpty())<p class="ct-status-muted">Aún no hay álbumes. Crea uno con "Crear álbum".</p>@endif
                </section>
            </div>
        @endif
        @unless ($trashAvailable)
            <p class="rounded-xl border border-[#EACB77] bg-[#FFF8DE] p-4 text-sm text-[#854D0E]">La papelera estará disponible después de actualizar el catálogo.</p>
        @endunless

        @if ($trashAvailable || $selectableIds !== [])
            <div class="ct-selection-bar" role="toolbar" aria-label="Acciones sobre las páginas seleccionadas">
                <div class="ct-sel-main">
                    <div class="ct-sel-info">
                        <span class="ct-sel-count" x-text="selected.length" aria-hidden="true">{{ count($selectedPages) }}</span>
                        <span class="ct-sel-text" role="status" x-text="selected.length === 1 ? 'página seleccionada' : 'páginas seleccionadas'">páginas seleccionadas</span>
                        <button type="button" x-on:click="selectVisible()" class="ct-sel-link">Seleccionar visibles</button>
                        <button type="button" x-on:click="clearSelection()" x-bind:disabled="selected.length === 0" class="ct-sel-link">Limpiar</button>
                    </div>
                    <div class="ct-sel-actions">
                        @if ($albumsAvailable && $filter !== 'trash')
                            <div class="ct-sel-group">
                                <label for="ct-target-album" class="sr-only">Agregar al álbum</label>
                                <select id="ct-target-album" wire:model="targetAlbum"><option value="">Agregar al álbum…</option>@foreach ($albums as $album)<option value="{{ $album->id }}">{{ $album->nombre }}</option>@endforeach</select>
                                <button type="button" wire:click="addSelectedToAlbum" disabled x-bind:disabled="selected.length === 0 || ! $wire.targetAlbum" class="ct-sel-btn ct-sel-btn-primary">Agregar</button>
                            </div>
                        @endif
                        @if ($filter === 'trash')
                            <button type="button" wire:click="deleteSelectedDrafts" wire:confirm="¿Eliminar definitivamente los borradores seleccionados y sus enlaces?" disabled x-bind:disabled="! canActOnDrafts" class="ct-sel-btn ct-sel-btn-danger">Eliminar seleccionados</button>
                            <button type="button" wire:click="emptyDraftTrash" wire:confirm="¿Vaciar la papelera de borradores nunca publicados? Se eliminarán definitivamente {{ $draftsInTrash }} borradores. Las páginas que estuvieron publicadas permanecerán protegidas." @disabled($draftsInTrash === 0) class="ct-sel-btn ct-sel-btn-solid-danger">Vaciar papelera ({{ $draftsInTrash }})</button>
                        @else
                            <button type="button" x-on:click="rejectOpen = ! rejectOpen" x-bind:aria-expanded="rejectOpen" disabled x-bind:disabled="! canRejectSelected" class="ct-sel-btn">Rechazar</button>
                            <button type="button" wire:click="publishSelectedDrafts" wire:confirm="¿Activar y solicitar la publicación en Cloudflare de los borradores seleccionados?" wire:loading.attr="disabled" wire:target="publishSelectedDrafts" disabled x-bind:disabled="! canActOnDrafts" class="ct-sel-btn ct-sel-btn-primary">Publicar seleccionados</button>
                            @if ($trashAvailable)
                                <button type="button" wire:click="moveSelectedDraftsToTrash" wire:confirm="¿Enviar los borradores seleccionados a la papelera durante 30 días?" disabled x-bind:disabled="! canActOnDrafts" class="ct-sel-btn">A papelera</button>
                                <button type="button" wire:click="deleteSelectedDraftsDirectly" wire:confirm="¿Eliminar definitivamente los borradores seleccionados y sus enlaces, sin pasar por la papelera? Esta acción no se puede deshacer." disabled x-bind:disabled="! canActOnDrafts" class="ct-sel-btn ct-sel-btn-danger">Eliminar directo</button>
                            @endif
                        @endif
                    </div>
                </div>
                <p class="ct-sel-hint">Rechazar aplica a páginas "Por vender"; papelera y eliminar, solo a borradores nunca publicados. Truco: marca una página, mantén Mayús y marca otra para seleccionar las de en medio.</p>
                @if ($filter !== 'trash')
                    <p x-cloak x-show="selected.length > bulkRejectLimit" style="display:none" class="ct-sel-warning" role="status">Para rechazar, selecciona hasta <span x-text="bulkRejectLimit"></span> páginas por vez.</p>
                    <div x-cloak x-show="rejectOpen && canRejectSelected" style="display:none" class="ct-sel-reject">
                        <p class="text-sm text-[#596052]">Se desactivarán <strong x-text="selected.length"></strong> páginas y se solicitará retirar su acceso público. Hasta verificar el despliegue podrían seguir visibles. Los pagos y tarjetas se resuelven aparte.</p>
                        <ul class="mt-2 max-h-32 list-disc overflow-auto pl-5 text-sm text-[#101010]">
                            <template x-for="id in selected" x-bind:key="id"><li x-text="nameOf(id)"></li></template>
                        </ul>
                        <label for="bulk-reject-reason" class="mt-3 block text-sm font-medium text-[#101010]">Motivo (se aplica a todas)</label>
                        <textarea id="bulk-reject-reason" wire:model="bulkRejectReason" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border-[#DCE0D5]"></textarea>
                        @error('bulkRejectReason') <p class="mt-1 text-sm text-[#B70000]" role="alert">{{ $message }}</p> @enderror
                        <button type="button" wire:click="rejectSelectedPages" wire:confirm="¿Rechazar las páginas seleccionadas y solicitar su retirada pública?" wire:loading.attr="disabled" wire:target="rejectSelectedPages" class="ct-sel-btn ct-sel-btn-solid mt-3">Confirmar rechazo</button>
                    </div>
                @endif
            </div>
        @endif

        @if ($pages->count() === 0)
            <div class="rounded-xl border border-dashed border-[#DCE0D5] bg-white p-8 text-center">
                <h3 class="font-semibold text-[#101010]">{{ $search || $filter !== 'all' ? 'No hay páginas con estos filtros' : 'Aún no hay páginas' }}</h3>
                <p class="mt-1 text-sm text-[#596052]">{{ $search || $filter !== 'all' ? 'Prueba otra búsqueda o pulsa de nuevo el estado seleccionado para ver todas.' : 'Crea una página para preparar la primera publicación.' }}</p>
            </div>
        @else
            <ul class="ct-pages-grid">

                @foreach ($pages as $page)
                    @php
                        $sale = $sales->get($page->id);
                        $task = $pageStatuses[$page->id] ?? null;
                        $withdrawal = in_array($page->publicacion_estado, ['retirada_pendiente', 'retirando', 'retirada_enviada', 'retirada_fallida'], true);
                        $allowed = ! is_array(config('clicktap.publication_slugs')) || in_array($page->slug, config('clicktap.publication_slugs'), true) || app(\App\Support\PageTrash::class)->isUnpublishedDraft($page);
                        $isRejected = $page->estado_comercial === 'rechazada';
                        $isSold = $page->estado_comercial === 'vendida';
                        $isForSale = $page->estado_comercial === 'por_vender';
                        $hasError = in_array($page->publicacion_estado, ['fallida', 'retirada_fallida'], true);
                        $badgeLabel = match (true) {
                            $page->papelera_en !== null => 'En papelera',
                            $isRejected => 'Rechazada',
                            $isSold => 'Vendida',
                            $isForSale => 'Por vender',
                            default => 'Revisión comercial',
                        };
                        $badgeClass = match (true) {
                            $page->papelera_en !== null => 'bg-white text-[#34382F]',
                            $isRejected || $hasError => 'bg-[#F80000] text-white',
                            $isSold => 'bg-[#EAF7EE] text-[#166534]',
                            default => 'bg-[#FFCB00] text-[#101010]',
                        };
                        $coverColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $page->color_fondo) ? $page->color_fondo : '#E9F1E1';
                    @endphp
                    <li wire:key="publication-page-{{ $page->id }}" class="ct-pages-card isolate flex min-w-0 flex-col overflow-hidden rounded-2xl border border-[#DCE0D5] bg-white shadow-sm" x-data="{ withdrawalOpen: false }">
                        <div class="relative flex h-40 items-center justify-center overflow-hidden border-b border-[#DCE0D5]" style="background-color: {{ $coverColor }}">
                            <div class="absolute inset-0 bg-gradient-to-br from-white/50 via-transparent to-[#101010]/10"></div>
                            @if ($page->papelera_en === null || in_array($page->id, $selectableIds, true))
                                <label class="absolute left-3 top-3 z-20 flex min-h-11 items-center gap-2 rounded-lg bg-white/95 px-3 text-xs font-semibold text-[#101010] shadow-sm">
                                    <input type="checkbox" wire:model="selectedPages" value="{{ $page->id }}" data-select-page data-name="{{ $page->nombre_publico ?: $page->slug }}" data-draft="{{ in_array($page->id, $selectableIds, true) ? '1' : '0' }}" data-rejectable="{{ $isForSale && $page->papelera_en === null ? '1' : '0' }}" x-on:click="rangeClick($event)" class="rounded border-[#596052] text-[#375500] focus:ring-[#5B8F00]" />
                                    Seleccionar
                                </label>
                            @endif
                            @if ($coverUrls[$page->id] ?? null)
                                <img src="{{ $coverUrls[$page->id] }}" alt="Logo de {{ $page->nombre_publico ?: $page->slug }}" loading="lazy" class="pointer-events-none absolute inset-0 z-0 h-full w-full object-contain p-4 drop-shadow-md" />
                            @else
                                <span class="relative z-10 flex h-20 w-20 items-center justify-center rounded-2xl bg-[#101010] text-3xl font-bold text-[#B6FF00] shadow-lg" aria-hidden="true">{{ mb_strtoupper(mb_substr($page->nombre_publico ?: $page->slug, 0, 1)) }}</span>
                            @endif
                            <span class="absolute right-3 top-3 z-20 rounded-full px-3 py-1 text-xs font-bold shadow-sm {{ $badgeClass }}" aria-label="Estado comercial: {{ $badgeLabel }}">{{ $badgeLabel }}</span>
                        </div>
                        <div class="flex flex-1 flex-col p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="break-words text-base font-semibold text-[#101010]">{{ $page->nombre_publico ?: 'Página sin nombre' }}</h3>
                                <p class="mt-1 break-all text-xs text-[#596052]">/{{ $page->slug }} · {{ $page->local?->nombre_interno ?: 'Sin local' }}</p>
                            </div>
                        </div>
                        <p class="mt-3 text-sm font-medium text-[#101010]">Estado técnico: {{ match ($page->publicacion_estado) { 'publicada' => 'En línea y verificado', 'retirada' => 'Retirada comprobada', 'enviada' => 'Enviado; despliegue sin verificar', 'retirada_pendiente' => 'Retirada pendiente; puede seguir visible', 'retirando' => 'Retirada en proceso; puede seguir visible', 'retirada_enviada' => 'Retirada enviada; acceso sin verificar', 'retirada_fallida' => 'Retirada fallida; puede seguir visible', 'fallida' => 'Publicación fallida', 'publicando' => 'Enviando al repositorio', 'legado' => 'Versión heredada', default => 'Sin publicación verificada' } }}</p>
                        <p class="mt-1 text-xs text-[#596052]">{{ $page->publicada_en ? 'Último envío: '.$page->publicada_en->format('d/m/Y H:i') : 'Aún no enviada' }} · {{ $page->activo ? 'Activa en el panel' : 'Inactiva en el panel' }}</p>
                        @if ($page->papelera_en)
                            <p class="mt-2 rounded-lg bg-[#F6F7F5] p-2 text-xs text-[#596052]">Se conserva hasta el {{ $page->papelera_en->copy()->addDays(30)->format('d/m/Y') }}. {{ $withdrawal ? 'El acceso puede seguir visible hasta verificar la retirada.' : 'Sin acceso público comprobado o nunca publicada.' }}</p>
                        @endif
                        @if ($task && in_array($task['state'] ?? null, ['pendiente', 'procesando', 'comprobando_cloudflare', 'fallida', 'retirada_fallida'], true))
                            <p class="mt-1 text-xs text-[#596052]">{{ str_replace('_', ' ', $task['state']) }} · <button type="button" x-on:click="$dispatch('open-connection-panel', { pageId: '{{ $page->id }}' })" class="underline">Ver actividad</button></p>
                        @endif
                        @if ($sale)
                            <a href="{{ \App\Filament\Pages\Ventas::getUrl(['venta' => $sale->id]) }}" wire:navigate class="mt-2 inline-flex min-h-11 items-center text-sm font-medium text-[#375500] underline">Venta: {{ match ($sale->estado) { 'borrador' => 'Borrador', 'confirmada' => 'Confirmada', 'rechazada' => 'No concretada', 'cancelacion_pendiente' => 'Cancelación pendiente', default => ucfirst($sale->estado) } }}</a>
                        @endif
                        <div class="ct-card-actions mt-auto pt-4">
                            @if ($page->papelera_en)
                                <button type="button" wire:click="downloadBackup('{{ $page->id }}')" class="min-h-11 rounded-lg border border-[#DCE0D5] px-3 text-sm font-semibold text-[#101010]">Descargar respaldo</button>
                                @if (app(\App\Support\PageTrash::class)->canLeaveTrash($page))
                                    <button type="button" wire:click="restoreFromTrash('{{ $page->id }}')" class="min-h-11 rounded-lg border border-[#DCE0D5] px-3 text-sm font-semibold text-[#101010]">Restaurar</button>
                                    <button type="button" wire:click="deleteFromTrash('{{ $page->id }}')" wire:confirm="¿Eliminar definitivamente esta página y sus enlaces? Descarga primero el respaldo si lo necesitas." class="min-h-11 rounded-lg border border-[#E9B4B4] px-3 text-sm font-semibold text-[#B70000]">Eliminar</button>
                                @elseif (! in_array($task['state'] ?? null, ['pendiente', 'procesando', 'comprobando_cloudflare'], true))
                                    <button type="button" wire:click="retryWithdrawal('{{ $page->id }}')" class="min-h-11 rounded-lg border border-[#DCE0D5] px-3 text-sm font-semibold text-[#101010]">Reintentar retirada</button>
                                @endif
                            @else
                            <a href="{{ $pageEditUrl($page) }}" wire:navigate class="inline-flex min-h-11 items-center rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#101010]">Editar</a>
                            <a href="{{ route('paginas.preview', $page) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#101010]">Vista previa</a>
                            @if ($albumFilter !== '')<button type="button" wire:click="removePageFromAlbum('{{ $page->id }}')" class="min-h-11 rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#596052]">Quitar del álbum</button>@endif
                            @if ($page->activo && in_array($page->publicacion_estado, ['publicada', 'enviada', 'legado'], true))
                                <a href="{{ rtrim((string) config('services.cloudflare.public_url', 'https://clicktap.app'), '/') }}/{{ rawurlencode($page->slug) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#101010]">Abrir sitio</a>
                            @endif
                            @if ($isForSale)
                                @if ($sale && $sale->estado === 'borrador')
                                    <a href="{{ \App\Filament\Pages\Ventas::getUrl(['venta' => $sale->id]) }}" wire:navigate class="inline-flex min-h-11 items-center rounded-lg bg-[#B6FF00] px-4 text-sm font-semibold text-[#101010]">Marcar vendida</a>
                                @elseif (! $sale)
                                    <button type="button" wire:click="startSaleForPage('{{ $page->id }}')" wire:loading.attr="disabled" wire:target="startSaleForPage" class="min-h-11 rounded-lg bg-[#B6FF00] px-4 text-sm font-semibold text-[#101010]">Marcar vendida</button>
                                @endif
                            @endif
                            @if (! $isRejected && $hasError && $page->activo)
                                <button type="button" wire:click="publishPage('{{ $page->id }}')" wire:loading.attr="disabled" wire:target="publishPage" class="min-h-11 rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#101010]">Reintentar publicación</button>
                            @elseif (! $page->activo && $page->publicacion_estado === 'retirada_fallida' && ! in_array($task['state'] ?? null, ['pendiente', 'procesando', 'comprobando_cloudflare'], true))
                                <button type="button" wire:click="retryWithdrawal('{{ $page->id }}')" wire:loading.attr="disabled" wire:target="retryWithdrawal" class="min-h-11 rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#101010]">Reintentar retirada</button>
                            @endif
                            @if ($isForSale)
                                <button type="button" x-on:click="withdrawalOpen = ! withdrawalOpen" x-bind:aria-expanded="withdrawalOpen" class="min-h-11 rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#101010]">Rechazar</button>
                            @endif
                            @if ($trashAvailable)
                                <button type="button" wire:click="moveToTrash('{{ $page->id }}')" wire:confirm="¿Mover esta página a la papelera por 30 días? Se desactivará y se solicitará retirar su acceso público." class="min-h-11 rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#596052]">Papelera</button>
                            @endif
                            @endif
                        </div>
                        @if ($isForSale && ! $page->papelera_en)
                            <div x-cloak x-show="withdrawalOpen" class="mt-4 rounded-lg border border-[#DCE0D5] bg-[#F6F7F5] p-4">
                                <p class="text-sm text-[#596052]">Se desactivará la página y se solicitará retirar su acceso. Hasta verificar el despliegue podría seguir visible. Los pagos y tarjetas se resuelven aparte.</p>
                                <label for="withdraw-{{ $page->id }}" class="mt-3 block text-sm font-medium text-[#101010]">Motivo</label>
                                <textarea id="withdraw-{{ $page->id }}" wire:model="withdrawalReasons.{{ $page->id }}" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border-[#DCE0D5]"></textarea>
                                @error('withdrawalReasons.'.$page->id) <p class="mt-1 text-sm text-[#B70000]" role="alert">{{ $message }}</p> @enderror
                                <button type="button" wire:click="withdrawPage('{{ $page->id }}')" wire:confirm="¿Rechazar esta página y solicitar la retirada pública?" wire:loading.attr="disabled" wire:target="withdrawPage" class="mt-3 min-h-11 w-full rounded-lg bg-[#101010] px-4 text-sm font-semibold text-white sm:w-auto">Confirmar rechazo</button>
                            </div>
                        @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <div>{{ $pages->links() }}</div>
        @endif
    </div>
</x-filament-panels::page>
