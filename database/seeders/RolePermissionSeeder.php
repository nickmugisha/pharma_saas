<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'platform.dashboard.view',
            'platform.users.view',
            'platform.users.manage',
            'platform.roles.manage',
            'platform.audit.view',
            'pharmacies.view',
            'pharmacies.manage',
            'pharmacies.approve',
            'pharmacies.suspend',
            'subscriptions.view',
            'subscriptions.manage',
            'platform.finance.view',
            'platform.finance.manage',
            'compliance.review',
            'support.manage',
            'pharmacy.dashboard.view',
            'pharmacy.settings.manage',
            'branches.manage',
            'employees.manage',
            'medicines.view',
            'medicines.manage',
            'stock.view',
            'stock.manage',
            'stock.adjust',
            'stock.transfer',
            'purchases.view',
            'purchases.manage',
            'customers.view',
            'customers.manage',
            'sales.view',
            'sales.manage',
            'sales.void',
            'sales.create',
            'sales.cancel',
            'sales.refund',
            'prescriptions.view',
            'prescriptions.manage',
            'prescriptions.validate',
            'prescriptions.dispense',
            'patients.view',
            'patients.manage',
            'deliveries.view',
            'deliveries.manage',
            'pharmacy.finance.view',
            'pharmacy.finance.manage',
            'reports.view',
            'marketplace.offers.view',
            'marketplace.offers.manage',
            'marketplace.orders.view',
            'marketplace.orders.manage',
            'marketplace.prescriptions.review',
            'wallets.view',
            'wallets.manage',
            'wallets.funding.review',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        $pharmacyPermissions = array_values(array_filter(
            $permissions,
            fn (string $permission): bool => str_starts_with($permission, 'pharmacy.')
                || str_starts_with($permission, 'branches.')
                || str_starts_with($permission, 'employees.')
                || str_starts_with($permission, 'medicines.')
                || str_starts_with($permission, 'stock.')
                || str_starts_with($permission, 'customers.')
                || str_starts_with($permission, 'purchases.')
                || str_starts_with($permission, 'sales.')
                || str_starts_with($permission, 'prescriptions.')
                || str_starts_with($permission, 'patients.')
                || str_starts_with($permission, 'deliveries.')
                || str_starts_with($permission, 'marketplace.')
                || $permission === 'reports.view',
        ));

        $roles = [
            'super_admin' => $permissions,
            'platform_admin' => [
                'platform.dashboard.view',
                'platform.users.view',
                'platform.users.manage',
                'platform.audit.view',
                'pharmacies.view',
                'pharmacies.manage',
                'pharmacies.approve',
                'pharmacies.suspend',
                'subscriptions.view',
                'subscriptions.manage',
                'platform.finance.view',
                'platform.finance.manage',
                'support.manage',
                'wallets.view',
                'wallets.manage',
                'wallets.funding.review',
            ],
            'compliance_officer' => [
                'platform.dashboard.view',
                'platform.audit.view',
                'pharmacies.view',
                'pharmacies.approve',
                'pharmacies.suspend',
                'compliance.review',
            ],
            'finance_manager' => [
                'platform.dashboard.view',
                'platform.audit.view',
                'pharmacies.view',
                'subscriptions.view',
                'subscriptions.manage',
                'platform.finance.view',
                'platform.finance.manage',
                'wallets.view',
                'wallets.manage',
                'wallets.funding.review',
            ],
            'support_agent' => [
                'platform.dashboard.view',
                'platform.users.view',
                'pharmacies.view',
                'support.manage',
                'wallets.view',
            ],
            'pharmacy_owner' => $pharmacyPermissions,
            'branch_manager' => [
                'pharmacy.dashboard.view',
                'branches.manage',
                'employees.manage',
                'medicines.view',
                'medicines.manage',
                'customers.view',
                'customers.manage',
                'stock.view',
                'stock.manage',
                'stock.adjust',
                'stock.transfer',
                'purchases.view',
                'purchases.manage',
                'sales.view',
                'sales.manage',
                'sales.void',
                'sales.create',
                'sales.cancel',
                'sales.refund',
                'prescriptions.view',
                'prescriptions.validate',
                'patients.view',
                'patients.manage',
                'deliveries.view',
                'deliveries.manage',
                'pharmacy.finance.view',
                'reports.view',
                'marketplace.offers.view',
                'marketplace.offers.manage',
                'marketplace.orders.view',
                'marketplace.orders.manage',
                'marketplace.prescriptions.review',
            ],
            'pharmacist' => [
                'pharmacy.dashboard.view',
                'medicines.view',
                'stock.view',
                'sales.view',
                'sales.create',
                'customers.view',
                'customers.manage',
                'prescriptions.view',
                'prescriptions.manage',
                'prescriptions.dispense',
                'prescriptions.validate',
                'patients.view',
                'patients.manage',
                'deliveries.view',
                'reports.view',
                'marketplace.offers.view',
                'marketplace.orders.view',
                'marketplace.orders.manage',
                'marketplace.prescriptions.review',
            ],
            'pharmacy_assistant' => [
                'pharmacy.dashboard.view',
                'medicines.view',
                'customers.view',
                'customers.manage',
                'stock.view',
                'sales.view',
                'sales.create',
                'prescriptions.view',
                'prescriptions.manage',
                'patients.view',
                'marketplace.orders.view',
            ],
            'stock_manager' => [
                'pharmacy.dashboard.view',
                'medicines.view',
                'medicines.manage',
                'stock.view',
                'stock.manage',
                'stock.adjust',
                'stock.transfer',
                'purchases.view',
                'purchases.manage',
                'reports.view',
                'marketplace.offers.view',
                'marketplace.offers.manage',
            ],
            'cashier' => [
                'pharmacy.dashboard.view',
                'medicines.view',
                'stock.view',
                'customers.view',
                'customers.manage',
                'sales.manage',
                'sales.view',
                'sales.create',
                'patients.view',
                'marketplace.orders.view',
            ],
            'accountant' => [
                'pharmacy.dashboard.view',
                'purchases.view',
                'customers.view',
                'sales.view',
                'pharmacy.finance.view',
                'pharmacy.finance.manage',
                'reports.view',
                'marketplace.orders.view',
            ],
            'delivery_coordinator' => [
                'pharmacy.dashboard.view',
                'sales.view',
                'customers.view',
                'patients.view',
                'deliveries.view',
                'deliveries.manage',
                'marketplace.orders.view',
                'marketplace.orders.manage',
            ],
            'client' => [],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, $guard);
            $role->syncPermissions($rolePermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
