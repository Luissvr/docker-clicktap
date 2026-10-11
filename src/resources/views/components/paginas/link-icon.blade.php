@props(['type'])

@if ($type === 'whatsapp')
    <svg class="link-icon link-icon--whatsapp" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.248-.446.05-.149.025-.273-.025-.397-.05-.124-.67-1.611-.916-2.206-.242-.579-.487-.5-.67-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982 1-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.887 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.893c0 2.096.547 4.142 1.588 5.946L.057 24l6.304-1.654a11.882 11.882 0 0 0 5.684 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.815 11.815 0 0 0-3.479-8.413" fill="currentColor" />
    </svg>
@elseif ($type === 'instagram')
    <svg class="link-icon link-icon--instagram" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="2" />
        <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2" />
        <circle cx="17.5" cy="6.5" r="1.25" fill="currentColor" />
    </svg>
@elseif ($type === 'facebook')
    <svg class="link-icon link-icon--facebook" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M13.5 21v-8h2.75l.42-3h-3.17V8.1c0-.87.24-1.46 1.5-1.46h1.8V3.96c-.31-.04-1.37-.13-2.61-.13-2.58 0-4.35 1.57-4.35 4.46V10H7v3h2.84v8h3.66Z" />
    </svg>
@else
    @php
        $heroicon = match ($type) {
            'web' => 'heroicon-o-globe-alt',
            'review' => 'heroicon-s-star',
            'location' => 'heroicon-o-map-pin',
            'transferencia' => 'heroicon-o-building-library',
            default => 'heroicon-o-link',
        };
        $iconClass = $type === 'review' ? 'link-icon link-icon--review' : 'link-icon';
    @endphp
    @svg($heroicon, $iconClass, ['aria-hidden' => 'true'])
@endif
