<x-filament-panels::page>
    <div class="space-y-6" @if ($pending || $running || $tracking) wire:poll.visible.10s @endif>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Resumen de páginas">
            <button type="button" wire:click="toggleFilter('published')" aria-pressed="{{ $filter === 'published' ? 'true' : 'false' }}" @class(['rounded-xl border border-[#BBD69B] bg-[#F2F9E9] p-4 text-left', 'ring-2 ring-[#5B8F00] ring-offset-2' => $filter === 'published'])>
                <span class="block text-xs font-semibold uppercase tracking-wide text-[#375500]">Publicadas</span>
                <strong class="mt-1 block text-2xl text-[#101010]">{{ $counts['published'] }}</strong>
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
        <div>
            <label for="publication-search" class="block text-sm font-medium text-[#101010]">Buscar página o local</label>
            <input id="publication-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Nombre, local o ruta" class="mt-1 w-full rounded-lg border-[#DCE0D5] bg-white" />
        </div>
        @unless ($trashAvailable)
            <p class="rounded-xl border border-[#EACB77] bg-[#FFF8DE] p-4 text-sm text-[#854D0E]">La papelera estará disponible después de actualizar el catálogo.</p>
        @endunless

        <livewire:publication-worker-monitor />

        @if ($trashAvailable && ($selectableIds !== [] || ($filter === 'trash' && $draftsInTrash > 0)))
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-[#DCE0D5] bg-white p-3">
                <p class="mr-auto text-sm text-[#596052]">Selecciona tarjetas de borradores nunca publicados. {{ count($selectedPages) }} seleccionadas.</p>
                @if ($filter === 'trash')
                    <button type="button" wire:click="deleteSelectedDrafts" wire:confirm="¿Eliminar definitivamente los borradores seleccionados y sus enlaces?" @disabled(count($selectedPages) === 0) class="min-h-11 rounded-lg border border-[#E9B4B4] px-4 text-sm font-semibold text-[#B70000] disabled:cursor-not-allowed disabled:opacity-50">Eliminar seleccionados</button>
                    <button type="button" wire:click="emptyDraftTrash" wire:confirm="¿Vaciar la papelera de borradores nunca publicados? Se eliminarán definitivamente {{ $draftsInTrash }} borradores. Las páginas que estuvieron publicadas permanecerán protegidas." @disabled($draftsInTrash === 0) class="min-h-11 rounded-lg bg-[#B70000] px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Vaciar papelera de borradores ({{ $draftsInTrash }})</button>
                @else
                    <button type="button" wire:click="moveSelectedDraftsToTrash" wire:confirm="¿Enviar los borradores seleccionados a la papelera durante 30 días?" @disabled(count($selectedPages) === 0) class="min-h-11 rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#101010] disabled:cursor-not-allowed disabled:opacity-50">Enviar seleccionados a papelera</button>
                    <button type="button" wire:click="deleteSelectedDraftsDirectly" wire:confirm="¿Eliminar definitivamente los borradores seleccionados y sus enlaces, sin pasar por la papelera? Esta acción no se puede deshacer." @disabled(count($selectedPages) === 0) class="min-h-11 rounded-lg border border-[#E9B4B4] px-4 text-sm font-semibold text-[#B70000] disabled:cursor-not-allowed disabled:opacity-50">Eliminar directamente</button>
                @endif
            </div>
        @endif

        @if ($pages->count() === 0)
            <div class="rounded-xl border border-dashed border-[#DCE0D5] bg-white p-8 text-center">
                <h3 class="font-semibold text-[#101010]">{{ $search || $filter !== 'all' ? 'No hay páginas con estos filtros' : 'Aún no hay páginas' }}</h3>
                <p class="mt-1 text-sm text-[#596052]">{{ $search || $filter !== 'all' ? 'Prueba otra búsqueda o pulsa de nuevo el estado seleccionado para ver todas.' : 'Crea una página para preparar la primera publicación.' }}</p>
            </div>
        @else
            <ul class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($pages as $page)
                    @php
                        $sale = $sales->get($page->id);
                        $task = $pageStatuses[$page->id] ?? null;
                        $withdrawal = in_array($page->publicacion_estado, ['retirada_pendiente', 'retirando', 'retirada_enviada', 'retirada_fallida'], true);
                        $allowed = ! is_array(config('clicktap.publication_slugs')) || in_array($page->slug, config('clicktap.publication_slugs'), true);
                        $isRejected = in_array($sale?->estado, ['rechazada', 'cancelacion_pendiente'], true);
                        $isPublished = $page->papelera_en === null && $page->activo && $page->publicacion_estado === 'publicada';
                        $isForSale = $page->papelera_en === null && $page->activo && $page->publicacion_estado === 'sin_publicar' && ! $isRejected && $sale?->estado !== 'confirmada';
                        $hasError = in_array($page->publicacion_estado, ['fallida', 'retirada_fallida'], true);
                        $badgeLabel = match (true) {
                            $page->papelera_en !== null => 'En papelera',
                            $isRejected => 'Rechazada',
                            $isPublished => 'Publicada',
                            $isForSale => 'Por vender',
                            $hasError => 'Con error',
                            $withdrawal => 'Retirada pendiente',
                            default => 'Pendiente',
                        };
                        $badgeClass = match (true) {
                            $page->papelera_en !== null => 'bg-white text-[#34382F]',
                            $isRejected || $hasError => 'bg-[#F80000] text-white',
                            $isPublished => 'bg-[#B6FF00] text-[#101010]',
                            default => 'bg-[#FFCB00] text-[#101010]',
                        };
                        $coverColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $page->color_fondo) ? $page->color_fondo : '#E9F1E1';
                    @endphp
                    <li wire:key="publication-page-{{ $page->id }}" class="isolate flex min-w-0 flex-col overflow-hidden rounded-2xl border border-[#DCE0D5] bg-white shadow-sm" x-data="{ withdrawalOpen: false }">
                        <div class="relative flex h-40 items-center justify-center overflow-hidden border-b border-[#DCE0D5]" style="background-color: {{ $coverColor }}">
                            <div class="absolute inset-0 bg-gradient-to-br from-white/50 via-transparent to-[#101010]/10"></div>
                            @if ($trashAvailable && in_array($page->id, $selectableIds, true))
                                <label class="absolute left-3 top-3 z-10 flex min-h-11 items-center gap-2 rounded-lg bg-white/95 px-3 text-xs font-semibold text-[#101010] shadow-sm">
                                    <input type="checkbox" wire:model.live="selectedPages" value="{{ $page->id }}" class="rounded border-[#596052] text-[#375500] focus:ring-[#5B8F00]" />
                                    Seleccionar
                                </label>
                            @endif
                            @if ($coverUrls[$page->id] ?? null)
                                <img src="{{ $coverUrls[$page->id] }}" alt="Logo de {{ $page->nombre_publico ?: $page->slug }}" loading="lazy" class="relative z-10 max-h-28 max-w-[70%] object-contain drop-shadow-md" />
                            @else
                                <span class="relative z-10 flex h-20 w-20 items-center justify-center rounded-2xl bg-[#101010] text-3xl font-bold text-[#B6FF00] shadow-lg" aria-hidden="true">{{ mb_strtoupper(mb_substr($page->nombre_publico ?: $page->slug, 0, 1)) }}</span>
                            @endif
                            <span class="absolute right-3 top-3 z-10 rounded-full px-3 py-1 text-xs font-bold shadow-sm {{ $badgeClass }}">{{ $badgeLabel }}</span>
                        </div>
                        <div class="flex flex-1 flex-col p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="break-words text-base font-semibold text-[#101010]">{{ $page->nombre_publico ?: 'Página sin nombre' }}</h3>
                                <p class="mt-1 break-all text-xs text-[#596052]">/{{ $page->slug }} · {{ $page->local?->nombre_interno ?: 'Sin local' }}</p>
                            </div>
                        </div>
                        <p class="mt-3 text-sm font-medium text-[#101010]">Cloudflare: {{ match ($page->publicacion_estado) { 'publicada' => 'Publicación comprobada', 'retirada' => 'Retirada comprobada', 'enviada' => 'Envío realizado; despliegue sin verificar', 'retirada_pendiente' => 'Retirada pendiente; puede seguir visible', 'retirando' => 'Retirada en proceso; puede seguir visible', 'retirada_enviada' => 'Retirada enviada; acceso sin verificar', 'retirada_fallida' => 'Retirada fallida; puede seguir visible', 'fallida' => 'Publicación fallida', 'publicando' => 'Enviando al repositorio', 'legado' => 'Versión heredada', default => 'Sin publicación verificada' } }}</p>
                        <p class="mt-1 text-xs text-[#596052]">{{ $page->publicada_en ? 'Último envío: '.$page->publicada_en->format('d/m/Y H:i') : 'Aún no enviada' }} · {{ $page->activo ? 'Activa en el panel' : 'Inactiva en el panel' }}</p>
                        @if ($page->papelera_en)
                            <p class="mt-2 rounded-lg bg-[#F6F7F5] p-2 text-xs text-[#596052]">Se conserva hasta el {{ $page->papelera_en->copy()->addDays(30)->format('d/m/Y') }}. {{ $withdrawal ? 'El acceso puede seguir visible hasta verificar la retirada.' : 'Sin acceso público comprobado o nunca publicada.' }}</p>
                        @endif
                        @if ($task)
                        <p class="mt-1 text-xs text-[#596052]">Operación: {{ str_replace('_', ' ', $task['state']) }} · {{ $task['message'] ?? 'Consulta el estado más tarde.' }}</p>
                        @endif
                        @if ($sale)
                            <a href="{{ \App\Filament\Pages\Ventas::getUrl(['venta' => $sale->id]) }}" wire:navigate class="mt-2 inline-flex min-h-11 items-center text-sm font-medium text-[#375500] underline">Venta: {{ match ($sale->estado) { 'borrador' => 'Borrador', 'confirmada' => 'Confirmada', 'rechazada' => 'No concretada', 'cancelacion_pendiente' => 'Cancelación pendiente', default => ucfirst($sale->estado) } }}</a>
                        @endif
                        <div class="mt-auto flex flex-wrap gap-2 pt-4">
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
                            @if ($page->activo && in_array($page->publicacion_estado, ['publicada', 'enviada', 'legado'], true))
                                <a href="{{ rtrim((string) config('services.cloudflare.public_url', 'https://clicktap.app'), '/') }}/{{ rawurlencode($page->slug) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#101010]">Abrir sitio</a>
                            @endif
                            @if ($page->activo && $allowed)
                                <button type="button" wire:click="publishPage('{{ $page->id }}')" wire:loading.attr="disabled" wire:target="publishPage" class="min-h-11 rounded-lg bg-[#B6FF00] px-4 text-sm font-semibold text-[#101010]">{{ $isPublished ? 'Actualizar sitio' : 'Subir a Cloudflare' }}</button>
                            @elseif (! $page->activo && in_array($page->publicacion_estado, ['retirada_fallida', 'retirada_enviada', 'retirada_pendiente'], true) && ! in_array($task['state'] ?? null, ['pendiente', 'procesando', 'comprobando_cloudflare'], true))
                                <button type="button" wire:click="retryWithdrawal('{{ $page->id }}')" wire:loading.attr="disabled" wire:target="retryWithdrawal" class="min-h-11 rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#101010]">Reintentar retirada</button>
                            @elseif (! $page->activo && ! $isRejected && $allowed && ! $withdrawal)
                                <button type="button" wire:click="reactivatePage('{{ $page->id }}')" wire:confirm="¿Reactivar y solicitar la publicación de esta página?" class="min-h-11 rounded-lg bg-[#B6FF00] px-4 text-sm font-semibold text-[#101010]">Reactivar</button>
                            @endif
                            @if ($page->activo)
                                <button type="button" wire:click="unpublishPage('{{ $page->id }}')" wire:confirm="¿Desactivar la página y solicitar su retirada de Cloudflare? La venta conservará su estado." class="min-h-11 rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#101010]">Bajar de Cloudflare</button>
                                @if ($sale && ! $isRejected)
                                    <button type="button" x-on:click="withdrawalOpen = ! withdrawalOpen" x-bind:aria-expanded="withdrawalOpen" class="min-h-11 rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#101010]">Cliente no continúa</button>
                                @endif
                            @endif
                            @if ($trashAvailable)
                                <button type="button" wire:click="moveToTrash('{{ $page->id }}')" wire:confirm="¿Mover esta página a la papelera por 30 días? Se desactivará y se solicitará retirar su acceso público." class="min-h-11 rounded-lg border border-[#DCE0D5] px-4 text-sm font-semibold text-[#596052]">Papelera</button>
                            @endif
                            @endif
                        </div>
                        @if ($page->activo && $sale && ! $isRejected && ! $page->papelera_en)
                            <div x-cloak x-show="withdrawalOpen" class="mt-4 rounded-lg border border-[#DCE0D5] bg-[#F6F7F5] p-4">
                                <p class="text-sm text-[#596052]">Se desactivará la página y se solicitará retirar su acceso. Hasta verificar el despliegue podría seguir visible. Los pagos y tarjetas se resuelven aparte.</p>
                                <label for="withdraw-{{ $page->id }}" class="mt-3 block text-sm font-medium text-[#101010]">Motivo</label>
                                <textarea id="withdraw-{{ $page->id }}" wire:model="withdrawalReasons.{{ $page->id }}" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border-[#DCE0D5]"></textarea>
                                @error('withdrawalReasons.'.$page->id) <p class="mt-1 text-sm text-[#B70000]" role="alert">{{ $message }}</p> @enderror
                                <button type="button" wire:click="withdrawPage('{{ $page->id }}')" wire:confirm="¿Desactivar esta página y solicitar la retirada pública?" wire:loading.attr="disabled" wire:target="withdrawPage" class="mt-3 min-h-11 w-full rounded-lg bg-[#101010] px-4 text-sm font-semibold text-white sm:w-auto">Confirmar retirada</button>
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
