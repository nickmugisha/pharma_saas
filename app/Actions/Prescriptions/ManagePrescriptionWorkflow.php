<?php

namespace App\Actions\Prescriptions;

use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManagePrescriptionWorkflow
{
    public function submit(
        User $actor,
        Prescription $prescription,
    ): Prescription {
        return DB::transaction(function () use (
            $actor,
            $prescription,
        ): Prescription {
            $locked = $this->lockPrescription(
                $prescription,
            );

            $this->authorize(
                $actor,
                $locked,
                'prescriptions.manage',
            );

            $this->requireStatus(
                $locked,
                Prescription::STATUS_DRAFT,
            );

            $this->validateForSubmission($locked);

            $locked->forceFill([
                'status' =>
                    Prescription::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'rejection_reason' => null,
                'rejected_at' => null,
            ])->save();

            app(RecordPrescriptionActivity::class)
                ->handle(
                    actor: $actor,
                    prescription: $locked,
                    activityType: 'submitted',
                    title: 'Prescription submitted',
                    description:
                        'The prescription was submitted for pharmacist review.',
                );

            return $this->freshPrescription($locked);
        });
    }

    public function startReview(
        User $actor,
        Prescription $prescription,
    ): Prescription {
        return DB::transaction(function () use (
            $actor,
            $prescription,
        ): Prescription {
            $locked = $this->lockPrescription(
                $prescription,
            );

            $this->authorize(
                $actor,
                $locked,
                'prescriptions.validate',
            );

            $this->requireStatus(
                $locked,
                Prescription::STATUS_SUBMITTED,
            );

            $this->ensureNotExpired($locked);

            $locked->forceFill([
                'status' =>
                    Prescription::STATUS_UNDER_REVIEW,

                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            app(RecordPrescriptionActivity::class)
                ->handle(
                    actor: $actor,
                    prescription: $locked,
                    activityType: 'under_review',
                    title: 'Prescription review started',
                    description:
                        'A pharmacist started reviewing the prescription.',
                );

            return $this->freshPrescription($locked);
        });
    }

    public function approve(
        User $actor,
        Prescription $prescription,
    ): Prescription {
        return DB::transaction(function () use (
            $actor,
            $prescription,
        ): Prescription {
            $locked = $this->lockPrescription(
                $prescription,
            );

            $this->authorize(
                $actor,
                $locked,
                'prescriptions.validate',
            );

            $this->requireStatus(
                $locked,
                Prescription::STATUS_UNDER_REVIEW,
            );

            $this->ensureNotExpired($locked);
            $this->validateItems($locked);

            $locked->forceFill([
                'status' =>
                    Prescription::STATUS_APPROVED,

                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' =>
                    $locked->reviewed_at ?? now(),

                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ])->save();

            app(RecordPrescriptionActivity::class)
                ->handle(
                    actor: $actor,
                    prescription: $locked,
                    activityType: 'approved',
                    title: 'Prescription approved',
                    description:
                        'The prescription was validated by a pharmacist.',
                );

            return $this->freshPrescription($locked);
        });
    }

    public function reject(
        User $actor,
        Prescription $prescription,
        string $reason,
    ): Prescription {
        return DB::transaction(function () use (
            $actor,
            $prescription,
            $reason,
        ): Prescription {
            $locked = $this->lockPrescription(
                $prescription,
            );

            $this->authorize(
                $actor,
                $locked,
                'prescriptions.validate',
            );

            $this->requireStatus(
                $locked,
                Prescription::STATUS_UNDER_REVIEW,
            );

            $reason = trim($reason);

            if (
                mb_strlen($reason) < 5
                || mb_strlen($reason) > 3000
            ) {
                throw ValidationException::withMessages([
                    'rejection_reason' =>
                        'A rejection reason of at least 5 characters is required.',
                ]);
            }

            $locked->forceFill([
                'status' =>
                    Prescription::STATUS_REJECTED,

                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' =>
                    $locked->reviewed_at ?? now(),

                'rejected_at' => now(),
                'approved_at' => null,
                'rejection_reason' => $reason,
            ])->save();

            app(RecordPrescriptionActivity::class)
                ->handle(
                    actor: $actor,
                    prescription: $locked,
                    activityType: 'rejected',
                    title: 'Prescription rejected',
                    description: $reason,
                    metadata: [
                        'rejection_reason' => $reason,
                    ],
                );

            return $this->freshPrescription($locked);
        });
    }

    private function lockPrescription(
        Prescription $prescription,
    ): Prescription {
        return Prescription::query()
            ->with([
                'customer',
                'branch',
                'items',
                'attachments',
            ])
            ->lockForUpdate()
            ->findOrFail($prescription->id);
    }

    private function authorize(
        User $actor,
        Prescription $prescription,
        string $permission,
    ): void {
        abort_unless(
            $actor->can($permission),
            403,
        );

        abort_unless(
            (int) $actor->pharmacy_id
                === (int) $prescription->pharmacy_id,
            403,
        );
    }

    private function requireStatus(
        Prescription $prescription,
        string $requiredStatus,
    ): void {
        if ($prescription->status !== $requiredStatus) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'This action requires the prescription status to be %s.',
                    $requiredStatus,
                ),
            ]);
        }
    }

    private function validateForSubmission(
        Prescription $prescription,
    ): void {
        abort_unless(
            (int) $prescription->customer->pharmacy_id
                === (int) $prescription->pharmacy_id,
            422,
        );

        abort_unless(
            (int) $prescription->branch->pharmacy_id
                === (int) $prescription->pharmacy_id,
            422,
        );

        if ($prescription->issued_at === null) {
            throw ValidationException::withMessages([
                'issued_at' =>
                    'The prescription issue date is required.',
            ]);
        }

        if ($prescription->issued_at->isFuture()) {
            throw ValidationException::withMessages([
                'issued_at' =>
                    'The prescription issue date cannot be in the future.',
            ]);
        }

        if (
            $prescription->valid_until !== null
            && $prescription->valid_until->lt(
                $prescription->issued_at,
            )
        ) {
            throw ValidationException::withMessages([
                'valid_until' =>
                    'The expiry date cannot be before the issue date.',
            ]);
        }

        $this->ensureNotExpired($prescription);
        $this->validateItems($prescription);

        if (
            $prescription->source === 'uploaded'
            && $prescription->attachments->isEmpty()
        ) {
            throw ValidationException::withMessages([
                'attachments' =>
                    'An uploaded prescription requires an image or PDF attachment.',
            ]);
        }
    }

    private function validateItems(
        Prescription $prescription,
    ): void {
        if ($prescription->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' =>
                    'At least one prescribed medicine is required.',
            ]);
        }

        foreach ($prescription->items as $item) {
            if (
                (float) $item->quantity_prescribed
                <= 0
            ) {
                throw ValidationException::withMessages([
                    'items' =>
                        'Every prescription item requires a positive quantity.',
                ]);
            }
        }
    }

    private function ensureNotExpired(
        Prescription $prescription,
    ): void {
        if (
            $prescription->valid_until !== null
            && $prescription->valid_until->isBefore(
                today(),
            )
        ) {
            throw ValidationException::withMessages([
                'valid_until' =>
                    'The prescription has expired.',
            ]);
        }
    }

    private function freshPrescription(
        Prescription $prescription,
    ): Prescription {
        return $prescription->fresh([
            'customer',
            'branch',
            'items',
            'attachments',
            'activities',
            'createdByUser',
            'reviewedByUser',
        ]);
    }
}
