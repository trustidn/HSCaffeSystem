<?php

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MenuModifier;
use App\Models\MenuVariant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Manajemen Menu')] class extends Component {
    use WithFileUploads;

    public string $search = '';
    public string $filterCategory = '';

    // Category form
    public bool $showCategoryModal = false;
    public ?int $editingCategoryId = null;
    public string $categoryName = '';
    public string $categoryDescription = '';
    public $categoryImage = null;
    public ?string $currentCategoryImage = null;

    // Menu item form
    public bool $showItemModal = false;
    public ?int $editingItemId = null;
    public string $itemName = '';
    public string $itemDescription = '';
    public string $itemPrice = '';
    public string $itemCategoryId = '';
    public $itemImage = null;

    // Variant form
    public bool $showVariantModal = false;
    public ?int $variantItemId = null;
    public string $variantName = '';
    public string $variantPrice = '';

    // Modifier form
    public bool $showModifierModal = false;
    public ?int $editingModifierId = null;
    public string $modifierName = '';
    public string $modifierPrice = '0';

    // Assign modifiers to menu item
    public bool $showAssignModifierModal = false;
    public ?int $assignModifierItemId = null;
    public string $assignModifierItemName = '';
    /** @var array<int, bool> */
    public array $assignedModifiers = [];

    // Delete
    public bool $showDeleteModal = false;
    public string $deleteType = '';
    public ?int $deleteId = null;

    #[Computed]
    public function categories()
    {
        return Category::query()
            ->withCount('menuItems')
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function menuItems()
    {
        return MenuItem::query()
            ->with(['category', 'variants', 'modifiers'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->filterCategory, fn ($q) => $q->where('category_id', $this->filterCategory))
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function modifiers()
    {
        return MenuModifier::query()->orderBy('name')->get();
    }

    // === Category CRUD ===
    public function openCategoryModal(?int $id = null): void
    {
        $this->resetValidation();
        $this->categoryImage = null;
        if ($id) {
            $cat = Category::findOrFail($id);
            $this->editingCategoryId = $cat->id;
            $this->categoryName = $cat->name;
            $this->categoryDescription = $cat->description ?? '';
            $this->currentCategoryImage = $cat->image;
        } else {
            $this->editingCategoryId = null;
            $this->categoryName = '';
            $this->categoryDescription = '';
            $this->currentCategoryImage = null;
        }
        $this->showCategoryModal = true;
    }

    public function saveCategory(): void
    {
        $this->validate([
            'categoryName' => ['required', 'string', 'max:255'],
            'categoryDescription' => ['nullable', 'string', 'max:1000'],
            'categoryImage' => ['nullable', 'image', 'max:2048'],
        ]);

        $data = [
            'name' => $this->categoryName,
            'slug' => Str::slug($this->categoryName),
            'description' => $this->categoryDescription ?: null,
        ];

        if ($this->categoryImage) {
            if ($this->editingCategoryId) {
                $existing = Category::find($this->editingCategoryId);
                if ($existing?->image) {
                    Storage::disk('public')->delete($existing->image);
                }
            }
            $data['image'] = $this->categoryImage->store('category-images', 'public');
        }

        Category::updateOrCreate(
            ['id' => $this->editingCategoryId],
            $data
        );

        $this->showCategoryModal = false;
        unset($this->categories, $this->menuItems);
    }

    public function removeCategoryImage(): void
    {
        if (! $this->editingCategoryId) {
            return;
        }

        $cat = Category::findOrFail($this->editingCategoryId);
        if ($cat->image) {
            Storage::disk('public')->delete($cat->image);
            $cat->update(['image' => null]);
        }

        $this->currentCategoryImage = null;
        unset($this->categories, $this->menuItems);
    }

    // === Menu Item CRUD ===
    public function openItemModal(?int $id = null): void
    {
        $this->resetValidation();
        $this->itemImage = null;
        if ($id) {
            $item = MenuItem::findOrFail($id);
            $this->editingItemId = $item->id;
            $this->itemName = $item->name;
            $this->itemDescription = $item->description ?? '';
            $this->itemPrice = (string) $item->base_price;
            $this->itemCategoryId = (string) $item->category_id;
        } else {
            $this->editingItemId = null;
            $this->itemName = '';
            $this->itemDescription = '';
            $this->itemPrice = '';
            $this->itemCategoryId = '';
        }
        $this->showItemModal = true;
    }

    public function saveItem(): void
    {
        $this->validate([
            'itemName' => ['required', 'string', 'max:255'],
            'itemDescription' => ['nullable', 'string', 'max:1000'],
            'itemPrice' => ['required', 'numeric', 'min:0'],
            'itemCategoryId' => ['required', 'exists:categories,id'],
            'itemImage' => ['nullable', 'image', 'max:2048'],
        ]);

        $data = [
            'name' => $this->itemName,
            'slug' => Str::slug($this->itemName),
            'description' => $this->itemDescription ?: null,
            'base_price' => $this->itemPrice,
            'category_id' => $this->itemCategoryId,
        ];

        if ($this->itemImage) {
            $data['image'] = $this->itemImage->store('menu-images', 'public');
        }

        MenuItem::updateOrCreate(
            ['id' => $this->editingItemId],
            $data
        );

        $this->showItemModal = false;
        unset($this->menuItems);
    }

    public function toggleAvailability(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $item->update(['is_available' => ! $item->is_available]);
        unset($this->menuItems);
    }

    // === Variant CRUD ===
    public function openVariantModal(int $itemId): void
    {
        $this->resetValidation();
        $this->variantItemId = $itemId;
        $this->variantName = '';
        $this->variantPrice = '';
        $this->showVariantModal = true;
    }

    public function saveVariant(): void
    {
        $this->validate([
            'variantName' => ['required', 'string', 'max:255'],
            'variantPrice' => ['required', 'numeric', 'min:0'],
        ]);

        MenuVariant::create([
            'menu_item_id' => $this->variantItemId,
            'name' => $this->variantName,
            'price' => $this->variantPrice,
        ]);

        $this->showVariantModal = false;
        unset($this->menuItems);
    }

    public function deleteVariant(int $id): void
    {
        MenuVariant::findOrFail($id)->delete();
        unset($this->menuItems);
    }

    // === Modifier CRUD ===
    public function openModifierModal(?int $id = null): void
    {
        $this->resetValidation();
        if ($id) {
            $mod = MenuModifier::findOrFail($id);
            $this->editingModifierId = $mod->id;
            $this->modifierName = $mod->name;
            $this->modifierPrice = (string) $mod->price;
        } else {
            $this->editingModifierId = null;
            $this->modifierName = '';
            $this->modifierPrice = '0';
        }
        $this->showModifierModal = true;
    }

    public function saveModifier(): void
    {
        $this->validate([
            'modifierName' => ['required', 'string', 'max:255'],
            'modifierPrice' => ['required', 'numeric', 'min:0'],
        ]);

        MenuModifier::updateOrCreate(
            ['id' => $this->editingModifierId],
            [
                'name' => $this->modifierName,
                'price' => $this->modifierPrice,
            ]
        );

        $this->showModifierModal = false;
        unset($this->modifiers);
    }

    // === Assign Modifiers to Menu Item ===
    public function openAssignModifierModal(int $itemId): void
    {
        $item = MenuItem::with('modifiers')->findOrFail($itemId);
        $this->assignModifierItemId = $item->id;
        $this->assignModifierItemName = $item->name;
        $this->assignedModifiers = [];

        $currentModifierIds = $item->modifiers->pluck('id')->toArray();
        foreach ($this->modifiers as $mod) {
            $this->assignedModifiers[$mod->id] = in_array($mod->id, $currentModifierIds);
        }

        $this->showAssignModifierModal = true;
    }

    public function saveAssignedModifiers(): void
    {
        $item = MenuItem::findOrFail($this->assignModifierItemId);
        $selectedIds = collect($this->assignedModifiers)
            ->filter(fn ($selected) => $selected)
            ->keys()
            ->toArray();

        $item->modifiers()->sync($selectedIds);

        $this->showAssignModifierModal = false;
        unset($this->menuItems);
    }

    // === Delete ===
    public function confirmDelete(string $type, int $id): void
    {
        $this->deleteType = $type;
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function executeDelete(): void
    {
        match ($this->deleteType) {
            'category' => Category::findOrFail($this->deleteId)->delete(),
            'item' => MenuItem::findOrFail($this->deleteId)->delete(),
            'modifier' => MenuModifier::findOrFail($this->deleteId)->delete(),
        };

        $this->showDeleteModal = false;
        unset($this->categories, $this->menuItems, $this->modifiers);
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Manajemen Menu') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Kelola kategori, menu item, varian, dan modifier.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button icon="tag" wire:click="openModifierModal()">{{ __('Modifier') }}</flux:button>
            <flux:button icon="folder-plus" wire:click="openCategoryModal()">{{ __('Kategori') }}</flux:button>
            <flux:button variant="primary" icon="plus" wire:click="openItemModal()">{{ __('Menu Baru') }}</flux:button>
        </div>
    </div>

    {{-- Categories Bar --}}
    <div class="flex flex-wrap gap-2">
        <flux:badge as="button" wire:click="$set('filterCategory', '')" :variant="$filterCategory === '' ? 'primary' : 'default'" size="lg">
            {{ __('Semua') }}
        </flux:badge>
        @foreach ($this->categories as $cat)
            <flux:badge as="button" wire:click="$set('filterCategory', '{{ $cat->id }}')" :variant="$filterCategory == $cat->id ? 'primary' : 'default'" size="lg">
                @if ($cat->image)
                    <img src="{{ Storage::url($cat->image) }}" alt="" class="size-5 rounded-full object-cover" />
                @endif
                {{ $cat->name }} ({{ $cat->menu_items_count }})
                <button wire:click.stop="openCategoryModal({{ $cat->id }})" class="ml-1 opacity-50 hover:opacity-100">
                    <flux:icon.pencil-square class="size-3" />
                </button>
            </flux:badge>
        @endforeach
    </div>

    {{-- Search --}}
    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Cari menu...')" />

    {{-- Menu Items Grid --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->menuItems as $item)
            <div wire:key="item-{{ $item->id }}" class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                @if ($item->effective_image)
                    <div class="relative">
                        <img src="{{ Storage::url($item->effective_image) }}" alt="{{ $item->name }}" class="h-40 w-full object-cover">
                        @if (! $item->image && $item->category?->image)
                            <span class="absolute bottom-1 right-1 rounded bg-black/50 px-1.5 py-0.5 text-[10px] text-white">{{ __('Dari kategori') }}</span>
                        @endif
                    </div>
                @else
                    <div class="flex h-32 items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon.photo class="size-12 text-zinc-300" />
                    </div>
                @endif
                <div class="p-4 space-y-2">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-semibold">{{ $item->name }}</h3>
                            <flux:text size="sm">{{ $item->category->name }}</flux:text>
                        </div>
                        <flux:switch wire:click="toggleAvailability({{ $item->id }})" :checked="$item->is_available" />
                    </div>
                    @if ($item->description)
                        <flux:text size="sm" class="line-clamp-2">{{ $item->description }}</flux:text>
                    @endif
                    <div class="font-bold text-lg">Rp {{ $item->display_price }}</div>

                    {{-- Variants --}}
                    @if ($item->variants->isNotEmpty())
                        <div class="flex flex-wrap gap-1">
                            @foreach ($item->variants as $variant)
                                <flux:badge size="sm" wire:key="v-{{ $variant->id }}">
                                    {{ $variant->name }}: Rp {{ number_format($variant->price, 0, ',', '.') }}
                                    <button wire:click="deleteVariant({{ $variant->id }})" class="ml-1 text-red-400 hover:text-red-600">&times;</button>
                                </flux:badge>
                            @endforeach
                        </div>
                    @endif

                    {{-- Assigned Modifiers --}}
                    @if ($item->modifiers->isNotEmpty())
                        <div class="flex flex-wrap gap-1">
                            @foreach ($item->modifiers as $mod)
                                <flux:badge size="sm" color="amber" wire:key="im-{{ $item->id }}-{{ $mod->id }}">
                                    {{ $mod->name }} +Rp {{ number_format($mod->price, 0, ',', '.') }}
                                </flux:badge>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex gap-1 pt-2">
                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openItemModal({{ $item->id }})" title="{{ __('Edit Menu') }}" />
                        <flux:button variant="ghost" size="sm" icon="squares-plus" wire:click="openVariantModal({{ $item->id }})" title="{{ __('Tambah Varian') }}" />
                        <flux:button variant="ghost" size="sm" icon="tag" wire:click="openAssignModifierModal({{ $item->id }})" title="{{ __('Atur Modifier') }}" />
                        <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmDelete('item', {{ $item->id }})" class="text-red-500" title="{{ __('Hapus') }}" />
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-zinc-500">
                {{ __('Belum ada menu. Tambahkan kategori terlebih dahulu, lalu buat menu baru.') }}
            </div>
        @endforelse
    </div>

    {{-- Modifiers List --}}
    @if ($this->modifiers->isNotEmpty())
        <div>
            <flux:heading size="lg" class="mb-3">{{ __('Modifier / Add-on') }}</flux:heading>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->modifiers as $mod)
                    <flux:badge size="lg" wire:key="mod-{{ $mod->id }}">
                        {{ $mod->name }} (+Rp {{ number_format($mod->price, 0, ',', '.') }})
                        <button wire:click="openModifierModal({{ $mod->id }})" class="ml-1 opacity-50 hover:opacity-100">
                            <flux:icon.pencil-square class="size-3" />
                        </button>
                        <button wire:click="confirmDelete('modifier', {{ $mod->id }})" class="ml-1 text-red-400 hover:text-red-600">&times;</button>
                    </flux:badge>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Category Modal --}}
    <flux:modal wire:model="showCategoryModal" class="max-w-md">
        <form wire:submit="saveCategory" class="space-y-4">
            <flux:heading size="lg">{{ $editingCategoryId ? __('Edit Kategori') : __('Tambah Kategori') }}</flux:heading>
            <flux:input wire:model="categoryName" :label="__('Nama Kategori')" required />
            <flux:textarea wire:model="categoryDescription" :label="__('Deskripsi')" rows="2" />

            {{-- Category Default Image --}}
            <flux:field>
                <flux:label>{{ __('Gambar Default Kategori') }}</flux:label>
                <flux:text class="text-xs">{{ __('Ditampilkan sebagai gambar menu jika item belum memiliki gambar sendiri.') }}</flux:text>
                <div class="mt-2 flex items-center gap-4">
                    @if ($categoryImage)
                        <img src="{{ $categoryImage->temporaryUrl() }}" alt="Preview" class="h-20 w-20 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                    @elseif ($currentCategoryImage)
                        <img src="{{ Storage::url($currentCategoryImage) }}" alt="Gambar kategori" class="h-20 w-20 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700" />
                    @else
                        <div class="flex h-20 w-20 items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-600">
                            <flux:icon.photo class="size-8 text-zinc-400" />
                        </div>
                    @endif
                    <div class="flex flex-col gap-2">
                        <label class="cursor-pointer rounded-lg border border-zinc-300 px-4 py-2 text-center text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800">
                            {{ __('Pilih Gambar') }}
                            <input wire:model="categoryImage" type="file" accept="image/*" class="hidden" />
                        </label>
                        @if ($currentCategoryImage)
                            <button type="button" wire:click="removeCategoryImage" wire:confirm="{{ __('Hapus gambar default kategori?') }}" class="text-sm text-red-500 hover:underline">
                                {{ __('Hapus Gambar') }}
                            </button>
                        @endif
                    </div>
                </div>
                <flux:error name="categoryImage" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCategoryModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Item Modal --}}
    <flux:modal wire:model="showItemModal" class="max-w-lg">
        <form wire:submit="saveItem" class="space-y-4">
            <flux:heading size="lg">{{ $editingItemId ? __('Edit Menu') : __('Tambah Menu') }}</flux:heading>
            <flux:select wire:model="itemCategoryId" :label="__('Kategori')" required>
                <option value="">{{ __('Pilih kategori...') }}</option>
                @foreach ($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="itemName" :label="__('Nama Menu')" required />
            <flux:textarea wire:model="itemDescription" :label="__('Deskripsi')" rows="2" />
            <flux:input wire:model="itemPrice" :label="__('Harga Dasar (Rp)')" type="number" required />
            <flux:field>
                <flux:label>{{ __('Foto Menu') }}</flux:label>
                <input type="file" wire:model="itemImage" accept="image/*" class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:file:text-zinc-300">
                <flux:error name="itemImage" />
            </flux:field>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showItemModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Variant Modal --}}
    <flux:modal wire:model="showVariantModal" class="max-w-md">
        <form wire:submit="saveVariant" class="space-y-4">
            <flux:heading size="lg">{{ __('Tambah Varian') }}</flux:heading>
            <flux:input wire:model="variantName" :label="__('Nama Varian')" :placeholder="__('contoh: Small, Medium, Large, Hot, Iced')" required />
            <flux:input wire:model="variantPrice" :label="__('Harga (Rp)')" type="number" required />
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showVariantModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modifier Modal --}}
    <flux:modal wire:model="showModifierModal" class="max-w-md">
        <form wire:submit="saveModifier" class="space-y-4">
            <flux:heading size="lg">{{ $editingModifierId ? __('Edit Modifier') : __('Tambah Modifier') }}</flux:heading>
            <flux:input wire:model="modifierName" :label="__('Nama Modifier')" :placeholder="__('contoh: Extra Shot, Less Sugar')" required />
            <flux:input wire:model="modifierPrice" :label="__('Harga Tambahan (Rp)')" type="number" required />
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showModifierModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Assign Modifier Modal --}}
    <flux:modal wire:model="showAssignModifierModal" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Atur Modifier') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Pilih modifier yang tersedia untuk') }} <strong>{{ $assignModifierItemName }}</strong></flux:text>
            </div>

            @if ($this->modifiers->isEmpty())
                <div class="rounded-lg border border-dashed p-4 text-center text-zinc-500 dark:border-zinc-700">
                    <flux:icon.tag class="mx-auto mb-2 size-8 text-zinc-300" />
                    <p class="text-sm">{{ __('Belum ada modifier. Buat modifier terlebih dahulu.') }}</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($this->modifiers as $mod)
                        <label wire:key="assign-mod-{{ $mod->id }}" class="flex cursor-pointer items-center justify-between rounded-lg border p-3 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">
                            <div class="flex items-center gap-3">
                                <flux:checkbox wire:model="assignedModifiers.{{ $mod->id }}" />
                                <span class="font-medium">{{ $mod->name }}</span>
                            </div>
                            <span class="text-sm text-zinc-500">+Rp {{ number_format($mod->price, 0, ',', '.') }}</span>
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showAssignModifierModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" wire:click="saveAssignedModifiers">{{ __('Simpan') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete Confirmation --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Konfirmasi Hapus') }}</flux:heading>
            <flux:text>{{ __('Apakah Anda yakin ingin menghapus item ini?') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showDeleteModal', false)">{{ __('Batal') }}</flux:button>
                <flux:button variant="danger" wire:click="executeDelete">{{ __('Hapus') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
