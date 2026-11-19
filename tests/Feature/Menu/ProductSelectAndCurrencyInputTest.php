<?php

namespace Tests\Feature\Menu;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealBranchAvailability;
use App\Domains\Menu\Models\MealCategory;
use App\Livewire\Forms\CurrencyInput;
use App\Livewire\Forms\ProductSelect;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSelectAndCurrencyInputTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;

    protected Branch $branch2;

    protected User $cashier1;

    protected User $nullBranchChef;

    protected MealCategory $category;

    protected Meal $globalMeal;

    protected Meal $branch1Meal;

    protected Meal $branch2Meal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], ['name' => 'Branch One', 'is_active' => true]);
        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], ['name' => 'Branch Two', 'is_active' => true]);

        $this->cashier1 = User::where('emp_id', 'B01-CSH-001')->firstOrFail();

        $this->nullBranchChef = User::create([
            'name' => 'Unassigned Chef',
            'email' => 'unassigned_ps@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-CHF-PS1',
            'branch_id' => null,
            'is_active' => true,
        ]);
        $this->nullBranchChef->assignRole(StaffRole::CHEF->value);

        $this->category = MealCategory::create([
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->globalMeal = Meal::create([
            'branch_id' => null,
            'category_id' => $this->category->id,
            'name' => 'Global Adobo',
            'menu_code' => 'GLB-PS1',
            'price' => 180.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $this->branch1Meal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'Branch 1 Sisig',
            'menu_code' => 'B1-PS1',
            'price' => 210.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->branch2Meal = Meal::create([
            'branch_id' => $this->branch2->id,
            'category_id' => $this->category->id,
            'name' => 'Branch 2 Secret Bulalo',
            'menu_code' => 'B2-PS1',
            'price' => 490.00,
            'is_available' => true,
            'is_active' => true,
        ]);
    }

    public function test_product_select_scopes_queries_and_selection_to_users_effective_branch(): void
    {
        $component = Livewire::actingAs($this->cashier1)
            ->test(ProductSelect::class, ['branchId' => $this->branch2->id]);

        // Server overrides requested branchId with effectiveBranchId()
        $component->assertSet('branchId', $this->branch1->id);

        // Search finds Branch 1 and Global meals, never Branch 2 meal
        $component->set('search', 'Branch')
            ->assertSee('Branch 1 Sisig')
            ->assertDontSee('Branch 2 Secret Bulalo')
            ->assertSee(SystemConstants::CURRENCY_SYMBOL.'210.00');

        // Selecting Branch 2 meal is a no-op and never dispatches selection
        $component->call('selectMeal', $this->branch2Meal->id)
            ->assertSet('selectedId', null)
            ->assertNotDispatched('product-selected');

        // Selecting Branch 1 meal succeeds
        $component->call('selectMeal', $this->branch1Meal->id)
            ->assertSet('selectedId', $this->branch1Meal->id)
            ->assertDispatched('product-selected');
    }

    public function test_product_select_respects_branch_availability_overrides_and_stock_quantities(): void
    {
        MealBranchAvailability::create([
            'meal_id' => $this->globalMeal->id,
            'branch_id' => $this->branch1->id,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 3,
        ]);

        $component = Livewire::actingAs($this->cashier1)
            ->test(ProductSelect::class)
            ->set('search', 'Global Adobo')
            ->assertSee('3 in stock');

        $component->call('selectMeal', $this->globalMeal->id)
            ->assertDispatched('product-selected', function ($event, $params) {
                return $params['mealId'] === $this->globalMeal->id
                    && $params['meal']['track_stock'] === true
                    && $params['meal']['stock_quantity'] === 3;
            });
    }

    public function test_product_select_locks_branch_id_against_client_tampering(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->cashier1)
            ->test(ProductSelect::class)
            ->set('branchId', $this->branch2->id);
    }

    public function test_product_select_fails_closed_for_unauthenticated_and_null_branch_users_without_leaking_global_meals(): void
    {
        // Unauthenticated caller: mount clears forged selectedId, search returns 0 results (no global leak), selectMeal aborts 403
        auth()->logout();

        $guestComponent = Livewire::test(ProductSelect::class, [
            'selectedId' => $this->globalMeal->id,
            'branchId' => $this->branch1->id,
        ]);

        $guestComponent->assertSet('selectedId', null)
            ->assertSet('selectedMeal', null)
            ->set('search', 'Global Adobo')
            ->assertViewHas('results', fn ($results) => $results->isEmpty())
            ->assertDontSee('GLB-PS1')
            ->call('selectMeal', $this->globalMeal->id)
            ->assertForbidden();

        // Null-branch scoped user (effectiveBranchId === -1): zero results (no global leak), selectMeal aborts 403
        $nullBranchComponent = Livewire::actingAs($this->nullBranchChef)
            ->test(ProductSelect::class, [
                'selectedId' => $this->globalMeal->id,
            ]);

        $nullBranchComponent->assertSet('selectedId', null)
            ->set('search', 'Global Adobo')
            ->assertViewHas('results', fn ($results) => $results->isEmpty())
            ->assertDontSee('GLB-PS1')
            ->call('selectMeal', $this->globalMeal->id)
            ->assertForbidden();
    }

    public function test_currency_input_deduplicated_clamping_disabled_guard_and_symbol(): void
    {
        $component = Livewire::test(CurrencyInput::class, [
            'amount' => 10.00,
            'min' => 5.00,
            'max' => 100.00,
        ]);

        $component->assertSet('currencySymbol', SystemConstants::CURRENCY_SYMBOL);

        $component->call('addAmount', 150.00)
            ->assertSet('amount', 100.00)
            ->assertDispatched('amount-changed', fn ($event, $params) => (float) $params['amount'] === 100.0);

        $component->call('addAmount', -200.00)
            ->assertSet('amount', 5.00)
            ->assertDispatched('amount-changed', fn ($event, $params) => (float) $params['amount'] === 5.0);

        $component->set('amount', 12.3456)
            ->assertSet('amount', 12.35)
            ->assertDispatched('amount-changed', fn ($event, $params) => (float) $params['amount'] === 12.35);

        $component->call('setAmount', 1.00)
            ->assertSet('amount', 5.00)
            ->assertDispatched('amount-changed', fn ($event, $params) => (float) $params['amount'] === 5.0);

        // Disabled state blocks addAmount, setAmount, and updatedAmount
        $disabled = Livewire::test(CurrencyInput::class, [
            'amount' => 50.00,
            'disabled' => true,
        ]);

        $disabled->call('addAmount', 25.00)
            ->assertSet('amount', 50.00)
            ->assertNotDispatched('amount-changed')
            ->call('setAmount', 10.00)
            ->assertSet('amount', 50.00)
            ->assertNotDispatched('amount-changed')
            ->set('amount', 99.00)
            ->assertSet('amount', 50.00)
            ->assertNotDispatched('amount-changed');
    }

    public function test_currency_input_clamps_and_rounds_initial_amount_on_mount_without_dispatching(): void
    {
        Livewire::test(CurrencyInput::class, [
            'amount' => -50.00,
            'min' => 0.00,
            'max' => 100.00,
        ])
            ->assertSet('amount', 0.00)
            ->assertNotDispatched('amount-changed');

        Livewire::test(CurrencyInput::class, [
            'amount' => 250.555,
            'min' => 5.00,
            'max' => 100.00,
        ])
            ->assertSet('amount', 100.00)
            ->assertNotDispatched('amount-changed');

        Livewire::test(CurrencyInput::class, [
            'amount' => 12.345,
            'min' => 0.00,
            'max' => 100.00,
        ])
            ->assertSet('amount', 12.35)
            ->assertNotDispatched('amount-changed');
    }

    public function test_currency_input_locks_configuration_properties_against_client_tampering(): void
    {
        $lockedOverrides = [
            'min' => 0.00,
            'max' => 9999.00,
            'currencySymbol' => '$',
            'eventName' => 'tampered-event',
            'disabled' => false,
        ];

        foreach ($lockedOverrides as $property => $value) {
            try {
                Livewire::test(CurrencyInput::class, [
                    'amount' => 50.00,
                    'min' => 10.00,
                    'max' => 100.00,
                    'disabled' => true,
                ])->set($property, $value);
                $this->fail("Expected CannotUpdateLockedPropertyException for CurrencyInput::\${$property}.");
            } catch (CannotUpdateLockedPropertyException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_product_select_clear_selection_is_blocked_when_disabled(): void
    {
        Livewire::actingAs($this->cashier1)
            ->test(ProductSelect::class, [
                'selectedId' => $this->branch1Meal->id,
                'disabled' => true,
            ])
            ->assertSet('selectedId', $this->branch1Meal->id)
            ->call('clearSelection')
            ->assertSet('selectedId', $this->branch1Meal->id)
            ->assertSet('selectedMeal', fn ($meal) => $meal !== null && $meal->id === $this->branch1Meal->id)
            ->assertNotDispatched('product-selected');

        Livewire::actingAs($this->cashier1)
            ->test(ProductSelect::class, [
                'selectedId' => $this->branch1Meal->id,
            ])
            ->assertSet('selectedId', $this->branch1Meal->id)
            ->call('clearSelection')
            ->assertSet('selectedId', null)
            ->assertSet('selectedMeal', null)
            ->assertDispatched('product-selected', fn ($event, $params) => $params['mealId'] === null && $params['meal'] === null);
    }

    public function test_product_select_locks_disabled_and_event_name_against_client_tampering(): void
    {
        $lockedOverrides = [
            'disabled' => false,
            'eventName' => 'tampered-event',
        ];

        foreach ($lockedOverrides as $property => $value) {
            $component = Livewire::actingAs($this->cashier1)
                ->test(ProductSelect::class, [
                    'selectedId' => $this->branch1Meal->id,
                    'disabled' => true,
                ]);

            try {
                $component->set($property, $value);
                $this->fail("Expected CannotUpdateLockedPropertyException for ProductSelect::\${$property}.");
            } catch (CannotUpdateLockedPropertyException) {
                $this->assertTrue(true);
            }

            $component->assertSet('disabled', true)
                ->assertSet('eventName', 'product-selected')
                ->assertSet('selectedId', $this->branch1Meal->id)
                ->call('clearSelection')
                ->assertSet('selectedId', $this->branch1Meal->id)
                ->call('selectMeal', $this->globalMeal->id)
                ->assertSet('selectedId', $this->branch1Meal->id)
                ->assertNotDispatched('product-selected')
                ->assertNotDispatched('tampered-event');
        }

        // Also confirm selectMeal cannot be bypassed via disabled tamper when mounted empty
        $emptyDisabled = Livewire::actingAs($this->cashier1)
            ->test(ProductSelect::class, [
                'disabled' => true,
            ]);

        try {
            $emptyDisabled->set('disabled', false);
            $this->fail('Expected CannotUpdateLockedPropertyException for ProductSelect::$disabled.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertTrue(true);
        }

        $emptyDisabled->call('selectMeal', $this->branch1Meal->id)
            ->assertSet('selectedId', null)
            ->assertNotDispatched('product-selected');
    }
}
