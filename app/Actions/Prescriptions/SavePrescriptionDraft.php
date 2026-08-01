<?php

namespace App\Actions\Prescriptions;

use App\Models\Customer;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SavePrescriptionDraft
{
    public function create(
        User $actor,
        array $data,
    ): Prescription {
        return $this->persist(
            actor: $actor,
            prescription: null,
            data: $data,
        );
    }

    public function update(
        User $actor,
        Prescription $prescription,
        array $data,
    ): Prescription {
        return $this->persist(
            actor: $actor,
            prescription: $prescription,
            data: $data,
        );
    }

    private function persist(
        User $actor,
        ?Prescription $prescription,
        array $data,
    ): Prescription {
        abort_unless(
            $actor->can('prescriptions.manage'),
            403,
        );

        if ($prescription !== null) {
            abort_unless(
                (int) $actor->pharmacy_id
                    === (int) $prescription->pharmacy_id,
                403,
            );

            if (
                $prescription->status
                !== Prescription::STATUS_DRAFT
            ) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Only draft prescriptions can be edited.',
                ]);
            }
        }

        return DB::transaction(function () use (
            $actor,
            $prescription,
            $data,
        ): Prescription {
            $customer = $this->resolveCustomer(
                $actor,
                $data,
            );

            $branch = $this->resolveBranch(
                $actor,
                $data,
            );

            $itemRows = $data['items'] ?? [];

            if (
                ! is_array($itemRows)
                || $itemRows === []
            ) {
                throw ValidationException::withMessages([
                    'items' =>
                        'At least one prescribed medicine is required.',
                ]);
            }

            $prescriberName = trim(
                (string) (
                    $data['prescriber_name'] ?? ''
                ),
            );

            if ($prescriberName === '') {
                throw ValidationException::withMessages([
                    'prescriber_name' =>
                        'The prescriber name is required.',
                ]);
            }

            $source = $data['source'] ?? 'uploaded';

            if (! in_array(
                $source,
                ['uploaded', 'manual'],
                true,
            )) {
                throw ValidationException::withMessages([
                    'source' =>
                        'The selected prescription source is invalid.',
                ]);
            }

            $attributes = [
                'pharmacy_id' => $actor->pharmacy_id,
                'pharmacy_branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'source' => $source,
                'prescriber_name' => $prescriberName,
                'prescriber_phone' =>
                    $data['prescriber_phone'] ?? null,
                'prescriber_facility' =>
                    $data['prescriber_facility'] ?? null,
                'prescriber_registration_number' =>
                    $data[
                        'prescriber_registration_number'
                    ] ?? null,
                'issued_at' => $data['issued_at'] ?? null,
                'valid_until' =>
                    $data['valid_until'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            $created = $prescription === null;

            if ($created) {
                $prescription = Prescription::create([
                    ...$attributes,
                    'created_by_user_id' => $actor->id,
                    'status' =>
                        Prescription::STATUS_DRAFT,
                ]);
            } else {
                $prescription->fill($attributes);
                $prescription->save();
            }

            $this->replaceItems(
                actor: $actor,
                prescription: $prescription,
                itemRows: $itemRows,
            );

            $newFileCount = $this->appendAttachments(
                actor: $actor,
                prescription: $prescription,
                paths: $data[
                    'new_attachment_paths'
                ] ?? [],
                originalNames: $data[
                    'attachment_original_names'
                ] ?? [],
            );

            app(RecordPrescriptionActivity::class)
                ->handle(
                    actor: $actor,
                    prescription: $prescription,
                    activityType:
                        $created
                            ? 'created'
                            : 'draft_updated',
                    title:
                        $created
                            ? 'Prescription draft created'
                            : 'Prescription draft updated',
                    description:
                        $created
                            ? 'A new prescription draft was registered.'
                            : 'The prescription draft information was updated.',
                    metadata: [
                        'item_count' =>
                            count($itemRows),
                        'new_attachment_count' =>
                            $newFileCount,
                    ],
                );

            return $prescription->fresh([
                'customer',
                'branch',
                'items.medicine',
                'items.pharmacyMedicine.medicine',
                'attachments',
                'activities',
                'createdByUser',
            ]);
        });
    }

    private function resolveCustomer(
        User $actor,
        array $data,
    ): Customer {
        $customerId = $data['customer_id'] ?? null;

        if (blank($customerId)) {
            throw ValidationException::withMessages([
                'customer_id' =>
                    'Select a customer or patient.',
            ]);
        }

        return Customer::query()
            ->whereKey($customerId)
            ->where(
                'pharmacy_id',
                $actor->pharmacy_id,
            )
            ->where('status', '!=', 'blocked')
            ->firstOrFail();
    }

    private function resolveBranch(
        User $actor,
        array $data,
    ): PharmacyBranch {
        $branchId = $data['pharmacy_branch_id']
            ?? $actor->pharmacy_branch_id;

        if (blank($branchId)) {
            throw ValidationException::withMessages([
                'pharmacy_branch_id' =>
                    'Select a pharmacy branch.',
            ]);
        }

        return PharmacyBranch::query()
            ->whereKey($branchId)
            ->where(
                'pharmacy_id',
                $actor->pharmacy_id,
            )
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function replaceItems(
        User $actor,
        Prescription $prescription,
        array $itemRows,
    ): void {
        $prescription->items()->delete();

        foreach ($itemRows as $index => $row) {
            $listing = null;

            if (filled(
                $row['pharmacy_medicine_id'] ?? null,
            )) {
                $listing = PharmacyMedicine::query()
                    ->with('medicine')
                    ->whereKey(
                        $row['pharmacy_medicine_id'],
                    )
                    ->where(
                        'pharmacy_id',
                        $actor->pharmacy_id,
                    )
                    ->where('status', 'active')
                    ->firstOrFail();
            }

            $prescribedName = trim(
                (string) (
                    $row['prescribed_name']
                    ?? $listing?->medicine?->brand_name
                    ?? ''
                ),
            );

            if ($prescribedName === '') {
                throw ValidationException::withMessages([
                    "items.{$index}.prescribed_name" =>
                        'Enter or select the prescribed medicine.',
                ]);
            }

            $quantity = (float) (
                $row['quantity_prescribed'] ?? 0
            );

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity_prescribed" =>
                        'The prescribed quantity must be greater than zero.',
                ]);
            }

            $prescription->items()->create([
                'medicine_id' =>
                    $listing?->medicine_id,
                'pharmacy_medicine_id' =>
                    $listing?->id,
                'prescribed_name' => $prescribedName,
                'strength' => $row['strength'] ?? null,
                'dosage_form' =>
                    $row['dosage_form'] ?? null,
                'dosage' => $row['dosage'] ?? null,
                'frequency' =>
                    $row['frequency'] ?? null,
                'duration' =>
                    $row['duration'] ?? null,
                'quantity_prescribed' => $quantity,
                'quantity_dispensed' => 0,
                'instructions' =>
                    $row['instructions'] ?? null,
                'substitution_allowed' =>
                    (bool) (
                        $row[
                            'substitution_allowed'
                        ] ?? false
                    ),
                'status' => 'pending',
            ]);
        }
    }

    private function appendAttachments(
        User $actor,
        Prescription $prescription,
        mixed $paths,
        mixed $originalNames,
    ): int {
        $paths = is_array($paths)
            ? array_values($paths)
            : [];

        $originalNames = is_array($originalNames)
            ? $originalNames
            : [];

        if (count($paths) > 5) {
            throw ValidationException::withMessages([
                'new_attachment_paths' =>
                    'A maximum of five files can be uploaded at once.',
            ]);
        }

        $storedCount = 0;
        $disk = Storage::disk('local');

        foreach ($paths as $index => $path) {
            $path = ltrim(
                str_replace('\\', '/', (string) $path),
                '/',
            );

            $requiredPrefix = sprintf(
                'prescriptions/%d/',
                $actor->pharmacy_id,
            );

            if (
                ! str_starts_with(
                    $path,
                    $requiredPrefix,
                )
                || ! $disk->exists($path)
            ) {
                throw ValidationException::withMessages([
                    'new_attachment_paths' =>
                        'One of the uploaded files is invalid.',
                ]);
            }

            $alreadyAttached = $prescription
                ->attachments()
                ->where('disk', 'local')
                ->where('path', $path)
                ->exists();

            if ($alreadyAttached) {
                continue;
            }

            $indexedNames = array_values(
                $originalNames,
            );

            $originalName =
                $originalNames[$path]
                ?? $indexedNames[$index]
                ?? basename($path);

            $originalName = basename(
                str_replace(
                    '\\',
                    '/',
                    (string) $originalName,
                ),
            );

            $mimeType = $disk->mimeType($path);

            $prescription->attachments()->create([
                'uploaded_by_user_id' => $actor->id,
                'attachment_type' => 'prescription',
                'disk' => 'local',
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' =>
                    is_string($mimeType)
                        ? $mimeType
                        : null,
                'size_bytes' => $disk->size($path),
            ]);

            $storedCount++;
        }

        return $storedCount;
    }
}