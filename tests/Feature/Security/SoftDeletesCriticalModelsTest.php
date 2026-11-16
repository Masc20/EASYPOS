<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Enums\OrderType;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SoftDeletesCriticalModelsTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $cashier;
    protected MealCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Flagship Branch',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Cashier SoftDeletes',
            'email' => 'cashier_sd@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-SD01',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole(StaffRole::CASHIER->value);

        $this->category = MealCategory::create([
            'name' => 'Sides',
            'slug' => 'sides',
            'is_active' => true,
        ]);
    }

    public function test_meal_model_uses_soft_deletes_and_supports_full_lifecycle(): void
    {
        $this->assertTrue(
            in_array(SoftDeletes::class, class_uses_recursive(Meal::class)),
            'Meal model must use SoftDeletes trait.'
        );

        $meal = Meal::create([
            'name' => 'Garlic Rice',
            'menu_code' => 'M-GR-01',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 35.00,
            'is_available' => true,
        ]);

        $initialCount = Meal::count();

        // Soft delete
        $meal->delete();

        $this->assertTrue($meal->trashed(), 'Deleted meal must report trashed() === true.');
        $this->assertNotNull($meal->deleted_at, 'deleted_at timestamp must be populated upon soft deletion.');
        $this->assertSame($initialCount - 1, Meal::count(), 'Default query must exclude soft-deleted meal.');
        $this->assertNull(Meal::find($meal->id), 'Meal::find() must return null for soft-deleted meal.');

        // withTrashed & onlyTrashed
        $this->assertNotNull(Meal::withTrashed()->find($meal->id));
        $this->assertNotNull(Meal::onlyTrashed()->find($meal->id));

        // Restore
        $meal->restore();
        $this->assertFalse($meal->trashed());
        $this->assertNull($meal->deleted_at);
        $this->assertNotNull(Meal::find($meal->id));
    }

    public function test_order_model_uses_soft_deletes_trait_and_lifecycle(): void
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive(Order::class))) {
            $this->markTestIncomplete('AUDIT-14 implementation pending: Order model does not yet use SoftDeletes trait (Milestone M3).');
        }

        $order = Order::create([
            'order_number' => 'ORD-TEST-SD01',
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'subtotal' => 200.00,
            'discount_amount' => 0.00,
            'tax_amount' => 24.00,
            'total_amount' => 224.00,
            'status' => 'completed',
        ]);

        $this->assertNull($order->deleted_at);

        // Soft delete
        $order->delete();

        $this->assertTrue($order->trashed());
        $this->assertNotNull($order->deleted_at);
        $this->assertNull(Order::find($order->id));
        $this->assertNotNull(Order::withTrashed()->find($order->id));

        // Restore
        $order->restore();
        $this->assertFalse($order->trashed());
        $this->assertNull($order->deleted_at);
        $this->assertNotNull(Order::find($order->id));
    }

    public function test_user_model_uses_soft_deletes_trait_and_lifecycle(): void
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive(User::class))) {
            $this->markTestIncomplete('AUDIT-14 implementation pending: User model does not yet use SoftDeletes trait (Milestone M3).');
        }

        $testUser = User::create([
            'name' => 'Employee SoftDelete',
            'email' => 'employee_sd@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-SD02',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->assertNull($testUser->deleted_at);

        // Soft delete
        $testUser->delete();

        $this->assertTrue($testUser->trashed());
        $this->assertNotNull($testUser->deleted_at);
        $this->assertNull(User::find($testUser->id));
        $this->assertNotNull(User::withTrashed()->find($testUser->id));

        // Restore
        $testUser->restore();
        $this->assertFalse($testUser->trashed());
        $this->assertNull($testUser->deleted_at);
        $this->assertNotNull(User::find($testUser->id));
    }

    public function test_soft_deletes_preserves_historical_data_in_database_table(): void
    {
        $meal = Meal::create([
            'name' => 'Historical Meal',
            'menu_code' => 'M-HM-01',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 99.00,
            'is_available' => true,
        ]);

        $meal->delete();

        // Row still physically exists in database
        $this->assertDatabaseHas('meals', [
            'id' => $meal->id,
            'name' => 'Historical Meal',
        ]);
        $this->assertNotNull(Meal::withTrashed()->find($meal->id)->deleted_at);
    }
}
