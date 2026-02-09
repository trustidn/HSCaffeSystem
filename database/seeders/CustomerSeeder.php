<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Seed customers for each active tenant.
     */
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $this->seedCustomersForTenant($tenant);
        }
    }

    private function seedCustomersForTenant(Tenant $tenant): void
    {
        $customers = [
            ['name' => 'Ahmad Fauzi', 'email' => 'ahmad.fauzi@email.com', 'phone' => '081234567890', 'address' => 'Jl. Kebon Jeruk No. 12, Jakarta'],
            ['name' => 'Putri Handayani', 'email' => 'putri.h@email.com', 'phone' => '082345678901', 'address' => 'Jl. Menteng No. 45, Jakarta'],
            ['name' => 'Rizky Ramadhan', 'email' => 'rizky.r@email.com', 'phone' => '083456789012', 'address' => null],
            ['name' => 'Nurul Hidayah', 'email' => null, 'phone' => '084567890123', 'address' => 'Jl. Cempaka Putih No. 88'],
            ['name' => 'Bayu Setiawan', 'email' => 'bayu.s@email.com', 'phone' => '085678901234', 'address' => null],
            ['name' => 'Anisa Fitri', 'email' => 'anisa.f@email.com', 'phone' => '086789012345', 'address' => 'Jl. Kemang Raya No. 21, Jakarta Selatan'],
            ['name' => 'Dedi Kurniawan', 'email' => null, 'phone' => '087890123456', 'address' => null],
            ['name' => 'Ratna Dewi', 'email' => 'ratna.d@email.com', 'phone' => '088901234567', 'address' => 'Jl. Bintaro No. 5, Tangerang Selatan'],
            ['name' => 'Irfan Hakim', 'email' => null, 'phone' => '089012345678', 'address' => null],
            ['name' => 'Sari Mulyani', 'email' => 'sari.m@email.com', 'phone' => '081122334455', 'address' => 'Jl. Pondok Indah No. 33'],
            ['name' => 'Tono Suherman', 'email' => null, 'phone' => '082233445566', 'address' => null],
            ['name' => 'Wulan Sari', 'email' => 'wulan@email.com', 'phone' => '083344556677', 'address' => 'Jl. Tebet No. 15'],
        ];

        foreach ($customers as $customer) {
            Customer::create([...$customer, 'tenant_id' => $tenant->id]);
        }
    }
}
