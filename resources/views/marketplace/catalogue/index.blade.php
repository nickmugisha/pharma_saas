@extends('layouts.marketplace', ['title' => 'Shop medicines â€” PharmaMarket'])

@section('content')
<section class="catalogue-banner">
    <div class="marketplace-container py-12">
        <span class="eyebrow">Public catalogue</span>
        <h1>Compare medicines across trusted pharmacies</h1>
        <p>Products appear only when at least one approved pharmacy has active, non-expired stock.</p>
    </div>
</section>

<section class="marketplace-container py-10">
    @include('marketplace.partials.category-strip', ['categories' => $categories, 'filters' => $filters])

    <form method="GET" class="filter-panel">
        <div class="filter-search">
            <label for="search">Search</label>
            <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Medicine, generic name or molecule">
        </div>
        <div>
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string)($filters['category'] ?? '') === (string)$category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="mode">Online access</label>
            <select id="mode" name="mode">
                <option value="">All access types</option>
                <option value="otc" @selected(($filters['mode'] ?? '') === 'otc')>No prescription needed</option>
                <option value="prescription_required" @selected(($filters['mode'] ?? '') === 'prescription_required')>Prescription required</option>
                <option value="pharmacist_review" @selected(($filters['mode'] ?? '') === 'pharmacist_review')>Pharmacist review</option>
                <option value="in_store_only" @selected(($filters['mode'] ?? '') === 'in_store_only')>In-store only</option>
            </select>
        </div>
        <div>
            <label for="sort">Sort by</label>
            <select id="sort" name="sort">
                <option value="recommended" @selected(($filters['sort'] ?? '') === 'recommended')>Recommended</option>
                <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Name Aâ€“Z</option>
                <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Newest</option>
            </select>
        </div>
        <button class="marketplace-button marketplace-button-primary" type="submit">Apply filters</button>
    </form>

    <div class="mt-8 flex items-center justify-between gap-4">
        <p class="text-sm text-slate-500"><strong class="text-slate-900">{{ $products->total() }}</strong> products available</p>
        @if(request()->hasAny(['search', 'category', 'mode', 'sort']))
            <a href="{{ route('marketplace.catalogue.index') }}" class="text-sm font-bold text-emerald-700">Clear filters</a>
        @endif
    </div>

    <div class="product-grid mt-7">
        @forelse($products as $medicine)
            @include('marketplace.partials.product-card', ['medicine' => $medicine])
        @empty
            <div class="empty-panel col-span-full">
                <strong>No matching products found.</strong>
                <p>Try a different search or remove one of the filters.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-10">{{ $products->links() }}</div>
</section>
@endsection

