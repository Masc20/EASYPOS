<?php

namespace Tests\Feature\Security\Audit2026;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Livewire\Checkout\ReceiptPreview;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Models\Payment;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SalesRecords;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SalesAggregationAndCsvSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch1;

    private Branch $branch2;

    private User $owner;

    private User $cashier1;

    private User $cashier2;

    private User $cook1;

    private User $nullBranchReportsUser;

    private User $nullBranchPosUser;

    private Meal $meal1;

    private Meal $meal2;

    private Order $orderBranch1;

    private Order $orderBranch2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Audit Sales Branch 1',
            'is_active' => true,
        ]);

        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], [
            'name' => 'Audit Sales Branch 2',
            'is_active' => true,
        ]);

        $category = MealCategory::firstOrCreate(['slug' => 'audit-sales-cat'], [
            'name' => 'Audit Sales Category',
            'is_active' => true,
        ]);

        $this->owner = User::firstOrCreate(['email' => 'owner@easypos.com'], [
            'name' => 'System Owner',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-ADM-001',
            'is_active' => true,
        ]);
        if (! $this->owner->hasRole(StaffRole::OWNER->value)) {
            $this->owner->assignRole(StaffRole::OWNER->value);
        }

        $this->cashier1 = User::create([
            'name' => 'Cashier One',
            'email' => 'sales_c1@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-S20',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier1->assignRole(StaffRole::CASHIER->value);

        $this->cashier2 = User::create([
            'name' => 'Cashier Two',
            'email' => 'sales_c2@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CSH-S20',
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->cashier2->assignRole(StaffRole::CASHIER->value);

        $this->cook1 = User::create([
            'name' => 'Cook One',
            'email' => 'sales_cook1@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-COK-S20',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cook1->assignRole(StaffRole::COOK->value);

        $this->nullBranchReportsUser = User::create([
            'name' => 'Unassigned Branch Manager',
            'email' => 'null_branch_mgr@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B00-MGR-NULL2',
            'branch_id' => null,
            'is_active' => true,
        ]);
        $this->nullBranchReportsUser->assignRole(StaffRole::BRANCH_MANAGER->value);

        $posOnlyRole = Role::firstOrCreate(['name' => 'pos-temp-null', 'guard_name' => 'web']);
        $posOnlyRole->syncPermissions(['pos.access']);
        $this->nullBranchPosUser = User::create([
            'name' => 'Unassigned POS Cashier',
            'email' => 'null_branch_pos@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B00-CSH-NULL2',
            'branch_id' => null,
            'is_active' => true,
        ]);
        $this->nullBranchPosUser->assignRole($posOnlyRole);

        $this->meal1 = Meal::create([
            'name' => 'Sales Adobo',
            'menu_code' => 'B01-SLS-01',
            'category_id' => $category->id,
            'branch_id' => $this->branch1->id,
            'price' => 300.00,
            'is_available' => true,
        ]);

        $this->meal2 = Meal::create([
            'name' => 'Sales Kare-Kare',
            'menu_code' => 'B02-SLS-01',
            'category_id' => $category->id,
            'branch_id' => $this->branch2->id,
            'price' => 500.00,
            'is_available' => true,
        ]);

        $this->orderBranch1 = $this->seedPaidOrder($this->branch1, $this->cashier1, $this->meal1, 'B01-20261118-0001');
        $this->orderBranch2 = $this->seedPaidOrder($this->branch2, $this->cashier2, $this->meal2, 'B02-20261118-0001');
    }

    private function seedPaidOrder(Branch $branch, User $cashier, Meal $meal, string $orderNumber, ?string $tableNumber = 'T-1'): Order
    {
        $order = Order::create([
            'order_number' => $orderNumber,
            'branch_id' => $branch->id,
            'cashier_id' => $cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'table_number' => $tableNumber,
            'subtotal' => $meal->price,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $meal->price,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'completed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'meal_id' => $meal->id,
            'meal_name' => $meal->name,
            'unit_price' => $meal->price,
            'quantity' => 1,
            'subtotal' => $meal->price,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'cashier_id' => $cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount' => $meal->price,
            'amount_tendered' => $meal->price,
            'change_given' => 0,
            'status' => 'successful',
        ]);

        return $order;
    }

    public function test_vuln02_null_branch_scoped_user_receives_empty_sales_data_across_all_aggregation_methods(): void
    {
        $this->assertSame(-1, $this->nullBranchReportsUser->effectiveBranchId());
        $this->actingAs($this->nullBranchReportsUser);

        $service = app(SalesAggregationService::class);
        $service->clearRuntimeCache();

        $summary = $service->getSummary(null, null, null, null, false, true);
        $this->assertSame(0, $summary['orders_count'], 'Null-branch user must see 0 orders in getSummary.');
        $this->assertSame(0.0, (float) $summary['gross_sales']);
        $this->assertSame(0.0, (float) $summary['net_sales']);

        $bestSellers = $service->getBestSellingRankings(null, null, null, null, null, 10, 'quantity', false);
        $this->assertSame([], $bestSellers, 'Null-branch user must see empty best-selling rankings.');

        $categories = $service->getCategoryRankings(null, null, null, null, null, false);
        $this->assertSame([], $categories, 'Null-branch user must see empty category rankings.');

        $dailyTrend = $service->getDailyRevenueTrend();
        $this->assertSame(0.0, array_sum($dailyTrend['values']), 'Null-branch user must see 0 revenue in daily trend.');

        $periodic = $service->getPeriodicReport('day');
        $this->assertSame(0, $periodic['totals']['orders_count']);
        $this->assertSame(0.0, (float) $periodic['totals']['gross_sales']);
        $this->assertSame([], $periodic['items']);

        $hourly = $service->getHourlySalesDistribution(null, null, null, null, false);
        $this->assertSame(0, $hourly['total_orders']);
        $this->assertSame(0.0, (float) $hourly['total_revenue']);

        $paymentDist = $service->getPaymentMethodDistribution();
        $this->assertSame(0.0, (float) array_sum($paymentDist['values']));

        $ordersCsv = $service->exportCsv();
        $this->assertStringNotContainsString($this->orderBranch1->order_number, $ordersCsv);
        $this->assertStringNotContainsString($this->orderBranch2->order_number, $ordersCsv);
        $lines = array_values(array_filter(explode("\n", trim($ordersCsv))));
        $this->assertCount(1, $lines, 'Null-branch user exportCsv must contain only the header row.');

        $periodicCsv = $service->exportPeriodicCsv('day');
        $periodicLines = array_values(array_filter(explode("\n", trim($periodicCsv))));
        $this->assertCount(2, $periodicLines, 'Null-branch user exportPeriodicCsv must contain only header + zeroed TOTAL row.');
        $this->assertStringContainsString('"TOTAL / SUMMARY",0,0.00', $periodicCsv);
    }

    public function test_vuln02_null_branch_scoped_user_cannot_bypass_via_audit_report_or_export_csv_routes(): void
    {
        $this->actingAs($this->nullBranchReportsUser);

        $auditResponse = $this->get('/admin/sales-records/audit-report?branchId='.$this->branch1->id);
        $this->assertTrue(in_array($auditResponse->getStatusCode(), [200, 403], true));
        if ($auditResponse->getStatusCode() === 200) {
            $report = $auditResponse->viewData('report');
            $this->assertSame(0, $report['totals']['orders_count']);
            $this->assertSame(0.0, (float) $report['totals']['gross_sales']);
        }

        $csvResponse = $this->get('/admin/sales-records/export-csv?branchId='.$this->branch1->id);
        $this->assertTrue(in_array($csvResponse->getStatusCode(), [200, 403], true));
        if ($csvResponse->getStatusCode() === 200) {
            $csv = $csvResponse->streamedContent();
            $this->assertStringNotContainsString($this->orderBranch1->order_number, $csv);
            $this->assertStringNotContainsString($this->orderBranch2->order_number, $csv);
        }
    }

    public function test_vuln04_streams_sales_csv_neutralizes_formula_injection_payloads(): void
    {
        $evilBranch = Branch::create([
            'code' => 'B99',
            'name' => '@SUM(1+1)',
            'is_active' => true,
        ]);

        $evilCashier = User::create([
            'name' => '+1+1',
            'email' => 'evil_cashier@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B99-CSH-001',
            'branch_id' => $evilBranch->id,
            'is_active' => true,
        ]);

        $payloads = [
            '=CMD|A0',
            '+1+1',
            '-2+3',
            '@SUM(A1)',
            "\t=1",
            "\r=1",
            ' =1+1',
        ];

        foreach ($payloads as $index => $payload) {
            $this->seedPaidOrder(
                $evilBranch,
                $evilCashier,
                $this->meal1,
                ($index === 0 ? '=ORD-0001' : "B99-ORD-000{$index}"),
                $payload
            );
        }

        $this->actingAs($this->owner);
        $csv = app(SalesAggregationService::class)->exportCsv(null, null, $evilBranch->id);
        $cleanCsv = ltrim($csv, "\xEF\xBB\xBF");

        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $cleanCsv);
        rewind($fp);

        $rows = [];
        while (($row = fgetcsv($fp)) !== false) {
            $rows[] = $row;
        }
        fclose($fp);

        // Header + 7 data rows
        $this->assertCount(8, $rows);
        $dataRows = array_slice($rows, 1);

        foreach ($dataRows as $rowIdx => $row) {
            // Check Order # (0), Branch (2), Cashier (3), Table (5)
            foreach ([0, 2, 3, 5] as $colIdx) {
                $cell = (string) ($row[$colIdx] ?? '');
                $trimmed = ltrim($cell, " \t\r\n");
                $this->assertFalse(
                    str_starts_with($cell, '=')
                    || str_starts_with($cell, '+')
                    || str_starts_with($cell, '-')
                    || str_starts_with($cell, '@')
                    || str_starts_with($cell, "\t")
                    || str_starts_with($cell, "\r")
                    || str_starts_with($trimmed, '=')
                    || str_starts_with($trimmed, '+')
                    || str_starts_with($trimmed, '-')
                    || str_starts_with($trimmed, '@'),
                    "Row {$rowIdx} col {$colIdx} was not formula-neutralized: ".json_encode($cell)
                );
            }

            $this->assertStringStartsWith("'", $row[2], 'Branch @SUM(1+1) must be prefixed with single quote.');
            $this->assertStringStartsWith("'", $row[3], 'Cashier +1+1 must be prefixed with single quote.');
            $this->assertStringStartsWith("'", $row[5], 'Formula table_number must be prefixed with single quote.');
        }

        $this->assertSame("'=ORD-0001", $dataRows[0][0]);
    }

    public function test_vuln05_receipt_preview_locks_order_id_and_enforces_auth_and_branch_isolation(): void
    {
        // 1. #[Locked] rejects client tampering of $orderId
        $lockedRejected = false;
        try {
            Livewire::actingAs($this->cashier1)
                ->test(ReceiptPreview::class)
                ->set('orderId', $this->orderBranch2->id);
        } catch (CannotUpdateLockedPropertyException $e) {
            $lockedRejected = true;
        }
        $this->assertTrue($lockedRejected, 'ReceiptPreview::$orderId must have #[Locked] to reject client set() tampering.');

        // 2. Unauthenticated guest cannot load order receipt
        Auth::logout();
        $guestComponent = Livewire::test(ReceiptPreview::class);
        try {
            $guestComponent->call('loadOrder', $this->orderBranch1->id);
            $guestComponent->assertDontSee($this->orderBranch1->order_number);
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // 3. Unauthorized user without pos.access / reports.view (cook) cannot view receipt
        $cookComponent = Livewire::actingAs($this->cook1)->test(ReceiptPreview::class);
        try {
            $cookComponent->call('loadOrder', $this->orderBranch1->id);
            $cookComponent->assertDontSee($this->orderBranch1->order_number);
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // 4. Null-branch scoped user with pos.access fails closed
        $nullBranchComponent = Livewire::actingAs($this->nullBranchPosUser)->test(ReceiptPreview::class);
        try {
            $nullBranchComponent->call('loadOrder', $this->orderBranch1->id);
            $nullBranchComponent->assertDontSee($this->orderBranch1->order_number);
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // 5. Branch 1 cashier cannot view Branch 2 receipt via loadOrder
        $crossBranchComponent = Livewire::actingAs($this->cashier1)->test(ReceiptPreview::class);
        try {
            $crossBranchComponent->call('loadOrder', $this->orderBranch2->id);
            $crossBranchComponent->assertDontSee($this->orderBranch2->order_number);
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // 6. Happy path: Branch 1 cashier can view Branch 1 receipt
        Livewire::actingAs($this->cashier1)
            ->test(ReceiptPreview::class)
            ->call('loadOrder', $this->orderBranch1->id)
            ->assertSee($this->orderBranch1->order_number);
    }

    public function test_vuln13_malformed_dates_never_return_500_and_sanitize_csv_filename(): void
    {
        $this->actingAs($this->owner);

        // 1. Audit report route with malformed dates
        $auditRes = $this->get('/admin/sales-records/audit-report?startDate=not-a-date&endDate=../../evil');
        $this->assertNotSame(500, $auditRes->getStatusCode(), 'Malformed dates on audit-report must not throw 500 InvalidFormatException.');

        // 2. Export CSV route with malformed dates & path traversal
        $csvRes = $this->get('/admin/sales-records/export-csv?startDate=not-a-date&endDate=../../evil');
        $this->assertNotSame(500, $csvRes->getStatusCode(), 'Malformed dates on export-csv must not throw 500 InvalidFormatException.');
        if ($csvRes->getStatusCode() === 200) {
            $disposition = (string) $csvRes->headers->get('Content-Disposition');
            $this->assertStringNotContainsString('..', $disposition);
            $this->assertStringNotContainsString('/', $disposition);
            $this->assertStringNotContainsString('\\', $disposition);
        }

        // 3. Dashboard Livewire page with malformed dates
        Livewire::actingAs($this->owner)
            ->test(Dashboard::class)
            ->set('startDate', 'not-a-date')
            ->set('endDate', '../../evil')
            ->assertOk();

        // 4. SalesRecords Livewire page with malformed dates
        Livewire::actingAs($this->owner)
            ->test(SalesRecords::class)
            ->set('startDate', 'not-a-date')
            ->set('endDate', '../../evil')
            ->assertOk();
    }

    public function test_vuln13_create_order_action_enforces_schema_string_and_options_bounds(): void
    {
        $this->actingAs($this->cashier1);
        $action = new CreateOrderAction;

        // Valid boundary: 20-char table_number and 100-char customer_name succeed
        $validOrder = $action->execute([
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => OrderType::DINE_IN->value,
            'table_number' => str_repeat('T', 20),
            'customer_name' => str_repeat('C', 100),
            'items' => [['meal_id' => $this->meal1->id, 'quantity' => 1]],
        ]);
        $this->assertSame(str_repeat('T', 20), $validOrder->table_number);
        $this->assertSame(str_repeat('C', 100), $validOrder->customer_name);

        // Reject 21-char table_number
        $tableRejected = false;
        try {
            $action->execute([
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => OrderType::DINE_IN->value,
                'table_number' => str_repeat('T', 21),
                'items' => [['meal_id' => $this->meal1->id, 'quantity' => 1]],
            ]);
        } catch (InvalidArgumentException $e) {
            $tableRejected = true;
        }
        $this->assertTrue($tableRejected, 'table_number of 21 chars must be rejected to match VARCHAR(20).');

        // Reject 101-char customer_name
        $customerRejected = false;
        try {
            $action->execute([
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => OrderType::DINE_IN->value,
                'customer_name' => str_repeat('C', 101),
                'items' => [['meal_id' => $this->meal1->id, 'quantity' => 1]],
            ]);
        } catch (InvalidArgumentException $e) {
            $customerRejected = true;
        }
        $this->assertTrue($customerRejected, 'customer_name of 101 chars must be rejected to match VARCHAR(100).');

        // Reject oversized selected_options
        $optionsRejected = false;
        try {
            $action->execute([
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [[
                    'meal_id' => $this->meal1->id,
                    'quantity' => 1,
                    'selected_options' => ['notes' => str_repeat('X', 5000)],
                ]],
            ]);
        } catch (InvalidArgumentException $e) {
            $optionsRejected = true;
        }
        $this->assertTrue($optionsRejected, 'Oversized selected_options payload must be rejected.');
    }
}
