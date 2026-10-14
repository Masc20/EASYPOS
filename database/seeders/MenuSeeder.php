<?php

namespace Database\Seeders;

use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds for initial restaurant menu catalog (in Philippine Peso ₱).
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Mains',
                'slug' => 'mains',
                'display_order' => 1,
                'is_active' => true,
                'meals' => [
                    [
                        'name' => 'Truffle Mushroom Risotto',
                        'menu_code' => 'M-01',
                        'description' => 'Arborio rice with wild forest mushrooms, white truffle oil, and 24-month aged parmesan.',
                        'price' => 420.00, // ₱420.00
                        'cost_price' => 135.00,
                        'is_available' => true,
                        'track_stock' => false,
                        'kitchen_tags' => ['vegetarian', 'gluten_free', 'bestseller'],
                    ],
                    [
                        'name' => 'Grilled Ribeye Steak (10oz)',
                        'menu_code' => 'M-02',
                        'description' => 'Prime cut ribeye with garlic herb butter, roasted vine tomatoes, and rosemary jus.',
                        'price' => 1450.00, // ₱1,450.00
                        'cost_price' => 650.00,
                        'is_available' => true,
                        'track_stock' => true,
                        'stock_quantity' => 15, // Limited daily steak supply
                        'kitchen_tags' => ['chef_favorite', 'gluten_free'],
                    ],
                    [
                        'name' => 'Pan-Seared Salmon Fillet',
                        'menu_code' => 'M-03',
                        'description' => 'Crispy skin Atlantic salmon over asparagus spears with lemon dill beurre blanc.',
                        'price' => 580.00, // ₱580.00
                        'cost_price' => 220.00,
                        'is_available' => true,
                        'track_stock' => false,
                        'kitchen_tags' => ['pescatarian', 'gluten_free'],
                    ],
                    [
                        'name' => 'Classic Chicken Parmigiana',
                        'menu_code' => 'M-04',
                        'description' => 'Breaded chicken breast topped with marinara sauce and melted fresh mozzarella.',
                        'price' => 340.00, // ₱340.00
                        'cost_price' => 110.00,
                        'is_available' => true,
                        'track_stock' => false,
                        'kitchen_tags' => ['comfort_food'],
                    ],
                ],
            ],
            [
                'name' => 'Starters & Appetizers',
                'slug' => 'starters',
                'display_order' => 2,
                'is_active' => true,
                'meals' => [
                    [
                        'name' => 'Crispy Calamari Fritti',
                        'menu_code' => 'S-01',
                        'description' => 'Tender squid rings lightly dusted in semolina with spicy garlic aioli and lemon wedges.',
                        'price' => 280.00, // ₱280.00
                        'cost_price' => 85.00,
                        'is_available' => true,
                        'track_stock' => false,
                        'kitchen_tags' => ['seafood', 'bestseller'],
                    ],
                    [
                        'name' => 'Heirloom Tomato Bruschetta',
                        'menu_code' => 'S-02',
                        'description' => 'Toasted sourdough with marinated heirloom tomatoes, fresh basil, garlic, and balsamic glaze.',
                        'price' => 195.00, // ₱195.00
                        'cost_price' => 55.00,
                        'is_available' => true,
                        'track_stock' => false,
                        'kitchen_tags' => ['vegetarian', 'vegan'],
                    ],
                ],
            ],
            [
                'name' => 'Chef Specials',
                'slug' => 'chef-specials',
                'display_order' => 3,
                'is_active' => true,
                'meals' => [
                    [
                        'name' => 'Slow-Braised Lamb Shank',
                        'menu_code' => 'SP-01',
                        'description' => '12-hour braised lamb shank in red wine reduction over creamy polenta.',
                        'price' => 890.00, // ₱890.00
                        'cost_price' => 320.00,
                        'is_available' => true,
                        'track_stock' => true,
                        'stock_quantity' => 10, // Daily limited batch
                        'kitchen_tags' => ['daily_special', 'signature'],
                    ],
                ],
            ],
            [
                'name' => 'Beverages',
                'slug' => 'beverages',
                'display_order' => 4,
                'is_active' => true,
                'meals' => [
                    [
                        'name' => 'Handcrafted Passionfruit Iced Tea',
                        'menu_code' => 'B-01',
                        'description' => 'Freshly brewed black tea infused with natural passionfruit purée and fresh mint.',
                        'price' => 120.00, // ₱120.00
                        'cost_price' => 28.00,
                        'is_available' => true,
                        'track_stock' => false,
                        'kitchen_tags' => ['refreshing', 'vegan'],
                    ],
                    [
                        'name' => 'Espresso / Americano',
                        'menu_code' => 'B-02',
                        'description' => 'Double shot of locally roasted Arabica espresso.',
                        'price' => 110.00, // ₱110.00
                        'cost_price' => 20.00,
                        'is_available' => true,
                        'track_stock' => false,
                        'kitchen_tags' => ['hot', 'coffee'],
                    ],
                ],
            ],
            [
                'name' => 'Desserts',
                'slug' => 'desserts',
                'display_order' => 5,
                'is_active' => true,
                'meals' => [
                    [
                        'name' => 'Warm Molten Lava Cake',
                        'menu_code' => 'D-01',
                        'description' => 'Rich Belgian chocolate fondant served with Madagascar vanilla bean gelato.',
                        'price' => 240.00, // ₱240.00
                        'cost_price' => 70.00,
                        'is_available' => true,
                        'track_stock' => false,
                        'kitchen_tags' => ['vegetarian', 'dessert'],
                    ],
                ],
            ],
        ];

        foreach ($categories as $catData) {
            $meals = $catData['meals'];
            unset($catData['meals']);

            $category = MealCategory::firstOrCreate(
                ['slug' => $catData['slug']],
                $catData
            );

            foreach ($meals as $mealData) {
                Meal::updateOrCreate(
                    ['menu_code' => $mealData['menu_code']],
                    array_merge($mealData, [
                        'category_id' => $category->id,
                        'branch_id' => null, // Shared across all branches
                        'is_active' => true,
                    ])
                );
            }
        }
    }
}
