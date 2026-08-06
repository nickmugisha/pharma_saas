<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Services\MarketplaceCatalogue;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly MarketplaceCatalogue $catalogue,
    ) {
    }

    public function home(): View
    {
        $featured = $this->catalogue
            ->productQuery()
            ->limit(8)
            ->get();

        $featured->each(function (Medicine $medicine): void {
            $medicine->setAttribute(
                'marketplace_summary_data',
                $this->catalogue->summarize($medicine),
            );
        });

        $availableMedicineIds = $this->catalogue
            ->productQuery()
            ->reorder()
            ->pluck('medicines.id');

        $categories = MedicineCategory::query()
            ->where('is_active', true)
            ->whereHas(
                'medicines',
                fn ($query) => $query->whereIn(
                    'medicines.id',
                    $availableMedicineIds,
                ),
            )
            ->withCount([
                'medicines as marketplace_products_count' =>
                    fn ($query) => $query->whereIn(
                        'medicines.id',
                        $availableMedicineIds,
                    ),
            ])
            ->orderByDesc('marketplace_products_count')
            ->limit(8)
            ->get();

        return view('marketplace.home', compact('featured', 'categories'));
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'mode' => ['nullable', 'in:otc,prescription_required,pharmacist_review,in_store_only'],
            'sort' => ['nullable', 'in:recommended,name,newest'],
        ]);

        $products = $this->catalogue
            ->productQuery($filters)
            ->paginate(12)
            ->withQueryString();

        $products->getCollection()->each(function (Medicine $medicine): void {
            $medicine->setAttribute(
                'marketplace_summary_data',
                $this->catalogue->summarize($medicine),
            );
        });

        $categories = MedicineCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('marketplace.catalogue.index', compact(
            'products',
            'categories',
            'filters',
        ));
    }

    public function show(Medicine $medicine): View
    {
        abort_unless(
            $medicine->approval_status === 'approved'
            && $medicine->is_active,
            404,
        );

        $medicine->load([
            'category',
            'dosageForm',
            'manufacturer',
            'images',
            'ingredients.molecule',
        ]);

        $offers = $this->catalogue->offersFor($medicine);
        abort_if($offers->isEmpty(), 404);

        return view('marketplace.catalogue.show', compact('medicine', 'offers'));
    }
}
