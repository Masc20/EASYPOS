<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Actions\ToggleUserActiveStatusAction;
use App\Domains\Identity\Models\User;
use App\Http\Middleware\PreloadAuthenticatedUserPermissions;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RbacIdentityRevocationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        $this->branch = Branch::create(['code' => 'RBAC', 'name' => 'RBAC Test Branch', 'is_active' => true]);
    }

    public static function managementRoles(): array
    {
        return [['owner'], ['super-admin'], ['branch-manager']];
    }

    #[DataProvider('managementRoles')]
    public function test_management_role_cannot_bypass_revoked_panel_permission(string $roleName): void
    {
        $user = $this->user($roleName);
        $this->assertTrue($user->canAccessAdmin());

        Role::findByName($roleName, 'web')->revokePermissionTo('admin.access');

        $this->assertFalse($user->canAccessAdmin());
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_direct_permission_allows_a_roleless_user_and_revocation_is_observed_next_request(): void
    {
        $user = $this->user();
        $user->givePermissionTo(['admin.access', 'reports.view']);
        $this->actingAs($user)->get('/admin')->assertOk();
        $this->assertTrue($user->relationLoaded('permissions'));

        User::findOrFail($user->id)->revokePermissionTo('admin.access');

        $this->get('/admin')->assertForbidden();
    }

    public function test_multi_role_permissions_are_combined_without_role_based_panel_entry(): void
    {
        $user = $this->user();
        $reports = Role::create(['name' => 'report-reader', 'guard_name' => 'web']);
        $reports->givePermissionTo('reports.view');
        $portal = Role::create(['name' => 'portal-entry', 'guard_name' => 'web']);
        $portal->givePermissionTo('admin.access');
        $user->assignRole($reports, $portal);
        $this->actingAs($user)->get('/admin')->assertOk();

        User::findOrFail($user->id)->removeRole($portal);

        $this->get('/admin')->assertForbidden();
        $this->assertTrue($user->can('reports.view'));
    }

    public function test_request_refreshes_role_scope_and_permissions_for_reused_user(): void
    {
        $user = $this->user('super-admin');
        $this->assertTrue($user->isOwner());
        $this->assertTrue($user->canAccessAdmin());
        User::findOrFail($user->id)->syncRoles(['cashier']);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        (new PreloadAuthenticatedUserPermissions())->handle($request, function () use ($user) {
            $this->assertFalse($user->isOwner());
            $this->assertTrue($user->isBranchScoped());
            $this->assertFalse($user->canAccessAdmin());
            $this->assertTrue($user->can('pos.access'));

            return response('checked');
        });
    }

    public function test_pin_session_cannot_reuse_a_previous_panel_access_decision(): void
    {
        $user = $this->user('branch-manager');
        $this->assertTrue($user->canAccessAdmin());
        session(['auth_method' => 'pin']);
        $this->assertFalse($user->canAccessAdmin());
    }

    public function test_inactive_and_missing_branch_constraints_still_override_panel_permission(): void
    {
        $user = $this->user('branch-manager');
        $user->branch_id = null;
        $this->assertFalse($user->canAccessAdmin());
        $user->branch_id = $this->branch->id;
        $this->branch->update(['is_active' => false]);
        $this->assertFalse($user->canAccessAdmin());
        $user->is_active = false;
        $this->assertFalse($user->canAccessAdmin());
    }

    public function test_wrong_guard_permission_cannot_be_assigned_to_web_user(): void
    {
        $user = $this->user();
        $permission = Permission::create(['name' => 'admin.access', 'guard_name' => 'other']);
        try {
            $user->givePermissionTo($permission);
            $this->fail('Wrong-guard permission must be rejected.');
        } catch (GuardDoesNotMatch) {
            $this->assertFalse($user->canAccessAdmin());
            $this->assertDatabaseMissing('model_has_permissions', ['model_id' => $user->id, 'permission_id' => $permission->id]);
        }
    }

    public function test_guest_and_unprivileged_actor_cannot_toggle_staff_or_delete_sessions(): void
    {
        $target = $this->user('cashier');
        foreach ([null, $this->user('cook')] as $actor) {
            try {
                app(ToggleUserActiveStatusAction::class)->execute($target, $actor);
                $this->fail('Unauthorized staff toggle must be rejected.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
                $this->assertTrue($target->fresh()->is_active);
            }
        }
    }

    public function test_revoked_staff_permission_blocks_domain_action_without_mutating_target(): void
    {
        $actor = $this->user('super-admin');
        $target = $this->user('cashier');
        $this->assertTrue($actor->can('users.manage'));
        Role::findByName('super-admin', 'web')->revokePermissionTo('users.manage');
        try {
            app(ToggleUserActiveStatusAction::class)->execute($target, $actor);
            $this->fail('Revoked staff permission must deny the action.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertTrue($target->fresh()->is_active);
        }
    }

    public function test_manager_cannot_toggle_a_mixed_floor_and_management_target(): void
    {
        $actor = $this->user('branch-manager');
        $target = $this->user('cashier');
        $target->assignRole('branch-manager');
        try {
            app(ToggleUserActiveStatusAction::class)->execute($target, $actor);
            $this->fail('Management targets must remain protected.');
        } catch (ValidationException) {
            $this->assertTrue($target->fresh()->is_active);
        }
    }

    public function test_authorized_manager_can_toggle_only_own_branch_floor_staff(): void
    {
        $actor = $this->user('branch-manager');
        $target = $this->user('cashier');
        app(ToggleUserActiveStatusAction::class)->execute($target, $actor);
        $this->assertFalse($target->fresh()->is_active);
        $otherBranch = Branch::create(['code' => 'OTHER', 'name' => 'Other Branch', 'is_active' => true]);
        $target->update(['branch_id' => $otherBranch->id, 'is_active' => true]);
        try {
            app(ToggleUserActiveStatusAction::class)->execute($target, $actor);
            $this->fail('Cross-branch target must remain protected.');
        } catch (ValidationException) {
            $this->assertTrue($target->fresh()->is_active);
        }
    }

    private function user(?string $role = null): User
    {
        $user = User::create([
            'name' => 'RBAC Test Actor',
            'emp_id' => 'RBAC-' . (User::withTrashed()->count() + 1),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user;
    }
}
