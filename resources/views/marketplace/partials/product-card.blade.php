@php
    $summary = $medicine->marketplace_summary_data ?? [];
    $minPrice = $summary['minimum_price'] ?? null;
@endphp
<article class="product-card group">
    <a href="{{ route('marketplace.catalogue.show', $medicine->slug) }}" class="product-image-wrap">
        @if($medicine->marketplace_image_url)
            <img src="{{ $medicine->marketplace_image_url }}" alt="{{ $medicine->brand_name }}" class="product-image">
        @else
            <div class="product-placeholder" aria-hidden="true">
                <span>+</span>
                <small>{{ $medicine->dosageForm?->name ?? 'Medicine' }}</small>
            </div>
        @endif
        <div class="absolute left-3 top-3">
            @include('marketplace.partials.mode-badge', ['mode' => $medicine->online_sale_mode])
        </div>
    </a>
    <div class="product-card-body">
        <p class="product-category">{{ $medicine->category?->name ?? 'Health & wellness' }}</p>
        <a href="{{ route('marketplace.catalogue.show', $medicine->slug) }}" class="product-title">
            {{ $medicine->brand_name }}
        </a>
        <p class="product-subtitle">
            {{ collect([$medicine->generic_name, $medicine->strength, $medicine->dosageForm?->name])->filter()->implode(' • ') ?: 'Verified catalogue medicine' }}
        </p>
        <div class="mt-5 flex items-end justify-between gap-4">
            <div>
                <span class="price-prefix">From</span>
                <strong class="product-price">
                    {{ $minPrice !== null ? number_format((float) $minPrice, 0).' BIF' : 'View offers' }}
                </strong>
            </div>
            <div class="text-right text-xs text-slate-500">
                <strong class="block text-slate-700">{{ $summary['pharmacy_count'] ?? 0 }} pharmacies</strong>
                {{ number_format((float) ($summary['total_available'] ?? 0), 0) }} units available
            </div>
        </div>
        <a href="{{ route('marketplace.catalogue.show', $medicine->slug) }}" class="marketplace-button marketplace-button-soft mt-5 w-full">
            Compare offers
            <span aria-hidden="true">→</span>
        </a>
    </div>
</article>
