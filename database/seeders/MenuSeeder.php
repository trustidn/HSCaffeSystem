<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MenuModifier;
use App\Models\MenuVariant;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    /**
     * Seed categories, menu items, variants, and modifiers for each tenant.
     */
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $this->seedMenuForTenant($tenant);
        }
    }

    private function seedMenuForTenant(Tenant $tenant): void
    {
        // ── Categories ──
        $categories = $this->createCategories($tenant);

        // ── Modifiers (shared across menu items) ──
        $modifiers = $this->createModifiers($tenant);

        // ── Menu Items per Category ──
        $menuData = $this->getMenuData();

        foreach ($menuData as $categoryName => $items) {
            $category = $categories[$categoryName] ?? null;
            if (! $category) {
                continue;
            }

            foreach ($items as $sort => $item) {
                $menuItem = MenuItem::create([
                    'tenant_id' => $tenant->id,
                    'category_id' => $category->id,
                    'name' => $item['name'],
                    'slug' => Str::slug($item['name']).'-'.$tenant->id,
                    'description' => $item['description'],
                    'base_price' => $item['price'],
                    'sort_order' => $sort,
                    'is_active' => true,
                    'is_available' => $item['available'] ?? true,
                ]);

                // Create variants if defined
                if (! empty($item['variants'])) {
                    foreach ($item['variants'] as $vSort => $variant) {
                        MenuVariant::create([
                            'menu_item_id' => $menuItem->id,
                            'name' => $variant['name'],
                            'price' => $variant['price'],
                            'sort_order' => $vSort,
                        ]);
                    }
                }

                // Attach modifiers (coffee/drink items get drink modifiers, food gets food modifiers)
                $attachModifiers = $item['modifier_type'] ?? null;
                if ($attachModifiers && isset($modifiers[$attachModifiers])) {
                    $menuItem->modifiers()->attach(
                        $modifiers[$attachModifiers]->pluck('id')
                    );
                }
            }
        }
    }

    /**
     * @return array<string, Category>
     */
    private function createCategories(Tenant $tenant): array
    {
        $data = [
            ['name' => 'Kopi', 'slug' => 'kopi', 'description' => 'Aneka minuman kopi pilihan', 'sort_order' => 1],
            ['name' => 'Non-Kopi', 'slug' => 'non-kopi', 'description' => 'Minuman segar non-kopi', 'sort_order' => 2],
            ['name' => 'Makanan Berat', 'slug' => 'makanan-berat', 'description' => 'Menu utama untuk mengenyangkan', 'sort_order' => 3],
            ['name' => 'Snack', 'slug' => 'snack', 'description' => 'Camilan ringan teman ngopi', 'sort_order' => 4],
            ['name' => 'Dessert', 'slug' => 'dessert', 'description' => 'Hidangan penutup manis', 'sort_order' => 5],
            ['name' => 'Paket Hemat', 'slug' => 'paket-hemat', 'description' => 'Kombinasi hemat makanan dan minuman', 'sort_order' => 6],
        ];

        $categories = [];
        foreach ($data as $cat) {
            $categories[$cat['name']] = Category::create([
                'tenant_id' => $tenant->id,
                'name' => $cat['name'],
                'slug' => $cat['slug'].'-'.$tenant->id,
                'description' => $cat['description'],
                'sort_order' => $cat['sort_order'],
            ]);
        }

        return $categories;
    }

    /**
     * @return array<string, \Illuminate\Support\Collection>
     */
    private function createModifiers(Tenant $tenant): array
    {
        $drinkModifiers = collect([
            ['name' => 'Extra Shot', 'price' => 5000],
            ['name' => 'Less Sugar', 'price' => 0],
            ['name' => 'No Sugar', 'price' => 0],
            ['name' => 'Oat Milk', 'price' => 8000],
            ['name' => 'Soy Milk', 'price' => 5000],
            ['name' => 'Extra Syrup', 'price' => 3000],
        ])->map(fn ($m) => MenuModifier::create([...$m, 'tenant_id' => $tenant->id]));

        $foodModifiers = collect([
            ['name' => 'Extra Cheese', 'price' => 5000],
            ['name' => 'Extra Egg', 'price' => 4000],
            ['name' => 'Extra Spicy', 'price' => 0],
            ['name' => 'No Onion', 'price' => 0],
            ['name' => 'Extra Sauce', 'price' => 3000],
        ])->map(fn ($m) => MenuModifier::create([...$m, 'tenant_id' => $tenant->id]));

        return [
            'drink' => $drinkModifiers,
            'food' => $foodModifiers,
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function getMenuData(): array
    {
        return [
            'Kopi' => [
                ['name' => 'Americano', 'description' => 'Espresso dengan air panas, cita rasa kopi murni.', 'price' => 22000, 'modifier_type' => 'drink', 'variants' => [
                    ['name' => 'Hot', 'price' => 22000],
                    ['name' => 'Iced', 'price' => 25000],
                ]],
                ['name' => 'Cappuccino', 'description' => 'Espresso, steamed milk, dan foam lembut.', 'price' => 28000, 'modifier_type' => 'drink', 'variants' => [
                    ['name' => 'Hot', 'price' => 28000],
                    ['name' => 'Iced', 'price' => 30000],
                ]],
                ['name' => 'Caffe Latte', 'description' => 'Espresso dengan susu steamed creamy.', 'price' => 30000, 'modifier_type' => 'drink', 'variants' => [
                    ['name' => 'Hot', 'price' => 30000],
                    ['name' => 'Iced', 'price' => 32000],
                ]],
                ['name' => 'Mocha Latte', 'description' => 'Perpaduan espresso, susu, dan cokelat.', 'price' => 32000, 'modifier_type' => 'drink', 'variants' => [
                    ['name' => 'Hot', 'price' => 32000],
                    ['name' => 'Iced', 'price' => 35000],
                ]],
                ['name' => 'Espresso', 'description' => 'Shot espresso murni, tebal dan kuat.', 'price' => 18000, 'modifier_type' => 'drink'],
                ['name' => 'Kopi Susu Gula Aren', 'description' => 'Kopi susu khas dengan gula aren asli.', 'price' => 25000, 'modifier_type' => 'drink', 'variants' => [
                    ['name' => 'Regular', 'price' => 25000],
                    ['name' => 'Large', 'price' => 32000],
                ]],
                ['name' => 'Affogato', 'description' => 'Espresso dituang di atas gelato vanilla.', 'price' => 35000, 'modifier_type' => 'drink'],
                ['name' => 'Cold Brew', 'description' => 'Kopi seduh dingin 12 jam, smooth dan bold.', 'price' => 28000, 'modifier_type' => 'drink'],
            ],
            'Non-Kopi' => [
                ['name' => 'Matcha Latte', 'description' => 'Matcha premium Jepang dengan susu.', 'price' => 30000, 'modifier_type' => 'drink', 'variants' => [
                    ['name' => 'Hot', 'price' => 30000],
                    ['name' => 'Iced', 'price' => 32000],
                ]],
                ['name' => 'Cokelat Panas', 'description' => 'Dark chocolate Belgia dengan susu.', 'price' => 28000, 'modifier_type' => 'drink', 'variants' => [
                    ['name' => 'Hot', 'price' => 28000],
                    ['name' => 'Iced', 'price' => 30000],
                ]],
                ['name' => 'Es Teh Manis', 'description' => 'Teh manis segar yang menyegarkan.', 'price' => 10000, 'modifier_type' => 'drink'],
                ['name' => 'Lemon Tea', 'description' => 'Teh dengan irisan lemon segar.', 'price' => 15000, 'modifier_type' => 'drink', 'variants' => [
                    ['name' => 'Hot', 'price' => 15000],
                    ['name' => 'Iced', 'price' => 18000],
                ]],
                ['name' => 'Jus Jeruk', 'description' => 'Jus jeruk segar peras langsung.', 'price' => 20000, 'modifier_type' => 'drink'],
                ['name' => 'Jus Alpukat', 'description' => 'Alpukat segar blended dengan susu.', 'price' => 22000, 'modifier_type' => 'drink'],
                ['name' => 'Milkshake Oreo', 'description' => 'Milkshake tebal dengan Oreo crush.', 'price' => 28000, 'modifier_type' => 'drink'],
                ['name' => 'Air Mineral', 'description' => 'Air mineral botol 600ml.', 'price' => 8000],
            ],
            'Makanan Berat' => [
                ['name' => 'Nasi Goreng Spesial', 'description' => 'Nasi goreng dengan telur, ayam, dan kerupuk.', 'price' => 35000, 'modifier_type' => 'food'],
                ['name' => 'Mie Goreng Jawa', 'description' => 'Mie goreng bumbu Jawa dengan sayuran segar.', 'price' => 30000, 'modifier_type' => 'food'],
                ['name' => 'Chicken Steak', 'description' => 'Dada ayam panggang dengan saus mushroom.', 'price' => 45000, 'modifier_type' => 'food'],
                ['name' => 'Spaghetti Bolognese', 'description' => 'Pasta dengan saus daging sapi khas Italia.', 'price' => 40000, 'modifier_type' => 'food'],
                ['name' => 'Rice Bowl Teriyaki', 'description' => 'Nasi dengan ayam teriyaki dan sayuran.', 'price' => 35000, 'modifier_type' => 'food'],
                ['name' => 'Club Sandwich', 'description' => 'Triple decker sandwich dengan ayam dan telur.', 'price' => 38000, 'modifier_type' => 'food'],
                ['name' => 'Nasi Ayam Geprek', 'description' => 'Nasi dengan ayam geprek sambal bawang.', 'price' => 30000, 'modifier_type' => 'food', 'variants' => [
                    ['name' => 'Level 1', 'price' => 30000],
                    ['name' => 'Level 3', 'price' => 30000],
                    ['name' => 'Level 5', 'price' => 32000],
                ]],
            ],
            'Snack' => [
                ['name' => 'French Fries', 'description' => 'Kentang goreng renyah dengan saus.', 'price' => 20000, 'modifier_type' => 'food'],
                ['name' => 'Chicken Wings', 'description' => '6 pcs sayap ayam goreng crispy.', 'price' => 28000, 'modifier_type' => 'food', 'variants' => [
                    ['name' => 'Original', 'price' => 28000],
                    ['name' => 'BBQ', 'price' => 30000],
                    ['name' => 'Spicy', 'price' => 30000],
                ]],
                ['name' => 'Roti Bakar', 'description' => 'Roti bakar dengan berbagai topping.', 'price' => 18000, 'modifier_type' => 'food', 'variants' => [
                    ['name' => 'Cokelat', 'price' => 18000],
                    ['name' => 'Keju', 'price' => 20000],
                    ['name' => 'Cokelat Keju', 'price' => 22000],
                ]],
                ['name' => 'Pisang Goreng Crispy', 'description' => 'Pisang goreng tepung renyah.', 'price' => 15000],
                ['name' => 'Dimsum', 'description' => 'Aneka dimsum kukus segar (5 pcs).', 'price' => 25000, 'modifier_type' => 'food'],
                ['name' => 'Onion Rings', 'description' => 'Bawang bombay goreng tepung renyah.', 'price' => 18000],
            ],
            'Dessert' => [
                ['name' => 'Brownies', 'description' => 'Brownies cokelat lembut dan fudgy.', 'price' => 22000],
                ['name' => 'Cheesecake', 'description' => 'New York style cheesecake creamy.', 'price' => 28000],
                ['name' => 'Tiramisu', 'description' => 'Classic tiramisu dengan mascarpone.', 'price' => 30000],
                ['name' => 'Banana Split', 'description' => 'Pisang dengan 3 scoop ice cream dan topping.', 'price' => 32000],
                ['name' => 'Pancake', 'description' => 'Fluffy pancake dengan maple syrup.', 'price' => 25000, 'variants' => [
                    ['name' => 'Classic', 'price' => 25000],
                    ['name' => 'Berries', 'price' => 30000],
                    ['name' => 'Banana Chocolate', 'price' => 28000],
                ]],
                ['name' => 'Es Krim', 'description' => 'Gelato artisan homemade.', 'price' => 18000, 'variants' => [
                    ['name' => '1 Scoop', 'price' => 18000],
                    ['name' => '2 Scoop', 'price' => 28000],
                    ['name' => '3 Scoop', 'price' => 35000],
                ]],
            ],
            'Paket Hemat' => [
                ['name' => 'Paket Nasi Goreng + Es Teh', 'description' => 'Nasi goreng spesial + es teh manis.', 'price' => 40000],
                ['name' => 'Paket Mie Goreng + Kopi', 'description' => 'Mie goreng + kopi susu gula aren.', 'price' => 45000],
                ['name' => 'Paket Snack Time', 'description' => 'French fries + chicken wings + lemon tea.', 'price' => 55000],
                ['name' => 'Paket Dessert', 'description' => 'Brownies + cappuccino.', 'price' => 42000],
            ],
        ];
    }
}
