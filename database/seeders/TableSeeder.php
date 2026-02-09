<?php

namespace Database\Seeders;

use App\Models\Table;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    /**
     * Seed tables for each active tenant.
     */
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $this->seedTablesForTenant($tenant);
        }
    }

    private function seedTablesForTenant(Tenant $tenant): void
    {
        $tables = [
            // Indoor section
            ['number' => 'A1', 'section' => 'Indoor', 'capacity' => 2, 'status' => 'available'],
            ['number' => 'A2', 'section' => 'Indoor', 'capacity' => 2, 'status' => 'available'],
            ['number' => 'A3', 'section' => 'Indoor', 'capacity' => 4, 'status' => 'available'],
            ['number' => 'A4', 'section' => 'Indoor', 'capacity' => 4, 'status' => 'occupied'],
            ['number' => 'A5', 'section' => 'Indoor', 'capacity' => 6, 'status' => 'available'],
            ['number' => 'A6', 'section' => 'Indoor', 'capacity' => 4, 'status' => 'available'],
            // Outdoor section
            ['number' => 'B1', 'section' => 'Outdoor', 'capacity' => 2, 'status' => 'available'],
            ['number' => 'B2', 'section' => 'Outdoor', 'capacity' => 4, 'status' => 'available'],
            ['number' => 'B3', 'section' => 'Outdoor', 'capacity' => 4, 'status' => 'reserved'],
            ['number' => 'B4', 'section' => 'Outdoor', 'capacity' => 6, 'status' => 'available'],
            // VIP section
            ['number' => 'V1', 'section' => 'VIP', 'capacity' => 4, 'status' => 'available'],
            ['number' => 'V2', 'section' => 'VIP', 'capacity' => 8, 'status' => 'available'],
            // Maintenance
            ['number' => 'X1', 'section' => 'Indoor', 'capacity' => 4, 'status' => 'maintenance'],
        ];

        foreach ($tables as $table) {
            Table::create([...$table, 'tenant_id' => $tenant->id]);
        }
    }
}
