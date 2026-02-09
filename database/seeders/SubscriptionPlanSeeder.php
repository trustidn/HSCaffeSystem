<?php

namespace Database\Seeders;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Seed subscription plans and assign subscriptions to tenants.
     */
    public function run(): void
    {
        // Create subscription plans
        $plans = [
            [
                'name' => 'Paket Starter',
                'duration_months' => 1,
                'price' => 199000,
                'description' => 'Cocok untuk cafe baru yang ingin mencoba platform.',
                'features' => [
                    'Menu Management',
                    'POS / Kasir',
                    'Kitchen Display',
                    'Manajemen Meja',
                    'QR Code Ordering',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Paket Basic',
                'duration_months' => 3,
                'price' => 499000,
                'description' => 'Paket 3 bulan dengan harga lebih hemat.',
                'features' => [
                    'Semua fitur Starter',
                    'Manajemen Inventaris',
                    'Laporan Penjualan',
                    'Manajemen Pelanggan',
                    'Export PDF & Excel',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Paket Professional',
                'duration_months' => 6,
                'price' => 899000,
                'description' => 'Ideal untuk cafe yang berkembang dengan fitur lengkap.',
                'features' => [
                    'Semua fitur Basic',
                    'Multi Staff Management',
                    'Laporan Stok Detail',
                    'Laporan Keuangan',
                    'Notifikasi Stok Rendah',
                    'Priority Support',
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Paket Enterprise',
                'duration_months' => 12,
                'price' => 1499000,
                'description' => 'Paket tahunan terlengkap dengan penghematan maksimal.',
                'features' => [
                    'Semua fitur Professional',
                    'Custom Branding',
                    'API Access',
                    'Dedicated Support',
                    'Unlimited Staff',
                    'Advanced Analytics',
                    'Backup Harian',
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }

        // Assign subscriptions to existing tenants
        $tenants = Tenant::all();
        $allPlans = SubscriptionPlan::all();

        foreach ($tenants as $index => $tenant) {
            if (! $tenant->is_active) {
                // Inactive tenant gets an expired subscription
                $plan = $allPlans->where('duration_months', 1)->first();
                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'subscription_plan_id' => $plan->id,
                    'starts_at' => now()->subMonths(2),
                    'ends_at' => now()->subDays(15),
                    'price_paid' => $plan->price,
                    'status' => SubscriptionStatus::Expired,
                    'payment_reference' => 'TRX-'.now()->subMonths(2)->format('Ymd').'-'.str_pad($tenant->id, 4, '0', STR_PAD_LEFT),
                    'notes' => 'Langganan telah berakhir.',
                ]);

                continue;
            }

            // Active tenants get current active subscription
            $plan = $allPlans[$index % $allPlans->count()];
            $startsAt = now()->subDays(rand(5, 20));
            $endsAt = $startsAt->copy()->addMonths($plan->duration_months);

            Subscription::create([
                'tenant_id' => $tenant->id,
                'subscription_plan_id' => $plan->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'price_paid' => $plan->price,
                'status' => SubscriptionStatus::Active,
                'payment_reference' => 'TRX-'.$startsAt->format('Ymd').'-'.str_pad($tenant->id, 4, '0', STR_PAD_LEFT),
            ]);

            // Add a previous expired subscription for history
            if ($index > 0) {
                $prevPlan = $allPlans->random();
                $prevStart = now()->subMonths($prevPlan->duration_months + 1);
                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'subscription_plan_id' => $prevPlan->id,
                    'starts_at' => $prevStart,
                    'ends_at' => $prevStart->copy()->addMonths($prevPlan->duration_months),
                    'price_paid' => $prevPlan->price,
                    'status' => SubscriptionStatus::Expired,
                    'payment_reference' => 'TRX-'.$prevStart->format('Ymd').'-'.str_pad($tenant->id, 4, '0', STR_PAD_LEFT),
                    'notes' => 'Langganan sebelumnya.',
                ]);
            }
        }
    }
}
