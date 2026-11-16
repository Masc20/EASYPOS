<?php

namespace Tests\Feature\Security;

use App\Domains\Identity\Models\User;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleFormModalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::firstOrCreate(['email' => 'owner-roles-test@easypos.com'], [
            'name' => 'Roles Test Owner',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-ROL-001',
            'is_active' => true,
        ]);
        $this->owner->assignRole(StaffRole::OWNER->value);
    }

    public function test_owner_can_create_role_with_tabbed_permissions_via_modal(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ListRoles::class)
            ->callAction('create', data: [
                'name' => 'shift-lead',
                'permissions_pos' => ['pos.access', 'pos.checkout'],
                'permissions_operations' => ['menu.view', 'kitchen.view'],
                'permissions_management' => ['reports.view'],
            ])
            ->assertHasNoActionErrors();

        $role = Role::where('name', 'shift-lead')->first();
        $this->assertNotNull($role);
        $this->assertEqualsCanonicalizing(
            ['pos.access', 'pos.checkout', 'menu.view', 'kitchen.view', 'reports.view'],
            $role->permissions()->pluck('name')->toArray()
        );
    }

    public function test_owner_can_edit_role_hydrates_tabbed_permissions_and_syncs_changes(): void
    {
        $role = Role::create(['name' => 'temp-lead', 'guard_name' => 'web']);
        $role->syncPermissions(['pos.access', 'kitchen.view', 'reports.view']);

        Livewire::actingAs($this->owner)
            ->test(ListRoles::class)
            ->mountAction(TestAction::make('edit')->table($role))
            ->assertSchemaStateSet([
                'name' => 'temp-lead',
                'permissions_pos' => ['pos.access'],
                'permissions_operations' => ['kitchen.view'],
                'permissions_management' => ['reports.view'],
            ])
            ->setActionData([
                'name' => 'temp-lead-updated',
                'permissions_pos' => ['pos.access', 'pos.refund'],
                'permissions_operations' => ['kitchen.prepare'],
                'permissions_management' => [],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $role->refresh();
        $this->assertSame('temp-lead-updated', $role->name);
        $this->assertEqualsCanonicalizing(
            ['pos.access', 'pos.refund', 'kitchen.prepare'],
            $role->permissions()->pluck('name')->toArray()
        );
    }
}
