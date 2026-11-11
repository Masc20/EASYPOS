<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Sec10SalesAuditReportRouteTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $owner;
    protected User $manager1;
    protected User $cashier1;
    protected User $cashier2;
    protected Order $orderBranch1;
    protected Order $orderBranch2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Branch Metro Manila',
            'is_active' => true,
        ]);

        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], [
            'name' => 'Branch Cebu Central',
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

        $this->manager1 = User::create([
            'name' => 'Branch 1 Manager',
            'email' => 'b1_mgr@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-S10',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->manager1->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashier1 = User::create([
            'name' => 'Cashier Metro 1',
            'email' => 'b1_csh@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-S10',
            'pin_code' => Hash::make('1111'),
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier1->assignRole(StaffRole::CASHIER->value);

        $this->cashier2 = User::create([
            'name' => 'Cashier Cebu 2',
            'email' => 'b2_csh@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CSH-S10',
            'pin_code' => Hash::make('2222'),
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->cashier2->assignRole(StaffRole::CASHIER->value);

        $this->orderBranch1 = Order::create([
            'order_number' => 'B01-20261110-S10',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'order_type' => 'dine_in',
            'subtotal' => 1500.00,
            'total_amount' => 1500.00,
            'created_at' => now(),
        ]);

        $this->orderBranch2 = Order::create([
            'order_number' => 'B02-20261110-S10',
            'branch_id' => $this->branch2->id,
            'cashier_id' => $this->cashier2->id,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'order_type' => 'dine_in',
            'subtotal' => 8800.00,
            'total_amount' => 8800.00,
            'created_at' => now(),
        ]);
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/sales-records/audit-report');
        $response->assertRedirect('/login');
    }

    public function test_user_without_reports_view_permission_is_forbidden(): void
    {
        $this->actingAs($this->cashier1);

        $response = $this->get('/admin/sales-records/audit-report');
        $response->assertStatus(403);
    }

    public function test_floor_pin_session_is_forbidden_from_audit_reports(): void
    {
        $this->actingAs($this->manager1);

        session([SystemConstants::SESSION_AUTH_METHOD => 'pin']);

        $response = $this->get('/admin/sales-records/audit-report');
        $response->assertStatus(403);
    }

    public function test_branch_manager_is_strictly_scoped_to_assigned_branch_despite_tampered_branch_id(): void
    {
        $this->actingAs($this->manager1);

        // Branch 1 Manager attempts to access report forging ?branchId=2
        $response = $this->get('/admin/sales-records/audit-report?branchId=' . $this->branch2->id);

        $response->assertStatus(200);

        // Verify view data: branch is forced to Branch 1
        $viewBranch = $response->viewData('branch');
        $this->assertNotNull($viewBranch);
        $this->assertEquals($this->branch1->id, $viewBranch->id);

        // Verify report totals: includes Branch 1 (1500.00), does NOT leak Branch 2 (8800.00)
        $reportData = $response->viewData('report');
        $this->assertEquals(1500.00, (float) $reportData['totals']['gross_sales']);
        $this->assertEquals(1, $reportData['totals']['orders_count']);
    }

    public function test_cross_branch_cashier_filter_is_neutralized_for_branch_manager(): void
    {
        $this->actingAs($this->manager1);

        // Branch 1 Manager attempts to filter by Cashier 2 (who belongs to Branch 2)
        $response = $this->get("/admin/sales-records/audit-report?cashierId={$this->cashier2->id}");

        $response->assertStatus(200);

        // Cashier filter must be neutralized to null because Cashier 2 is not in Branch 1
        $this->assertNull($response->viewData('cashier'));
    }

    public function test_owner_can_access_any_branch_audit_report(): void
    {
        $this->actingAs($this->owner);

        // Query Branch 2 specifically
        $responseB2 = $this->get('/admin/sales-records/audit-report?branchId=' . $this->branch2->id);
        $responseB2->assertStatus(200);
        $this->assertEquals($this->branch2->id, $responseB2->viewData('branch')->id);
        $reportB2 = $responseB2->viewData('report');
        $this->assertEquals(8800.00, (float) $reportB2['totals']['gross_sales']);

        // Query all branches (null branchId)
        $responseAll = $this->get('/admin/sales-records/audit-report');
        $responseAll->assertStatus(200);
        $this->assertNull($responseAll->viewData('branch'));
        $reportAll = $responseAll->viewData('report');
        // Total of Branch 1 (1500) + Branch 2 (8800) = 10300.00
        $this->assertEquals(10300.00, (float) $reportAll['totals']['gross_sales']);
        $this->assertEquals(2, $reportAll['totals']['orders_count']);
    }
}
