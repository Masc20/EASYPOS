<?php

namespace Tests\Feature\Security\Audit2026;

use App\Domains\Audit\Models\ActivityLog;
use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Models\Order;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Support\Enums\OrderType;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\NotificationMailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRbacAndCredentialHygieneTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch1;

    private Branch $branch2;

    private User $owner;

    private User $manager1;

    private User $cashier1;

    private User $cashier2;

    private Meal $meal1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Admin Audit Branch 1',
            'is_active' => true,
        ]);

        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], [
            'name' => 'Admin Audit Branch 2',
            'is_active' => true,
        ]);

        $category = MealCategory::firstOrCreate(['slug' => 'admin-audit-cat'], [
            'name' => 'Admin Audit Cat',
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
            'email' => 'adm_b1_mgr@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-ADM1',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->manager1->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashier1 = User::create([
            'name' => 'Branch 1 Cashier',
            'email' => 'adm_b1_csh@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-ADM1',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier1->assignRole(StaffRole::CASHIER->value);

        $this->cashier2 = User::create([
            'name' => 'Branch 2 Cashier',
            'email' => 'adm_b2_csh@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CSH-ADM2',
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->cashier2->assignRole(StaffRole::CASHIER->value);

        $this->meal1 = Meal::create([
            'name' => 'Admin Audit Meal',
            'menu_code' => 'B01-ADM-01',
            'category_id' => $category->id,
            'branch_id' => $this->branch1->id,
            'price' => 150.00,
            'is_available' => true,
        ]);
    }

    private function pullFilamentNotificationsJson(): string
    {
        app(NotificationMailbox::class)->flush();
        $notificationsComponent = new Notifications;
        $notificationsComponent->mount();
        $notificationsComponent->pullNotifications();

        return (string) json_encode($notificationsComponent->notifications->toArray(), JSON_UNESCAPED_SLASHES);
    }

    public function test_vuln07_dashboard_can_access_requires_reports_view_and_non_null_branch_for_scoped_users(): void
    {
        // Custom role with admin.access + menu.manage WITHOUT reports.view
        $menuAdminRole = Role::create(['name' => 'menu-admin-no-reports', 'guard_name' => 'web']);
        $menuAdminRole->syncPermissions(['admin.access', 'menu.manage']);

        $menuAdmin = User::create([
            'name' => 'Menu Admin',
            'email' => 'menu_admin@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MEN-001',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $menuAdmin->assignRole($menuAdminRole);

        $this->actingAs($menuAdmin);
        $this->assertFalse(
            Dashboard::canAccess(),
            'Dashboard::canAccess() must be false when user lacks reports.view permission.'
        );
        $this->get('/admin')->assertStatus(403);

        // Null-branch scoped user with reports.view must also be denied Dashboard access
        $nullBranchMgr = User::create([
            'name' => 'Null Branch Manager',
            'email' => 'null_dash_mgr@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B00-MGR-DASH',
            'branch_id' => null,
            'is_active' => true,
        ]);
        $nullBranchMgr->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->actingAs($nullBranchMgr);
        $this->assertFalse(Dashboard::canAccess(), 'Null-branch branch-scoped user must be denied Dashboard access.');
        $this->get('/admin')->assertStatus(403);

        // Authorized Branch Manager & Owner still retain Dashboard access
        $this->actingAs($this->manager1);
        $this->assertTrue(Dashboard::canAccess());
        $this->get('/admin')->assertStatus(200);

        $this->actingAs($this->owner);
        $this->assertTrue(Dashboard::canAccess());
        $this->get('/admin')->assertStatus(200);
    }

    public function test_vuln07_role_resource_protects_system_roles_writes_activity_logs_and_blocks_non_executives(): void
    {
        // Non-executive denied on /admin/roles
        $this->actingAs($this->manager1)->get('/admin/roles')->assertStatus(403);
        $this->actingAs($this->cashier1)->get('/admin/roles')->assertStatus(403);

        $ownerRole = Role::where('name', StaffRole::OWNER->value)->firstOrFail();
        $superAdminRole = Role::where('name', StaffRole::SUPER_ADMIN->value)->firstOrFail();

        // System roles must not be deleted via DeleteBulkAction
        $customRole = Role::create(['name' => 'temp-audit-role', 'guard_name' => 'web']);

        try {
            Livewire::actingAs($this->owner)
                ->test(ListRoles::class)
                ->callTableBulkAction('delete', [$ownerRole, $superAdminRole, $customRole]);
        } catch (\Throwable) {
            // Rejection / hidden / unauthorized bulk delete is acceptable as long as system roles survive
        }

        $this->assertDatabaseHas('roles', ['id' => $ownerRole->id, 'name' => StaffRole::OWNER->value]);
        $this->assertDatabaseHas('roles', ['id' => $superAdminRole->id, 'name' => StaffRole::SUPER_ADMIN->value]);

        // System Owner can edit executive roles' permissions while preserving their canonical role slug,
        // whereas non-owner Super Admin cannot edit executive roles.
        $superAdminUser = User::create([
            'name' => 'HQ Super Admin',
            'email' => 'hq_super_admin_role_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-SA-ROLE1',
            'branch_id' => null,
            'is_active' => true,
        ]);
        $superAdminUser->assignRole(StaffRole::SUPER_ADMIN->value);

        foreach ([$ownerRole, $superAdminRole] as $protectedRole) {
            $originalName = $protectedRole->name;
            $originalPermissions = $protectedRole->permissions()->pluck('name')->all();

            // Non-owner Super Admin is denied from editing executive roles
            try {
                Livewire::actingAs($superAdminUser)
                    ->test(ListRoles::class)
                    ->callAction(TestAction::make('edit')->table($protectedRole), data: [
                        'name' => 'renamed-'.$originalName,
                        'permissions_pos' => [],
                        'permissions_operations' => [],
                        'permissions_management' => [],
                    ]);
            } catch (\Throwable) {
                // Hidden, unauthorized, or exception rejection expected for non-owner
            }

            $protectedRole->refresh();
            $this->assertSame($originalName, $protectedRole->name, "System role {$originalName} must not be renameable by non-owner.");
            $this->assertCount(count($originalPermissions), $protectedRole->permissions()->get(), "System role {$originalName} permissions must not be modified by non-owner.");

            // System Owner CAN see and use EditAction on Owner and Super Admin roles to update permissions, while preserving the canonical role slug
            Livewire::actingAs($this->owner)
                ->test(ListRoles::class)
                ->assertActionVisible(TestAction::make('edit')->table($protectedRole))
                ->callAction(TestAction::make('edit')->table($protectedRole), data: [
                    'name' => 'renamed-'.$originalName,
                    'permissions_pos' => ['pos.access', 'pos.checkout'],
                    'permissions_operations' => ['menu.view'],
                    'permissions_management' => ['admin.access', 'reports.view'],
                ])
                ->assertHasNoActionErrors();

            $protectedRole->refresh();
            $this->assertSame($originalName, $protectedRole->name, "System role {$originalName} canonical slug must be preserved.");
            $this->assertTrue($protectedRole->hasPermissionTo('pos.access'));

            // Restore original permissions for subsequent assertions
            $protectedRole->syncPermissions($originalPermissions);
        }

        // Custom role lifecycle writes ActivityLog entries on create, edit, and delete
        $initialLogCount = ActivityLog::count();

        Livewire::actingAs($this->owner)
            ->test(ListRoles::class)
            ->callAction('create', data: [
                'name' => 'audit-logged-role',
                'permissions_pos' => ['pos.access'],
                'permissions_operations' => [],
                'permissions_management' => [],
            ])
            ->assertHasNoActionErrors();

        $afterCreateCount = ActivityLog::count();
        $this->assertGreaterThan($initialLogCount, $afterCreateCount, 'Role creation must record an ActivityLog entry.');

        $createdRole = Role::where('name', 'audit-logged-role')->firstOrFail();

        Livewire::actingAs($this->owner)
            ->test(ListRoles::class)
            ->callAction(TestAction::make('edit')->table($createdRole), data: [
                'name' => 'audit-logged-role-v2',
                'permissions_pos' => ['pos.access', 'pos.checkout'],
                'permissions_operations' => [],
                'permissions_management' => [],
            ])
            ->assertHasNoActionErrors();

        $afterEditCount = ActivityLog::count();
        $this->assertGreaterThan($afterCreateCount, $afterEditCount, 'Role edit must record an ActivityLog entry.');

        Livewire::actingAs($this->owner)
            ->test(ListRoles::class)
            ->callTableBulkAction('delete', [$createdRole]);

        $this->assertDatabaseMissing('roles', ['id' => $createdRole->id]);
        $this->assertGreaterThan($afterEditCount, ActivityLog::count(), 'Role deletion must record an ActivityLog entry.');
    }

    public function test_vuln08_users_table_actions_enforce_server_side_authorize_and_prevent_role_escalation(): void
    {
        $otherManager1 = User::create([
            'name' => 'Other Branch 1 Manager',
            'email' => 'adm_b1_mgr2@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-ADM2',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $otherManager1->assignRole(StaffRole::BRANCH_MANAGER->value);

        $inactiveCashier2 = User::create([
            'name' => 'Inactive Branch 2 Cashier',
            'email' => 'adm_b2_csh_inact@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CSH-INACT',
            'branch_id' => $this->branch2->id,
            'is_active' => false,
        ]);
        $inactiveCashier2->assignRole(StaffRole::CASHIER->value);

        $livewire = Livewire::actingAs($this->manager1)->test(ListUsers::class);
        $table = $livewire->instance()->getTable();

        // Row actions must enforce ->authorize() (not merely ->visible()) against cross-branch, non-floor, and self
        $deactivateAction = $table->getAction('deactivate');
        $reactivateAction = $table->getAction('reactivate');
        $editAction = $table->getAction('edit');

        $this->assertFalse($deactivateAction->record($this->cashier2)->isAuthorized(), 'deactivate must deny cross-branch target.');
        $this->assertFalse($deactivateAction->record($otherManager1)->isAuthorized(), 'deactivate must deny non-floor target.');
        $this->assertFalse($deactivateAction->record($this->manager1)->isAuthorized(), 'deactivate must deny self target.');

        $this->assertFalse($reactivateAction->record($inactiveCashier2)->isAuthorized(), 'reactivate must deny cross-branch target.');
        $this->assertFalse($editAction->record($this->cashier2)->isAuthorized(), 'edit must deny cross-branch target.');
        $this->assertFalse($editAction->record($otherManager1)->isAuthorized(), 'edit must deny non-floor target.');
        $this->assertFalse($editAction->record($this->manager1)->isAuthorized(), 'edit must deny self target.');

        // DeleteBulkAction must enforce individual record authorization (preserve Branch 2 & Owner)
        $bulkDeleteAction = collect($table->getToolbarActions())
            ->flatMap(fn ($group) => method_exists($group, 'getActions') ? $group->getActions() : [$group])
            ->first(fn ($action) => $action->getName() === 'delete');
        $this->assertNotNull($bulkDeleteAction);

        try {
            $livewire->callTableBulkAction('delete', [$this->cashier1, $this->cashier2, $this->owner]);
        } catch (\Throwable) {
            // Strict denial of mixed bulk selection is valid
        }

        $this->assertDatabaseHas('users', ['id' => $this->cashier2->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $this->owner->id, 'deleted_at' => null]);

        // Branch manager cannot escalate floor staff to super-admin or branch-manager via EditAction
        $superAdminRole = Role::where('name', StaffRole::SUPER_ADMIN->value)->firstOrFail();
        $branchManagerRole = Role::where('name', StaffRole::BRANCH_MANAGER->value)->firstOrFail();

        $freshCashier1 = User::create([
            'name' => 'Escalation Target Cashier',
            'email' => null,
            'emp_id' => 'B01-CSH-ESC1',
            'branch_id' => $this->branch1->id,
            'pin_code' => Hash::make('4444'),
            'is_active' => true,
        ]);
        $freshCashier1->assignRole(StaffRole::CASHIER->value);

        foreach ([$superAdminRole, $branchManagerRole] as $privilegedRole) {
            try {
                Livewire::actingAs($this->manager1)
                    ->test(ListUsers::class)
                    ->callAction(TestAction::make('edit')->table($freshCashier1), data: [
                        'name' => $freshCashier1->name,
                        'roles' => [$privilegedRole->id],
                        'emp_id' => $freshCashier1->emp_id,
                        'is_active' => true,
                    ]);
            } catch (\Throwable) {
                // Validation/403 rejection expected
            }

            $freshCashier1->refresh();
            $this->assertFalse(
                $freshCashier1->hasRole($privilegedRole->name),
                "Branch manager must never be able to escalate floor user to {$privilegedRole->name}."
            );
        }
    }

    public function test_vuln09_list_users_creation_hashes_credentials_and_never_exposes_plaintext_in_notifications(): void
    {
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);

        $floorPin = '4829';
        $this->pullFilamentNotificationsJson();

        $floorTest = Livewire::actingAs($this->owner)
            ->test(ListUsers::class)
            ->callAction('create_floor_staff', data: [
                'name' => 'New Floor Cashier',
                'branch_id' => $this->branch1->id,
                'role' => StaffRole::CASHIER->value,
                'emp_id' => 'B01-CSH-HYG1',
                'pin_code' => $floorPin,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $createdFloor = User::where('emp_id', 'B01-CSH-HYG1')->firstOrFail();
        $this->assertNotSame($floorPin, $createdFloor->pin_code);
        $this->assertTrue($createdFloor->verifyPin($floorPin));

        $floorNotificationsJson = $this->pullFilamentNotificationsJson();
        $this->assertStringContainsString('Floor Staff Created', $floorNotificationsJson);
        $this->assertStringNotContainsString($floorPin, $floorNotificationsJson, 'Floor PIN must not be stored in persistent notifications.');
        $this->assertStringNotContainsString($floorPin, (string) $floorTest->html());

        // Create Admin / Manager user
        $adminPass = 'Str0ng!PosPass#2026';
        $adminPin = '7391';
        $this->pullFilamentNotificationsJson();

        $adminTest = Livewire::actingAs($this->owner)
            ->test(ListUsers::class)
            ->callAction('create_admin_user', data: [
                'name' => 'New Backoffice Manager',
                'email' => 'hygiene_mgr@easypos.test',
                'password' => $adminPass,
                'branch_id' => $this->branch1->id,
                'role' => StaffRole::BRANCH_MANAGER->value,
                'emp_id' => 'B01-MGR-HYG1',
                'pin_code' => $adminPin,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $createdAdmin = User::where('emp_id', 'B01-MGR-HYG1')->firstOrFail();
        $this->assertNotSame($adminPass, $createdAdmin->password);
        $this->assertTrue(Hash::check($adminPass, $createdAdmin->password));
        $this->assertTrue($createdAdmin->verifyPin($adminPin));

        $adminNotificationsJson = $this->pullFilamentNotificationsJson();
        $this->assertStringContainsString('Admin / Manager Created', $adminNotificationsJson);
        $this->assertStringNotContainsString($adminPass, $adminNotificationsJson, 'Plaintext password must not be stored in persistent notifications.');
        $this->assertStringNotContainsString($adminPin, $adminNotificationsJson, 'Plaintext approval PIN must not be stored in persistent notifications.');
        $this->assertStringNotContainsString($adminPass, (string) $adminTest->html());
        $this->assertStringNotContainsString($adminPin, (string) $adminTest->html());
    }

    public function test_vuln12_orders_table_void_order_masks_unexpected_exceptions_but_surfaces_business_errors(): void
    {
        $order = (new CreateOrderAction)->execute([
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [['meal_id' => $this->meal1->id, 'quantity' => 1]],
        ]);

        // 1. Unexpected RuntimeException with SQL details must be masked
        $this->app->bind(VoidOrderAction::class, function () {
            return new class extends VoidOrderAction
            {
                public function execute(array $data): Order
                {
                    throw new RuntimeException('SQLSTATE[42S02]: Base table or view not found: secret_table');
                }
            };
        });

        $this->pullFilamentNotificationsJson();

        Livewire::actingAs($this->owner)
            ->test(ListOrders::class)
            ->callAction(TestAction::make('void_order')->table($order), data: [
                'reason' => 'Testing exception masking',
            ]);

        $notificationsJson = $this->pullFilamentNotificationsJson();
        $this->assertStringContainsString('Void Failed', $notificationsJson);
        $this->assertStringNotContainsString('SQLSTATE', $notificationsJson);
        $this->assertStringNotContainsString('secret_table', $notificationsJson);

        // 2. Safe InvalidArgumentException business errors still surface cleanly
        $this->app->bind(VoidOrderAction::class, function () {
            return new class extends VoidOrderAction
            {
                public function execute(array $data): Order
                {
                    throw new InvalidArgumentException('Cannot void an already settled batch.');
                }
            };
        });

        $this->pullFilamentNotificationsJson();

        Livewire::actingAs($this->owner)
            ->test(ListOrders::class)
            ->callAction(TestAction::make('void_order')->table($order), data: [
                'reason' => 'Testing business error pass-through',
            ]);

        $businessNotificationsJson = $this->pullFilamentNotificationsJson();
        $this->assertStringContainsString('Cannot void an already settled batch.', $businessNotificationsJson);
    }

    public function test_vuln12_error_500_and_503_render_with_zero_db_queries_and_without_vite_manifest(): void
    {
        $this->actingAs($this->cashier1);

        // Simulate missing Vite manifest & no hot file
        app(Vite::class)
            ->useBuildDirectory('missing-vite-dir-'.uniqid())
            ->useHotFile(storage_path('missing-hot-'.uniqid()));

        DB::flushQueryLog();
        DB::enableQueryLog();

        $html500 = view('errors.500')->render();
        $html503 = view('errors.503')->render();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertStringContainsString('500', $html500);
        $this->assertStringContainsString('503', $html503);
        $this->assertCount(
            0,
            $queries,
            'Error layout (errors.500 / errors.503) must execute 0 DB queries even when authenticated.'
        );
    }
}
