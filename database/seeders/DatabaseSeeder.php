<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with comprehensive demo data.
     */
    public function run(): void
    {
        $this->call([
            TenantSeeder::class,             // Tenants + staff users
            SubscriptionPlanSeeder::class,   // Subscription plans + assign to tenants
            MenuSeeder::class,               // Categories, menu items, variants, modifiers
            TableSeeder::class,              // Tables with QR tokens
            CustomerSeeder::class,           // Customer/member data
            InventorySeeder::class,          // Ingredients, recipes, stock movements
            OrderSeeder::class,              // Orders, items, payments (14 days of history)
            AnnouncementSeeder::class,       // Platform feature announcements
        ]);
    }
}
