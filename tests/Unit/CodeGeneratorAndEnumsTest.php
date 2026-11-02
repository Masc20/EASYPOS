<?php

namespace Tests\Unit;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\PermissionEnum;
use App\Support\Enums\StaffRole;
use App\Support\Services\CodeGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CodeGeneratorAndEnumsTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_constants_and_enums_are_properly_defined(): void
    {
        $this->assertEquals(0.12, SystemConstants::VAT_RATE);
        $this->assertEquals(0.20, SystemConstants::SENIOR_PWD_DISCOUNT_RATE);
        $this->assertEquals('₱', SystemConstants::CURRENCY_SYMBOL);

        $this->assertEquals('Completed', OrderStatus::COMPLETED->label());
        $this->assertEquals('Refunded', PaymentStatus::REFUNDED->label());
        $this->assertEquals('GCash / Maya QR', PaymentMethod::QR_TRANSFER->label());

        // Delivery must not exist on OrderType
        $orderTypeValues = array_map(fn ($c) => $c->value, OrderType::cases());
        $this->assertEquals(['dine_in', 'takeout'], $orderTypeValues);

        // Friendly Permission Labels
        $this->assertEquals('Approve Voids & Refunds', PermissionEnum::label('pos.refund'));
        $this->assertEquals('Point of Sale Terminal Access', PermissionEnum::label('pos.access'));
    }

    public function test_auto_generates_branch_code(): void
    {
        $branch1 = Branch::create([
            'name' => 'Downtown Flagship',
            'is_active' => true,
        ]);

        $this->assertEquals('DOFL', $branch1->code);

        $branch2 = Branch::create([
            'name' => 'Makati',
            'is_active' => true,
        ]);

        $this->assertEquals('MAKA', $branch2->code);
    }

    public function test_auto_generates_menu_code_based_on_category(): void
    {
        $category = MealCategory::create([
            'name' => 'Burgers',
            'slug' => 'burgers',
            'is_active' => true,
        ]);

        $meal1 = Meal::create([
            'category_id' => $category->id,
            'name' => 'Classic Cheeseburger',
            'price' => 180.00,
        ]);

        $meal2 = Meal::create([
            'category_id' => $category->id,
            'name' => 'Double Bacon Burger',
            'price' => 250.00,
        ]);

        $this->assertEquals('B-001', $meal1->menu_code);
        $this->assertEquals('B-002', $meal2->menu_code);

        $map = CodeGeneratorService::getCategoryMenuCodeMap();
        $this->assertEquals('B-003', $map[$category->id]);
    }

    public function test_auto_generates_emp_id_based_on_branch_position_and_sequence(): void
    {
        $branch = Branch::create([
            'name' => 'BGC Taguig',
            'code' => 'B01',
            'is_active' => true,
        ]);

        $empId1 = CodeGeneratorService::generateEmpId($branch->id, StaffRole::CASHIER->value);
        $this->assertEquals('B01-CSH-001', $empId1);

        User::create([
            'name' => 'First Cashier',
            'emp_id' => $empId1,
            'branch_id' => $branch->id,
            'pin_code' => Hash::make('1234'),
        ]);

        $empId2 = CodeGeneratorService::generateEmpId($branch->id, StaffRole::CASHIER->value);
        $this->assertEquals('B01-CSH-002', $empId2);

        $previewMap = CodeGeneratorService::getEmpIdPreviewMap();
        $this->assertEquals('B01-CSH-002', $previewMap["{$branch->id}:" . StaffRole::CASHIER->value]);

        $cookId = CodeGeneratorService::generateEmpId($branch->id, StaffRole::COOK->value);
        $this->assertEquals('B01-COK-001', $cookId);

        $adminId = CodeGeneratorService::generateEmpId(null, StaffRole::OWNER->value);
        $this->assertEquals('HQ-ADM-001', $adminId);
    }

    public function test_filament_modals_render_clean_html_and_reactively_update_codes(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $branch1 = Branch::firstOrCreate(['code' => 'B01'], ['name' => 'Branch One', 'is_active' => true]);
        $branch2 = Branch::firstOrCreate(['code' => 'B02'], ['name' => 'Branch Two', 'is_active' => true]);

        $cat1 = MealCategory::firstOrCreate(['slug' => 'burgers'], ['name' => 'Burgers', 'is_active' => true]);
        $cat2 = MealCategory::firstOrCreate(['slug' => 'drinks'], ['name' => 'Drinks', 'is_active' => true]);

        $owner = User::where('email', 'owner@easypos.local')->first() ?? User::create([
            'name' => 'System Owner',
            'email' => 'owner@easypos.local',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-ADM-001',
            'is_active' => true,
        ]);
        if (! $owner->hasRole(StaffRole::OWNER->value)) {
            $owner->assignRole(StaffRole::OWNER->value);
        }
        $this->actingAs($owner);

        // 1. New Branch modal: ensure no leaked JS attributes and live name->code works
        \Livewire\Livewire::test(\App\Filament\Resources\Branches\Pages\ListBranches::class)
            ->mountAction('create')
            ->assertDontSee('= 2) { code =')
            ->setActionData(['name' => 'Makati Central'])
            ->assertActionDataSet(['code' => 'MACE']);

        // 2. Meal modal: changing category_id updates menu_code
        \Livewire\Livewire::test(\App\Filament\Resources\Meals\Pages\ListMeals::class)
            ->mountAction('create')
            ->setActionData(['category_id' => $cat1->id])
            ->assertActionDataSet(['menu_code' => CodeGeneratorService::generateMenuCode($cat1->id)])
            ->setActionData(['category_id' => $cat2->id])
            ->assertActionDataSet(['menu_code' => CodeGeneratorService::generateMenuCode($cat2->id)]);

        // 3. Staff & Users modal: changing branch_id on Add Floor Staff updates emp_id
        $expectedB1 = CodeGeneratorService::generateEmpId($branch1->id, StaffRole::CASHIER->value);
        $expectedB2 = CodeGeneratorService::generateEmpId($branch2->id, StaffRole::CASHIER->value);

        \Livewire\Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)
            ->mountAction('create_floor_staff')
            ->assertActionDataSet(['emp_id' => $expectedB1])
            ->setActionData(['branch_id' => $branch2->id])
            ->assertActionDataSet(['emp_id' => $expectedB2]);

        // 4. Edit Staff User modal: changing branch_id on an existing employee updates emp_id
        $cashier = User::where('emp_id', 'B01-CSH-001')->first() ?? User::create([
            'name' => 'Jane Cashier',
            'emp_id' => 'B01-CSH-001',
            'branch_id' => $branch1->id,
            'pin_code' => '1234',
            'is_active' => true,
        ]);
        if (! $cashier->hasRole(StaffRole::CASHIER->value)) {
            $cashier->assignRole(StaffRole::CASHIER->value);
        }

        \Livewire\Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)
            ->mountTableAction('edit', $cashier)
            ->setTableActionData(['branch_id' => $branch2->id])
            ->assertTableActionDataSet(['emp_id' => CodeGeneratorService::generateEmpId($branch2->id, StaffRole::CASHIER->value)]);
    }
}
