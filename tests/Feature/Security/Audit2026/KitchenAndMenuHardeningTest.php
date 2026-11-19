<?php

namespace Tests\Feature\Security\Audit2026;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Actions\ClaimKitchenTicketAction;
use App\Domains\Kitchen\Actions\RecallKitchenTicketAction;
use App\Domains\Kitchen\Actions\ToggleKitchenItemStatusAction;
use App\Domains\Kitchen\Actions\ToggleKitchenTicketPriorityAction;
use App\Domains\Kitchen\Actions\UpdateKitchenTicketStatusAction;
use App\Domains\Kitchen\Livewire\KitchenDisplay;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Menu\Actions\CreateMealAction;
use App\Domains\Menu\Actions\DeleteMealAction;
use App\Domains\Menu\Actions\ToggleMealAvailabilityAction;
use App\Domains\Menu\Actions\UpdateBranchPortionsAction;
use App\Domains\Menu\Actions\UpdateMealAction;
use App\Domains\Menu\Livewire\EditMealModal;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutModal;
use App\Domains\PointOfSale\Livewire\OrderProgress;
use App\Domains\PointOfSale\Models\Order;
use App\Livewire\Forms\ProductSelect;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderType;
use App\Support\Enums\StaffRole;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class KitchenAndMenuHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch1;

    private Branch $branch2;

    private MealCategory $category1;

    private User $cashier1;

    private User $cook1;

    private User $cook2;

    private User $nullBranchCook;

    private User $nullBranchCashier;

    private User $chef1;

    private User $nullBranchChef;

    private Meal $meal1;

    private Meal $meal2;

    private Meal $globalMeal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Kitchen Audit Branch 1',
            'is_active' => true,
        ]);

        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], [
            'name' => 'Kitchen Audit Branch 2',
            'is_active' => true,
        ]);

        $this->category1 = MealCategory::firstOrCreate(['slug' => 'kitchen-audit-cat'], [
            'name' => 'Kitchen Audit Category',
            'is_active' => true,
        ]);

        $this->cashier1 = User::create([
            'name' => 'Kitchen Audit Cashier 1',
            'email' => 'ktn_c1@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-KTN1',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier1->assignRole(StaffRole::CASHIER->value);

        $this->cook1 = User::create([
            'name' => 'Kitchen Audit Cook 1',
            'email' => 'ktn_cook1@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-COK-KTN1',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cook1->assignRole(StaffRole::COOK->value);

        $this->cook2 = User::create([
            'name' => 'Kitchen Audit Cook 2',
            'email' => 'ktn_cook2@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-COK-KTN2',
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->cook2->assignRole(StaffRole::COOK->value);

        $this->nullBranchCook = User::create([
            'name' => 'Null Branch Cook',
            'email' => 'ktn_null_cook@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B00-COK-NULL',
            'branch_id' => null,
            'is_active' => true,
        ]);
        $this->nullBranchCook->assignRole(StaffRole::COOK->value);

        $this->nullBranchCashier = User::create([
            'name' => 'Null Branch Cashier',
            'email' => 'ktn_null_csh@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B00-CSH-NULL',
            'branch_id' => null,
            'is_active' => true,
        ]);
        $this->nullBranchCashier->assignRole(StaffRole::CASHIER->value);

        $this->chef1 = User::create([
            'name' => 'Kitchen Audit Chef 1',
            'email' => 'ktn_chef1@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CHF-KTN1',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->chef1->assignRole(StaffRole::CHEF->value);

        $this->nullBranchChef = User::create([
            'name' => 'Null Branch Chef',
            'email' => 'ktn_null_chef@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B00-CHF-NULL',
            'branch_id' => null,
            'is_active' => true,
        ]);
        $this->nullBranchChef->assignRole(StaffRole::CHEF->value);

        $this->meal1 = Meal::create([
            'name' => 'Branch 1 Sisig',
            'menu_code' => 'B01-KTN-01',
            'category_id' => $this->category1->id,
            'branch_id' => $this->branch1->id,
            'price' => 220.00,
            'is_available' => true,
        ]);

        $this->meal2 = Meal::create([
            'name' => 'Branch 2 Bulalo',
            'menu_code' => 'B02-KTN-01',
            'category_id' => $this->category1->id,
            'branch_id' => $this->branch2->id,
            'price' => 380.00,
            'is_available' => true,
        ]);

        $this->globalMeal = Meal::create([
            'name' => 'Global Halo-Halo',
            'menu_code' => 'HQ-KTN-01',
            'category_id' => $this->category1->id,
            'branch_id' => null,
            'price' => 140.00,
            'is_available' => true,
        ]);
    }

    private function createBranch1Ticket(string $status = KitchenTicketStatus::NEW->value): KitchenTicket
    {
        $order = (new CreateOrderAction)->execute([
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => OrderType::DINE_IN->value,
            'table_number' => 'T-10',
            'items' => [['meal_id' => $this->meal1->id, 'quantity' => 1]],
        ]);

        (new ProcessPaymentAction)->execute($order, [
            'payment_method' => 'cash',
            'amount_tendered' => (float) $order->total_amount,
            'cashier_id' => $this->cashier1->id,
        ]);

        $ticket = KitchenTicket::where('order_id', $order->id)->firstOrFail();

        if ($status !== KitchenTicketStatus::NEW->value) {
            $ticket->update([
                'status' => $status,
                'served_at' => $status === KitchenTicketStatus::SERVED->value ? now() : null,
            ]);
        }

        return $ticket->fresh(['items']);
    }

    private function assertKitchenActionRejectedFor(callable $fn, string $label): void
    {
        $status = null;
        try {
            $fn();
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
        }
        $this->assertSame(403, $status, "Expected 403 HttpException for {$label}.");
    }

    public function test_vuln10_all_five_kitchen_actions_reject_guest_cross_branch_and_null_branch_actors(): void
    {
        $ticketNew = $this->createBranch1Ticket(KitchenTicketStatus::NEW->value);
        $ticketServed = $this->createBranch1Ticket(KitchenTicketStatus::SERVED->value);
        $ticketItem = $ticketNew->items->firstOrFail();

        $claim = app(ClaimKitchenTicketAction::class);
        $recall = app(RecallKitchenTicketAction::class);
        $toggleItem = app(ToggleKitchenItemStatusAction::class);
        $togglePriority = app(ToggleKitchenTicketPriorityAction::class);
        $updateStatus = app(UpdateKitchenTicketStatusAction::class);

        $actors = [
            'guest' => null,
            'cross-branch cook' => $this->cook2,
            'null-branch cook' => $this->nullBranchCook,
        ];

        foreach ($actors as $label => $actor) {
            if ($actor === null) {
                Auth::logout();
            } else {
                $this->actingAs($actor);
            }

            $this->assertKitchenActionRejectedFor(fn () => $claim->execute($ticketNew, $this->cook2), "{$label} ClaimKitchenTicketAction");
            $this->assertKitchenActionRejectedFor(fn () => $recall->execute($ticketServed), "{$label} RecallKitchenTicketAction");
            $this->assertKitchenActionRejectedFor(fn () => $toggleItem->execute($ticketItem), "{$label} ToggleKitchenItemStatusAction");
            $this->assertKitchenActionRejectedFor(fn () => $togglePriority->execute($ticketNew), "{$label} ToggleKitchenTicketPriorityAction");
            $this->assertKitchenActionRejectedFor(fn () => $updateStatus->execute($ticketNew, KitchenTicketStatus::READY->value), "{$label} UpdateKitchenTicketStatusAction");

            // Assert zero DB mutation on ticket & item
            $this->assertSame(KitchenTicketStatus::NEW->value, $ticketNew->fresh()->status);
            $this->assertSame('normal', $ticketNew->fresh()->priority);
            $this->assertSame('pending', $ticketItem->fresh()->status);
            $this->assertSame(KitchenTicketStatus::SERVED->value, $ticketServed->fresh()->status);
        }
    }

    public function test_vuln10_kitchen_display_checkout_modal_and_order_progress_fail_closed_for_null_branch_users(): void
    {
        $branch1Ticket = $this->createBranch1Ticket();

        // 1. KitchenDisplay must fail closed (403 or never display/mutate Branch 1 ticket)
        try {
            $kds = Livewire::actingAs($this->nullBranchCook)->test(KitchenDisplay::class);
            $kds->assertDontSee($branch1Ticket->ticket_number);
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // 2. CheckoutModal must fail closed and NOT create an order on Branch 1
        $beforeOrderCount = Order::count();
        $checkout = Livewire::actingAs($this->nullBranchCashier)->test(CheckoutModal::class);
        $checkout->call('openWithData', [
            'items' => [['meal_id' => $this->meal1->id, 'quantity' => 1]],
            'order_type' => OrderType::DINE_IN->value,
            'subtotal' => 220.00,
            'total_amount' => 220.00,
        ]);
        try {
            $checkout->call('processCheckout');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame($beforeOrderCount, Order::count(), 'Null-branch cashier must not create orders on Branch 1 via CheckoutModal.');

        // 3. OrderProgress must fail closed and never leak Branch 1 ticket counts/numbers
        try {
            $progress = Livewire::actingAs($this->nullBranchCashier)->test(OrderProgress::class);
            $progress->call('open');
            $counts = $progress->get('counts');
            $this->assertSame(0, $counts['all'] ?? 0, 'Null-branch cashier must not see Branch 1 badge counts.');
            $progress->assertDontSee($branch1Ticket->ticket_number);
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_vuln11_meal_catalog_table_clamps_per_page_and_locks_sensitive_properties(): void
    {
        $livewire = Livewire::actingAs($this->chef1)->test(MealCatalogTable::class);

        $livewire->set('perPage', 999999);
        $this->assertLessThanOrEqual(100, $livewire->viewData('meals')->perPage(), 'perPage=999999 must clamp to <= 100.');

        $livewire->set('perPage', 0);
        $this->assertGreaterThanOrEqual(1, $livewire->viewData('meals')->perPage(), 'perPage=0 must clamp to >= 1.');

        $livewire->set('perPage', -5);
        $this->assertGreaterThanOrEqual(1, $livewire->viewData('meals')->perPage(), 'perPage=-5 must clamp to >= 1.');

        // #[Locked] on MealCatalogTable::$portionsMealId
        $lockedPortions = false;
        try {
            $livewire->set('portionsMealId', $this->meal2->id);
        } catch (CannotUpdateLockedPropertyException) {
            $lockedPortions = true;
        }
        $this->assertTrue($lockedPortions, 'MealCatalogTable::$portionsMealId must be #[Locked].');

        // #[Locked] on EditMealModal::$mealId
        $lockedEdit = false;
        try {
            Livewire::actingAs($this->chef1)
                ->test(EditMealModal::class)
                ->set('mealId', $this->meal2->id);
        } catch (CannotUpdateLockedPropertyException) {
            $lockedEdit = true;
        }
        $this->assertTrue($lockedEdit, 'EditMealModal::$mealId must be #[Locked].');
    }

    public function test_vuln11_all_five_menu_actions_reject_unauthenticated_and_null_branch_actors(): void
    {
        $create = app(CreateMealAction::class);
        $update = app(UpdateMealAction::class);
        $delete = app(DeleteMealAction::class);
        $toggle = app(ToggleMealAvailabilityAction::class);
        $portions = app(UpdateBranchPortionsAction::class);

        $actors = [
            'unauthenticated' => null,
            'null-branch chef' => $this->nullBranchChef,
        ];

        foreach ($actors as $label => $actor) {
            if ($actor === null) {
                Auth::logout();
            } else {
                $this->actingAs($actor);
            }

            $this->assertKitchenActionRejectedFor(fn () => $create->handle([
                'name' => 'Rogue Dish',
                'category_id' => $this->category1->id,
                'price' => 100.00,
                'branch_id' => $this->branch1->id,
            ]), "{$label} CreateMealAction");

            $this->assertKitchenActionRejectedFor(fn () => $update->handle($this->meal1, ['name' => 'Hacked']), "{$label} UpdateMealAction");
            $this->assertKitchenActionRejectedFor(fn () => $delete->handle($this->meal1), "{$label} DeleteMealAction");
            $this->assertKitchenActionRejectedFor(fn () => $toggle->handle($this->globalMeal), "{$label} ToggleMealAvailabilityAction");
            $this->assertKitchenActionRejectedFor(fn () => $portions->handle($this->globalMeal->id, true, 5), "{$label} UpdateBranchPortionsAction");
        }

        $this->assertNull($this->meal1->fresh()->deleted_at);
        $this->assertSame('Branch 1 Sisig', $this->meal1->fresh()->name);
        $this->assertTrue((bool) $this->globalMeal->fresh()->is_available);
    }

    public function test_vuln11_menu_version_bumps_monotonically_within_same_second_and_xss_is_stripped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 12:00:00'));

        try {
            $this->actingAs($this->chef1);

            $meal = app(CreateMealAction::class)->handle([
                'name' => '<script>alert(1)</script>Crispy Pata<iframe src="https://evil.test"></iframe>',
                'description' => '<style>body{display:none}</style>Freshly cooked',
                'category_id' => $this->category1->id,
                'price' => 550.00,
                'branch_id' => $this->branch1->id,
            ]);

            $this->assertStringNotContainsString('<script', $meal->name);
            $this->assertStringNotContainsString('<iframe', $meal->name);
            $this->assertSame('Crispy Pata', $meal->name);
            $this->assertStringNotContainsString('<style', (string) $meal->description);

            $v1 = (int) Cache::get("branch_{$this->branch1->id}_menu_version", 0);

            app(UpdateMealAction::class)->handle($meal, ['price' => 560.00]);
            $v2 = (int) Cache::get("branch_{$this->branch1->id}_menu_version", 0);

            app(ToggleMealAvailabilityAction::class)->handle($meal);
            $v3 = (int) Cache::get("branch_{$this->branch1->id}_menu_version", 0);

            $this->assertGreaterThan($v1, $v2, 'UpdateMealAction must bump menu version monotonically even in same second.');
            $this->assertGreaterThan($v2, $v3, 'ToggleMealAvailabilityAction must bump menu version monotonically even in same second.');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_vuln15_product_select_locks_branch_id_and_blocks_cross_branch_meal_selection(): void
    {
        // #[Locked] on ProductSelect::$branchId
        $locked = false;
        try {
            Livewire::actingAs($this->cashier1)
                ->test(ProductSelect::class, ['branchId' => $this->branch1->id])
                ->set('branchId', $this->branch2->id);
        } catch (CannotUpdateLockedPropertyException) {
            $locked = true;
        }
        $this->assertTrue($locked, 'ProductSelect::$branchId must be #[Locked].');

        // Selecting Branch 2 meal from Branch 1 must fail silently (selectedId & selectedMeal stay null)
        $select = Livewire::actingAs($this->cashier1)
            ->test(ProductSelect::class, ['branchId' => $this->branch1->id])
            ->call('selectMeal', $this->meal2->id);

        $this->assertNull($select->get('selectedId'), 'Branch 1 ProductSelect must not select a Branch 2 meal.');
        $this->assertNull($select->get('selectedMeal'), 'Branch 1 ProductSelect must not load a Branch 2 meal.');

        // Selecting own-branch meal succeeds
        $select->call('selectMeal', $this->meal1->id);
        $this->assertSame($this->meal1->id, $select->get('selectedId'));
    }
}
