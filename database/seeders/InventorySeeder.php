<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * Seed ingredients, recipes, and stock movements for each active tenant.
     */
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $this->seedInventoryForTenant($tenant);
        }
    }

    private function seedInventoryForTenant(Tenant $tenant): void
    {
        $manager = User::where('tenant_id', $tenant->id)->where('role', 'owner')->first();

        // ── Create Ingredients ──
        $ingredients = $this->createIngredients($tenant);

        // ── Create Recipes (linking menu items to ingredients) ──
        $this->createRecipes($tenant, $ingredients);

        // ── Create Stock Movements (history) ──
        $this->createStockMovements($tenant, $ingredients, $manager);
    }

    /**
     * @return array<string, Ingredient>
     */
    private function createIngredients(Tenant $tenant): array
    {
        $data = [
            ['name' => 'Kopi Arabica', 'unit' => 'kg', 'current_stock' => 15.5, 'minimum_stock' => 5, 'cost_per_unit' => 120000],
            ['name' => 'Kopi Robusta', 'unit' => 'kg', 'current_stock' => 8.0, 'minimum_stock' => 3, 'cost_per_unit' => 80000],
            ['name' => 'Susu Segar', 'unit' => 'liter', 'current_stock' => 25.0, 'minimum_stock' => 10, 'cost_per_unit' => 18000],
            ['name' => 'Susu Oat', 'unit' => 'liter', 'current_stock' => 6.0, 'minimum_stock' => 3, 'cost_per_unit' => 45000],
            ['name' => 'Gula Pasir', 'unit' => 'kg', 'current_stock' => 20.0, 'minimum_stock' => 5, 'cost_per_unit' => 15000],
            ['name' => 'Gula Aren', 'unit' => 'kg', 'current_stock' => 4.0, 'minimum_stock' => 2, 'cost_per_unit' => 35000],
            ['name' => 'Cokelat Bubuk', 'unit' => 'kg', 'current_stock' => 3.0, 'minimum_stock' => 1.5, 'cost_per_unit' => 85000],
            ['name' => 'Matcha Powder', 'unit' => 'kg', 'current_stock' => 1.2, 'minimum_stock' => 0.5, 'cost_per_unit' => 250000],
            ['name' => 'Es Batu', 'unit' => 'kg', 'current_stock' => 50.0, 'minimum_stock' => 20, 'cost_per_unit' => 3000],
            ['name' => 'Beras', 'unit' => 'kg', 'current_stock' => 30.0, 'minimum_stock' => 10, 'cost_per_unit' => 14000],
            ['name' => 'Mie Instant', 'unit' => 'pcs', 'current_stock' => 100, 'minimum_stock' => 30, 'cost_per_unit' => 3500],
            ['name' => 'Ayam Fillet', 'unit' => 'kg', 'current_stock' => 8.0, 'minimum_stock' => 3, 'cost_per_unit' => 55000],
            ['name' => 'Daging Sapi', 'unit' => 'kg', 'current_stock' => 3.5, 'minimum_stock' => 2, 'cost_per_unit' => 130000],
            ['name' => 'Telur', 'unit' => 'pcs', 'current_stock' => 120, 'minimum_stock' => 30, 'cost_per_unit' => 2500],
            ['name' => 'Roti Tawar', 'unit' => 'pcs', 'current_stock' => 15, 'minimum_stock' => 5, 'cost_per_unit' => 15000],
            ['name' => 'Kentang', 'unit' => 'kg', 'current_stock' => 10.0, 'minimum_stock' => 3, 'cost_per_unit' => 18000],
            ['name' => 'Keju Cheddar', 'unit' => 'kg', 'current_stock' => 2.0, 'minimum_stock' => 1, 'cost_per_unit' => 95000],
            ['name' => 'Tepung Terigu', 'unit' => 'kg', 'current_stock' => 12.0, 'minimum_stock' => 5, 'cost_per_unit' => 12000],
            ['name' => 'Minyak Goreng', 'unit' => 'liter', 'current_stock' => 8.0, 'minimum_stock' => 3, 'cost_per_unit' => 18000],
            ['name' => 'Pisang', 'unit' => 'kg', 'current_stock' => 5.0, 'minimum_stock' => 2, 'cost_per_unit' => 12000],
            // Low stock items for alerts
            ['name' => 'Cream Cheese', 'unit' => 'kg', 'current_stock' => 0.3, 'minimum_stock' => 1, 'cost_per_unit' => 150000],
            ['name' => 'Maple Syrup', 'unit' => 'liter', 'current_stock' => 0.2, 'minimum_stock' => 0.5, 'cost_per_unit' => 180000],
            ['name' => 'Vanilla Extract', 'unit' => 'liter', 'current_stock' => 0.05, 'minimum_stock' => 0.1, 'cost_per_unit' => 350000],
        ];

        $ingredients = [];
        foreach ($data as $item) {
            $ingredient = Ingredient::create([...$item, 'tenant_id' => $tenant->id]);
            $ingredients[$item['name']] = $ingredient;
        }

        return $ingredients;
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     */
    private function createRecipes(Tenant $tenant, array $ingredients): void
    {
        // Map menu item names to ingredient recipes
        $recipeMap = [
            'Americano' => [['Kopi Arabica', 0.018], ['Es Batu', 0.1]],
            'Cappuccino' => [['Kopi Arabica', 0.018], ['Susu Segar', 0.15], ['Es Batu', 0.1]],
            'Caffe Latte' => [['Kopi Arabica', 0.018], ['Susu Segar', 0.2], ['Es Batu', 0.1]],
            'Mocha Latte' => [['Kopi Arabica', 0.018], ['Susu Segar', 0.15], ['Cokelat Bubuk', 0.015], ['Es Batu', 0.1]],
            'Espresso' => [['Kopi Arabica', 0.018]],
            'Kopi Susu Gula Aren' => [['Kopi Robusta', 0.02], ['Susu Segar', 0.15], ['Gula Aren', 0.02], ['Es Batu', 0.15]],
            'Cold Brew' => [['Kopi Arabica', 0.03], ['Es Batu', 0.15]],
            'Matcha Latte' => [['Matcha Powder', 0.005], ['Susu Segar', 0.2], ['Gula Pasir', 0.01], ['Es Batu', 0.1]],
            'Cokelat Panas' => [['Cokelat Bubuk', 0.025], ['Susu Segar', 0.2], ['Gula Pasir', 0.015]],
            'Nasi Goreng Spesial' => [['Beras', 0.15], ['Telur', 1], ['Minyak Goreng', 0.03], ['Ayam Fillet', 0.05]],
            'Mie Goreng Jawa' => [['Mie Instant', 1], ['Telur', 1], ['Minyak Goreng', 0.02]],
            'Chicken Steak' => [['Ayam Fillet', 0.2], ['Kentang', 0.1], ['Minyak Goreng', 0.05]],
            'Spaghetti Bolognese' => [['Daging Sapi', 0.1], ['Minyak Goreng', 0.02], ['Keju Cheddar', 0.02]],
            'French Fries' => [['Kentang', 0.2], ['Minyak Goreng', 0.1]],
            'Roti Bakar' => [['Roti Tawar', 1], ['Keju Cheddar', 0.03]],
            'Pisang Goreng Crispy' => [['Pisang', 0.2], ['Tepung Terigu', 0.05], ['Minyak Goreng', 0.05]],
            'Pancake' => [['Tepung Terigu', 0.1], ['Telur', 2], ['Susu Segar', 0.1], ['Maple Syrup', 0.03]],
        ];

        $menuItems = MenuItem::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()->keyBy('name');

        foreach ($recipeMap as $menuName => $recipeIngredients) {
            $menuItem = $menuItems->get($menuName);
            if (! $menuItem) {
                continue;
            }

            foreach ($recipeIngredients as [$ingredientName, $qty]) {
                $ingredient = $ingredients[$ingredientName] ?? null;
                if (! $ingredient) {
                    continue;
                }

                Recipe::create([
                    'menu_item_id' => $menuItem->id,
                    'ingredient_id' => $ingredient->id,
                    'quantity_needed' => $qty,
                ]);
            }
        }
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     */
    private function createStockMovements(Tenant $tenant, array $ingredients, ?User $user): void
    {
        $userId = $user?->id;

        // Create initial stock-in movements (received stock)
        foreach ($ingredients as $ingredient) {
            // Initial large stock-in (7 days ago)
            StockMovement::create([
                'tenant_id' => $tenant->id,
                'ingredient_id' => $ingredient->id,
                'type' => StockMovementType::In->value,
                'quantity' => $ingredient->current_stock * 1.5,
                'cost_per_unit' => $ingredient->cost_per_unit,
                'reference' => 'PO-'.str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT),
                'notes' => 'Stok awal pengisian',
                'user_id' => $userId,
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(7),
            ]);
        }

        // Some usage movements over the past week
        $usageItems = ['Kopi Arabica', 'Susu Segar', 'Gula Pasir', 'Es Batu', 'Telur', 'Beras', 'Minyak Goreng'];
        foreach ($usageItems as $name) {
            $ingredient = $ingredients[$name] ?? null;
            if (! $ingredient) {
                continue;
            }

            for ($day = 5; $day >= 1; $day--) {
                StockMovement::create([
                    'tenant_id' => $tenant->id,
                    'ingredient_id' => $ingredient->id,
                    'type' => StockMovementType::OrderDeduct->value,
                    'quantity' => rand(1, 5) * 0.5,
                    'cost_per_unit' => $ingredient->cost_per_unit,
                    'reference' => 'Auto deduct',
                    'notes' => 'Pengurangan otomatis dari pesanan',
                    'user_id' => null,
                    'created_at' => now()->subDays($day),
                    'updated_at' => now()->subDays($day),
                ]);
            }
        }

        // A few waste entries
        $wasteItems = ['Susu Segar', 'Ayam Fillet', 'Roti Tawar'];
        foreach ($wasteItems as $name) {
            $ingredient = $ingredients[$name] ?? null;
            if (! $ingredient) {
                continue;
            }

            StockMovement::create([
                'tenant_id' => $tenant->id,
                'ingredient_id' => $ingredient->id,
                'type' => StockMovementType::Waste->value,
                'quantity' => rand(1, 3) * 0.5,
                'cost_per_unit' => $ingredient->cost_per_unit,
                'reference' => null,
                'notes' => 'Kadaluarsa / rusak',
                'user_id' => $userId,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);
        }

        // A restock yesterday
        $restockItems = ['Susu Segar', 'Telur', 'Es Batu'];
        foreach ($restockItems as $name) {
            $ingredient = $ingredients[$name] ?? null;
            if (! $ingredient) {
                continue;
            }

            StockMovement::create([
                'tenant_id' => $tenant->id,
                'ingredient_id' => $ingredient->id,
                'type' => StockMovementType::In->value,
                'quantity' => rand(5, 20),
                'cost_per_unit' => $ingredient->cost_per_unit,
                'reference' => 'PO-'.str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT),
                'notes' => 'Restok harian',
                'user_id' => $userId,
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ]);
        }

        // Stock adjustment
        if ($adj = $ingredients['Gula Pasir'] ?? null) {
            StockMovement::create([
                'tenant_id' => $tenant->id,
                'ingredient_id' => $adj->id,
                'type' => StockMovementType::Adjustment->value,
                'quantity' => -1.5,
                'cost_per_unit' => $adj->cost_per_unit,
                'reference' => 'Stock Opname',
                'notes' => 'Penyesuaian setelah stock opname',
                'user_id' => $userId,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]);
        }
    }
}
