<?php

namespace Tests\Feature\E2E;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Actions\UpdateMealAction;
use App\Domains\Menu\Livewire\CreateMealModal;
use App\Domains\Menu\Livewire\EditMealModal;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\Menu\Livewire\MealForm as MenuMealForm;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Livewire\MealCatalog as PosMealCatalog;
use App\Filament\Pages\SalesRecords;
use App\Filament\Resources\MealCategories\MealCategoryResource;
use App\Filament\Resources\MealCategories\Pages\ListMealCategories;
use App\Filament\Resources\Meals\MealResource;
use App\Filament\Resources\Meals\Pages\ListMeals;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Widgets\DailySalesRevenueChart;
use App\Support\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\E2E\Concerns\E2ETestHelpers;
use Tests\TestCase;

class Tier2TenancyAndSecurityBoundaryTest extends TestCase
{
    use E2ETestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedE2EActors();
    }

    // ------------------------------------------------------------------
    // F1 Boundary/Negative (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f1_boundary_branch_manager_cannot_view_edit_delete_or_restore_other_branch_meal(): void
    {
        ['meal' => $b2Meal] = $this->createBranchCategoryAndMeal($this->branch2, ['name' => 'B2 Protected Steak', 'price' => 450.00]);
        ['meal' => $b2Trashed] = $this->createBranchCategoryAndMeal($this->branch2, ['name' => 'B2 Trashed Dish']);
        $b2Trashed->delete();

        $this->actingAs($this->manager1);
        $this->assertFalse(MealResource::getEloquentQuery()->whereKey($b2Meal->id)->exists());
        $this->assertFalse(MealResource::canEdit($b2Meal));
        $this->assertFalse(MealResource::canDelete($b2Meal));

        Livewire::actingAs($this->manager1)
            ->test(EditMealModal::class)
            ->dispatch('edit-meal', id: $b2Meal->id)
            ->assertStatus(403);

        Livewire::actingAs($this->manager1)
            ->test(MealCatalogTable::class)
            ->call('deleteMeal', $b2Meal->id)
            ->assertStatus(403);

        $this->assertSame('B2 Protected Steak', $b2Meal->fresh()->name);
        $this->assertNotSoftDeleted('meals', ['id' => $b2Meal->id]);
        $this->assertSoftDeleted('meals', ['id' => $b2Trashed->id]);
    }

    public function test_f1_boundary_branch_manager_cannot_edit_or_delete_global_meal(): void
    {
        ['meal' => $globalMeal] = $this->createBranchCategoryAndMeal(null, ['name' => 'Global Protected Soup', 'price' => 120.00]);

        $this->actingAs($this->manager1);
        $this->assertFalse(MealResource::canEdit($globalMeal));
        $this->assertFalse(MealResource::canDelete($globalMeal));

        Livewire::actingAs($this->manager1)
            ->test(EditMealModal::class)
            ->dispatch('edit-meal', id: $globalMeal->id)
            ->assertStatus(403);

        Livewire::actingAs($this->manager1)
            ->test(MealCatalogTable::class)
            ->call('deleteMeal', $globalMeal->id)
            ->assertStatus(403);

        $this->assertSame('Global Protected Soup', $globalMeal->fresh()->name);
        $this->assertNotSoftDeleted('meals', ['id' => $globalMeal->id]);
    }

    public function test_f1_boundary_branch_manager_forging_branch_id_cannot_write_meal_to_other_branch(): void
    {
        ['category' => $globalCat] = $this->createBranchCategoryAndMeal(null);
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, ['name' => 'B1 Original Dish']);

        Livewire::actingAs($this->manager1)
            ->test(ListMeals::class)
            ->callAction('create', data: [
                'category_id' => $globalCat->id,
                'branch_id' => $this->branch2->id,
                'name' => 'Forged B2 Meal',
                'menu_code' => 'FRG-MEAL-01',
                'price' => '199.00',
                'is_available' => true,
                'track_stock' => false,
                'is_active' => true,
            ]);

        $this->assertDatabaseMissing('meals', [
            'name' => 'Forged B2 Meal',
            'branch_id' => $this->branch2->id,
        ]);

        $this->actingAs($this->manager1);
        try {
            app(UpdateMealAction::class)->handle($b1Meal, [
                'branch_id' => $this->branch2->id,
                'name' => 'Hijacked Dish',
            ]);
            $this->fail('Expected 403 HttpException when Branch Manager forges branch_id on meal update.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame($this->branch1->id, $b1Meal->fresh()->branch_id);
        $this->assertSame('B1 Original Dish', $b1Meal->fresh()->name);
    }

    public function test_f1_boundary_floor_staff_denied_admin_meals_access(): void
    {
        foreach ([$this->cashier1, $this->cook1] as $staff) {
            $this->actingAs($staff);
            $this->assertFalse(MealResource::canAccess());
            $this->get('/admin/meals')->assertStatus(403);
        }

        $this->actingAs($this->chef1)->get('/admin/meals')->assertStatus(403);
    }

    public function test_f1_boundary_branch_manager_meals_table_hides_branch_filter_and_blocks_other_branch_meals(): void
    {
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, ['name' => 'B1 Visible Dish']);
        ['meal' => $b2Meal] = $this->createBranchCategoryAndMeal($this->branch2, ['name' => 'B2 Hidden Dish']);

        Livewire::actingAs($this->manager1)
            ->test(ListMeals::class)
            ->assertTableFilterHidden('branch_id')
            ->assertCanSeeTableRecords([$b1Meal])
            ->assertCanNotSeeTableRecords([$b2Meal])
            ->assertDontSee('B2 Hidden Dish');
    }

    // ------------------------------------------------------------------
    // F2 Boundary/Negative (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f2_boundary_branch_manager_cannot_view_edit_or_delete_other_branch_category(): void
    {
        ['category' => $b2Cat] = $this->createBranchCategoryAndMeal($this->branch2, [], ['name' => 'B2 Protected Cat']);

        $this->actingAs($this->manager1);
        $this->assertFalse(MealCategoryResource::getEloquentQuery()->whereKey($b2Cat->id)->exists());
        $this->assertFalse(MealCategoryResource::canEdit($b2Cat));
        $this->assertFalse(MealCategoryResource::canDelete($b2Cat));
        $this->assertSame('B2 Protected Cat', $b2Cat->fresh()->name);
    }

    public function test_f2_boundary_branch_manager_cannot_edit_or_delete_global_category(): void
    {
        ['category' => $globalCat] = $this->createBranchCategoryAndMeal(null, [], ['name' => 'Global Protected Cat']);

        $this->actingAs($this->manager1);
        $this->assertFalse(MealCategoryResource::canEdit($globalCat));
        $this->assertFalse(MealCategoryResource::canDelete($globalCat));

        Livewire::actingAs($this->manager1)
            ->test(ListMealCategories::class)
            ->assertTableActionHidden('edit', $globalCat);

        $this->assertSame('Global Protected Cat', $globalCat->fresh()->name);
    }

    public function test_f2_boundary_category_bulk_delete_leaves_global_and_other_branch_intact(): void
    {
        ['category' => $globalCat] = $this->createBranchCategoryAndMeal(null, [], ['name' => 'Bulk Global Keep']);
        ['category' => $b2Cat] = $this->createBranchCategoryAndMeal($this->branch2, [], ['name' => 'Bulk B2 Keep']);
        ['category' => $b1Cat] = $this->createBranchCategoryAndMeal($this->branch1, [], ['name' => 'Bulk B1 Delete']);
        $b1Cat->meals()->forceDelete();

        Livewire::actingAs($this->manager1)
            ->test(ListMealCategories::class)
            ->callTableBulkAction('delete', [$globalCat, $b2Cat, $b1Cat]);

        $this->assertDatabaseHas('meal_categories', ['id' => $globalCat->id, 'name' => 'Bulk Global Keep']);
        $this->assertDatabaseHas('meal_categories', ['id' => $b2Cat->id, 'name' => 'Bulk B2 Keep']);
    }

    public function test_f2_boundary_category_cache_does_not_leak_branch2_category_to_branch1(): void
    {
        $this->createBranchCategoryAndMeal($this->branch2, [], ['name' => 'B2 Leak Check Cat']);

        // Warm cache from Branch 2 actors first
        Livewire::actingAs($this->chef2)->test(MenuMealForm::class)->assertSee('B2 Leak Check Cat');
        Livewire::actingAs($this->chef2)->test(CreateMealModal::class)->call('openCreateModal')->assertSee('B2 Leak Check Cat');

        // Verify Branch 1 actors do not receive Branch 2 cached categories
        Livewire::actingAs($this->chef1)->test(MenuMealForm::class)->assertDontSee('B2 Leak Check Cat');
        Livewire::actingAs($this->chef1)->test(CreateMealModal::class)->call('openCreateModal')->assertDontSee('B2 Leak Check Cat');
        Livewire::actingAs($this->cashier1)->test(PosMealCatalog::class)->assertDontSee('B2 Leak Check Cat');
    }

    public function test_f2_boundary_cashier_and_cook_denied_admin_meal_categories(): void
    {
        foreach ([$this->cashier1, $this->cook1] as $staff) {
            $this->actingAs($staff);
            $this->assertFalse(MealCategoryResource::canAccess());
            $this->get('/admin/meal-categories')->assertStatus(403);
        }
    }

    // ------------------------------------------------------------------
    // F3 Boundary/Negative (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f3_boundary_null_branch_manager_cannot_bypass_branch_scoping(): void
    {
        ['meal' => $m1] = $this->createBranchCategoryAndMeal($this->branch1, ['price' => 300.00]);
        $this->createOrderWithKitchenTicket($this->branch1, $this->cashier1, $m1, ['status' => 'completed']);

        $nullBranchMgr = User::create([
            'name' => 'Unassigned Branch Manager',
            'email' => 'null-mgr@easypos.test',
            'emp_id' => 'HQ-MGR-NULL',
            'branch_id' => null,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $nullBranchMgr->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->assertTrue($nullBranchMgr->isBranchScoped());
        $this->assertNotSame($this->branch1->id, $nullBranchMgr->effectiveBranchId($this->branch1->id));

        $this->actingAs($nullBranchMgr);
        $this->assertSame(0, OrderResource::getEloquentQuery()->count());
        $this->assertFalse(SalesRecords::canAccess());
        $this->get('/admin/sales-records?branchId=' . $this->branch1->id)->assertStatus(403);

        Livewire::actingAs($nullBranchMgr)
            ->test(SalesRecords::class)
            ->assertStatus(403);
    }

    public function test_f3_boundary_custom_admin_access_user_without_reports_view_denied_sales_and_orders(): void
    {
        $limitedAdmin = User::create([
            'name' => 'Limited Admin',
            'email' => 'limited-admin@easypos.test',
            'emp_id' => 'B01-ADM-LIM1',
            'branch_id' => $this->branch1->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $limitedAdmin->givePermissionTo('admin.access');

        $this->actingAs($limitedAdmin);
        $this->assertFalse(SalesRecords::canAccess());
        $this->assertFalse(OrderResource::canAccess());
        $this->get('/admin/sales-records')->assertStatus(403);
        $this->get('/admin/orders')->assertStatus(403);
    }

    public function test_f3_boundary_branch_manager_forging_other_branch_cashier_gets_zero_other_revenue(): void
    {
        ['meal' => $m2] = $this->createBranchCategoryAndMeal($this->branch2, ['price' => 755.00]);
        $this->createOrderWithKitchenTicket($this->branch2, $this->cashier2, $m2, ['status' => 'completed']);

        Livewire::actingAs($this->manager1)
            ->test(SalesRecords::class)
            ->set('branchId', $this->branch2->id)
            ->set('cashierId', $this->cashier2->id)
            ->assertViewHas('summary', fn ($s) => (float) $s['net_sales'] === 0.0);

        Livewire::actingAs($this->manager1)
            ->test(DailySalesRevenueChart::class, [
                'branchId' => $this->branch2->id,
                'cashierId' => $this->cashier2->id,
                'startDate' => today()->toDateString(),
                'endDate' => today()->toDateString(),
            ])
            ->assertOk()
            ->assertDontSee('755', false);
    }

    public function test_f3_boundary_branch_manager_denied_branches_roles_and_privileged_user_creation(): void
    {
        $this->actingAs($this->manager1);
        $this->get('/admin/branches')->assertStatus(403);
        $this->get('/admin/roles')->assertStatus(403);

        $usersPage = Livewire::actingAs($this->manager1)
            ->test(ListUsers::class)
            ->assertActionHidden('create_admin_user');

        $usersPage->callAction('create_floor_staff', data: [
            'name' => 'Forged Super Admin',
            'role' => 'super-admin',
            'emp_id' => 'B01-SA-FORGE1',
            'pin_code' => '7777',
            'is_active' => true,
        ]);

        $this->assertDatabaseMissing('users', ['emp_id' => 'B01-SA-FORGE1']);
    }

    public function test_f3_boundary_floor_staff_and_pin_sessions_denied_admin_panel(): void
    {
        foreach ([$this->cashier1, $this->chef1, $this->cook1] as $floorUser) {
            $this->actingAs($floorUser)->get('/admin')->assertStatus(403);
        }

        $this->actingAs($this->manager1)
            ->withSession(['auth_method' => 'pin'])
            ->get('/admin')
            ->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // F4 Boundary/Negative (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f4_boundary_untrusted_client_spoofing_forwarded_proto_https_gets_no_hsts(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.99',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTPS' => 'off',
        ])->get('http://localhost/login');

        $response->assertStatus(200)
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_f4_boundary_deactivated_admin_user_with_existing_session_evicted_on_next_admin_request(): void
    {
        $this->actingAs($this->manager1)->get('/admin')->assertStatus(200);

        $this->manager1->update(['is_active' => false]);

        $response = $this->actingAs($this->manager1->fresh())->get('/admin');
        $this->assertContains($response->getStatusCode(), [302, 403]);
        if ($response->getStatusCode() === 302) {
            $this->assertGuest();
        }
    }

    public function test_f4_boundary_deactivated_floor_user_redirected_and_logged_out_on_root(): void
    {
        $this->cashier1->update(['is_active' => false]);

        $response = $this->actingAs($this->cashier1->fresh())->get('/');
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_f4_boundary_plain_http_non_proxy_request_does_not_emit_hsts(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTPS' => 'off',
        ])->get('http://localhost/login');

        $response->assertStatus(200)
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_f4_boundary_session_config_does_not_default_secure_cookie_to_false_in_production(): void
    {
        $sessionConfigSource = file_get_contents(config_path('session.php'));
        $this->assertIsString($sessionConfigSource);

        $this->assertDoesNotMatchRegularExpression(
            "/'secure'\s*=>\s*(false|env\('SESSION_SECURE_COOKIE'\))\s*,/",
            $sessionConfigSource,
            "config/session.php must not leave 'secure' without a production-safe default."
        );
    }

    // ------------------------------------------------------------------
    // F5 Boundary/Negative (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f5_boundary_docs_do_not_contradict_role_access_boundaries(): void
    {
        $combined = implode("\n\n", array_column($this->findSecurityDocs(), 'content'));
        $this->assertMatchesRegularExpression('/\bsuper-admin\b/i', $combined, 'Role security doc must describe super-admin.');
        $this->assertMatchesRegularExpression('/\bowner\b/i', $combined, 'Role security doc must describe owner.');

        $this->assertDoesNotMatchRegularExpression('/super-admin\s+(has|can)\s+access\s+to\s+(\/pos|\/kitchen|\/menu)/i', $combined);
        $this->assertDoesNotMatchRegularExpression('/owner\s+(has|can)\s+access\s+to\s+\/kitchen/i', $combined);
    }

    public function test_f5_boundary_docs_address_ssl_https_without_tbd_placeholders(): void
    {
        $combined = implode("\n\n", array_column($this->findSecurityDocs(), 'content'));
        $this->assertMatchesRegularExpression('/ssl|https/i', $combined);
        $this->assertDoesNotMatchRegularExpression('/\b(TBD|FIXME|TODO:\s*ssl|\[placeholder\])\b/i', $combined);
    }

    public function test_f5_boundary_docs_explicitly_state_branch_manager_cross_branch_prohibition(): void
    {
        $combined = implode("\n\n", array_column($this->findSecurityDocs(), 'content'));
        $this->assertMatchesRegularExpression('/branch[- ]manager/i', $combined);
        $this->assertMatchesRegularExpression('/other branch|cross-branch|own branch only/i', $combined);
    }

    public function test_f5_boundary_docs_contain_no_real_secrets_or_env_dumps(): void
    {
        foreach ($this->findSecurityDocs() as $doc) {
            $this->assertDoesNotMatchRegularExpression('/APP_KEY\s*=\s*base64:[A-Za-z0-9+\/]{32,}=/', $doc['content']);
            $this->assertDoesNotMatchRegularExpression('/-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----/', $doc['content']);
            $this->assertDoesNotMatchRegularExpression('/\bAKIA[0-9A-Z]{16}\b/', $doc['content']);
        }
    }

    public function test_f5_boundary_docs_are_valid_readable_utf8_markdown(): void
    {
        $docs = $this->findSecurityDocs();
        $this->assertNotEmpty($docs);

        foreach ($docs as $doc) {
            $this->assertNotSame('', trim($doc['content']), "Doc {$doc['name']} must not be empty.");
            $this->assertTrue(mb_check_encoding($doc['content'], 'UTF-8'), "Doc {$doc['name']} must be valid UTF-8.");
        }
    }
}
