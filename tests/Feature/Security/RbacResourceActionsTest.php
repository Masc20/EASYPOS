<?php

namespace Tests\Feature\Security;

use App\Domains\Identity\Models\User;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SalesRecords;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\MealCategories\MealCategoryResource;
use App\Filament\Resources\Meals\MealResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\DailySalesRevenueChart;
use App\Filament\Widgets\PaymentMethodsDistributionChart;
use App\Filament\Widgets\PeakSalesHoursChart;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacResourceActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public static function resources(): array
    {
        return array_map(fn ($class) => [$class], [BranchResource::class, RoleResource::class, MealResource::class, MealCategoryResource::class, UserResource::class, OrderResource::class, ActivityLogResource::class]);
    }

    #[DataProvider('resources')]
    public function test_executive_role_without_module_permission_cannot_use_resource_or_operations(string $resource): void
    {
        $role = Role::findByName('super-admin', 'web');
        $role->syncPermissions(['admin.access']);
        $this->actingAs(User::where('email', 'admin@easypos.com')->firstOrFail()->fresh());

        $this->assertFalse($resource::canAccess());
        $this->assertFalse($resource::shouldRegisterNavigation());
        $this->assertFalse($resource::canViewAny());
        $this->assertFalse($resource::canCreate());
        $this->assertFalse($resource::canDeleteAny());
    }

    public function test_read_only_resources_deny_unspecified_mutations_even_to_executives(): void
    {
        $this->actingAs(User::where('email', 'owner@easypos.com')->firstOrFail());
        foreach ([OrderResource::class, ActivityLogResource::class] as $resource) {
            $this->assertTrue($resource::canAccess());
            $this->assertFalse($resource::canCreate());
            $this->assertFalse($resource::canDeleteAny());
            $this->assertFalse($resource::canRestoreAny());
            $this->assertFalse($resource::canForceDeleteAny());
            $this->assertFalse($resource::getAuthorizationResponse('unsupported-operation')->allowed());
        }
    }

    public function test_authorized_executives_retain_branch_and_role_management(): void
    {
        $this->actingAs(User::where('email', 'admin@easypos.com')->firstOrFail());
        foreach ([BranchResource::class, RoleResource::class] as $resource) {
            $this->assertTrue($resource::canAccess());
            $this->assertTrue($resource::canCreate());
        }
        $this->assertFalse(RoleResource::canEdit(Role::findByName('owner', 'web')));
    }

    public function test_role_management_permission_is_independent_of_staff_management(): void
    {
        $actor = User::where('email', 'admin@easypos.com')->firstOrFail();
        Role::findByName('super-admin', 'web')->revokePermissionTo('roles.manage');
        $this->actingAs($actor->fresh());
        $this->assertTrue(UserResource::canAccess());
        $this->assertFalse(RoleResource::canAccess());
        $this->get('/admin/roles')->assertForbidden();
        $this->assertFalse(RoleResource::canCreate());
    }

    public function test_branch_manager_remains_denied_hq_resources_with_direct_permission(): void
    {
        $actor = User::where('emp_id', 'B01-MGR-001')->firstOrFail();
        $actor->givePermissionTo('branch.manage');
        $this->actingAs($actor);
        $this->assertFalse(BranchResource::canAccess());
        $this->assertFalse(RoleResource::canAccess());
        $this->assertFalse(BranchResource::canCreate());
    }

    public function test_custom_role_permissions_survive_role_form_round_trip_and_other_guards_are_rejected(): void
    {
        Permission::create(['name' => 'custom.capability', 'guard_name' => 'web']);
        Permission::create(['name' => 'foreign.capability', 'guard_name' => 'other']);
        $role = Role::create(['name' => 'custom-reviewer', 'guard_name' => 'web']);
        $role->givePermissionTo('custom.capability');
        $data = RoleForm::fillPermissionTabs(['name' => $role->name], $role);
        $data['permissions_other'][] = 'foreign.capability';
        [$clean, $permissions] = RoleForm::extractPermissions($data);

        $this->assertSame(['custom.capability'], $permissions);
        $this->assertArrayNotHasKey('permissions_other', $clean);
    }

    public function test_role_and_staff_creation_are_denied_after_revocation_without_writes(): void
    {
        $actor = User::where('email', 'owner@easypos.com')->firstOrFail();
        $this->actingAs($actor);
        $roleComponent = Livewire::test(ListRoles::class);
        $staffComponent = Livewire::test(ListUsers::class);
        $roles = Role::count();
        $users = User::count();
        Role::findByName('owner', 'web')->revokePermissionTo(['users.manage', 'roles.manage']);
        $actor->refresh();

        $roleComponent->call('mountAction', 'create')->assertForbidden();
        $staffComponent->call('mountAction', 'create_floor_staff')->assertForbidden();
        $this->assertSame($roles, Role::count());
        $this->assertSame($users, User::count());
    }

    public function test_reports_and_widgets_reject_missing_report_permission(): void
    {
        $role = Role::findByName('super-admin', 'web');
        $role->revokePermissionTo('reports.view');
        $this->actingAs(User::where('email', 'admin@easypos.com')->firstOrFail()->fresh());

        foreach ([Dashboard::class, SalesRecords::class, DailySalesRevenueChart::class, PaymentMethodsDistributionChart::class, PeakSalesHoursChart::class] as $component) {
            Livewire::test($component)->assertForbidden();
        }
    }

    public function test_pin_session_cannot_execute_resource_operations(): void
    {
        $this->actingAs(User::where('email', 'owner@easypos.com')->firstOrFail());
        session(['auth_method' => 'pin']);
        foreach ([BranchResource::class, RoleResource::class, MealResource::class, UserResource::class] as $resource) {
            $this->assertFalse($resource::canCreate());
            $this->assertFalse($resource::canDeleteAny());
        }
    }

    public function test_super_admin_cannot_take_over_or_deactivate_executive_accounts(): void
    {
        $actor = User::where('email', 'admin@easypos.com')->firstOrFail();
        $owner = User::where('email', 'owner@easypos.com')->firstOrFail();
        $this->actingAs($actor);
        $this->assertFalse(UserResource::canEdit($owner));
        $this->assertFalse(UserResource::canDelete($owner));
        try {
            app(\App\Domains\Identity\Actions\ToggleUserActiveStatusAction::class)->execute($owner);
            $this->fail('An executive account must remain protected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_forged_owner_assignment_rolls_back_staff_edits(): void
    {
        $actor = User::where('email', 'admin@easypos.com')->firstOrFail();
        $target = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $before = $target->getAttributes();
        $this->actingAs($actor);
        Livewire::test(ListUsers::class)
            ->mountTableAction('edit', $target)
            ->setTableActionData([
                'name' => 'Unauthorized edit',
                'roles' => [Role::findByName('owner', 'web')->id],
            ])
            ->callMountedTableAction()->assertForbidden();
        $this->assertSame($before, $target->fresh()->getAttributes());
        $this->assertFalse($target->fresh()->hasRole('owner'));
    }

    public function test_manager_cannot_edit_a_floor_user_who_also_has_a_management_role(): void
    {
        $manager = User::where('emp_id', 'B01-MGR-001')->firstOrFail();
        $target = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $target->assignRole('branch-manager');
        $this->actingAs($manager);
        $this->assertFalse(UserResource::canEdit($target));
        $this->assertFalse(UserResource::canDelete($target));
    }
}
