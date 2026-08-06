<?php

namespace App\Filament\Pharmacy\Widgets;

use App\Filament\Pharmacy\Resources\StaffMembers\StaffMemberResource;
use App\Models\StaffManagementEvent;
use App\Models\User;
use App\Services\StaffRecruitmentService;
use Filament\Widgets\Widget;

class StaffActivityFeed extends Widget
{
    protected string $view =
        'filament.pharmacy.widgets.staff-activity-feed';

    protected static ?int $sort = 8;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(StaffRecruitmentService::class)
                ->canManageStaff($user);
    }

    public function getCreateUrl(): string
    {
        return StaffMemberResource::getUrl('create');
    }

    public function getIndexUrl(): string
    {
        return StaffMemberResource::getUrl('index');
    }

    public function getScopeLabel(): string
    {
        $user = auth()->user();

        return $user?->hasRole('pharmacy_owner')
            ? 'All branches in your pharmacy'
            : 'Your assigned branch only';
    }

    public function getEvents(): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return StaffManagementEvent::query()
            ->with(['actor', 'target', 'branch'])
            ->where('pharmacy_id', $user->pharmacy_id)
            ->when(
                $user->hasRole('branch_manager'),
                fn ($query) => $query->where(
                    'pharmacy_branch_id',
                    $user->pharmacy_branch_id,
                ),
            )
            ->latest('occurred_at')
            ->limit(12)
            ->get()
            ->map(fn (StaffManagementEvent $event): array => [
                'title' => match ($event->event_type) {
                    'staff_recruited' => 'Employee recruited',
                    'staff_updated' => 'Employee access updated',
                    'staff_deactivated' => 'Employee account deactivated',
                    'staff_reactivated' => 'Employee account reactivated',
                    default => str($event->event_type)->headline()->toString(),
                },
                'description' => sprintf(
                    '%s · %s · %s',
                    $event->target?->name ?? 'Employee',
                    StaffRecruitmentService::ROLE_LABELS[$event->new_role]
                        ?? str($event->new_role ?? 'staff')->headline()->toString(),
                    $event->branch?->name ?? 'Branch not assigned',
                ),
                'actor' => $event->actor?->name ?? 'System',
                'relative_time' => $event->occurred_at?->diffForHumans()
                    ?? 'Recently',
                'tone' => match ($event->event_type) {
                    'staff_deactivated' => 'danger',
                    'staff_reactivated', 'staff_recruited' => 'success',
                    default => 'info',
                },
            ])
            ->all();
    }
}
