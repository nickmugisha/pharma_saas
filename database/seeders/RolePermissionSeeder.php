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
            // Platform administration
            'platform.dashboard.view',
            'platform.users.view',
            'platform.users.manage',
            'platform.roles.manage',
            'platform.audit.view',

            // Partner pharmacies
            'pharmacies.view',
            'pharmacies.manage',
            'pharmacies.approve',
            'pharmacies.suspend',

            // SaaS subscriptions and platform finance
            'subscriptions.view',
            'subscriptions.manage',
            'platform.finance.view',
            'platform.finance.manage',

            // Platform support and compliance
            'compliance.review',
            'support.manage',

            // Pharmacy administration
            'pharmacy.dashboard.view',
            'pharmacy.settings.manage',
            'branches.manage',
            'employees.manage',

            // Medicines and stock
            'medicines.view',
            'medicines.manage',
            'stock.view',
            'stock.manage',
            'stock.adjust',
            'stock.transfer',

            // Purchases and suppliers
            'purchases.view',
            'purchases.manage',

            // Customers
'customers.view',
'customers.manage',

            // Sales
            'sales.view',
            'sales.manage',
            'sales.void',
            'sales.create',
            'sales.cancel',
            'sales.refund',

            // Prescriptions and patients
            'prescriptions.view',
            'prescriptions.validate',
            'patients.view',
            'patients.manage',

            // Delivery
            'deliveries.view',
            'deliveries.manage',

            // Pharmacy finance and reports
            'pharmacy.finance.view',
            'pharmacy.finance.manage',
            'reports.view',
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
                || $permission === 'reports.view',
        ));

        $roles = [
            // Platform roles
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
            ],

            'support_agent' => [
                'platform.dashboard.view',
                'platform.users.view',
                'pharmacies.view',
                'support.manage',
            ],

            // Pharmacy roles
            'pharmacy_owner' => $pharmacyPermissions,

            'branch_manager' => [
                'pharmacy.dashboard.view',
                'branches.manage',
                'employees.manage',
                'medicines.view',
                'customers.view',
'customers.manage',
                'sales.manage',
                'sales.void',
                'medicines.manage',
                'stock.view',
                'stock.manage',
                'stock.adjust',
                'stock.transfer',
                'purchases.view',
                'purchases.manage',
                'sales.view',
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
            ],

            'pharmacist' => [
                'pharmacy.dashboard.view',
                'medicines.view',
                'stock.view',
                'sales.view',
                'customers.view',
'customers.manage',
                'sales.create',
                'prescriptions.view',
                'prescriptions.validate',
                'patients.view',
                'patients.manage',
                'deliveries.view',
                'reports.view',
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
                'patients.view',
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
            ],

            'accountant' => [
                'pharmacy.dashboard.view',
                'purchases.view',
                'customers.view',
                'sales.view',
                'pharmacy.finance.view',
                'pharmacy.finance.manage',
                'reports.view',
            ],

            'delivery_coordinator' => [
                'pharmacy.dashboard.view',
                'sales.view',
                'customers.view',
                'patients.view',
                'deliveries.view',
                'deliveries.manage',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, $guard);
            $role->syncPermissions($rolePermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}