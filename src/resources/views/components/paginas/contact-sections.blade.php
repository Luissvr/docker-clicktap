@props(['address' => null, 'phone' => null, 'email' => null])

@if ($address || $phone || $email)
    <div class="details" aria-label="Ubicación y contacto">
        @if ($address)
            <section class="detail-section" aria-labelledby="detail-location">
                @svg('heroicon-o-map-pin', 'detail-icon', ['aria-hidden' => 'true'])
                <div class="detail-content">
                    <h2 id="detail-location">Ubicación</h2>
                    <p>{{ $address }}</p>
                </div>
            </section>
        @endif
        @if ($phone || $email)
            <section class="detail-section" aria-labelledby="detail-contact">
                @svg('heroicon-o-phone', 'detail-icon', ['aria-hidden' => 'true'])
                <div class="detail-content">
                    <h2 id="detail-contact">Contacto</h2>
                    @if ($phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a>@endif
                    @if ($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@endif
                </div>
            </section>
        @endif
    </div>
@endif
