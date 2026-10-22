<x-filament-panels::page>
    <div class="rounded-xl border border-[#DCE0D5] bg-white p-8 shadow-sm">
        <div class="max-w-xl">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[#EDF3FF] px-3 py-1 text-xs font-semibold text-[#1E40AF]">
                <span class="h-1.5 w-1.5 rounded-full bg-[#1E40AF]"></span>
                En preparación
            </span>
            <h2 class="mt-4 text-xl font-bold tracking-tight text-[#101010]">
                {{ static::getTitle() }}
            </h2>
            <p class="mt-2 text-sm leading-relaxed text-[#596052]">
                Este módulo está planificado en el sistema ClickTap y su interfaz completa se habilitará próximamente. Puedes navegar por las secciones activas desde el menú lateral.
            </p>
            <div class="mt-6 flex items-center gap-3">
                <a href="{{ route('filament.admin.pages.dashboard') }}" class="inline-flex items-center justify-center rounded-lg bg-[#B6FF00] px-4 py-2.5 text-sm font-semibold text-[#101010] shadow-sm hover:brightness-95 transition">
                    Volver a Resumen
                </a>
            </div>
        </div>
    </div>
</x-filament-panels::page>
