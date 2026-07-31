<?php

namespace App\Filament\Resources\Medicines\Pages;

use App\Filament\Resources\Medicines\MedicineResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMedicine extends EditRecord
{
    protected static string $resource = MedicineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(
                    fn (): bool =>
                        $this->record->approval_status !== 'approved'
                )
                ->schema([
                    Textarea::make('review_notes')
                        ->label('Approval note')
                        ->rows(3),
                ])
                ->action(
                    fn (array $data): mixed =>
                        $this->reviewMedicine(
                            'approved',
                            $data['review_notes'] ?? null,
                        ),
                ),

            Action::make('request_changes')
                ->label('Request changes')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->schema([
                    Textarea::make('review_notes')
                        ->label('Required corrections')
                        ->required()
                        ->rows(4),
                ])
                ->action(
                    fn (array $data): mixed =>
                        $this->reviewMedicine(
                            'changes_requested',
                            $data['review_notes'],
                        ),
                ),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('review_notes')
                        ->label('Rejection reason')
                        ->required()
                        ->rows(4),
                ])
                ->action(
                    fn (array $data): mixed =>
                        $this->reviewMedicine(
                            'rejected',
                            $data['review_notes'],
                        ),
                ),

            Action::make('suspend')
                ->label('Suspend')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(
                    fn (): bool =>
                        $this->record->approval_status === 'approved'
                )
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('review_notes')
                        ->label('Suspension reason')
                        ->required()
                        ->rows(4),
                ])
                ->action(
                    fn (array $data): mixed =>
                        $this->reviewMedicine(
                            'suspended',
                            $data['review_notes'],
                        ),
                ),
        ];
    }

    private function reviewMedicine(
        string $status,
        ?string $notes,
    ): mixed {
        $this->record->forceFill([
            'approval_status' => $status,
            'reviewed_by_user_id' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ])->save();

        Notification::make()
            ->title(
                match ($status) {
                    'approved' => 'Medicine approved',
                    'changes_requested' => 'Corrections requested',
                    'rejected' => 'Medicine rejected',
                    'suspended' => 'Medicine suspended',
                    default => 'Medicine updated',
                },
            )
            ->success()
            ->send();

        return $this->redirect(
            MedicineResource::getUrl('edit', [
                'record' => $this->record,
            ]),
        );
    }
}