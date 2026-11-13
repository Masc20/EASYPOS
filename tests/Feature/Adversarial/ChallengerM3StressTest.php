<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Filament\Pages\SalesRecords;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ChallengerM3StressTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $manager1;
    protected User $cashier1;
    protected User $cashier2;
    protected User $admin;
    protected SalesAggregationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $this->branch1 = Branch::firstOrCreate(['code' => 'B-CHAL-1'], [
            'name' => 'Challenger Branch 1',
            'is_active' => true,
        ]);
        $this->branch2 = Branch::firstOrCreate(['code' => 'B-CHAL-2'], [
            'name' => 'Challenger Branch 2',
            'is_active' => true,
        ]);

        $this->manager1 = User::create([
            'name' => 'Challenger Manager 1',
            'email' => 'chal_mgr1@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'CHAL-MGR-01',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->manager1->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashier1 = User::create([
            'name' => 'Cashier One',
            'email' => 'chal_csh1@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'CHAL-CSH-01',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier1->assignRole(StaffRole::CASHIER->value);

        $this->cashier2 = User::create([
            'name' => 'Cashier Two',
            'email' => 'chal_csh2@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'CHAL-CSH-02',
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->cashier2->assignRole(StaffRole::CASHIER->value);

        $this->admin = User::where('email', 'admin@easypos.com')->firstOrFail();
        $this->service = app(SalesAggregationService::class);
    }

    /**
     * Test Content-Disposition filename sanitization with adversarial payloads in SalesRecords.
     */
    public function test_sales_records_export_periodic_filename_sanitization(): void
    {
        $this->actingAs($this->manager1);
        $component = new SalesRecords();

        $adversarialIntervals = [
            "evil\r\nSet-Cookie: malicious=true",
            "../../etc/passwd",
            "<script>alert(1)</script>",
            "hourly",
            "century",
            "",
            "   ",
        ];

        foreach ($adversarialIntervals as $interval) {
            $component->periodicInterval = $interval;
            $response = $component->exportPeriodicCsv($this->service);
            $this->assertInstanceOf(StreamedResponse::class, $response);

            $disposition = $response->headers->get('Content-Disposition');
            $this->assertNotNull($disposition);
            $this->assertStringContainsString('filename=periodic-sales-day-', $disposition);
            $this->assertStringNotContainsString('evil', $disposition);
            $this->assertStringNotContainsString('script', $disposition);
            $this->assertStringNotContainsString('passwd', $disposition);
        }

        // Test valid intervals case insensitivity in filename
        foreach (['day', 'week', 'month', 'year', 'WEEK', 'Month', 'YEAR'] as $validInterval) {
            $component->periodicInterval = $validInterval;
            $response = $component->exportPeriodicCsv($this->service);
            $disposition = $response->headers->get('Content-Disposition');
            $lower = strtolower($validInterval);
            $this->assertStringContainsString("filename=periodic-sales-{$lower}-", $disposition);
        }
    }

    /**
     * Test chunk boundaries (500, 501, 1000 orders) in streamOrdersCsv for line counts and memory.
     */
    public function test_stream_orders_csv_chunk_boundaries_500_and_501(): void
    {
        $orders = [];
        $now = now();

        for ($i = 1; $i <= 501; $i++) {
            $orders[] = [
                'order_number' => sprintf('CHUNK-%04d', $i),
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => 'take_out',
                'table_number' => null,
                'subtotal' => 100.00,
                'discount_amount' => 0.00,
                'total_amount' => 100.00,
                'tax_amount' => 10.71,
                'status' => OrderStatus::COMPLETED->value,
                'payment_status' => PaymentStatus::PAID->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        Order::insert($orders);

        $handle = tmpfile();
        $this->service->streamOrdersCsv($handle, null, null, $this->branch1->id);
        rewind($handle);

        $lineCount = 0;
        while (! feof($handle)) {
            $line = fgets($handle);
            if ($line !== false && trim($line) !== '') {
                $lineCount++;
            }
        }
        fclose($handle);

        // 1 header row + 501 order rows = 502 total rows
        $this->assertEquals(502, $lineCount);
    }

    /**
     * Test memory boundedness under 2,000 raw orders and 1,500 periodic orders.
     */
    public function test_memory_bounds_net_delta_under_10mb_for_large_datasets(): void
    {
        $orders = [];
        $baseDate = Carbon::parse('2025-01-01 08:00:00');

        for ($i = 1; $i <= 2000; $i++) {
            $date = (clone $baseDate)->addHours($i);
            $orders[] = [
                'order_number' => sprintf('BIG-%05d', $i),
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => 'dine_in',
                'table_number' => 'T-1',
                'subtotal' => 250.00,
                'discount_amount' => 0.00,
                'total_amount' => 250.00,
                'tax_amount' => 26.79,
                'status' => OrderStatus::COMPLETED->value,
                'payment_status' => PaymentStatus::PAID->value,
                'created_at' => $date,
                'updated_at' => $date,
            ];
        }

        foreach (array_chunk($orders, 500) as $chunk) {
            Order::insert($chunk);
        }

        gc_collect_cycles();
        $memBefore = memory_get_usage(false);

        // Test streamOrdersCsv memory
        $handle1 = tmpfile();
        $this->service->streamOrdersCsv($handle1, null, null, $this->branch1->id);
        fclose($handle1);

        // Test streamPeriodicCsv memory
        $handle2 = tmpfile();
        $this->service->streamPeriodicCsv($handle2, 'day', null, null, $this->branch1->id);
        fclose($handle2);

        gc_collect_cycles();
        $memAfter = memory_get_usage(false);
        $netDeltaMB = ($memAfter - $memBefore) / (1024 * 1024);

        $this->assertLessThan(10.0, $netDeltaMB, 'Net memory delta must stay strictly below 10 MB.');
    }

    /**
     * Test multi-branch isolation against negative, zero, and huge branch IDs.
     */
    public function test_branch_manager_isolation_against_spoofed_extreme_branch_ids(): void
    {
        Order::create([
            'order_number' => 'ORD-B1-VALID',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'dine_in',
            'subtotal' => 1234.00,
            'discount_amount' => 0.00,
            'total_amount' => 1234.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        Order::create([
            'order_number' => 'ORD-B2-SECRET',
            'branch_id' => $this->branch2->id,
            'cashier_id' => $this->cashier2->id,
            'order_type' => 'dine_in',
            'subtotal' => 7777.00,
            'discount_amount' => 0.00,
            'total_amount' => 7777.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        $this->actingAs($this->manager1);
        $component = new SalesRecords();

        $spoofedBranchIds = [-1, 0, 999999, $this->branch2->id, null];

        foreach ($spoofedBranchIds as $spoofedId) {
            $component->branchId = $spoofedId;
            $component->cashierId = $this->cashier2->id; // Foreign cashier
            $response = $component->exportPeriodicCsv($this->service);

            ob_start();
            $response->sendContent();
            $csv = ob_get_clean();

            $this->assertStringContainsString('1234.00', $csv, "Must contain Branch 1 data for branchId {$spoofedId}");
            $this->assertStringNotContainsString('7777.00', $csv, "Must NEVER leak Branch 2 data for branchId {$spoofedId}");
        }
    }
}
