<div class="ct-paginas-toolbar">
    <label class="ct-paginas-search" for="ct-paginas-search">
        <span class="sr-only">Buscar páginas</span>
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><circle cx="8.75" cy="8.75" r="5.75" stroke="currentColor" stroke-width="1.7"/><path d="m13.2 13.2 4.1 4.1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        <input id="ct-paginas-search" type="search" wire:model.live.debounce.300ms="tableSearch" placeholder="Buscar página, local o ruta…" autocomplete="off">
    </label>

    <div class="ct-paginas-toolbar-control">
        <span>Ordenar por</span>
        <x-filter-select model="paginaSort" label="Ordenar páginas por" :options="['updated_at:desc' => 'Más recientes', 'updated_at:asc' => 'Más antiguas', 'nombre_publico:asc' => 'Nombre A–Z', 'nombre_publico:desc' => 'Nombre Z–A']" />
    </div>

    <div class="ct-paginas-toolbar-control ct-paginas-toolbar-limit">
        <span>Mostrar</span>
        <x-filter-select model="tableRecordsPerPage" label="Páginas por vista" :options="[10 => '10 por página', 25 => '25 por página', 50 => '50 por página']" />
    </div>
</div>
