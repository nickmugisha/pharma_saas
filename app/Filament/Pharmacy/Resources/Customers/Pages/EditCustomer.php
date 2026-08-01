<?php

namespace App\Filament\Pharmacy\Resources\Customers\Pages;

use App\Actions\Customers\RecordCustomerActivity;
use App\Filament\Pharmacy\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Models\PatientProfile;
use App\Models\PharmacyBranch;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditCustomer extends EditRecord
{
    protected static string $resource =
        CustomerResource::class;

    protected function mutateFormDataBeforeFill(
        array $data,
    ): array {
        /** @var Customer $customer */
        $customer = $this->record;

        $customer->loadMissing('patientProfile');

        $profile = $customer->patientProfile;

        $data['has_patient_profile'] =
            $profile !== null;

        $data['patient_date_of_birth'] =
            $profile?->date_of_birth?->toDateString();

        $data['patient_sex'] =
            $profile?->sex;

        $data['patient_emergency_contact_name'] =
            $profile?->emergency_contact_name;

        $data['patient_emergency_contact_phone'] =
            $profile?->emergency_contact_phone;

        $data['patient_emergency_contact_relation'] =
            $profile?->emergency_contact_relation;

        return $data;
    }

    protected function handleRecordUpdate(
        Model $record,
        array $data,
    ): Model {
        abort_unless($record instanceof Customer, 404);

        $user = auth()->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->can('customers.manage'), 403);

        abort_unless(
            (int) $record->pharmacy_id
                === (int) $user->pharmacy_id,
            403,
        );

        return DB::transaction(function () use (
            $record,
            $data,
            $user,
        ): Customer {
            $branchId = (int) (
                $data['registered_branch_id']
                ?? $record->registered_branch_id
            );

            PharmacyBranch::query()
                ->whereKey($branchId)
                ->where('pharmacy_id', $user->pharmacy_id)
                ->where('status', 'active')
                ->firstOrFail();

            $record->loadMissing('patientProfile');

            $record->fill([
                ...$this->customerData($data),
                'registered_branch_id' => $branchId,
            ]);

            $customerChanges = array_keys(
                $record->getDirty(),
            );

            $record->save();

            $profile = $record->patientProfile;

            $hasPatientProfile =
                $profile !== null
                || (bool) (
                    $data['has_patient_profile']
                    ?? false
                );

            $profileChanges = [];

            if ($hasPatientProfile) {
                if ($profile === null) {
                    $profile = PatientProfile::create([
                        'customer_id' => $record->id,
                        'created_by_user_id' => $user->id,
                        ...$this->patientData($data),
                    ]);

                    $profileChanges = [
                        'patient_profile_created',
                    ];
                } else {
                    $profile->fill(
                        $this->patientData($data),
                    );

                    $profileChanges = array_keys(
                        $profile->getDirty(),
                    );

                    $profile->save();
                }
            }

            if (
                $customerChanges !== []
                || $profileChanges !== []
            ) {
                app(RecordCustomerActivity::class)
                    ->handle(
                        actor: $user,
                        customer: $record,
                        activityType: 'customer_updated',
                        title: 'Customer account updated',
                        description:
                            'Customer or patient-profile information was updated.',
                        subject: $record,
                        metadata: [
                            'customer_fields' =>
                                $customerChanges,

                            'patient_fields' =>
                                $profileChanges,
                        ],
                        branchId: $branchId,
                    );
            }

            return $record->fresh([
                'registeredBranch',
                'patientProfile',
            ]);
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Customer updated successfully';
    }

    private function customerData(array $data): array
    {
        return [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? 'Burundi',
            'preferred_language' =>
                $data['preferred_language'] ?? 'fr',
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function patientData(array $data): array
    {
        return [
            'date_of_birth' =>
                $data['patient_date_of_birth'] ?? null,

            'sex' =>
                $data['patient_sex'] ?? null,

            'emergency_contact_name' =>
                $data[
                    'patient_emergency_contact_name'
                ] ?? null,

            'emergency_contact_phone' =>
                $data[
                    'patient_emergency_contact_phone'
                ] ?? null,

            'emergency_contact_relation' =>
                $data[
                    'patient_emergency_contact_relation'
                ] ?? null,
        ];
    }
}