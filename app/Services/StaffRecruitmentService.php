<?php

namespace App\Services;

use App\Models\PharmacyBranch;
use App\Models\StaffManagementEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffRecruitmentService
{
    public const OWNER_ASSIGNABLE_ROLES = [
        'branch_manager',
        'pharmacist',
        'pharmacy_assistant',
        'stock_manager',
        'cashier',
        'accountant',
        'delivery_coordinator',
    ];

    public const MANAGER_ASSIGNABLE_ROLES = [
        'pharmacist',
        'pharmacy_assistant',
        'stock_manager',
        'cashier',
        'delivery_coordinator',
    ];

    public const ROLE_LABELS = [
        'branch_manager' => 'Branch Manager',
        'pharmacist' => 'Pharmacist',
        'pharmacy_assistant' => 'Pharmacy Assistant',
        'stock_manager' => 'Stock Manager',
        'cashier' => 'Cashier',
        'accountant' => 'Accountant',
        'delivery_coordinator' => 'Delivery Coordinator',
    ];

    public function canManageStaff(?User $actor): bool
    {
        return $actor instanceof User
            && filled($actor->pharmacy_id)
            && ($actor->can('employees.manage') ?? false)
            && $actor->hasAnyRole([
                'pharmacy_owner',
                'branch_manager',
            ])
            && (
                ! $actor->hasRole('branch_manager')
                || filled($actor->pharmacy_branch_id)
            );
    }

    public function isOwner(User $actor): bool
    {
        return $actor->hasRole('pharmacy_owner');
    }

    public function isBranchManager(User $actor): bool
    {
        return $actor->hasRole('branch_manager');
    }

    public function assignableRoleNames(User $actor): array
    {
        $this->assertCanManageStaff($actor);

        return $this->isOwner($actor)
            ? self::OWNER_ASSIGNABLE_ROLES
            : self::MANAGER_ASSIGNABLE_ROLES;
    }

    public function assignableRoleOptions(User $actor): array
    {
        return collect($this->assignableRoleNames($actor))
            ->mapWithKeys(fn (string $role): array => [
                $role => self::ROLE_LABELS[$role] ?? str($role)->headline()->toString(),
            ])
            ->all();
    }

    public function branchOptions(User $actor): array
    {
        $this->assertCanManageStaff($actor);

        return PharmacyBranch::query()
            ->where('pharmacy_id', $actor->pharmacy_id)
            ->where('status', 'active')
            ->when(
                $this->isBranchManager($actor),
                fn (Builder $query): Builder => $query->whereKey(
                    $actor->pharmacy_branch_id,
                ),
            )
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function manageableQuery(User $actor): Builder
    {
        $this->assertCanManageStaff($actor);

        return User::query()
            ->where('pharmacy_id', $actor->pharmacy_id)
            ->whereHas(
                'roles',
                fn (Builder $query): Builder => $query->whereIn(
                    'name',
                    $this->assignableRoleNames($actor),
                ),
            )
            ->when(
                $this->isBranchManager($actor),
                fn (Builder $query): Builder => $query->where(
                    'pharmacy_branch_id',
                    $actor->pharmacy_branch_id,
                ),
            );
    }

    public function canManageTarget(User $actor, User $target): bool
    {
        if (! $this->canManageStaff($actor)) {
            return false;
        }

        if ((int) $actor->pharmacy_id !== (int) $target->pharmacy_id) {
            return false;
        }

        if (
            $this->isBranchManager($actor)
            && (int) $actor->pharmacy_branch_id
                !== (int) $target->pharmacy_branch_id
        ) {
            return false;
        }

        $targetRole = $this->roleName($target);

        return in_array(
            $targetRole,
            $this->assignableRoleNames($actor),
            true,
        );
    }

    public function create(User $actor, array $data): User
    {
        $this->assertCanManageStaff($actor);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:191'],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'hired_at' => ['nullable', 'date'],
            'pharmacy_branch_id' => ['required', 'integer'],
            'staff_role' => [
                'required',
                'string',
                Rule::in($this->assignableRoleNames($actor)),
            ],
            'is_active' => ['required', 'boolean'],
            'password' => ['required', 'string', 'min:12', 'same:password_confirmation'],
            'password_confirmation' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $branch = $this->resolveBranch(
            $actor,
            (int) $validated['pharmacy_branch_id'],
        );

        return DB::transaction(function () use (
            $actor,
            $validated,
            $branch,
        ): User {
            $user = new User();

            $user->forceFill([
                'name' => $validated['name'],
                'email' => mb_strtolower($validated['email']),
                'phone' => $validated['phone'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'hired_at' => $validated['hired_at'] ?? today(),
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
                'is_active' => (bool) $validated['is_active'],
                'pharmacy_id' => $actor->pharmacy_id,
                'pharmacy_branch_id' => $branch->id,
                'invited_by_user_id' => $actor->id,
                'staff_updated_by_user_id' => $actor->id,
            ])->save();

            $user->syncRoles([$validated['staff_role']]);

            $this->recordEvent(
                actor: $actor,
                target: $user,
                eventType: 'staff_recruited',
                newRole: $validated['staff_role'],
                newBranchId: $branch->id,
                reason: $validated['reason'] ?? null,
                metadata: [
                    'active' => (bool) $validated['is_active'],
                    'job_title' => $validated['job_title'] ?? null,
                ],
            );

            return $user->fresh(['roles']);
        });
    }

    public function update(User $actor, User $target, array $data): User
    {
        $this->assertCanManageTarget($actor, $target);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:191'],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($target->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'hired_at' => ['nullable', 'date'],
            'pharmacy_branch_id' => ['required', 'integer'],
            'staff_role' => [
                'required',
                'string',
                Rule::in($this->assignableRoleNames($actor)),
            ],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:12', 'same:password_confirmation'],
            'password_confirmation' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $branch = $this->resolveBranch(
            $actor,
            (int) $validated['pharmacy_branch_id'],
        );

        return DB::transaction(function () use (
            $actor,
            $target,
            $validated,
            $branch,
        ): User {
            $oldRole = $this->roleName($target);
            $oldBranchId = $target->pharmacy_branch_id;
            $oldActive = (bool) $target->is_active;

            $changes = [
                'name' => $validated['name'],
                'email' => mb_strtolower($validated['email']),
                'phone' => $validated['phone'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'hired_at' => $validated['hired_at'] ?? null,
                'is_active' => (bool) $validated['is_active'],
                'pharmacy_branch_id' => $branch->id,
                'staff_updated_by_user_id' => $actor->id,
            ];

            if (filled($validated['password'] ?? null)) {
                $changes['password'] = Hash::make($validated['password']);
            }

            $target->forceFill($changes)->save();
            $target->syncRoles([$validated['staff_role']]);

            $this->recordEvent(
                actor: $actor,
                target: $target,
                eventType: 'staff_updated',
                oldRole: $oldRole,
                newRole: $validated['staff_role'],
                oldBranchId: $oldBranchId,
                newBranchId: $branch->id,
                reason: $validated['reason'] ?? null,
                metadata: [
                    'old_active' => $oldActive,
                    'new_active' => (bool) $validated['is_active'],
                    'password_reset' => filled($validated['password'] ?? null),
                    'changed_fields' => array_keys($target->getChanges()),
                ],
            );

            return $target->fresh(['roles']);
        });
    }

    public function setActive(
        User $actor,
        User $target,
        bool $active,
        ?string $reason = null,
    ): User {
        $this->assertCanManageTarget($actor, $target);

        if ((bool) $target->is_active === $active) {
            return $target;
        }

        return DB::transaction(function () use (
            $actor,
            $target,
            $active,
            $reason,
        ): User {
            $target->forceFill([
                'is_active' => $active,
                'staff_updated_by_user_id' => $actor->id,
            ])->save();

            $this->recordEvent(
                actor: $actor,
                target: $target,
                eventType: $active
                    ? 'staff_reactivated'
                    : 'staff_deactivated',
                oldRole: $this->roleName($target),
                newRole: $this->roleName($target),
                oldBranchId: $target->pharmacy_branch_id,
                newBranchId: $target->pharmacy_branch_id,
                reason: $reason,
                metadata: ['active' => $active],
            );

            return $target->fresh(['roles']);
        });
    }

    public function roleName(User $user): ?string
    {
        return $user->roles()->value('name');
    }

    private function resolveBranch(User $actor, int $branchId): PharmacyBranch
    {
        $branch = PharmacyBranch::query()
            ->whereKey($branchId)
            ->where('pharmacy_id', $actor->pharmacy_id)
            ->where('status', 'active')
            ->first();

        if (! $branch) {
            throw ValidationException::withMessages([
                'pharmacy_branch_id' =>
                    'The selected branch is not active or does not belong to your pharmacy.',
            ]);
        }

        if (
            $this->isBranchManager($actor)
            && (int) $actor->pharmacy_branch_id !== (int) $branch->id
        ) {
            throw ValidationException::withMessages([
                'pharmacy_branch_id' =>
                    'A branch manager can recruit only for their assigned branch.',
            ]);
        }

        return $branch;
    }

    private function assertCanManageStaff(User $actor): void
    {
        if (! $this->canManageStaff($actor)) {
            throw new AuthorizationException(
                'You are not allowed to manage pharmacy staff.',
            );
        }
    }

    private function assertCanManageTarget(User $actor, User $target): void
    {
        if (! $this->canManageTarget($actor, $target)) {
            throw new AuthorizationException(
                'This staff account is outside your recruitment scope.',
            );
        }
    }

    private function recordEvent(
        User $actor,
        User $target,
        string $eventType,
        ?string $oldRole = null,
        ?string $newRole = null,
        ?int $oldBranchId = null,
        ?int $newBranchId = null,
        ?string $reason = null,
        array $metadata = [],
    ): StaffManagementEvent {
        return StaffManagementEvent::create([
            'pharmacy_id' => $actor->pharmacy_id,
            'pharmacy_branch_id' => $newBranchId
                ?? $target->pharmacy_branch_id,
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'event_type' => $eventType,
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'old_branch_id' => $oldBranchId,
            'new_branch_id' => $newBranchId,
            'reason' => $reason,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
