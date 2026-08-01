<?php

namespace Tests\Feature;

use App\Actions\Reports\BuildSalesReport;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use App\Models\Sale;
use App\Models\Medicine;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_report_calculates_sales_profit_and_voids(): void
    {
        $context = $this->createContext('REPORT');

        $this->createSale(
            context: $context,
            branch: $context['mainBranch'],
            suffix: 'MAIN',
            status: 'completed',
            subtotal: 10000,
            discount: 1000,
            tax: 900,
            total: 9900,
            cost: 5000,
            quantity: 2,
            paymentMethod: 'cash',
            paymentAmount: 10000,
        );

        $this->createSale(
            context: $context,
            branch: $context['secondBranch'],
            suffix: 'SECOND',
            status: 'completed',
            subtotal: 5000,
            discount: 0,
            tax: 0,
            total: 5000,
            cost: 3000,
            quantity: 1,
            paymentMethod: 'mobile_money',
            paymentAmount: 5000,
        );

        $this->createSale(
            context: $context,
            branch: $context['mainBranch'],
            suffix: 'VOIDED',
            status: 'voided',
            subtotal: 7000,
            discount: 0,
            tax: 0,
            total: 7000,
            cost: 4000,
            quantity: 2,
            paymentMethod: null,
            paymentAmount: 0,
        );

        $report = app(BuildSalesReport::class)->handle(
            $context['owner'],
            [
                'start_date' =>
                    now()->subDays(5)->toDateString(),

                'end_date' =>
                    now()->toDateString(),
            ],
        );

        $summary = $report['summary'];

        $this->assertSame(2, $summary['sales_count']);
        $this->assertSame(3.0, $summary['units_sold']);
        $this->assertSame(15000.0, $summary['subtotal']);
        $this->assertSame(1000.0, $summary['discount_total']);

        $this->assertSame(
            14000.0,
            $summary['net_sales_before_tax'],
        );

        $this->assertSame(900.0, $summary['tax_total']);
        $this->assertSame(14900.0, $summary['revenue']);
        $this->assertSame(8000.0, $summary['cost_of_goods']);
        $this->assertSame(6000.0, $summary['gross_profit']);

        $this->assertSame(
            42.86,
            $summary['gross_margin_percentage'],
        );

        $this->assertSame(7450.0, $summary['average_sale']);
        $this->assertSame(100.0, $summary['cash_change_total']);
        $this->assertSame(1, $summary['voided_sales_count']);
        $this->assertSame(7000.0, $summary['voided_sales_value']);
    }

    public function test_report_supports_branch_filter_and_excludes_other_pharmacies(): void
    {
        $contextA = $this->createContext('TENANT-A');
        $contextB = $this->createContext('TENANT-B');

        $this->createSale(
            $contextA,
            $contextA['mainBranch'],
            'A-MAIN',
            'completed',
            6000,
            0,
            0,
            6000,
            3000,
            2,
            'cash',
            6000,
        );

        $this->createSale(
            $contextA,
            $contextA['secondBranch'],
            'A-SECOND',
            'completed',
            9000,
            0,
            0,
            9000,
            4500,
            3,
            'cash',
            9000,
        );

        $this->createSale(
            $contextB,
            $contextB['mainBranch'],
            'B-FOREIGN',
            'completed',
            50000,
            0,
            0,
            50000,
            20000,
            10,
            'cash',
            50000,
        );

        $report = app(BuildSalesReport::class)->handle(
            $contextA['owner'],
            [
                'start_date' =>
                    now()->subDays(5)->toDateString(),

                'end_date' =>
                    now()->toDateString(),

                'pharmacy_branch_id' =>
                    $contextA['mainBranch']->id,
            ],
        );

        $this->assertSame(
            1,
            $report['summary']['sales_count'],
        );

        $this->assertSame(
            6000.0,
            $report['summary']['revenue'],
        );

        $this->assertCount(
            1,
            $report['branch_performance'],
        );

        $this->assertSame(
            $contextA['mainBranch']->id,
            $report['branch_performance'][0]
                ['pharmacy_branch_id'],
        );

        $this->assertStringNotContainsString(
            'TENANT-B',
            json_encode($report),
        );
    }

    public function test_report_rejects_foreign_branch(): void
    {
        $contextA = $this->createContext('BRANCH-A');
        $contextB = $this->createContext('BRANCH-B');

        $this->expectException(
            ModelNotFoundException::class,
        );

        app(BuildSalesReport::class)->handle(
            $contextA['owner'],
            [
                'pharmacy_branch_id' =>
                    $contextB['mainBranch']->id,
            ],
        );
    }

    public function test_user_without_reports_permission_is_denied(): void
    {
        $context = $this->createContext('DENIED');

        $viewer = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $viewer->forceFill([
            'pharmacy_id' => $context['pharmacy']->id,
            'pharmacy_branch_id' =>
                $context['mainBranch']->id,
        ])->save();

        try {
            app(BuildSalesReport::class)->handle($viewer);

            $this->fail(
                'A user without reports.view accessed reports.'
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode(),
            );
        }
    }

    private function createContext(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "{$suffix} Report Pharmacy",
            'phone' => '+257 61 '.random_int(
                100000,
                999999,
            ),
            'status' => 'approved',
        ]);

        $mainBranch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Main Branch",
            'code' => 'REP-M-'.strtoupper(
                substr(md5($suffix), 0, 6),
            ),
            'is_main' => true,
            'status' => 'active',
        ]);

        $secondBranch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Second Branch",
            'code' => 'REP-S-'.strtoupper(
                substr(md5($suffix), 0, 6),
            ),
            'is_main' => false,
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $owner->forceFill([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $mainBranch->id,
        ])->save();

        $owner->assignRole('pharmacy_owner');

        return compact(
            'pharmacy',
            'mainBranch',
            'secondBranch',
            'owner',
        );
    }

    private function createSale(
        array $context,
        PharmacyBranch $branch,
        string $suffix,
        string $status,
        float $subtotal,
        float $discount,
        float $tax,
        float $total,
        float $cost,
        float $quantity,
        ?string $paymentMethod,
        float $paymentAmount,
    ): Sale {
        $medicine = Medicine::create([
    'brand_name' => "{$suffix} Report Medicine",
    'approval_status' => 'approved',
    'is_active' => true,
]);

$listing = PharmacyMedicine::create([
    'pharmacy_id' => $context['pharmacy']->id,
    'medicine_id' => $medicine->id,
    'sku' => "REPORT-SKU-{$suffix}",
    'selling_price' => $subtotal,
    'minimum_stock_level' => 0,
    'reorder_quantity' => 0,
    'expiry_warning_days' => 90,
    'alerts_enabled' => true,
    'status' => 'active',
]);

        $sale = Sale::create([
            'pharmacy_id' => $context['pharmacy']->id,
            'pharmacy_branch_id' => $branch->id,
            'cashier_user_id' => $context['owner']->id,
            'sale_number' => "REPORT-SALE-{$suffix}",
            'receipt_number' => "REPORT-RCT-{$suffix}",
            'channel' => 'pos',
            'sold_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
            'status' => $status,
            'payment_status' =>
                $status === 'completed'
                    ? 'paid'
                    : 'refunded',

            'currency' => 'BIF',
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'grand_total' => $total,
            'paid_amount' =>
                $status === 'completed'
                    ? $total
                    : 0,

            'change_amount' =>
                $status === 'completed'
                    ? max(
                        $paymentAmount - $total,
                        0,
                    )
                    : 0,
        ]);

      SaleItem::create([
    'sale_id' => $sale->id,
    'pharmacy_medicine_id' => $listing->id,
    'medicine_name' => $medicine->brand_name,
    'sku' => $listing->sku,
    'quantity' => $quantity,
    'unit_price' => $quantity > 0
        ? $subtotal / $quantity
        : 0,
    'discount_amount' => $discount,
    'tax_rate' => 0,
    'tax_amount' => $tax,
    'line_total' => $total,
    'cost_total' => $cost,
]);

        if (
            $status === 'completed'
            && $paymentMethod !== null
        ) {
            SalePayment::create([
                'pharmacy_id' =>
                    $context['pharmacy']->id,

                'sale_id' =>
                    $sale->id,

                'received_by_user_id' =>
                    $context['owner']->id,

                'payment_number' =>
                    "REPORT-PAY-{$suffix}",

                'paid_at' =>
                    now()->subDay(),

                'amount' =>
                    $paymentAmount,

                'payment_method' =>
                    $paymentMethod,

                'status' =>
                    'completed',
            ]);
        }

        return $sale;
    }
}