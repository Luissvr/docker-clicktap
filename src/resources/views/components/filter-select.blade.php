@props(['model', 'label', 'options'])

<label class="ct-mobile-filter">
    <span class="sr-only">{{ $label }}</span>
    <select wire:model.live="{{ $model }}" class="ct-native-filter" aria-label="{{ $label }}">
        @foreach ($options as $key => $text)
            <option value="{{ $key }}">{{ $text }}</option>
        @endforeach
    </select>
</label>

<div
    x-data="{ open: false, value: $wire.entangle(@js($model)).live }"
    x-id="['ct-filter-options']"
    x-on:keydown.escape.stop="open = false; $refs.trigger.focus()"
    x-on:focusout="if (!$el.contains($event.relatedTarget)) open = false"
    x-on:click.outside="open = false"
    class="ct-filter-select ct-desktop-filter"
>
    <button
        x-ref="trigger"
        type="button"
        class="ct-filter-select-trigger"
        aria-haspopup="listbox"
        aria-label="{{ $label }}"
        x-bind:aria-expanded="open.toString()"
        x-bind:aria-controls="$id('ct-filter-options')"
        x-on:click="open = !open"
        x-on:keydown.arrow-down.prevent="open = true; $nextTick(() => $refs.options.querySelector('[role=option]')?.focus())"
    >
        <span x-text="@js($options)[String(value)] ?? @js(reset($options))"></span>
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="ct-filter-select-chevron"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div
        x-ref="options"
        x-cloak
        x-show="open"
        x-bind:id="$id('ct-filter-options')"
        class="ct-filter-select-options"
        role="listbox"
        aria-label="{{ $label }}"
    >
        @foreach ($options as $key => $text)
            <button
                type="button"
                role="option"
                class="ct-filter-select-option"
                x-bind:aria-selected="(String(value) === @js((string) $key)).toString()"
                x-on:click="value = @js((string) $key); open = false; $refs.trigger.focus()"
                x-on:keydown.arrow-down.prevent="$el.nextElementSibling?.focus()"
                x-on:keydown.arrow-up.prevent="($el.previousElementSibling ?? $refs.trigger).focus()"
            >{{ $text }}</button>
        @endforeach
    </div>
</div>
