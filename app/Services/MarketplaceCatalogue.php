<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarketplaceCatalogue
{
    public function productQuery(array $filters = []): Builder
    {
        $query = Medicine::query()
            ->with([
                'category',
                'dosageForm',
                'manufacturer',
                'primaryImage',
                'ingredients.molecule',
            ])
            ->where('approval_status', 'approved')
            ->where('is_active', true)
            ->whereHas('pharmacyListings', function (Builder $listing): void {
                $listing
                    ->where('status', 'active')
                    ->where('is_available', true)
                    ->where('is_visible_online', true)
                    ->where(function (Builder $offerScope): void {
                        $offerScope
                            ->whereDoesntHave('marketplaceOffers')
                            ->orWhereHas(
                                'marketplaceOffers',
                                fn (Builder $offer): Builder =>
                                    $offer->where('status', 'active'),
                            );
                    })
                    ->whereHas('pharmacy', fn (Builder $pharmacy): Builder =>
                        $pharmacy->where('status', 'approved'))
                    ->whereHas('medicineBatches', function (Builder $batch): void {
                        $batch
                            ->where('status', 'active')
                            ->where('quantity_available', '>', 0)
                            ->whereDate('expiry_date', '>', today())
                            ->whereHas('branch', fn (Builder $branch): Builder =>
                                $branch->where('status', 'active'));
                    });
            });

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('brand_name', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('marketplace_summary', 'like', "%{$search}%")
                    ->orWhereHas('ingredients.molecule', fn (Builder $molecule): Builder =>
                        $molecule->where('name', 'like', "%{$search}%"));
            });
        }

        if (filled($filters['category'] ?? null)) {
            $query->whereHas('category', fn (Builder $category): Builder =>
                $category->whereKey((int) $filters['category']));
        }

        if (filled($filters['mode'] ?? null)) {
            $query->where('online_sale_mode', $filters['mode']);
        }

        return match ($filters['sort'] ?? 'recommended') {
            'name' => $query->orderBy('brand_name'),
            'newest' => $query->latest(),
            default => $query
                ->orderByDesc('is_marketplace_featured')
                ->orderBy('brand_name'),
        };
    }

    public function offersFor(Medicine $medicine): Collection
    {
        $stockRows = MedicineBatch::query()
            ->select([
                'pharmacy_medicine_id',
                'pharmacy_branch_id',
                DB::raw('SUM(quantity_available) as available_quantity'),
                DB::raw('MIN(expiry_date) as nearest_expiry_date'),
            ])
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '>', today())
            ->whereHas('branch', fn (Builder $branch): Builder =>
                $branch->where('status', 'active'))
            ->whereHas('pharmacyMedicine', function (Builder $listing) use ($medicine): void {
                $listing
                    ->where('medicine_id', $medicine->id)
                    ->where('status', 'active')
                    ->where('is_available', true)
                    ->where('is_visible_online', true)
                    ->whereHas('pharmacy', fn (Builder $pharmacy): Builder =>
                        $pharmacy->where('status', 'approved'));
            })
            ->groupBy('pharmacy_medicine_id', 'pharmacy_branch_id')
            ->get();

        if ($stockRows->isEmpty()) {
            return collect();
        }

        $listings = PharmacyMedicine::query()
            ->with(['pharmacy', 'medicine', 'marketplaceOffers'])
            ->whereIn('id', $stockRows->pluck('pharmacy_medicine_id'))
            ->get()
            ->keyBy('id');

        $branches = PharmacyBranch::query()
            ->whereIn('id', $stockRows->pluck('pharmacy_branch_id'))
            ->get()
            ->keyBy('id');

        return $stockRows
            ->map(function (MedicineBatch $row) use ($listings, $branches): ?array {
                $listing = $listings->get($row->pharmacy_medicine_id);
                $branch = $branches->get($row->pharmacy_branch_id);

                if (! $listing || ! $branch || ! $listing->pharmacy) {
                    return null;
                }

                $offer = $listing->marketplaceOffers
                    ->firstWhere('pharmacy_branch_id', $branch->id);

                if ($offer && $offer->status !== 'active') {
                    return null;
                }

                $price = round((float) (
                    $offer?->online_price
                    ?? $listing->online_price
                    ?? $listing->selling_price
                ), 2);

                if ($price <= 0) {
                    return null;
                }

                $available = round((float) $row->available_quantity, 3);
                $configuredMax = $offer?->max_order_quantity;
                $maxOrder = $configuredMax === null
                    ? $available
                    : min($available, (float) $configuredMax);

                return [
                    'offer_id' => $offer?->id,
                    'offer_uuid' => $offer?->uuid,
                    'pharmacy_id' => $listing->pharmacy_id,
                    'pharmacy_name' => $listing->pharmacy->name,
                    'pharmacy_city' => $listing->pharmacy->city,
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'branch_city' => $branch->city,
                    'pharmacy_medicine_id' => $listing->id,
                    'price' => $price,
                    'currency' => $offer?->currency ?? $listing->currency ?? 'BIF',
                    'available_quantity' => $available,
                    'max_order_quantity' => round($maxOrder, 3),
                    'pickup_enabled' => $offer?->pickup_enabled ?? true,
                    'delivery_enabled' => $offer?->delivery_enabled ?? false,
                    'delivery_fee' => round((float) ($offer?->delivery_fee ?? 0), 2),
                    'preparation_minutes' => (int) ($offer?->preparation_minutes ?? 30),
                    'description' => $offer?->marketplace_description
                        ?? $listing->pharmacy_description,
                    'nearest_expiry_date' => $row->nearest_expiry_date,
                    'online_sale_mode' => $listing->medicine?->online_sale_mode ?? 'otc',
                ];
            })
            ->filter()
            ->sortBy(
                fn (array $offer): string => sprintf(
                    '%020.2f-%s',
                    (float) $offer['price'],
                    mb_strtolower((string) $offer['pharmacy_name']),
                ),
            )
            ->values();
    }

    public function findOffer(
        int $pharmacyMedicineId,
        int $branchId,
    ): ?array {
        $listing = PharmacyMedicine::query()
            ->with('medicine')
            ->whereKey($pharmacyMedicineId)
            ->first();

        if (! $listing?->medicine) {
            return null;
        }

        return $this->offersFor($listing->medicine)
            ->first(fn (array $offer): bool =>
                (int) $offer['pharmacy_medicine_id'] === $pharmacyMedicineId
                && (int) $offer['branch_id'] === $branchId);
    }

    public function summarize(Medicine $medicine): array
    {
        $offers = $this->offersFor($medicine);

        return [
            'offers' => $offers,
            'offer_count' => $offers->count(),
            'pharmacy_count' => $offers->pluck('pharmacy_id')->unique()->count(),
            'minimum_price' => $offers->min('price'),
            'maximum_price' => $offers->max('price'),
            'total_available' => round((float) $offers->sum('available_quantity'), 3),
            'has_delivery' => $offers->contains('delivery_enabled', true),
            'has_pickup' => $offers->contains('pickup_enabled', true),
        ];
    }
}
