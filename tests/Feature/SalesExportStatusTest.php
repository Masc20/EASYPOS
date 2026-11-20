<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Models\Order;
use App\Filament\Pages\SalesRecords;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SalesExportStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(4, 0));
        $cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        foreach ([
            ['PAID', 'in_kitchen', 'paid', 100],
            ['COMPLETED', 'completed', 'unpaid', 200],
            ['BOTH', 'completed', 'paid', 300],
            ['PENDING', 'pending', 'unpaid', 400],
            ['PENDING-PAID', 'pending', 'paid', 500],
            ['CANCELLED-PAID', 'cancelled', 'paid', 600],
        ] as [$number, $status, $payment, $total]) {
            Order::create([
                'order_number' => $number, 'status' => $status, 'payment_status' => $payment,
                'branch_id' => 1, 'cashier_id' => $cashier->id, 'order_type' => 'takeout',
                'subtotal' => $total, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => $total,
            ]);
        }
        $this->actingAs(User::where('email', 'admin@easypos.com')->firstOrFail());
    }

    public static function selections(): array
    {
        $cases = [];
        foreach (['csv', 'excel', 'periodic-csv', 'periodic-excel'] as $format) {
            foreach (['paid_or_completed' => [3, 600], 'paid' => [2, 400], 'completed' => [2, 500]] as $status => [$count, $total]) {
                $cases[$format . '-' . $status] = [$format, $status, $count, $total];
            }
        }
        return $cases;
    }

    #[DataProvider('selections')]
    public function test_downloads_apply_selected_status_and_exclude_pending_and_cancelled(string $format, string $status, int $count, int $total): void
    {
        $periodic = str_starts_with($format, 'periodic');
        $excel = str_ends_with($format, 'excel');
        $suffix = $status === 'paid_or_completed' ? '' : '-' . $status;
        $filename = ($periodic ? 'periodic-sales-day' : 'sales-records') . '-2026-11-20-to-2026-11-20' . $suffix . ($excel ? '.xlsx' : '.csv');
        $response = $this->get('/admin/sales-records/export-' . $format . '?startDate=2026-11-20&endDate=2026-11-20&status=' . $status)
            ->assertOk()->assertDownload($filename);
        $rows = $this->readRows($response->streamedContent(), $excel);
        if ($periodic) {
            $this->assertCount(3, $rows);
            $this->assertSame('TOTAL / SUMMARY', $rows[2][0]);
            $this->assertEquals($count, $rows[2][1]);
            $this->assertEquals($total, $rows[2][4]);
            if ($excel) {
                $this->assertIsInt($rows[2][1]);
                $this->assertTrue(is_int($rows[2][4]) || is_float($rows[2][4]));
            }
        } else {
            $this->assertCount($count + 1, $rows);
            $this->assertSame('Payment Status', $rows[0][11]);
            $numbers = array_column(array_slice($rows, 1), 0);
            $this->assertContains('BOTH', $numbers);
            foreach (['PENDING', 'PENDING-PAID', 'CANCELLED-PAID'] as $number) {
                $this->assertNotContains($number, $numbers);
            }
            $this->assertEquals($total, array_sum(array_column(array_slice($rows, 1), 8)));
        }
    }

    public function test_ui_download_links_carry_the_selected_status_and_periodic_interval(): void
    {
        $component = Livewire::test(SalesRecords::class)->set('exportStatus', 'completed')
            ->call('setPeriodicInterval', 'month');
        $html = new \DOMDocument();
        $html->loadHTML($component->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = (new \DOMXPath($html))->query('//a[contains(@href, "/sales-records/export-")]');
        $this->assertCount(4, $links);
        foreach ($links as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $filters);
            $this->assertSame('completed', $filters['status']);
            if (str_contains($link->getAttribute('href'), 'periodic')) {
                $this->assertSame('month', $filters['interval']);
            }
            foreach ($link->attributes as $attribute) {
                $this->assertStringStartsNotWith('wire:navigate', $attribute->name);
            }
        }
        $component->call('resetFilters')->assertSet('exportStatus', 'paid_or_completed');
    }

    public function test_invalid_and_disallowed_status_values_are_rejected_before_download(): void
    {
        foreach (['pending', 'cancelled', 'anything'] as $status) {
            $this->getJson('/admin/sales-records/export-csv?status=' . $status)->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->getJson('/admin/sales-records/export-periodic-csv?interval=invalid')->assertUnprocessable()->assertJsonValidationErrors('interval');
        $this->assertDatabaseCount('orders', 6);
    }

    public function test_periodic_downloads_enforce_permissions_and_branch_scope(): void
    {
        $branch = \App\Domains\Branches\Models\Branch::create(['name' => 'Other export branch', 'code' => 'EXP', 'is_active' => true]);
        $cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        Order::create([
            'order_number' => 'OTHER-BRANCH', 'status' => 'completed', 'payment_status' => 'paid',
            'branch_id' => $branch->id, 'cashier_id' => $cashier->id, 'order_type' => 'takeout',
            'subtotal' => 9999, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 9999,
        ]);
        $manager = User::where('emp_id', 'B01-MGR-001')->firstOrFail();
        $response = $this->actingAs($manager)->get('/admin/sales-records/export-periodic-csv?branchId=' . $branch->id)->assertOk();
        $rows = $this->readRows($response->streamedContent(), false);
        $this->assertEquals(600, $rows[count($rows) - 1][4]);
        foreach (['periodic-csv', 'periodic-excel'] as $format) {
            $this->actingAs($cashier)->get('/admin/sales-records/export-' . $format)->assertForbidden();
        }
        $this->actingAs($manager)->withSession(['auth_method' => 'pin'])
            ->get('/admin/sales-records/export-periodic-csv')->assertForbidden();
        $this->assertDatabaseCount('orders', 7);
    }

    public function test_periodic_empty_excel_has_headers_and_zero_summary(): void
    {
        $response = $this->get('/admin/sales-records/export-periodic-excel?startDate=2026-11-21&endDate=2026-11-21&interval=year')
            ->assertOk()->assertDownload('periodic-sales-year-2026-11-21-to-2026-11-21.xlsx');
        $rows = $this->readRows($response->streamedContent(), true);
        $this->assertCount(2, $rows);
        $this->assertSame('Period (Year)', $rows[0][0]);
        $this->assertSame('TOTAL / SUMMARY', $rows[1][0]);
        $this->assertSame(0, $rows[1][1]);
        $this->assertEquals(0, $rows[1][4]);
    }

    private function readRows(string $content, bool $excel): array
    {
        if (! $excel) {
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, substr($content, 3));
            rewind($stream);
            $rows = [];
            while (($row = fgetcsv($stream)) !== false) {
                $rows[] = $row;
            }
            fclose($stream);
            return $rows;
        }
        $path = tempnam(sys_get_temp_dir(), 'sales-export-');
        $reader = new \OpenSpout\Reader\XLSX\Reader();
        try {
            file_put_contents($path, $content);
            $reader->open($path);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }
            return $rows;
        } finally {
            $reader->close();
            unlink($path);
        }
    }
}
