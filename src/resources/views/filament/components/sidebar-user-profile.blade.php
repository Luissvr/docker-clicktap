@php
    $user = filament()->auth()->user();
    if (! $user) {
        return;
    }

    $name = filament()->getUserName($user);
    $words = preg_split('/\s+/', trim($name));
    $userInitials = count($words) >= 2 
        ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1))
        : mb_strtoupper(mb_substr($name, 0, 2));
    $logoutUrl = filament()->getLogoutUrl();
@endphp

<div class="fi-sidebar-footer border-t border-[#242820] bg-[#101010] p-2.5">
    <x-filament::dropdown placement="top-start" teleport class="w-full">
        <x-slot name="trigger">
            <button
                type="button"
                class="flex w-full items-center gap-x-3 rounded-lg p-1.5 text-left transition hover:bg-[#1C2018] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#B6FF00]"
                x-bind:class="$store.sidebar.isOpen ? 'justify-start' : 'justify-center'"
                title="{{ $name }} ({{ $user->email }})"
            >
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#29341C] text-xs font-bold text-[#C8FF75] ring-1 ring-[#41483A]">
                    {{ $userInitials }}
                </div>

                <div
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="lg:transition lg:delay-100"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="min-w-0 flex-1"
                >
                    <p class="truncate text-xs font-semibold text-white">
                        {{ $name }}
                    </p>
                    <p class="truncate text-[11px] text-[#B9C4AD]">
                        Sesión activa
                    </p>
                </div>

                <x-filament::icon
                    icon="heroicon-m-ellipsis-vertical"
                    x-show="$store.sidebar.isOpen"
                    class="h-5 w-5 shrink-0 text-[#8C9681]"
                />
            </button>
        </x-slot>

        <x-filament::dropdown.header
            :icon="\Filament\Support\Facades\FilamentIcon::resolve('panels::user-menu.profile-item') ?? 'heroicon-m-user-circle'"
        >
            {{ $name }}
            <span class="block text-xs font-normal text-gray-500">
                {{ $user->email }}
            </span>
        </x-filament::dropdown.header>

        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item
                :action="$logoutUrl"
                color="danger"
                icon="heroicon-m-arrow-left-on-rectangle"
                method="post"
                tag="form"
            >
                Cerrar sesión
            </x-filament::dropdown.list.item>
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</div>
