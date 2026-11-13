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

class AdversarialPeriodicCsvAndStreamingStressTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchA;
    protected Branch $branchB;
    protected User $managerA;
    protected User $cashierA;
    protected User $cashierB;
    protected SalesAggregationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $this->branchA = Branch::firstOrCreate(['code' => 'B-ADV-A'], [
            'name' => 'Branch España ₱ (Alpha)',
            'is_active' => true,
        ]);
        $this->branchB = Branch::firstOrCreate(['code' => 'B-ADV-B'], [
            'name' => 'Branch Quiapo ₱ (Beta)',
            'is_active' => true,
        ]);

        $this->managerA = User::create([
            'name' => 'Manager Alpha, CPA',
            'email' => 'mgr_a_periodic@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-P01',
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);
        $this->managerA->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashierA = User::create([
            'name' => 'José "Pepe" Peña-ñ',
            'email' => 'cashier_a_periodic@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-P01',
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);
        $this->cashierA->assignRole(StaffRole::CASHIER->value);

        $this->cashierB = User::create([
            'name' => 'Cashier Quiapo 李四',
            'email' => 'cashier_b_periodic@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CSH-P02',
            'branch_id' => $this->branchB->id,
            'is_active' => true,
        ]);
        $this->cashierB->assignRole(StaffRole::CASHIER->value);

        $this->service = app(SalesAggregationService::class);
    }

    /**
     * Constant memory streaming of periodic reports over 1,500 orders across 120+ days.
     */
    public function test_periodic_csv_streaming_constant_memory_bounds_over_multi_year_dataset(): void
    {
        $batchCount = 1500;
        $orders = [];
        $baseDate = Carbon::parse('2024-01-01 10:00:00');

        for ($i = 1; $i <= $batchCount; $i++) {
            $orderDate = (clone $baseDate)->addDays($i % 120);
            $orders[] = [
                'order_number' => sprintf('PER-STR-%05d', $i),
                'branch_id' => $this->branchA->id,
                'cashier_id' => $this->cashierA->id,
                'order_type' => 'dine_in',
                'table_number' => 'T-' . ($i % 10 + 1),
                'subtotal' => 200.00,
                'discount_amount' => $i % 4 === 0 ? 20.00 : 0.00,
                'total_amount' => $i % 4 === 0 ? 180.00 : 200.00,
                'tax_amount' => 0.00,
                'status' => OrderStatus::COMPLETED->value,
                'payment_status' => PaymentStatus::PAID->value,
                'created_at' => $orderDate,
                'updated_at' => $orderDate,
            ];
        }

        foreach (array_chunk($orders, 500) as $chunk) {
            Order::insert($chunk);
        }

        $this->assertEquals($batchCount, Order::where('branch_id', $this->branchA->id)->count());

        gc_collect_cycles();
        $memBefore = memory_get_usage(false);
        $peakBefore = memory_get_peak_usage(false);

        $outputHandle = tmpfile();
        $this->assertIsResource($outputHandle);

        $this->service->streamPeriodicCsv($outputHandle, 'day', null, null, $this->branchA->id);

        $memAfter = memory_get_usage(false);
        $peakAfter = memory_get_peak_usage(false);

        rewind($outputHandle);
        $rows = [];
        while (! feof($outputHandle)) {
            $line = fgets($outputHandle);
            if ($line !== false && trim($line) !== '') {
                $rows[] = $line;
            }
        }
        fclose($outputHandle);

        $this->assertCount(122, $rows, 'Must contain 1 header, 120 daily rows, and 1 totals row.');
        $this->assertStringContainsString('TOTAL / SUMMARY', end($rows));

        $peakDeltaMB = ($peakAfter - $peakBefore) / (1024 * 1024);
        $memDeltaMB = ($memAfter - $memBefore) / (1024 * 1024);

        $this->assertLessThan(20.0, $peakDeltaMB);
        $this->assertLessThan(10.0, $memDeltaMB);
    }

    /**
     * Stream orders CSV memory bounds under 2,000 orders.
     */
    public function test_stream_orders_csv_memory_bounds_under_2000_orders(): void
    {
        $batchCount = 2000;
        $now = now();
        $orders = [];

        for ($i = 1; $i <= $batchCount; $i++) {
            $orders[] = [
                'order_number' => sprintf('ORD-VOL-%05d', $i),
                'branch_id' => $this->branchA->id,
                'cashier_id' => $this->cashierA->id,
                'order_type' => 'dine_in',
                'table_number' => 'T-' . ($i % 15 + 1),
                'subtotal' => 150.00,
                'discount_amount' => 0.00,
                'total_amount' => 150.00,
                'tax_amount' => 0.00,
                'status' => OrderStatus::COMPLETED->value,
                'payment_status' => PaymentStatus::PAID->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($orders, 500) as $chunk) {
            Order::insert($chunk);
        }

        gc_collect_cycles();
        $memBefore = memory_get_usage(false);
        $peakBefore = memory_get_peak_usage(false);

        $handle = tmpfile();
        $this->service->streamOrdersCsv($handle, null, null, $this->branchA->id);

        $memAfter = memory_get_usage(false);
        $peakAfter = memory_get_peak_usage(false);

        rewind($handle);
        $lineCount = 0;
        while (! feof($handle)) {
            $line = fgets($handle);
            if ($line !== false && trim($line) !== '') {
                $lineCount++;
            }
        }
        fclose($handle);

        $this->assertEquals(2001, $lineCount);
        $peakDeltaMB = ($peakAfter - $peakBefore) / (1024 * 1024);
        $memDeltaMB = ($memAfter - $memBefore) / (1024 * 1024);

        $this->assertLessThan(20.0, $peakDeltaMB);
        $this->assertLessThan(10.0, $memDeltaMB);
    }

    /**
     * Multi-branch isolation in SalesRecords exportPeriodicCsv Livewire action.
     */
    public function test_sales_records_export_periodic_csv_strictly_enforces_branch_manager_isolation(): void
    {
        Order::create([
            'order_number' => 'ORD-PER-A-001',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'subtotal' => 5000.00,
            'discount_amount' => 0.00,
            'total_amount' => 5000.00,
            'tax_amount' => 535.71,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        Order::create([
            'order_number' => 'ORD-PER-B-SECRET',
            'branch_id' => $this->branchB->id,
            'cashier_id' => $this->cashierB->id,
            'order_type' => 'dine_in',
            'subtotal' => 88888.00,
            'discount_amount' => 0.00,
            'total_amount' => 88888.00,
            'tax_amount' => 9523.71,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        $this->actingAs($this->managerA);

        $component = new SalesRecords();
        $component->periodicInterval = 'day';
        $component->branchId = $this->branchB->id;

        $response = $component->exportPeriodicCsv($this->service);
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertEquals('no', $response->headers->get('X-Accel-Buffering'));

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('5000.00', $csvContent);
        $this->assertStringNotContainsString('88888.00', $csvContent);

        // Tamper 2: setting branchId = null to bypass scoping
        $component->branchId = null;
        $responseNull = $component->exportPeriodicCsv($this->service);
        ob_start();
        $responseNull->sendContent();
        $csvNull = ob_get_clean();
        $this->assertStringContainsString('5000.00', $csvNull);
        $this->assertStringNotContainsString('88888.00', $csvNull);
    }

    /**
     * Foreign cashier ID spoofing in SalesRecords exportPeriodicCsv is safely neutralized.
     */
    public function test_sales_records_export_periodic_csv_neutralizes_foreign_cashier_spoofing(): void
    {
        Order::create([
            'order_number' => 'ORD-PER-CSH-001',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'subtotal' => 1200.00,
            'discount_amount' => 0.00,
            'total_amount' => 1200.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        $this->actingAs($this->managerA);

        $component = new SalesRecords();
        $component->periodicInterval = 'day';
        $component->branchId = $this->branchA->id;
        $component->cashierId = $this->cashierB->id;

        $response = $component->exportPeriodicCsv($this->service);
        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('1200.00', $csvContent);
    }

    /**
     * Periodic intervals, edge intervals, and injection fallbacks.
     */
    public function test_periodic_csv_streaming_interval_tamper_and_fallback(): void
    {
        Order::create([
            'order_number' => 'ORD-INTERVAL-01',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'subtotal' => 600.00,
            'discount_amount' => 0.00,
            'total_amount' => 600.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => Carbon::parse('2026-03-15 14:00:00'),
        ]);

        foreach (["invalid'; DROP TABLE orders;--", 'hourly', 'century', ''] as $interval) {
            $handle = tmpfile();
            $this->service->streamPeriodicCsv($handle, $interval, '2026-03-01', '2026-03-31', $this->branchA->id);
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            $this->assertStringContainsString('Period (Day)', $content);
            $this->assertStringContainsString('600.00', $content);
        }

        foreach (['week' => 'Period (Week)', 'month' => 'Period (Month)', 'year' => 'Period (Year)'] as $intKey => $expectedHeader) {
            $handle = tmpfile();
            $this->service->streamPeriodicCsv($handle, $intKey, '2026-01-01', '2026-12-31', $this->branchA->id);
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            $this->assertStringContainsString($expectedHeader, $content);
            $this->assertStringContainsString('600.00', $content);
        }
    }

    /**
     * Edge cases: zero matching orders and inverted date ranges.
     */
    public function test_periodic_csv_streaming_zero_orders_and_inverted_dates_robustness(): void
    {
        $handle = tmpfile();
        $this->service->streamPeriodicCsv($handle, 'day', '2099-01-01', '2099-01-31', $this->branchA->id);
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $lines = explode("\n", trim(substr($content, 3)));
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('TOTAL / SUMMARY', $lines[1]);
        $this->assertStringContainsString('0.00', $lines[1]);

        $handleInverted = tmpfile();
        $this->service->streamPeriodicCsv($handleInverted, 'day', '2026-12-31', '2026-01-01', $this->branchA->id);
        rewind($handleInverted);
        $invertedContent = stream_get_contents($handleInverted);
        fclose($handleInverted);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $invertedContent);
        $invLines = explode("\n", trim(substr($invertedContent, 3)));
        $this->assertCount(2, $invLines);
        $this->assertStringContainsString('TOTAL / SUMMARY', $invLines[1]);
    }

    /**
     * Multibyte UTF-8 symbols and spreadsheet formula injection defense.
     */
    public function test_periodic_and_orders_csv_utf8_and_formula_injection_defense(): void
    {
        $order = Order::create([
            'order_number' => '=SUM(A1:A10)',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'table_number' => '+CMD|calc',
            'subtotal' => 990.00,
            'discount_amount' => 90.00,
            'total_amount' => 900.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => Carbon::parse('2026-05-10 11:00:00'),
        ]);

        Payment::create([
            'order_id' => $order->id,
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'payment_method' => 'cash',
            'amount' => 900.00,
            'amount_tendered' => 1000.00,
            'change_given' => 100.00,
            'status' => 'successful',
        ]);

        $handle = tmpfile();
        $this->service->streamOrdersCsv($handle, '2026-05-01', '2026-05-31', $this->branchA->id);
        rewind($handle);
        $rawOrdersCsv = stream_get_contents($handle);
        fclose($handle);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $rawOrdersCsv);
        $csvClean = substr($rawOrdersCsv, 3);
        $lines = explode("\n", trim($csvClean));
        $this->assertCount(2, $lines);

        $row = str_getcsv($lines[1]);
        $this->assertEquals('=SUM(A1:A10)', $row[0]);
        $this->assertEquals('Branch España ₱ (Alpha)', $row[2]);
        $this->assertEquals('José "Pepe" Peña-ñ', $row[3]);
        $this->assertEquals('+CMD|calc', $row[5]);
        $this->assertEquals('900.00', $row[8]);
    }

    /**
     * Mathematical precision and BIR tax breakdown verification in periodic export.
     */
    public function test_periodic_csv_mathematical_precision_and_bir_tax_breakdown(): void
    {
        Order::create([
            'order_number' => 'ORD-TAX-01',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'subtotal' => 1120.00,
            'discount_amount' => 0.00,
            'total_amount' => 1120.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => Carbon::parse('2026-06-01 10:00:00'),
        ]);

        Order::create([
            'order_number' => 'ORD-TAX-02',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'subtotal' => 1000.00,
            'discount_amount' => 200.00,
            'total_amount' => 800.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => Carbon::parse('2026-06-01 12:00:00'),
        ]);

        $handle = tmpfile();
        $this->service->streamPeriodicCsv($handle, 'day', '2026-06-01', '2026-06-01', $this->branchA->id);
        rewind($handle);
        $rawContent = stream_get_contents($handle);
        fclose($handle);

        $csvClean = substr($rawContent, 3);
        $lines = explode("\n", trim($csvClean));
        $this->assertCount(3, $lines);

        $dataRow = str_getcsv($lines[1]);
        $totalsRow = str_getcsv($lines[2]);

        $this->assertEquals('2', $dataRow[1]);
        $this->assertEquals('2120.00', $dataRow[2]);
        $this->assertEquals('200.00', $dataRow[3]);
        $this->assertEquals('1920.00', $dataRow[4]);
        $this->assertEquals('1000.00', $dataRow[5]);
        $this->assertEquals('120.00', $dataRow[6]);
        $this->assertEquals('800.00', $dataRow[7]);
        $this->assertEquals('960.00', $dataRow[8]);

        $this->assertEquals('TOTAL / SUMMARY', $totalsRow[0]);
        $this->assertEquals('2', $totalsRow[1]);
        $this->assertEquals('2120.00', $totalsRow[2]);
        $this->assertEquals('200.00', $totalsRow[3]);
        $this->assertEquals('1920.00', $totalsRow[4]);
        $this->assertEquals('1000.00', $totalsRow[5]);
        $this->assertEquals('120.00', $totalsRow[6]);
        $this->assertEquals('800.00', $totalsRow[7]);
        $this->assertEquals('960.00', $totalsRow[8]);
    }
}
