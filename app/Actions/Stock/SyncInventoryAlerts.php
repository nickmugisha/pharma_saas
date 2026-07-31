<?php

namespace App\Actions\Stock;

use App\Models\BranchMedicineSetting;
use App\Models\InventoryAlert;
use App\Models\MedicineBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SyncInventoryAlerts
{
    public function handle(
        ?int $pharmacyId = null,
        ?int $branchId = null,
        ?int $pharmacyMedicineId = null,
    ): int {
        $query = BranchMedicineSetting::query()
            ->with('pharmacyMedicine.medicine')
            ->when(
                $pharmacyId,
                fn (Builder $query): Builder =>
                    $query->where('pharmacy_id', $pharmacyId),
            )
            ->when(
                $branchId,
                fn (Builder $query): Builder =>
                    $query->where('pharmacy_branch_id', $branchId),
            )
            ->when(
                $pharmacyMedicineId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'pharmacy_medicine_id',
                        $pharmacyMedicineId,
                    ),
            );

        $synchronized = 0;

        $query->chunkById(100, function ($settings) use (
            &$synchronized,
        ): void {
            foreach ($settings as $setting) {
                $this->synchronizeSetting($setting);
                $synchronized++;
            }
        });

        return $synchronized;
    }

    private function synchronizeSetting(
        BranchMedicineSetting $setting,
    ): void {
        DB::transaction(function () use ($setting): void {
            $setting->refresh();
            $setting->loadMissing('pharmacyMedicine.medicine');

            if (! $setting->alerts_enabled) {
                $this->resolveAllForSetting($setting);

                return;
            }

            $medicineName =
                $setting->pharmacyMedicine?->medicine?->brand_name
                ?? $setting->pharmacyMedicine?->medicine?->generic_name
                ?? 'Medicine';

            $availableStock = (float) MedicineBatch::query()
                ->where('pharmacy_id', $setting->pharmacy_id)
                ->where(
                    'pharmacy_branch_id',
                    $setting->pharmacy_branch_id,
                )
                ->where(
                    'pharmacy_medicine_id',
                    $setting->pharmacy_medicine_id,
                )
                ->where('status', 'active')
                ->whereDate('expiry_date', '>', today())
                ->sum('quantity_available');

            $minimumStock = (float) $setting->minimum_stock_level;

            $stockAlertKey = sprintf(
                'stock:%d:%d',
                $setting->pharmacy_branch_id,
                $setting->pharmacy_medicine_id,
            );

            if ($availableStock <= 0) {
                $this->openOrUpdateAlert(
                    setting: $setting,
                    alertKey: $stockAlertKey,
                    alertType: 'out_of_stock',
                    severity: 'critical',
                    currentValue: 0,
                    thresholdValue: $minimumStock,
                    message: "{$medicineName} is out of stock.",
                );
            } elseif (
                $minimumStock > 0
                && $availableStock <= $minimumStock
            ) {
                $this->openOrUpdateAlert(
                    setting: $setting,
                    alertKey: $stockAlertKey,
                    alertType: 'low_stock',
                    severity: 'warning',
                    currentValue: $availableStock,
                    thresholdValue: $minimumStock,
                    message: sprintf(
                        '%s stock is low: %s available, threshold %s.',
                        $medicineName,
                        number_format($availableStock, 3),
                        number_format($minimumStock, 3),
                    ),
                );
            } else {
                $this->resolveByKey($stockAlertKey);
            }

            $warningLimit = today()->addDays(
                $setting->expiry_warning_days,
            );

            $activeBatchAlertKeys = [];

            $batches = MedicineBatch::query()
                ->where('pharmacy_id', $setting->pharmacy_id)
                ->where(
                    'pharmacy_branch_id',
                    $setting->pharmacy_branch_id,
                )
                ->where(
                    'pharmacy_medicine_id',
                    $setting->pharmacy_medicine_id,
                )
                ->where('quantity_available', '>', 0)
                ->whereNotIn('status', [
                    'depleted',
                    'recalled',
                ])
                ->get();

            foreach ($batches as $batch) {
                $alertKey = "batch:{$batch->id}";
                $expiryDate = $batch->expiry_date->copy()->startOfDay();

                if ($expiryDate->lte(today())) {
                    if ($batch->status !== 'expired') {
                        $batch->forceFill([
                            'status' => 'expired',
                        ])->save();
                    }

                    $activeBatchAlertKeys[] = $alertKey;

                    $this->openOrUpdateAlert(
                        setting: $setting,
                        alertKey: $alertKey,
                        alertType: 'expired',
                        severity: 'critical',
                        currentValue: 0,
                        thresholdValue:
                            $setting->expiry_warning_days,
                        message: sprintf(
                            '%s batch %s expired on %s.',
                            $medicineName,
                            $batch->batch_number,
                            $expiryDate->format('d M Y'),
                        ),
                        medicineBatchId: $batch->id,
                    );

                    continue;
                }

                if ($expiryDate->lte($warningLimit)) {
                    $daysRemaining = today()->diffInDays(
                        $expiryDate,
                    );

                    $activeBatchAlertKeys[] = $alertKey;

                    $this->openOrUpdateAlert(
                        setting: $setting,
                        alertKey: $alertKey,
                        alertType: 'expiring',
                        severity: 'warning',
                        currentValue: $daysRemaining,
                        thresholdValue:
                            $setting->expiry_warning_days,
                        message: sprintf(
                            '%s batch %s expires in %d days.',
                            $medicineName,
                            $batch->batch_number,
                            $daysRemaining,
                        ),
                        medicineBatchId: $batch->id,
                    );

                    continue;
                }

                $this->resolveByKey($alertKey);
            }

            $staleBatchAlerts = InventoryAlert::query()
                ->where('pharmacy_id', $setting->pharmacy_id)
                ->where(
                    'pharmacy_branch_id',
                    $setting->pharmacy_branch_id,
                )
                ->where(
                    'pharmacy_medicine_id',
                    $setting->pharmacy_medicine_id,
                )
                ->whereIn('alert_type', [
                    'expiring',
                    'expired',
                ])
                ->where('status', '!=', 'resolved');

            if ($activeBatchAlertKeys !== []) {
                $staleBatchAlerts->whereNotIn(
                    'alert_key',
                    $activeBatchAlertKeys,
                );
            }

            foreach ($staleBatchAlerts->get() as $alert) {
                $this->resolveSystemAlert($alert);
            }
        });
    }

    private function openOrUpdateAlert(
        BranchMedicineSetting $setting,
        string $alertKey,
        string $alertType,
        string $severity,
        float|int|null $currentValue,
        float|int|null $thresholdValue,
        string $message,
        ?int $medicineBatchId = null,
    ): void {
        $alert = InventoryAlert::query()
            ->firstOrNew([
                'alert_key' => $alertKey,
            ]);

        $isNewCondition = ! $alert->exists
            || $alert->status === 'resolved'
            || $alert->alert_type !== $alertType;

        $alert->fill([
            'pharmacy_id' => $setting->pharmacy_id,
            'pharmacy_branch_id' =>
                $setting->pharmacy_branch_id,
            'pharmacy_medicine_id' =>
                $setting->pharmacy_medicine_id,
            'medicine_batch_id' => $medicineBatchId,
            'alert_type' => $alertType,
            'severity' => $severity,
            'current_value' => $currentValue,
            'threshold_value' => $thresholdValue,
            'message' => $message,
        ]);

        if ($isNewCondition) {
            $alert->forceFill([
                'status' => 'open',
                'detected_at' => now(),
                'acknowledged_by_user_id' => null,
                'acknowledged_at' => null,
                'resolved_by_user_id' => null,
                'resolved_at' => null,
            ]);
        }

        $alert->save();
    }

    private function resolveByKey(string $alertKey): void
    {
        $alert = InventoryAlert::query()
            ->where('alert_key', $alertKey)
            ->where('status', '!=', 'resolved')
            ->first();

        if ($alert) {
            $this->resolveSystemAlert($alert);
        }
    }

    private function resolveAllForSetting(
        BranchMedicineSetting $setting,
    ): void {
        $alerts = InventoryAlert::query()
            ->where('pharmacy_id', $setting->pharmacy_id)
            ->where(
                'pharmacy_branch_id',
                $setting->pharmacy_branch_id,
            )
            ->where(
                'pharmacy_medicine_id',
                $setting->pharmacy_medicine_id,
            )
            ->where('status', '!=', 'resolved')
            ->get();

        foreach ($alerts as $alert) {
            $this->resolveSystemAlert($alert);
        }
    }

    private function resolveSystemAlert(
        InventoryAlert $alert,
    ): void {
        $alert->forceFill([
            'status' => 'resolved',
            'resolved_by_user_id' => null,
            'resolved_at' => now(),
        ])->save();
    }
}