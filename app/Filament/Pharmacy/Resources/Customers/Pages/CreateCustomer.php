<?php

namespace App\Filament\Pharmacy\Resources\Customers\Pages;

use App\Actions\Customers\RecordCustomerActivity;
use App\Filament\Pharmacy\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Models\PatientProfile;
use App\Models\PharmacyBranch;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateCustomer extends CreateRecord
{
    protected static string $resource =
        CustomerResource::class;

    protected static ?string $title =
        'Register Customer';

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(
        array $data,
    ): Model {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->can('customers.manage'), 403);

        return DB::transaction(function () use (
            $data,
            $user,
        ): Customer {
            $branchId = (int) (
                $data['registered_branch_id']
                ?? $user->pharmacy_branch_id
            );

            PharmacyBranch::query()
                ->whereKey($branchId)
                ->where('pharmacy_id', $user->pharmacy_id)
                ->where('status', 'active')
                ->firstOrFail();

            $customer = Customer::create([
                ...$this->customerData($data),

                'pharmacy_id' =>
                    $user->pharmacy_id,

                'registered_branch_id' =>
                    $branchId,
            ]);

            if (
                (bool) (
                    $data['has_patient_profile']
                    ?? false
                )
            ) {
                PatientProfile::create([
                    'customer_id' => $customer->id,
                    'created_by_user_id' => $user->id,
                    ...$this->patientData($data),
                ]);
            }

            app(RecordCustomerActivity::class)
                ->handle(
                    actor: $user,
                    customer: $customer,
                    activityType: 'customer_registered',
                    title: 'Customer account registered',
                    description:
                        'The customer account was created through the pharmacy panel.',
                    subject: $customer,
                    metadata: [
                        'patient_profile_created' =>
                            (bool) (
                                $data[
                                    'has_patient_profile'
                                ] ?? false
                            ),
                    ],
                    branchId: $branchId,
                );

            return $customer->fresh([
                'registeredBranch',
                'patientProfile',
            ]);
        });
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl(
            'view',
            [
                'record' => $this->record,
            ],
        );
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Customer registered successfully';
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