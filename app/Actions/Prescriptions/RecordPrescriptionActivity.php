<?php

namespace App\Actions\Prescriptions;

use App\Actions\Customers\RecordCustomerActivity;
use App\Models\Prescription;
use App\Models\PrescriptionActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPrescriptionActivity
{
    public function handle(
        ?User $actor,
        Prescription $prescription,
        string $activityType,
        string $title,
        ?string $description = null,
        array $metadata = [],
    ): PrescriptionActivity {
        if ($actor !== null) {
            abort_unless(
                $actor->can('prescriptions.manage')
                    || $actor->can('prescriptions.validate')
                    || $actor->can('prescriptions.dispense'),
                403,
            );

            abort_unless(
                (int) $actor->pharmacy_id
                    === (int) $prescription->pharmacy_id,
                403,
            );
        }

        $activityType = trim($activityType);
        $title = trim($title);

        if (
            $activityType === ''
            || mb_strlen($activityType) > 100
        ) {
            throw ValidationException::withMessages([
                'activity_type' =>
                    'A valid activity type is required.',
            ]);
        }

        if ($title === '' || mb_strlen($title) > 191) {
            throw ValidationException::withMessages([
                'title' =>
                    'A valid activity title is required.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $prescription,
            $activityType,
            $title,
            $description,
            $metadata,
        ): PrescriptionActivity {
            $activity = PrescriptionActivity::create([
                'pharmacy_id' => $prescription->pharmacy_id,
                'pharmacy_branch_id' =>
                    $prescription->pharmacy_branch_id,
                'prescription_id' => $prescription->id,
                'actor_user_id' => $actor?->id,
                'activity_type' => $activityType,
                'title' => $title,
                'description' => filled($description)
                    ? trim($description)
                    : null,
                'metadata' => $metadata === []
                    ? null
                    : $metadata,
                'occurred_at' => now(),
            ]);

            app(RecordCustomerActivity::class)->handle(
                actor: $actor,
                customer: $prescription->customer,
                activityType:
                    "prescription_{$activityType}",
                title: $title,
                description: $description,
                subject: $prescription,
                metadata: [
                    'prescription_number' =>
                        $prescription->prescription_number,
                    ...$metadata,
                ],
                branchId: $prescription->pharmacy_branch_id,
            );

            return $activity->fresh([
                'actorUser',
                'branch',
                'prescription',
            ]);
        });
    }
}