<?php

namespace App\Filament\Pharmacy\Resources\Prescriptions\Pages;

use App\Actions\Prescriptions\ManagePrescriptionWorkflow;
use App\Filament\Pharmacy\Resources\Prescriptions\PrescriptionResource;
use App\Models\Prescription;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use App\Actions\Prescriptions\DispensePrescription;
use App\Filament\Pharmacy\Resources\Prescriptions\Schemas\PrescriptionDispensingForm;

class ViewPrescription extends ViewRecord
{
    protected static string $resource =
        PrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(
                    fn (): bool =>
                        PrescriptionResource::canEdit(
                            $this->record,
                        ),
                ),

            Action::make('submit')
                ->label('Submit for review')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading(
                    'Submit prescription for review'
                )
                ->modalDescription(
                    'The draft will become read-only and will be sent to a pharmacist for validation.'
                )
                ->visible(
                    fn (): bool =>
                        $this->record->status
                            === Prescription::STATUS_DRAFT
                        && (
                            auth()->user()
                                ?->can(
                                    'prescriptions.manage'
                                )
                            ?? false
                        ),
                )
                ->action(function (): void {
                    $user = $this->currentUser();

                    $prescription = app(
                        ManagePrescriptionWorkflow::class,
                    )->submit(
                        $user,
                        $this->record,
                    );

                    Notification::make()
                        ->success()
                        ->title(
                            'Prescription submitted'
                        )
                        ->send();

                    $this->redirectToRecord(
                        $prescription,
                    );
                }),

            Action::make('startReview')
                ->label('Start review')
                ->icon(
                    'heroicon-o-magnifying-glass'
                )
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(
                    'Start pharmacist review'
                )
                ->visible(
                    fn (): bool =>
                        $this->record->status
                            === Prescription::STATUS_SUBMITTED
                        && (
                            auth()->user()
                                ?->can(
                                    'prescriptions.validate'
                                )
                            ?? false
                        ),
                )
                ->action(function (): void {
                    $user = $this->currentUser();

                    $prescription = app(
                        ManagePrescriptionWorkflow::class,
                    )->startReview(
                        $user,
                        $this->record,
                    );

                    Notification::make()
                        ->success()
                        ->title(
                            'Prescription review started'
                        )
                        ->send();

                    $this->redirectToRecord(
                        $prescription,
                    );
                }),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(
                    'Approve prescription'
                )
                ->modalDescription(
                    'Confirm that the prescription is valid and ready for dispensing.'
                )
                ->visible(
                    fn (): bool =>
                        $this->record->status
                            === Prescription::STATUS_UNDER_REVIEW
                        && (
                            auth()->user()
                                ?->can(
                                    'prescriptions.validate'
                                )
                            ?? false
                        ),
                )
                ->action(function (): void {
                    $user = $this->currentUser();

                    $prescription = app(
                        ManagePrescriptionWorkflow::class,
                    )->approve(
                        $user,
                        $this->record,
                    );

                    Notification::make()
                        ->success()
                        ->title(
                            'Prescription approved'
                        )
                        ->send();

                    $this->redirectToRecord(
                        $prescription,
                    );
                }),

            Action::make('dispense')
    ->label('Dispense medicines')
    ->icon('heroicon-o-shopping-cart')
    ->color('success')
    ->visible(
        fn (): bool =>
            $this->canDispensePrescription(),
    )
    ->modalHeading(
        'Dispense prescription medicines'
    )
    ->modalDescription(
        'Completing this form creates a paid POS sale, deducts stock using FEFO and permanently records the dispensing event.'
    )
    ->schema(
        PrescriptionDispensingForm::components(
            $this->record,
        ),
    )
    ->action(
        function (array $data): void {
            $dispensing = app(
                DispensePrescription::class,
            )->handle(
                actor: $this->currentUser(),

                prescription: $this->record,

                lines: $data['lines'] ?? [],

                payments:
                    $data['payments'] ?? [],

                notes: $data['notes'] ?? null,
            );

            Notification::make()
                ->success()
                ->title(
                    'Medicines dispensed successfully'
                )
                ->body(
                    sprintf(
                        'Dispensing %s was linked to sale %s.',
                        $dispensing
                            ->dispensing_number,

                        $dispensing
                            ->sale
                            ->sale_number,
                    ),
                )
                ->send();

            $this->redirectToRecord(
                $dispensing
                    ->prescription
                    ->fresh(),
            );
        },
    ),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(
                    fn (): bool =>
                        $this->record->status
                            === Prescription::STATUS_UNDER_REVIEW
                        && (
                            auth()->user()
                                ?->can(
                                    'prescriptions.validate'
                                )
                            ?? false
                        ),
                )
                ->schema([
                    Textarea::make(
                        'rejection_reason'
                    )
                        ->label('Rejection reason')
                        ->required()
                        ->minLength(5)
                        ->maxLength(3000)
                        ->rows(5),
                ])
                ->modalHeading(
                    'Reject prescription'
                )
                ->modalDescription(
                    'Provide a clear reason. The decision will be permanently recorded.'
                )
                ->action(
                    function (array $data): void {
                        $user =
                            $this->currentUser();

                        $prescription = app(
                            ManagePrescriptionWorkflow::class,
                        )->reject(
                            $user,
                            $this->record,
                            $data[
                                'rejection_reason'
                            ],
                        );

                        Notification::make()
                            ->success()
                            ->title(
                                'Prescription rejected'
                            )
                            ->send();

                        $this->redirectToRecord(
                            $prescription,
                        );
                    },
                ),
        ];
    }

    private function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function redirectToRecord(
        Prescription $prescription,
    ): void {
        $this->redirect(
            PrescriptionResource::getUrl(
                'view',
                [
                    'record' => $prescription,
                ],
            ),
        );
    }

    private function canDispensePrescription(): bool
{
    $user = auth()->user();

    if (! $user instanceof User) {
        return false;
    }

    if (
        ! $user->can('prescriptions.dispense')
        || ! $user->can('sales.create')
    ) {
        return false;
    }

    if (
        (int) $user->pharmacy_id
            !== (int) $this->record->pharmacy_id
        || (int) $user->pharmacy_branch_id
            !== (int) $this->record
                ->pharmacy_branch_id
    ) {
        return false;
    }

    if (
        ! in_array(
            $this->record->status,
            [
                Prescription::STATUS_APPROVED,
                Prescription
                    ::STATUS_PARTIALLY_DISPENSED,
            ],
            true,
        )
    ) {
        return false;
    }

    if (
        $this->record->valid_until !== null
        && $this->record
            ->valid_until
            ->isBefore(today())
    ) {
        return false;
    }

    return $this->record
        ->items()
        ->whereColumn(
            'quantity_dispensed',
            '<',
            'quantity_prescribed',
        )
        ->exists();
}
}