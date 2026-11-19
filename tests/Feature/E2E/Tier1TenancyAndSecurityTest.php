<?php

namespace Tests\Feature\E2E;

use App\Domains\Menu\Actions\UpdateBranchPortionsAction;
use App\Domains\Menu\Livewire\CreateMealModal;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\Menu\Livewire\MealForm as MenuMealForm;
use App\Domains\Menu\Models\Meal;
use App\Domains\PointOfSale\Livewire\MealCatalog as PosMealCatalog;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SalesRecords;
use App\Filament\Resources\MealCategories\MealCategoryResource;
use App\Filament\Resources\MealCategories\Pages\ListMealCategories;
use App\Filament\Resources\Meals\MealResource;
use App\Filament\Resources\Meals\Pages\ListMeals;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Widgets\DailySalesRevenueChart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Feature\E2E\Concerns\E2ETestHelpers;
use Tests\TestCase;

class Tier1TenancyAndSecurityTest extends TestCase
{
    use E2ETestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedE2EActors();
    }

    // ------------------------------------------------------------------
    // F1: Meal & Special Menu Branch Isolation (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f1_branch_manager_views_own_branch_and_global_meals_in_admin(): void
    {
        ['meal' => $globalMeal] = $this->createBranchCategoryAndMeal(null, ['name' => 'Global Adobo']);
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, ['name' => 'B1 Sisig Special']);
        ['meal' => $b2Meal] = $this->createBranchCategoryAndMeal($this->branch2, ['name' => 'B2 Bulalo Secret']);

        $this->actingAs($this->manager1)->get('/admin/meals')
            ->assertStatus(200)
            ->assertSee('Global Adobo')
            ->assertSee('B1 Sisig Special')
            ->assertDontSee('B2 Bulalo Secret');

        $visibleIds = MealResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($globalMeal->id, $visibleIds);
        $this->assertContains($b1Meal->id, $visibleIds);
        $this->assertNotContains($b2Meal->id, $visibleIds);
    }

    public function test_f1_branch_manager_creates_special_meal_forced_to_own_branch(): void
    {
        ['category' => $globalCat] = $this->createBranchCategoryAndMeal(null);

        Livewire::actingAs($this->manager1)
            ->test(ListMeals::class)
            ->callAction('create', data: [
                'category_id' => $globalCat->id,
                'name' => 'Manila Halo-Halo Special',
                'menu_code' => 'B1-HALO-01',
                'price' => '165.00',
                'is_available' => true,
                'track_stock' => false,
                'is_active' => true,
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('meals', ['name' => 'Manila Halo-Halo Special', 'branch_id' => $this->branch1->id]);

        Livewire::actingAs($this->manager1)
            ->test(MenuMealForm::class)
            ->set('name', 'Manila Kare-Kare')
            ->set('menu_code', 'B1-KARE-02')
            ->set('category_id', $globalCat->id)
            ->set('price', '295.00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('meals', ['name' => 'Manila Kare-Kare', 'branch_id' => $this->branch1->id]);
    }

    public function test_f1_branch_manager_edits_and_deletes_own_branch_meal(): void
    {
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, ['name' => 'B1 Original Pasta', 'price' => 210.00]);
        ['meal' => $b2Meal] = $this->createBranchCategoryAndMeal($this->branch2, ['name' => 'B2 Untouched Pasta', 'price' => 240.00]);

        Livewire::actingAs($this->manager1)
            ->test(ListMeals::class)
            ->callTableAction('edit', $b1Meal, data: [
                'category_id' => $b1Meal->category_id,
                'name' => 'B1 Updated Pasta',
                'menu_code' => $b1Meal->menu_code,
                'price' => '235.00',
                'is_available' => true,
                'track_stock' => false,
                'is_active' => true,
            ])
            ->assertHasNoErrors();

        $this->assertSame('B1 Updated Pasta', $b1Meal->fresh()->name);
        $this->assertSame('235.00', (string) $b1Meal->fresh()->price);
        $this->assertSame('B2 Untouched Pasta', $b2Meal->fresh()->name);

        $this->actingAs($this->manager1);
        $this->assertTrue(MealResource::canDelete($b1Meal));

        Livewire::actingAs($this->manager1)->test(MealCatalogTable::class)->call('deleteMeal', $b1Meal->id);
        $this->assertSoftDeleted('meals', ['id' => $b1Meal->id]);
        $this->assertNotSoftDeleted('meals', ['id' => $b2Meal->id]);
    }

    public function test_f1_owner_and_super_admin_view_and_manage_meals_across_all_branches(): void
    {
        ['meal' => $globalMeal] = $this->createBranchCategoryAndMeal(null, ['name' => 'HQ Global Burger']);
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, ['name' => 'Manila Pork Belly']);
        ['meal' => $b2Meal] = $this->createBranchCategoryAndMeal($this->branch2, ['name' => 'Cebu Lechon Belly']);

        foreach ([$this->owner, $this->superAdmin] as $executive) {
            auth()->logout();
            $this->flushSession();
            $this->actingAs($executive)->get('/admin/meals')
                ->assertStatus(200)
                ->assertSee('HQ Global Burger')
                ->assertSee('Manila Pork Belly')
                ->assertSee('Cebu Lechon Belly');

            foreach ([$globalMeal, $b1Meal, $b2Meal] as $meal) {
                $this->assertTrue(MealResource::canEdit($meal));
                $this->assertTrue(MealResource::canDelete($meal));
            }
        }
    }

    public function test_f1_branch_portion_update_scopes_availability_and_menu_version_cache_to_branch(): void
    {
        ['meal' => $globalMeal] = $this->createBranchCategoryAndMeal(null, ['name' => 'Shared Crispy Pata']);
        Cache::forever("branch_{$this->branch1->id}_menu_version", 100);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 200);

        $this->actingAs($this->manager1);
        app(UpdateBranchPortionsAction::class)->handle($globalMeal->id, true, 12);

        $this->assertDatabaseHas('meal_branch_availabilities', [
            'meal_id' => $globalMeal->id,
            'branch_id' => $this->branch1->id,
            'track_stock' => true,
            'stock_quantity' => 12,
        ]);
        $this->assertDatabaseMissing('meal_branch_availabilities', ['meal_id' => $globalMeal->id, 'branch_id' => $this->branch2->id]);

        $fresh = $globalMeal->fresh();
        $this->assertFalse((bool) $fresh->track_stock);
        $this->assertTrue($fresh->trackStockForBranch($this->branch1->id));
        $this->assertSame(12, $fresh->stockQuantityForBranch($this->branch1->id));
        $this->assertGreaterThan(100, (int) Cache::get("branch_{$this->branch1->id}_menu_version"));
        $this->assertSame(200, (int) Cache::get("branch_{$this->branch2->id}_menu_version"));
    }

    // ------------------------------------------------------------------
    // F2: Meal Category Isolation & Cache Scoping (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f2_branch_manager_views_global_and_own_branch_categories_in_admin(): void
    {
        ['category' => $globalCat] = $this->createBranchCategoryAndMeal(null, [], ['name' => 'Global Appetizers']);
        ['category' => $b1Cat] = $this->createBranchCategoryAndMeal($this->branch1, [], ['name' => 'Manila Exclusives']);
        ['category' => $b2Cat] = $this->createBranchCategoryAndMeal($this->branch2, [], ['name' => 'Cebu Exclusives']);

        $this->actingAs($this->manager1)->get('/admin/meal-categories')
            ->assertStatus(200)
            ->assertSee('Global Appetizers')
            ->assertSee('Manila Exclusives')
            ->assertDontSee('Cebu Exclusives');

        $visibleIds = MealCategoryResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($globalCat->id, $visibleIds);
        $this->assertContains($b1Cat->id, $visibleIds);
        $this->assertNotContains($b2Cat->id, $visibleIds);
    }

    public function test_f2_branch_manager_creates_own_branch_category_forced_to_own_branch(): void
    {
        Livewire::actingAs($this->manager1)
            ->test(ListMealCategories::class)
            ->callAction('create', data: [
                'name' => 'B1 Breakfast Specials',
                'slug' => 'b1-breakfast-specials',
                'display_order' => 5,
                'is_active' => true,
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('meal_categories', [
            'name' => 'B1 Breakfast Specials',
            'slug' => 'b1-breakfast-specials',
            'branch_id' => $this->branch1->id,
        ]);
    }

    public function test_f2_branch_manager_edits_and_deletes_own_branch_category(): void
    {
        ['category' => $b1Cat] = $this->createBranchCategoryAndMeal($this->branch1, [], ['name' => 'B1 Old Cat']);
        Meal::where('category_id', $b1Cat->id)->forceDelete();

        Livewire::actingAs($this->manager1)
            ->test(ListMealCategories::class)
            ->callTableAction('edit', $b1Cat, data: [
                'name' => 'B1 Renamed Cat',
                'slug' => $b1Cat->slug,
                'display_order' => 3,
                'is_active' => true,
            ])
            ->assertHasNoErrors();

        $this->assertSame('B1 Renamed Cat', $b1Cat->fresh()->name);

        Livewire::actingAs($this->manager1)
            ->test(ListMealCategories::class)
            ->callTableBulkAction('delete', [$b1Cat]);

        $this->assertDatabaseMissing('meal_categories', ['id' => $b1Cat->id]);
    }

    public function test_f2_branch_scoped_meals_count_excludes_other_branch_meals(): void
    {
        ['category' => $globalCat] = $this->createBranchCategoryAndMeal(null, ['name' => 'Count Global Dish']);
        $this->createBranchCategoryAndMeal($this->branch1, ['category_id' => $globalCat->id, 'name' => 'Count B1 Dish 1']);
        $this->createBranchCategoryAndMeal($this->branch1, ['category_id' => $globalCat->id, 'name' => 'Count B1 Dish 2']);
        $this->createBranchCategoryAndMeal($this->branch2, ['category_id' => $globalCat->id, 'name' => 'Count B2 Dish 1']);
        $this->createBranchCategoryAndMeal($this->branch2, ['category_id' => $globalCat->id, 'name' => 'Count B2 Dish 2']);
        $this->createBranchCategoryAndMeal($this->branch2, ['category_id' => $globalCat->id, 'name' => 'Count B2 Dish 3']);

        $adminList = Livewire::actingAs($this->manager1)->test(ListMealCategories::class);
        $record = $adminList->instance()->getTableRecords()->firstWhere('id', $globalCat->id);
        $this->assertNotNull($record);
        $this->assertSame(3, (int) $record->meals_count);

        $menuTable = Livewire::actingAs($this->chef1)->test(MealCatalogTable::class);
        $menuCategory = $menuTable->viewData('categories')->firstWhere('id', $globalCat->id);
        $this->assertNotNull($menuCategory);
        $this->assertSame(3, (int) $menuCategory->meals_count);
    }

    public function test_f2_active_category_options_and_cache_isolate_branch1_from_branch2(): void
    {
        $this->createBranchCategoryAndMeal(null, [], ['name' => 'Shared Rice Bowls']);
        $this->createBranchCategoryAndMeal($this->branch1, [], ['name' => 'B1 Manila Only Cat']);
        $this->createBranchCategoryAndMeal($this->branch2, [], ['name' => 'B2 Cebu Only Cat']);

        Livewire::actingAs($this->chef2)->test(MenuMealForm::class)->assertSee('B2 Cebu Only Cat');
        Livewire::actingAs($this->chef2)->test(CreateMealModal::class)->call('openCreateModal')->assertSee('B2 Cebu Only Cat');
        Livewire::actingAs($this->cashier2)->test(PosMealCatalog::class)->assertSee('B2 Cebu Only Cat');

        Livewire::actingAs($this->chef1)->test(MenuMealForm::class)->assertSee('B1 Manila Only Cat')->assertDontSee('B2 Cebu Only Cat');
        Livewire::actingAs($this->chef1)->test(CreateMealModal::class)->call('openCreateModal')->assertSee('B1 Manila Only Cat')->assertDontSee('B2 Cebu Only Cat');
        Livewire::actingAs($this->cashier1)->test(PosMealCatalog::class)->assertSee('B1 Manila Only Cat')->assertDontSee('B2 Cebu Only Cat');
        Livewire::actingAs($this->manager1)->test(Dashboard::class)
            ->assertViewHas('categories', fn ($cats) => $cats->contains('name', 'B1 Manila Only Cat') && ! $cats->contains('name', 'B2 Cebu Only Cat'));
    }

    // ------------------------------------------------------------------
    // F3: Admin Resource & Widget Boundary Hardening (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f3_owner_accesses_all_core_admin_resources_and_pages(): void
    {
        $this->actingAs($this->owner);

        foreach (['/admin', '/admin/branches', '/admin/roles', '/admin/users', '/admin/orders', '/admin/sales-records'] as $uri) {
            $this->get($uri)->assertStatus(200);
        }
    }

    public function test_f3_super_admin_accesses_cross_branch_resources_and_orders(): void
    {
        ['meal' => $m1] = $this->createBranchCategoryAndMeal($this->branch1);
        ['meal' => $m2] = $this->createBranchCategoryAndMeal($this->branch2);
        ['order' => $o1] = $this->createOrderWithKitchenTicket($this->branch1, $this->cashier1, $m1);
        ['order' => $o2] = $this->createOrderWithKitchenTicket($this->branch2, $this->cashier2, $m2);

        $this->actingAs($this->superAdmin);

        foreach (['/admin/branches', '/admin/roles', '/admin/users', '/admin/orders', '/admin/sales-records'] as $uri) {
            $this->get($uri)->assertStatus(200);
        }

        $visibleOrderIds = OrderResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($o1->id, $visibleOrderIds);
        $this->assertContains($o2->id, $visibleOrderIds);
    }

    public function test_f3_branch_manager_accesses_branch_scoped_orders_sales_and_users(): void
    {
        ['meal' => $m1] = $this->createBranchCategoryAndMeal($this->branch1);
        ['meal' => $m2] = $this->createBranchCategoryAndMeal($this->branch2);
        ['order' => $o1] = $this->createOrderWithKitchenTicket($this->branch1, $this->cashier1, $m1);
        ['order' => $o2] = $this->createOrderWithKitchenTicket($this->branch2, $this->cashier2, $m2);

        $this->actingAs($this->manager1);
        $this->get('/admin/orders')->assertStatus(200)->assertSee($o1->order_number)->assertDontSee($o2->order_number);
        $this->get('/admin/sales-records')->assertStatus(200);
        $this->get('/admin/users')->assertStatus(200)->assertSee($this->cashier1->emp_id)->assertDontSee($this->cashier2->emp_id);
    }

    public function test_f3_sales_records_and_chart_scope_manager_to_branch_and_allow_hq_filter(): void
    {
        ['meal' => $m1] = $this->createBranchCategoryAndMeal($this->branch1, ['price' => 245.00]);
        ['meal' => $m2] = $this->createBranchCategoryAndMeal($this->branch2, ['price' => 585.00]);
        $this->createOrderWithKitchenTicket($this->branch1, $this->cashier1, $m1, ['status' => 'completed']);
        $this->createOrderWithKitchenTicket($this->branch2, $this->cashier2, $m2, ['status' => 'completed']);

        Livewire::actingAs($this->manager1)
            ->test(SalesRecords::class)
            ->assertSet('branchId', $this->branch1->id)
            ->assertViewHas('summary', fn ($s) => (float) $s['net_sales'] === 245.0);

        Livewire::actingAs($this->owner)
            ->test(SalesRecords::class)
            ->set('branchId', $this->branch2->id)
            ->assertViewHas('summary', fn ($s) => (float) $s['net_sales'] === 585.0);

        Livewire::actingAs($this->owner)
            ->test(DailySalesRevenueChart::class, [
                'branchId' => $this->branch2->id,
                'startDate' => today('Asia/Manila')->toDateString(),
                'endDate' => today('Asia/Manila')->toDateString(),
            ])
            ->assertOk()
            ->assertSee('585', false)
            ->assertDontSee('245', false);
    }

    public function test_f3_branch_manager_creates_own_branch_floor_staff_in_user_resource(): void
    {
        Livewire::actingAs($this->manager1)
            ->test(ListUsers::class)
            ->callAction('create_floor_staff', data: [
                'name' => 'New B1 Floor Cashier',
                'role' => 'cashier',
                'emp_id' => 'B01-CSH-901',
                'pin_code' => '4567',
                'is_active' => true,
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['emp_id' => 'B01-CSH-901', 'branch_id' => $this->branch1->id]);
    }

    // ------------------------------------------------------------------
    // F4: HTTPS / Cookie / Session / Middleware Hardening (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f4_trusted_vpc_proxy_forwarded_proto_https_emits_hsts_header(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.1.25',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/login')
            ->assertStatus(200)
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    }

    public function test_f4_security_headers_present_on_web_and_admin_responses(): void
    {
        $responses = [
            $this->get('/login'),
            $this->get('/admin/login'),
            $this->actingAs($this->cashier1)->get('/pos'),
            (function () {
                auth()->logout();
                $this->flushSession();
                return $this->actingAs($this->owner)->get('/admin');
            })(),
        ];

        foreach ($responses as $response) {
            $response->assertStatus(200)
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
        }
    }

    public function test_f4_session_defaults_enforce_httponly_samesite_lax_and_encryption(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));

        $sessionConfigSource = file_get_contents(config_path('session.php'));
        $this->assertIsString($sessionConfigSource);
        $this->assertStringContainsString("'http_only' => env('SESSION_HTTP_ONLY', true)", $sessionConfigSource);
        $this->assertStringContainsString("'same_site' => env('SESSION_SAME_SITE', 'lax')", $sessionConfigSource);
        $this->assertStringContainsString("'encrypt' => env('SESSION_ENCRYPT', true)", $sessionConfigSource);
    }

    public function test_f4_active_authenticated_admin_user_accesses_admin_panel_cleanly(): void
    {
        $this->actingAs($this->owner)->get('/admin')->assertStatus(200);
        auth()->logout();
        $this->flushSession();
        $this->actingAs($this->manager1)->get('/admin')->assertStatus(200);
    }

    public function test_f4_pin_authenticated_floor_session_accesses_assigned_stations(): void
    {
        $this->actingAs($this->cashier1)->withSession(['auth_method' => 'pin'])->get('/pos')->assertStatus(200);
        $this->actingAs($this->chef1)->withSession(['auth_method' => 'pin'])->get('/menu')->assertStatus(200);
        $this->actingAs($this->cook1)->withSession(['auth_method' => 'pin'])->get('/kitchen')->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // F5: Architecture, Security & SSL Documentation (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f5_architecture_and_security_markdown_doc_exists_and_is_non_empty(): void
    {
        $docs = $this->findSecurityDocs();
        $this->assertNotEmpty($docs, 'Expected at least one Markdown doc in docs/.');

        $hasArchAndSecDoc = collect($docs)->contains(
            fn (array $doc): bool => trim($doc['content']) !== ''
                && preg_match('/architecture/i', $doc['content']) === 1
                && preg_match('/security/i', $doc['content']) === 1
                && preg_match('/branch-manager|branch manager/i', $doc['content']) === 1
        );

        $this->assertTrue($hasArchAndSecDoc, 'Expected a non-empty docs/*.md covering cloud architecture, security model, and roles.');
    }

    public function test_f5_documentation_covers_all_six_roles_and_their_access_and_limitations(): void
    {
        $combined = implode("\n\n", array_column($this->findSecurityDocs(), 'content'));

        foreach (['owner', 'super-admin', 'branch-manager', 'cashier', 'cook', 'chef'] as $role) {
            $this->assertMatchesRegularExpression('/\b' . preg_quote($role, '/') . '\b/i', $combined, "Documentation must cover role [{$role}].");
        }
        $this->assertMatchesRegularExpression('/limitation|restrict|denied|cannot/i', $combined);
    }

    public function test_f5_documentation_explains_ssl_https_configuration_and_proxy_termination(): void
    {
        $combined = implode("\n\n", array_column($this->findSecurityDocs(), 'content'));

        $this->assertMatchesRegularExpression('/ssl|tls/i', $combined);
        $this->assertMatchesRegularExpression('/https/i', $combined);
        $this->assertMatchesRegularExpression('/Strict-Transport-Security|HSTS/i', $combined);
        $this->assertMatchesRegularExpression('/X-Forwarded-Proto|ALB|proxy/i', $combined);
    }

    public function test_f5_documentation_explains_session_management_and_cookie_hardening(): void
    {
        $combined = implode("\n\n", array_column($this->findSecurityDocs(), 'content'));

        $this->assertMatchesRegularExpression('/session/i', $combined);
        $this->assertMatchesRegularExpression('/HttpOnly/i', $combined);
        $this->assertMatchesRegularExpression('/SameSite/i', $combined);
        $this->assertMatchesRegularExpression('/encrypt|SESSION_ENCRYPT|SESSION_SECURE_COOKIE/i', $combined);
    }

    public function test_f5_documentation_explains_terminal_page_lock_and_branch_isolation_architecture(): void
    {
        $combined = implode("\n\n", array_column($this->findSecurityDocs(), 'content'));

        $this->assertMatchesRegularExpression('/page\s*lock|terminal\s*lock/i', $combined);
        $this->assertMatchesRegularExpression('/branch\s*isolation|branch_id|branch-scoped/i', $combined);
    }
}
