<?php

namespace Tests\Feature\Security;

use App\Domains\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacRouteMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTES = [
        '/pos' => ['pos.access'],
        '/menu' => ['menu.view'],
        '/kitchen' => ['kitchen.view'],
        '/admin' => ['reports.view'],
        '/admin/sales-records' => ['reports.view'],
        '/admin/orders' => ['reports.view', 'pos.refund'],
        '/admin/meals' => ['menu.manage'],
        '/admin/meal-categories' => ['menu.manage'],
        '/admin/users' => ['users.manage'],
        '/admin/branches' => ['branch.manage'],
        '/admin/roles' => ['roles.manage'],
        '/admin/activity-logs' => ['reports.view', 'users.manage'],
        '/admin/sales-records/export-csv' => ['reports.view'],
        '/admin/sales-records/export-excel' => ['reports.view'],
        '/admin/sales-records/audit-report' => ['reports.view'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public static function roles(): array
    {
        return array_combine(
            ['owner', 'super-admin', 'branch-manager', 'cashier', 'chef', 'cook'],
            array_map(fn ($role) => [$role], ['owner', 'super-admin', 'branch-manager', 'cashier', 'chef', 'cook'])
        );
    }

    #[DataProvider('roles')]
    public function test_seeded_role_route_matrix(string $role): void
    {
        $user = User::whereHas('roles', fn ($query) => $query->where('name', $role))->firstOrFail();
        $this->actingAs($user);
        foreach (self::ROUTES as $url => $permissions) {
            $expected = $user->hasAnyPermission($permissions);
            if (str_starts_with($url, '/admin')) {
                $expected = $expected && $user->hasPermissionTo('admin.access');
            }
            if (in_array($url, ['/admin/branches', '/admin/roles'], true)) {
                $expected = $expected && in_array($role, ['owner', 'super-admin'], true);
            }
            $this->assertRouteOutcome($role, $url, $permissions, $expected ? 200 : 403, 'seeded');
        }
    }

    #[DataProvider('roles')]
    public function test_all_roles_with_revoked_permissions_are_denied_direct_urls(string $role): void
    {
        $user = User::whereHas('roles', fn ($query) => $query->where('name', $role))->firstOrFail();
        Role::findByName($role)->syncPermissions([]);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->actingAs($user);
        foreach (self::ROUTES as $url => $permissions) {
            $this->assertRouteOutcome($role, $url, $permissions, 403, 'all grants revoked');
        }
    }

    public function test_super_admin_report_permission_revocation_takes_effect_in_active_session(): void
    {
        $user = User::whereHas('roles', fn ($query) => $query->where('name', 'super-admin'))->firstOrFail();
        $this->actingAs($user)->get('/admin/sales-records')->assertOk();
        Role::findByName('super-admin')->revokePermissionTo('reports.view');
        $this->get('/admin/sales-records')->assertForbidden();
        $this->get('/admin/sales-records/export-csv')->assertForbidden();
        $this->get('/admin/sales-records/export-excel')->assertForbidden();
    }

    public function test_report_exports_require_panel_permission_even_with_report_grant(): void
    {
        $user = User::whereHas('roles', fn ($query) => $query->where('name', 'super-admin'))->firstOrFail();
        Role::findByName('super-admin')->revokePermissionTo('admin.access');
        $this->actingAs($user);
        foreach (['export-csv', 'export-excel', 'audit-report'] as $action) {
            $this->get('/admin/sales-records/' . $action)->assertForbidden();
        }
    }

    public function test_guest_requests_are_redirected_without_protected_data(): void
    {
        foreach (array_keys(self::ROUTES) as $url) {
            $response = $this->get($url);
            $this->assertTrue($response->isRedirect(), $url);
        }
        $this->assertGuest();
    }

    public function test_open_pos_livewire_request_is_denied_after_route_permission_is_revoked(): void
    {
        $cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $html = $this->actingAs($cashier)->get('/pos')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $html, $matches));
        Role::findByName('cashier')->revokePermissionTo('pos.access');
        $ordersBefore = \App\Domains\PointOfSale\Models\Order::count();
        $this->postJson('/livewire/update', ['components' => [[
            'snapshot' => html_entity_decode($matches[1], ENT_QUOTES),
            'updates' => [],
            'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]],
        ]]], ['X-Livewire' => 'true'])->assertForbidden();
        $this->assertSame($ordersBefore, \App\Domains\PointOfSale\Models\Order::count());
    }

    private function assertRouteOutcome(string $role, string $url, array $permissions, int $expected, string $state): void
    {
        $response = $this->get($url);
        file_put_contents(storage_path('logs/rbac-route-matrix.jsonl'), json_encode([
            'role' => $role, 'route' => $url, 'permissions' => $permissions,
            'state' => $state, 'expected' => $expected, 'actual' => $response->getStatusCode(),
        ]) . PHP_EOL, FILE_APPEND | LOCK_EX);
        $response->assertStatus($expected);
    }
}
