@if(isset($categories) && $categories->isNotEmpty())
    @php
        $activeCategory = (string) ($filters['category'] ?? request('category', ''));
        $baseQuery = request()->except(['page', 'category']);
    @endphp

    <nav
        class="catalogue-category-strip"
        aria-label="Medicine categories"
    >
        <span class="catalogue-category-label">Categories</span>

        <div class="catalogue-category-scroller">
            <a
                href="{{ route('marketplace.catalogue.index', $baseQuery) }}"
                class="catalogue-category-chip {{ $activeCategory === '' ? 'is-active' : '' }}"
            >
                All
            </a>

            @foreach($categories as $category)
                @php
                    $categoryQuery = array_merge(
                        $baseQuery,
                        ['category' => $category->id],
                    );
                @endphp

                <a
                    href="{{ route('marketplace.catalogue.index', $categoryQuery) }}"
                    class="catalogue-category-chip {{ $activeCategory === (string) $category->id ? 'is-active' : '' }}"
                >
                    {{ $category->name }}
                </a>
            @endforeach
        </div>
    </nav>
@endif
