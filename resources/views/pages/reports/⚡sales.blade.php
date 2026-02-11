<?php

use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\StockMovement;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Laporan')] class extends Component {
    public string $activeTab = 'sales';

    public string $period = 'today';

    public string $startDate = '';

    public string $endDate = '';

    public string $topItemsCategoryId = '';

    public function mount(): void
    {
        $this->startDate = now()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function updatedPeriod(): void
    {
        $this->startDate = match ($this->period) {
            'today' => now()->format('Y-m-d'),
            'week' => now()->startOfWeek()->format('Y-m-d'),
            'month' => now()->startOfMonth()->format('Y-m-d'),
            default => $this->startDate,
        };
        $this->endDate = now()->format('Y-m-d');
    }

    #[Computed]
    public function salesSummary(): array
    {
        $query = Order::query()
            ->whereBetween('created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59']);

        $totalOrders = $query->count();
        $completedOrders = (clone $query)->where('payment_status', PaymentStatus::Paid->value)->count();
        $totalRevenue = (float) (clone $query)->where('payment_status', PaymentStatus::Paid->value)->sum('total');
        $totalTax = (float) (clone $query)->where('payment_status', PaymentStatus::Paid->value)->sum('tax_amount');
        $totalService = (float) (clone $query)->where('payment_status', PaymentStatus::Paid->value)->sum('service_charge');
        $avgPerOrder = $completedOrders > 0 ? $totalRevenue / $completedOrders : 0;
        $cancelledOrders = (clone $query)->where('status', 'cancelled')->count();

        return compact('totalOrders', 'completedOrders', 'totalRevenue', 'totalTax', 'totalService', 'avgPerOrder', 'cancelledOrders');
    }

    #[Computed]
    public function menuCategories(): \Illuminate\Support\Collection
    {
        return Category::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function topItems(): \Illuminate\Support\Collection
    {
        $query = OrderItem::query()
            ->selectRaw('item_name, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue')
            ->whereHas('order', fn ($q) => $q
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('payment_status', PaymentStatus::Paid->value)
                ->whereBetween('created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            );

        if ($this->topItemsCategoryId !== '') {
            $query->whereHas('menuItem', fn ($q) => $q->where('category_id', $this->topItemsCategoryId));
        }

        return $query
            ->groupBy('item_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function paymentMethodSummary(): \Illuminate\Support\Collection
    {
        return Payment::query()
            ->selectRaw('method, COUNT(*) as count, SUM(amount) as total')
            ->whereBetween('created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            ->groupBy('method')
            ->get();
    }

    #[Computed]
    public function dailyBreakdown(): \Illuminate\Support\Collection
    {
        return Order::query()
            ->selectRaw("DATE(created_at) as date, COUNT(*) as orders, SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue")
            ->whereBetween('created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();
    }

    #[Computed]
    public function stockSummary(): array
    {
        $totalIngredients = Ingredient::where('is_active', true)->count();
        $lowStockCount = Ingredient::whereColumn('current_stock', '<=', 'minimum_stock')
            ->where('is_active', true)
            ->count();
        $totalStockValue = (float) Ingredient::where('is_active', true)
            ->selectRaw('SUM(current_stock * cost_per_unit) as value')
            ->value('value') ?? 0;

        return compact('totalIngredients', 'lowStockCount', 'totalStockValue');
    }

    #[Computed]
    public function lowStockItems(): \Illuminate\Support\Collection
    {
        return Ingredient::whereColumn('current_stock', '<=', 'minimum_stock')
            ->where('is_active', true)
            ->orderByRaw('current_stock - minimum_stock')
            ->get();
    }

    #[Computed]
    public function stockMovementSummary(): \Illuminate\Support\Collection
    {
        return StockMovement::query()
            ->selectRaw('type, COUNT(*) as count, SUM(quantity) as total_qty')
            ->whereBetween('created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            ->groupBy('type')
            ->get();
    }

    #[Computed]
    public function recentStockMovements(): \Illuminate\Support\Collection
    {
        return StockMovement::with(['ingredient', 'user'])
            ->whereBetween('created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            ->latest()
            ->limit(20)
            ->get();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Laporan') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Laporan penjualan dan keuangan.') }}</flux:text>
        </div>
    </div>

    {{-- Period Selection --}}
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex gap-2">
            @foreach (['today' => 'Hari Ini', 'week' => 'Minggu Ini', 'month' => 'Bulan Ini', 'custom' => 'Custom'] as $p => $label)
                <flux:badge as="button" wire:click="$set('period', '{{ $p }}')" :variant="$period === $p ? 'primary' : 'default'" size="sm">
                    {{ __($label) }}
                </flux:badge>
            @endforeach
        </div>
        @if ($period === 'custom')
            <div class="flex items-center gap-2">
                <flux:input wire:model.live="startDate" type="date" size="sm" />
                <span class="text-zinc-400">-</span>
                <flux:input wire:model.live="endDate" type="date" size="sm" />
            </div>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="flex gap-2 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700">
        @foreach (['sales' => 'Penjualan', 'items' => 'Item Terlaris', 'payments' => 'Pembayaran', 'daily' => 'Harian', 'stock' => 'Stok'] as $tab => $label)
            <button wire:click="$set('activeTab', '{{ $tab }}')" class="border-b-2 px-4 py-2 text-sm font-medium {{ $activeTab === $tab ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">
                {{ __($label) }}
            </button>
        @endforeach
    </div>

    @if ($activeTab === 'sales')
        {{-- Sales Summary Cards --}}
        @php $summary = $this->salesSummary; @endphp
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Total Pesanan') }}</flux:text>
                <div class="mt-2 text-3xl font-bold">{{ $summary['totalOrders'] }}</div>
                <flux:text class="text-xs">{{ $summary['cancelledOrders'] }} {{ __('dibatalkan') }}</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Total Pendapatan') }}</flux:text>
                <div class="mt-2 text-3xl font-bold text-emerald-600">Rp {{ number_format($summary['totalRevenue'], 0, ',', '.') }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Rata-rata/Order') }}</flux:text>
                <div class="mt-2 text-3xl font-bold">Rp {{ number_format($summary['avgPerOrder'], 0, ',', '.') }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Total Pajak') }}</flux:text>
                <div class="mt-2 text-3xl font-bold">Rp {{ number_format($summary['totalTax'], 0, ',', '.') }}</div>
                <flux:text class="text-xs">{{ __('Service:') }} Rp {{ number_format($summary['totalService'], 0, ',', '.') }}</flux:text>
            </div>
        </div>

    @elseif ($activeTab === 'items')
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <flux:text class="shrink-0">{{ __('Kategori:') }}</flux:text>
            <flux:select wire:model.live="topItemsCategoryId" class="min-w-[200px]">
                <option value="">{{ __('Semua Kategori') }}</option>
                @foreach ($this->menuCategories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </flux:select>
        </div>
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 font-medium">#</th>
                        <th class="px-4 py-3 font-medium">{{ __('Menu Item') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Qty Terjual') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Revenue') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->topItems as $i => $item)
                        <tr>
                            <td class="px-4 py-3 font-bold text-zinc-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-medium">{{ $item->item_name }}</td>
                            <td class="px-4 py-3 text-right">{{ $item->total_qty }}</td>
                            <td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($item->total_revenue, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-zinc-500">{{ __('Belum ada data.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif ($activeTab === 'payments')
        <div class="grid gap-4 md:grid-cols-3">
            @forelse ($this->paymentMethodSummary as $pm)
                <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:text class="text-sm">{{ $pm->method->label() }}</flux:text>
                    <div class="mt-2 text-2xl font-bold">Rp {{ number_format($pm->total, 0, ',', '.') }}</div>
                    <flux:text class="text-xs">{{ $pm->count }} {{ __('transaksi') }}</flux:text>
                </div>
            @empty
                <div class="col-span-3 py-8 text-center text-zinc-500">{{ __('Belum ada data pembayaran.') }}</div>
            @endforelse
        </div>

    @elseif ($activeTab === 'daily')
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Tanggal') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Total Order') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Revenue') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->dailyBreakdown as $day)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ \Carbon\Carbon::parse($day->date)->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">{{ $day->orders }}</td>
                            <td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($day->revenue, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-zinc-500">{{ __('Belum ada data.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif ($activeTab === 'stock')
        {{-- Stock Summary Cards --}}
        @php $stock = $this->stockSummary; @endphp
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Total Bahan Aktif') }}</flux:text>
                <div class="mt-2 text-3xl font-bold">{{ $stock['totalIngredients'] }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Stok Rendah') }}</flux:text>
                <div class="mt-2 text-3xl font-bold {{ $stock['lowStockCount'] > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                    {{ $stock['lowStockCount'] }}
                </div>
            </div>
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:text class="text-sm">{{ __('Estimasi Nilai Stok') }}</flux:text>
                <div class="mt-2 text-3xl font-bold">Rp {{ number_format($stock['totalStockValue'], 0, ',', '.') }}</div>
            </div>
        </div>

        {{-- Low Stock Alert --}}
        @if ($this->lowStockItems->isNotEmpty())
            <div class="rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-800 dark:bg-red-900/20">
                <flux:heading size="lg" class="mb-3 text-red-700 dark:text-red-400">{{ __('Peringatan Stok Rendah') }}</flux:heading>
                <div class="space-y-2">
                    @foreach ($this->lowStockItems as $item)
                        <div class="flex items-center justify-between rounded-lg bg-white p-3 dark:bg-zinc-800">
                            <div>
                                <span class="font-medium">{{ $item->name }}</span>
                                <span class="ml-2 text-sm text-zinc-500">({{ $item->unit }})</span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-red-600">{{ number_format($item->current_stock, 1) }}</span>
                                <span class="text-sm text-zinc-400"> / min {{ number_format($item->minimum_stock, 1) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Stock Movement Summary --}}
        @if ($this->stockMovementSummary->isNotEmpty())
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-4">{{ __('Ringkasan Pergerakan Stok') }}</flux:heading>
                <div class="grid gap-3 md:grid-cols-3">
                    @foreach ($this->stockMovementSummary as $movement)
                        @php
                            $type = $movement->type instanceof \App\Enums\StockMovementType
                                ? $movement->type
                                : \App\Enums\StockMovementType::from($movement->type);
                        @endphp
                        <div class="flex items-center justify-between rounded-lg border border-zinc-100 p-4 dark:border-zinc-700">
                            <div>
                                <flux:badge :variant="$type->color()" size="sm">{{ $type->label() }}</flux:badge>
                                <div class="mt-1 text-sm text-zinc-500">{{ $movement->count }} {{ __('transaksi') }}</div>
                            </div>
                            <div class="text-right text-lg font-bold">{{ number_format($movement->total_qty, 1) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Recent Stock Movements --}}
        @if ($this->recentStockMovements->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="border-b border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg">{{ __('Pergerakan Stok Terbaru') }}</flux:heading>
                </div>
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Waktu') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Bahan') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Tipe') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Jumlah') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Oleh') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->recentStockMovements as $mv)
                            <tr>
                                <td class="px-4 py-3 text-zinc-500">{{ $mv->created_at->format('d/m H:i') }}</td>
                                <td class="px-4 py-3 font-medium">{{ $mv->ingredient?->name ?? '-' }}</td>
                                <td class="px-4 py-3"><flux:badge :variant="$mv->type->color()" size="sm">{{ $mv->type->label() }}</flux:badge></td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($mv->quantity, 1) }}</td>
                                <td class="px-4 py-3 text-zinc-500">{{ $mv->user?->name ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
