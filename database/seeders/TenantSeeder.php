<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Seed tenants and their staff users.
     */
    public function run(): void
    {
        // Super Admin
        User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'admin@hscaffe.test',
        ]);

        // Tenant 1: Cafe Nusantara
        $cafeNusantara = Tenant::factory()->create([
            'name' => 'Cafe Nusantara',
            'slug' => 'cafe-nusantara',
            'primary_color' => '#8B4513',
            'secondary_color' => '#D2691E',
            'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
            'phone' => '021-5551234',
            'email' => 'info@cafenusantara.id',
            'tax_rate' => 10,
            'service_charge_rate' => 5,
        ]);

        User::factory()->owner($cafeNusantara)->create(['name' => 'Budi Santoso', 'email' => 'owner@cafenusantara.id']);
        User::factory()->manager($cafeNusantara)->create(['name' => 'Siti Rahayu', 'email' => 'manager@cafenusantara.id']);
        User::factory()->cashier($cafeNusantara)->create(['name' => 'Andi Pratama', 'email' => 'kasir@cafenusantara.id']);
        User::factory()->cashier($cafeNusantara)->create(['name' => 'Linda Sari', 'email' => 'kasir2@cafenusantara.id']);
        User::factory()->kitchen($cafeNusantara)->create(['name' => 'Dewi Lestari', 'email' => 'kitchen@cafenusantara.id']);
        User::factory()->kitchen($cafeNusantara)->create(['name' => 'Wahyu Saputra', 'email' => 'kitchen2@cafenusantara.id']);
        User::factory()->waiter($cafeNusantara)->create(['name' => 'Rudi Hermawan', 'email' => 'waiter@cafenusantara.id']);
        User::factory()->waiter($cafeNusantara)->create(['name' => 'Dina Putri', 'email' => 'waiter2@cafenusantara.id']);

        // Tenant 2: Kopi Kita
        $kopiKita = Tenant::factory()->create([
            'name' => 'Kopi Kita',
            'slug' => 'kopi-kita',
            'primary_color' => '#2D5016',
            'secondary_color' => '#4A7C2E',
            'address' => 'Jl. Gatot Subroto No. 45, Jakarta Selatan',
            'phone' => '021-5559876',
            'email' => 'hello@kopikita.id',
            'tax_rate' => 10,
            'service_charge_rate' => 0,
        ]);

        User::factory()->owner($kopiKita)->create(['name' => 'Agus Wijaya', 'email' => 'owner@kopikita.id']);
        User::factory()->manager($kopiKita)->create(['name' => 'Maya Anggraini', 'email' => 'manager@kopikita.id']);
        User::factory()->cashier($kopiKita)->create(['name' => 'Rina Marlina', 'email' => 'kasir@kopikita.id']);
        User::factory()->kitchen($kopiKita)->create(['name' => 'Hendra Gunawan', 'email' => 'kitchen@kopikita.id']);
        User::factory()->waiter($kopiKita)->create(['name' => 'Fajar Nugroho', 'email' => 'waiter@kopikita.id']);

        // Tenant 3: Warung Sederhana (inactive)
        Tenant::factory()->create([
            'name' => 'Warung Sederhana',
            'slug' => 'warung-sederhana',
            'primary_color' => '#B22222',
            'secondary_color' => '#DC143C',
            'address' => 'Jl. Merdeka No. 88, Bandung',
            'phone' => '022-1234567',
            'email' => 'info@warungsederhana.id',
            'is_active' => false,
        ]);
    }
}
