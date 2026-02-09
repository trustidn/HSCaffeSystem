<?php

use App\Enums\StockMovementType;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\StockMovement;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manajemen Inventaris')] class extends Component {
    public string $activeTab = 'ingredients';
    public string $search = '';

    // Ingredient form
    public bool $showIngredientModal = false;
    public ?int $editingIngredientId = null;
    public string $ingredientName = '';
    public string $ingredientUnit = 'kg';
    public string $minimumStock = '0';
    public string $costPerUnit = '0';

    // Stock movement form
    public bool $showStockModal = false;
    public string $stockIngredientId = '';
    public string $stockType = 'in';
    public string $stockQuantity = '';
    public string $stockCostPerUnit = '';
    public string $stockReference = '';
    public string $stockNotes = '';

    // Recipe form
    public bool $showRecipeModal = false;
    public ?int $editingRecipeId = null;
    public string $recipeMenuItemId = '';
    public string $recipeIngredientId = '';
    public string $recipeQuantity = '';

    // Delete
    public bool $showDeleteModal = false;
    public ?int $deleteIngredientId = null;

    #[Computed]
    public function ingredients()
    {
        return Ingredient::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function lowStockIngredients()
    {
        return Ingredient::query()
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function recentMovements()
    {
        return StockMovement::query()
            ->with(['ingredient', 'user'])
            ->latest()
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function menuItems()
    {
        return MenuItem::query()->orderBy('name')->get();
    }

    #[Computed]
    public function recipes()
    {
        return Recipe::query()
            ->with(['menuItem', 'ingredient'])
            ->whereHas('menuItem', fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id))
            ->get()
            ->groupBy('menu_item_id');
    }

    // === Ingredients ===
    public function openIngredientModal(?int $id = null): void
    {
        $this->resetValidation();
        if ($id) {
            $ing = Ingredient::findOrFail($id);
            $this->editingIngredientId = $ing->id;
            $this->ingredientName = $ing->name;
            $this->ingredientUnit = $ing->unit;
            $this->minimumStock = (string) $ing->minimum_stock;
            $this->costPerUnit = (string) $ing->cost_per_unit;
        } else {
            $this->editingIngredientId = null;
            $this->ingredientName = '';
            $this->ingredientUnit = 'kg';
            $this->minimumStock = '0';
            $this->costPerUnit = '0';
        }
        $this->showIngredientModal = true;
    }

    public function saveIngredient(): void
    {
        $this->validate([
            'ingredientName' => ['required', 'string', 'max:255'],
            'ingredientUnit' => ['required', 'string', 'max:20'],
            'minimumStock' => ['required', 'numeric', 'min:0'],
            'costPerUnit' => ['required', 'numeric', 'min:0'],
        ]);

        Ingredient::updateOrCreate(
            ['id' => $this->editingIngredientId],
            [
                'name' => $this->ingredientName,
                'unit' => $this->ingredientUnit,
                'minimum_stock' => $this->minimumStock,
                'cost_per_unit' => $this->costPerUnit,
            ]
        );

        $this->showIngredientModal = false;
        unset($this->ingredients);
    }

    public function confirmDeleteIngredient(int $id): void
    {
        $this->deleteIngredientId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteIngredient(): void
    {
        Ingredient::findOrFail($this->deleteIngredientId)->delete();
        $this->showDeleteModal = false;
        unset($this->ingredients);
    }

    // === Stock Movements ===
    public function openStockModal(int $ingredientId, string $type = 'in'): void
    {
        $this->resetValidation();
        $this->stockIngredientId = (string) $ingredientId;
        $this->stockType = $type;
        $this->stockQuantity = '';
        $this->stockCostPerUnit = '';
        $this->stockReference = '';
        $this->stockNotes = '';
        $this->showStockModal = true;
    }

    public function saveStockMovement(): void
    {
        $this->validate([
            'stockIngredientId' => ['required', 'exists:ingredients,id'],
            'stockType' => ['required', 'in:in,out,adjustment,waste'],
            'stockQuantity' => ['required', 'numeric', 'min:0.001'],
            'stockCostPerUnit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $ingredient = Ingredient::findOrFail($this->stockIngredientId);

        StockMovement::create([
            'ingredient_id' => $ingredient->id,
            'type' => $this->stockType,
            'quantity' => $this->stockQuantity,
            'cost_per_unit' => $this->stockCostPerUnit ?: null,
            'reference' => $this->stockReference ?: null,
            'notes' => $this->stockNotes ?: null,
            'user_id' => auth()->id(),
        ]);

        // Update stock
        $newStock = match ($this->stockType) {
            'in' => $ingredient->current_stock + $this->stockQuantity,
            'out', 'waste' => max(0, $ingredient->current_stock - $this->stockQuantity),
            'adjustment' => $this->stockQuantity,
            default => $ingredient->current_stock,
        };

        $ingredient->update([
            'current_stock' => $newStock,
            'cost_per_unit' => $this->stockType === 'in' && $this->stockCostPerUnit ? $this->stockCostPerUnit : $ingredient->cost_per_unit,
        ]);

        $this->showStockModal = false;
        unset($this->ingredients, $this->recentMovements, $this->lowStockIngredients);
    }

    // === Recipes ===
    public function openRecipeModal(?int $id = null): void
    {
        $this->resetValidation();
        if ($id) {
            $recipe = Recipe::findOrFail($id);
            $this->editingRecipeId = $recipe->id;
            $this->recipeMenuItemId = (string) $recipe->menu_item_id;
            $this->recipeIngredientId = (string) $recipe->ingredient_id;
            $this->recipeQuantity = (string) $recipe->quantity_needed;
        } else {
            $this->editingRecipeId = null;
            $this->recipeMenuItemId = '';
            $this->recipeIngredientId = '';
            $this->recipeQuantity = '';
        }
        $this->showRecipeModal = true;
    }

    public function saveRecipe(): void
    {
        $this->validate([
            'recipeMenuItemId' => ['required', 'exists:menu_items,id'],
            'recipeIngredientId' => ['required', 'exists:ingredients,id'],
            'recipeQuantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        if ($this->editingRecipeId) {
            Recipe::findOrFail($this->editingRecipeId)->update([
                'menu_item_id' => $this->recipeMenuItemId,
                'ingredient_id' => $this->recipeIngredientId,
                'quantity_needed' => $this->recipeQuantity,
            ]);
        } else {
            Recipe::updateOrCreate(
                ['menu_item_id' => $this->recipeMenuItemId, 'ingredient_id' => $this->recipeIngredientId],
                ['quantity_needed' => $this->recipeQuantity]
            );
        }

        $this->showRecipeModal = false;
        unset($this->recipes);
    }

    public function deleteRecipe(int $id): void
    {
        Recipe::findOrFail($id)->delete();
        unset($this->recipes);
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Manajemen Inventaris') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola bahan baku, stok, dan resep.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            @if ($activeTab === 'ingredients')
                <flux:button variant="primary" icon="plus" wire:click="openIngredientModal()">{{ __('Bahan Baru') }}</flux:button>
            @elseif ($activeTab === 'recipes')
                <flux:button variant="primary" icon="plus" wire:click="openRecipeModal()">{{ __('Resep Baru') }}</flux:button>
            @endif
        </div>
    </div>

    {{-- Low Stock Alert --}}
    @if ($this->lowStockIngredients->isNotEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle">
            <strong>{{ __('Stok Rendah:') }}</strong>
            {{ $this->lowStockIngredients->pluck('name')->implode(', ') }}
        </flux:callout>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700">
        @foreach (['ingredients' => 'Bahan Baku', 'movements' => 'Pergerakan Stok', 'recipes' => 'Resep'] as $tab => $label)
            <button wire:click="$set('activeTab', '{{ $tab }}')" class="border-b-2 px-4 py-2 text-sm font-medium {{ $activeTab === $tab ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">
                {{ __($label) }}
            </button>
        @endforeach
    </div>

    @if ($activeTab === 'ingredients')
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Cari bahan baku...')" />

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Bahan') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Stok') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Min.') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Harga/Unit') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->ingredients as $ing)
                        <tr wire:key="ing-{{ $ing->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3 font-medium">{{ $ing->name }}</td>
                            <td class="px-4 py-3">{{ number_format($ing->current_stock, 1) }} {{ $ing->unit }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ number_format($ing->minimum_stock, 1) }} {{ $ing->unit }}</td>
                            <td class="px-4 py-3">Rp {{ number_format($ing->cost_per_unit, 0, ',', '.') }}</td>
                            <td class="px-4 py-3">
                                @if ($ing->isLowStock())
                                    <flux:badge variant="warning" size="sm">{{ __('Rendah') }}</flux:badge>
                                @else
                                    <flux:badge variant="success" size="sm">{{ __('Cukup') }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button variant="ghost" size="sm" icon="plus-circle" wire:click="openStockModal({{ $ing->id }}, 'in')" title="{{ __('Stok Masuk') }}" />
                                    <flux:button variant="ghost" size="sm" icon="minus-circle" wire:click="openStockModal({{ $ing->id }}, 'out')" title="{{ __('Stok Keluar') }}" />
                                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openIngredientModal({{ $ing->id }})" />
                                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmDeleteIngredient({{ $ing->id }})" class="text-red-500" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">{{ __('Belum ada bahan baku.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($activeTab === 'movements')
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Waktu') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Bahan') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Tipe') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Jumlah') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Referensi') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Oleh') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->recentMovements as $mv)
                        <tr wire:key="mv-{{ $mv->id }}">
                            <td class="px-4 py-3 text-xs text-zinc-500">{{ $mv->created_at->format('d/m H:i') }}</td>
                            <td class="px-4 py-3 font-medium">{{ $mv->ingredient->name }}</td>
                            <td class="px-4 py-3"><flux:badge :variant="$mv->type->color()" size="sm">{{ $mv->type->label() }}</flux:badge></td>
                            <td class="px-4 py-3">{{ number_format($mv->quantity, 2) }} {{ $mv->ingredient->unit }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ $mv->reference ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ $mv->user?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">{{ __('Belum ada pergerakan stok.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($activeTab === 'recipes')
        @forelse ($this->recipes as $menuItemId => $recipeItems)
            @php $menuItem = $recipeItems->first()->menuItem; @endphp
            <div wire:key="recipe-group-{{ $menuItemId }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="lg">{{ $menuItem->name }}</flux:heading>
                <div class="mt-2 space-y-1">
                    @foreach ($recipeItems as $recipe)
                        <div wire:key="recipe-{{ $recipe->id }}" class="flex items-center justify-between rounded-lg px-2 py-1.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <span>{{ $recipe->ingredient->name }}</span>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ number_format($recipe->quantity_needed, 2) }} {{ $recipe->ingredient->unit }}</span>
                                <button wire:click="openRecipeModal({{ $recipe->id }})" class="text-indigo-500 hover:text-indigo-700" title="{{ __('Edit') }}">
                                    <flux:icon.pencil-square class="size-4" />
                                </button>
                                <button wire:click="deleteRecipe({{ $recipe->id }})" class="text-red-400 hover:text-red-600" title="{{ __('Hapus') }}">&times;</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="py-12 text-center text-zinc-500">{{ __('Belum ada resep. Buat resep untuk menghubungkan menu dengan bahan baku.') }}</div>
        @endforelse
    @endif

    {{-- Ingredient Modal --}}
    <flux:modal wire:model="showIngredientModal" class="max-w-md">
        <form wire:submit="saveIngredient" class="space-y-4">
            <flux:heading size="lg">{{ $editingIngredientId ? __('Edit Bahan') : __('Tambah Bahan') }}</flux:heading>
            <flux:input wire:model="ingredientName" :label="__('Nama Bahan')" required />
            <flux:select wire:model="ingredientUnit" :label="__('Satuan')">
                @foreach (['kg', 'gram', 'liter', 'ml', 'pcs'] as $unit)
                    <option value="{{ $unit }}">{{ $unit }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="minimumStock" :label="__('Stok Minimum')" type="number" step="0.1" />
            <flux:input wire:model="costPerUnit" :label="__('Harga per Unit (Rp)')" type="number" />
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showIngredientModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Stock Movement Modal --}}
    <flux:modal wire:model="showStockModal" class="max-w-md">
        <form wire:submit="saveStockMovement" class="space-y-4">
            <flux:heading size="lg">{{ __('Pergerakan Stok') }}</flux:heading>
            <flux:select wire:model="stockType" :label="__('Tipe')">
                <option value="in">{{ __('Stok Masuk') }}</option>
                <option value="out">{{ __('Stok Keluar') }}</option>
                <option value="adjustment">{{ __('Penyesuaian (Opname)') }}</option>
                <option value="waste">{{ __('Waste / Rusak') }}</option>
            </flux:select>
            <flux:input wire:model="stockQuantity" :label="__('Jumlah')" type="number" step="0.001" required />
            @if ($stockType === 'in')
                <flux:input wire:model="stockCostPerUnit" :label="__('Harga per Unit (Rp)')" type="number" />
            @endif
            <flux:input wire:model="stockReference" :label="__('Referensi')" :placeholder="__('Nama supplier, no. PO, dll')" />
            <flux:textarea wire:model="stockNotes" :label="__('Catatan')" rows="2" />
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showStockModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Recipe Modal --}}
    <flux:modal wire:model="showRecipeModal" class="max-w-md">
        <form wire:submit="saveRecipe" class="space-y-4">
            <flux:heading size="lg">{{ $editingRecipeId ? __('Edit Resep') : __('Tambah Resep') }}</flux:heading>
            <flux:select wire:model="recipeMenuItemId" :label="__('Menu Item')">
                <option value="">{{ __('Pilih menu...') }}</option>
                @foreach ($this->menuItems as $mi)
                    <option value="{{ $mi->id }}">{{ $mi->name }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model="recipeIngredientId" :label="__('Bahan Baku')">
                <option value="">{{ __('Pilih bahan...') }}</option>
                @foreach ($this->ingredients as $ing)
                    <option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit }})</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="recipeQuantity" :label="__('Jumlah yang Dibutuhkan')" type="number" step="0.001" required />
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showRecipeModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Confirmation --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Hapus Bahan') }}</flux:heading>
            <flux:text>{{ __('Yakin ingin menghapus bahan baku ini?') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showDeleteModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteIngredient">{{ __('Hapus') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
