<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\PharmacyBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordCustomerActivity
{
    public function handle(
        ?User $actor,
        Customer $customer,
        string $activityType,
        string $title,
        ?string $description = null,
        ?Model $subject = null,
        array $metadata = [],
        ?int $branchId = null,
    ): CustomerActivity {
        if ($actor !== null) {
            abort_unless(
                $actor->can('customers.manage'),
                403,
            );

            abort_unless(
                (int) $actor->pharmacy_id
                    === (int) $customer->pharmacy_id,
                403,
            );
        }

        $activityType = trim($activityType);
        $title = trim($title);
        $description = filled($description)
            ? trim($description)
            : null;

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

        $resolvedBranchId = $branchId
            ?? $actor?->pharmacy_branch_id
            ?? $customer->registered_branch_id;

        if ($resolvedBranchId !== null) {
            PharmacyBranch::query()
                ->whereKey($resolvedBranchId)
                ->where(
                    'pharmacy_id',
                    $customer->pharmacy_id,
                )
                ->firstOrFail();
        }

        return DB::transaction(function () use (
            $actor,
            $customer,
            $activityType,
            $title,
            $description,
            $subject,
            $metadata,
            $resolvedBranchId,
        ): CustomerActivity {
            $occurredAt = now();

            $activity = CustomerActivity::create([
                'pharmacy_id' => $customer->pharmacy_id,
                'pharmacy_branch_id' => $resolvedBranchId,
                'customer_id' => $customer->id,
                'actor_user_id' => $actor?->id,
                'activity_type' => $activityType,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'title' => $title,
                'description' => $description,
                'metadata' => $metadata === []
                    ? null
                    : $metadata,
                'occurred_at' => $occurredAt,
            ]);

            $customer->forceFill([
                'last_activity_at' => $occurredAt,
            ])->save();

            return $activity->fresh([
                'branch',
                'actorUser',
                'customer',
            ]);
        });
    }
}