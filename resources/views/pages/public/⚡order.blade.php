<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Table;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pesan Online')] #[Layout('layouts.public')] class extends Component {
    public ?Tenant $tenant = null;
    public ?Table $table = null;
    public string $filterCategory = '';

    /** @var array<string, array{id: int, name: string, variant_id: ?int, variant_name: ?string, price: float, base_price: float, quantity: int, modifiers: array, notes: string}> */
    public array $cart = [];

    public string $orderType = 'dine_in';
    public string $customerName = '';
    public string $customerPhone = '';
    public string $deliveryAddress = '';
    public string $orderNotes = '';

    public bool $showCart = false;
    public bool $orderPlaced = false;
    public string $orderNumber = '';

    // Modifier selection
    public bool $showModifierModal = false;
    public ?int $pendingMenuItemId = null;
    public ?int $pendingVariantId = null;
    public string $pendingItemName = '';
    public float $pendingItemPrice = 0;
    /** @var array<int, bool> */
    public array $selectedModifiers = [];
    /** @var array<int, array{name: string, price: float}> */
    public array $availableModifiers = [];
    public string $itemNotes = '';

    public function mount(string $tenantSlug): void
    {
        $this->tenant = Tenant::where('slug', $tenantSlug)->where('is_active', true)->firstOrFail();

        $tableToken = request()->query('table');
        if ($tableToken) {
            $this->table = Table::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant->id)
                ->where('qr_token', $tableToken)
                ->first();
            $this->orderType = 'dine_in';
        } else {
            $this->orderType = 'takeaway';
        }
    }

    #[Computed]
    public function categories()
    {
        return Category::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->withCount(['menuItems' => fn ($q) => $q->where('is_active', true)->where('is_available', true)])
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function menuItems()
    {
        return MenuItem::withoutGlobalScopes()
            ->with(['variants', 'modifiers', 'category'])
            ->where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->where('is_available', true)
            ->when($this->filterCategory, fn ($q) => $q->where('category_id', $this->filterCategory))
            ->orderBy('sort_order')
            ->get();
    }

    public function addToCart(int $menuItemId, ?int $variantId = null): void
    {
        $item = MenuItem::withoutGlobalScopes()->with(['variants', 'modifiers'])->findOrFail($menuItemId);
        $variant = $variantId ? $item->variants->find($variantId) : null;
        $price = $variant ? (float) $variant->price : (float) $item->base_price;

        $activeModifiers = $item->modifiers->where('is_active', true);
        if ($activeModifiers->isNotEmpty()) {
            $this->pendingMenuItemId = $menuItemId;
            $this->pendingVariantId = $variantId;
            $this->pendingItemName = $item->name.($variant ? ' ('.$variant->name.')' : '');
            $this->pendingItemPrice = $price;
            $this->selectedModifiers = [];
            $this->availableModifiers = [];
            $this->itemNotes = '';

            foreach ($activeModifiers as $mod) {
                $this->availableModifiers[$mod->id] = [
                    'name' => $mod->name,
                    'price' => (float) $mod->price,
                ];
                $this->selectedModifiers[$mod->id] = false;
            }

            $this->showModifierModal = true;

            return;
        }

        $this->addItemDirectly($menuItemId, $variantId, $item->name, $variant?->name, $price, [], '');
    }

    public function confirmModifiers(): void
    {
        $modifiers = [];

        foreach ($this->selectedModifiers as $modId => $selected) {
            if ($selected && isset($this->availableModifiers[$modId])) {
                $modifiers[$modId] = $this->availableModifiers[$modId];
            }
        }

        $item = MenuItem::withoutGlobalScopes()->with('variants')->findOrFail($this->pendingMenuItemId);
        $variant = $this->pendingVariantId ? $item->variants->find($this->pendingVariantId) : null;

        $this->addItemDirectly(
            $this->pendingMenuItemId,
            $this->pendingVariantId,
            $item->name,
            $variant?->name,
            $this->pendingItemPrice,
            $modifiers,
            $this->itemNotes,
        );

        $this->showModifierModal = false;
    }

    protected function addItemDirectly(int $menuItemId, ?int $variantId, string $name, ?string $variantName, float $basePrice, array $modifiers, string $notes): void
    {
        $modifierPrice = collect($modifiers)->sum('price');
        $totalPrice = $basePrice + $modifierPrice;

        $modKey = $modifiers ? '-m'.implode(',', array_keys($modifiers)) : '';
        $cartKey = $menuItemId.'-'.($variantId ?? 0).$modKey;

        if (isset($this->cart[$cartKey]) && ($this->cart[$cartKey]['notes'] ?? '') === $notes) {
            $this->cart[$cartKey]['quantity']++;
        } else {
            $this->cart[$cartKey] = [
                'id' => $menuItemId,
                'name' => $name,
                'variant_id' => $variantId,
                'variant_name' => $variantName,
                'price' => $totalPrice,
                'base_price' => $basePrice,
                'quantity' => 1,
                'modifiers' => $modifiers,
                'notes' => $notes,
            ];
        }
    }

    public function updateQuantity(string $key, int $qty): void
    {
        if ($qty <= 0) {
            unset($this->cart[$key]);
        } else {
            $this->cart[$key]['quantity'] = $qty;
        }
    }

    public function removeFromCart(string $key): void
    {
        unset($this->cart[$key]);
    }

    #[Computed]
    public function cartTotal(): float
    {
        $subtotal = collect($this->cart)->sum(fn ($item) => $item['price'] * $item['quantity']);
        $tax = $subtotal * ($this->tenant->tax_rate / 100);
        $service = $subtotal * ($this->tenant->service_charge_rate / 100);

        return $subtotal + $tax + $service;
    }

    #[Computed]
    public function cartCount(): int
    {
        return collect($this->cart)->sum('quantity');
    }

    public function placeOrder(): void
    {
        if (empty($this->cart)) {
            return;
        }

        $rules = [
            'customerName' => ['required', 'string', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'orderNotes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->orderType === 'delivery') {
            $rules['deliveryAddress'] = ['required', 'string', 'max:500'];
        }

        $this->validate($rules, [
            'customerPhone.regex' => 'Format nomor telepon tidak valid.',
            'deliveryAddress.required' => 'Alamat delivery wajib diisi.',
        ]);

        // Server-side cart validation: verify all items exist, are active, and recalculate prices
        $validatedCart = $this->validateAndRecalculateCart();
        if ($validatedCart === null) {
            return; // Error already flashed
        }

        $customer = \App\Models\Customer::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'phone' => $this->customerPhone],
            ['name' => $this->customerName]
        );

        $order = Order::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'order_number' => Order::generateOrderNumber($this->tenant->id),
            'table_id' => $this->table?->id,
            'customer_id' => $customer->id,
            'type' => $this->orderType,
            'status' => OrderStatus::Pending->value,
            'payment_status' => PaymentStatus::Unpaid->value,
            'notes' => $this->orderNotes ?: null,
            'delivery_address' => $this->deliveryAddress ?: null,
        ]);

        foreach ($validatedCart as $cartItem) {
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'menu_item_id' => $cartItem['menu_item_id'],
                'menu_variant_id' => $cartItem['variant_id'],
                'item_name' => $cartItem['name'],
                'variant_name' => $cartItem['variant_name'],
                'unit_price' => $cartItem['unit_price'],
                'quantity' => $cartItem['quantity'],
                'subtotal' => $cartItem['unit_price'] * $cartItem['quantity'],
                'notes' => $cartItem['notes'] ?: null,
            ]);

            foreach ($cartItem['modifiers'] as $mod) {
                OrderItemModifier::create([
                    'order_item_id' => $orderItem->id,
                    'menu_modifier_id' => $mod['id'],
                    'modifier_name' => $mod['name'],
                    'price' => $mod['price'],
                ]);
            }
        }

        $order->recalculateTotals();

        $this->orderNumber = $order->order_number;
        $this->orderPlaced = true;
        $this->cart = [];
        $this->showCart = false;
    }

    /**
     * Validate all cart items server-side: verify items exist, are available, belong to tenant, and recalculate prices from DB.
     *
     * @return array<int, array{menu_item_id: int, variant_id: ?int, name: string, variant_name: ?string, unit_price: float, quantity: int, notes: ?string, modifiers: array}>|null
     */
    protected function validateAndRecalculateCart(): ?array
    {
        $validated = [];

        foreach ($this->cart as $cartItem) {
            $menuItemId = (int) ($cartItem['id'] ?? 0);
            $variantId = $cartItem['variant_id'] ?? null;
            $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));

            // Verify menu item exists, is active, available, and belongs to this tenant
            $menuItem = MenuItem::withoutGlobalScopes()
                ->with(['variants', 'modifiers'])
                ->where('tenant_id', $this->tenant->id)
                ->where('is_active', true)
                ->where('is_available', true)
                ->find($menuItemId);

            if (! $menuItem) {
                session()->flash('error', 'Menu "' . ($cartItem['name'] ?? 'Unknown') . '" tidak tersedia lagi.');

                return null;
            }

            // Recalculate base price from DB
            $basePrice = (float) $menuItem->base_price;
            $variantName = null;

            if ($variantId) {
                $variant = $menuItem->variants->where('is_active', true)->find($variantId);
                if (! $variant) {
                    session()->flash('error', 'Varian untuk "' . $menuItem->name . '" tidak tersedia lagi.');

                    return null;
                }
                $basePrice = (float) $variant->price;
                $variantName = $variant->name;
            }

            // Validate and recalculate modifier prices from DB
            $validatedModifiers = [];
            foreach ($cartItem['modifiers'] ?? [] as $modId => $modData) {
                $modifier = $menuItem->modifiers->where('is_active', true)->find((int) $modId);
                if ($modifier) {
                    $validatedModifiers[] = [
                        'id' => $modifier->id,
                        'name' => $modifier->name,
                        'price' => (float) $modifier->price,
                    ];
                }
            }

            $modifierTotal = collect($validatedModifiers)->sum('price');

            $validated[] = [
                'menu_item_id' => $menuItem->id,
                'variant_id' => $variantId,
                'name' => $menuItem->name,
                'variant_name' => $variantName,
                'unit_price' => $basePrice + $modifierTotal,
                'quantity' => $quantity,
                'notes' => Str::limit($cartItem['notes'] ?? '', 500),
                'modifiers' => $validatedModifiers,
            ];
        }

        return $validated;
    }
}; ?>

<div class="min-h-screen" style="--primary: {{ $tenant->primary_color }}; --secondary: {{ $tenant->secondary_color }}">
    {{-- Header --}}
    <div class="sticky top-0 z-10 border-b bg-white px-4 py-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mx-auto flex max-w-4xl items-center justify-between">
            <div>
                <h1 class="text-xl font-bold" style="color: var(--primary)">{{ $tenant->name }}</h1>
                @if ($table)
                    <p class="text-sm text-zinc-500">{{ __('Meja') }} {{ $table->number }}</p>
                @endif
            </div>
            @if (! $orderPlaced && ! empty($cart))
                <button wire:click="$toggle('showCart')" class="relative rounded-full p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <flux:icon.shopping-cart class="size-6" />
                    <span class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold text-white" style="background-color: var(--primary)">
                        {{ $this->cartCount }}
                    </span>
                </button>
            @endif
        </div>
    </div>

    @if ($orderPlaced)
        {{-- Order Confirmation --}}
        <div class="flex min-h-[60vh] items-center justify-center p-4">
            <div class="max-w-sm text-center">
                <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                    <flux:icon.check class="size-10 text-emerald-600" />
                </div>
                <h2 class="text-2xl font-bold">{{ __('Pesanan Berhasil!') }}</h2>
                <p class="mt-2 text-zinc-500">{{ __('Nomor pesanan Anda:') }}</p>
                <p class="mt-1 text-3xl font-bold" style="color: var(--primary)">{{ $orderNumber }}</p>
                <p class="mt-4 text-sm text-zinc-500">{{ __('Pesanan Anda sedang diproses. Silakan tunggu.') }}</p>
                <div class="mt-6 flex flex-col items-center gap-3">
                    <a href="{{ route('public.track', $tenant->slug) }}?q={{ $orderNumber }}" class="rounded-lg border-2 px-6 py-2 text-sm font-medium" style="border-color: var(--primary); color: var(--primary)">
                        {{ __('Lacak Pesanan') }}
                    </a>
                    <button wire:click="$set('orderPlaced', false)" class="rounded-lg px-6 py-2 text-white" style="background-color: var(--primary)">
                        {{ __('Pesan Lagi') }}
                    </button>
                </div>
            </div>
        </div>
    @elseif ($showCart)
        {{-- Cart View --}}
        <div class="mx-auto max-w-4xl p-4 space-y-4">
            <button wire:click="$set('showCart', false)" class="flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-700">
                <flux:icon.arrow-left class="size-4" /> {{ __('Kembali ke menu') }}
            </button>

            <h2 class="text-xl font-bold">{{ __('Keranjang Anda') }}</h2>

            <div class="space-y-3">
                @foreach ($cart as $key => $item)
                    <div wire:key="pub-cart-{{ $key }}" class="flex items-center gap-4 rounded-lg border p-3 dark:border-zinc-700">
                        <div class="flex-1">
                            <div class="font-medium">{{ $item['name'] }}</div>
                            @if ($item['variant_name'] ?? null)
                                <div class="text-sm text-zinc-500">{{ $item['variant_name'] }}</div>
                            @endif
                            @if (! empty($item['modifiers']))
                                @foreach ($item['modifiers'] as $mod)
                                    <div class="text-xs" style="color: var(--primary)">+ {{ $mod['name'] }} (+Rp {{ number_format($mod['price'], 0, ',', '.') }})</div>
                                @endforeach
                            @endif
                            @if (! empty($item['notes']))
                                <div class="text-xs italic text-amber-600">{{ $item['notes'] }}</div>
                            @endif
                            <div class="font-semibold" style="color: var(--primary)">Rp {{ number_format($item['price'], 0, ',', '.') }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="updateQuantity('{{ $key }}', {{ $item['quantity'] - 1 }})" class="flex h-8 w-8 items-center justify-center rounded-full border">-</button>
                            <span class="w-8 text-center font-medium">{{ $item['quantity'] }}</span>
                            <button wire:click="updateQuantity('{{ $key }}', {{ $item['quantity'] + 1 }})" class="flex h-8 w-8 items-center justify-center rounded-full border">+</button>
                        </div>
                        <div class="w-24 text-right font-bold">Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Order Type --}}
            @if (! $table)
                <div>
                    <label class="text-sm font-medium">{{ __('Tipe Pesanan') }}</label>
                    <div class="mt-1 flex gap-2">
                        <button wire:click="$set('orderType', 'takeaway')" class="rounded-lg border px-4 py-2 text-sm {{ $orderType === 'takeaway' ? 'border-2 font-bold' : '' }}" style="{{ $orderType === 'takeaway' ? 'border-color: var(--primary); color: var(--primary)' : '' }}">
                            {{ __('Takeaway') }}
                        </button>
                        <button wire:click="$set('orderType', 'delivery')" class="rounded-lg border px-4 py-2 text-sm {{ $orderType === 'delivery' ? 'border-2 font-bold' : '' }}" style="{{ $orderType === 'delivery' ? 'border-color: var(--primary); color: var(--primary)' : '' }}">
                            {{ __('Delivery') }}
                        </button>
                    </div>
                </div>
            @endif

            {{-- Customer Info --}}
            <div class="space-y-3">
                <flux:input wire:model="customerName" :label="__('Nama Anda')" required />
                <flux:input wire:model="customerPhone" :label="__('No. HP')" required />
                @if ($orderType === 'delivery')
                    <flux:textarea wire:model="deliveryAddress" :label="__('Alamat Delivery')" rows="2" required />
                @endif
                <flux:textarea wire:model="orderNotes" :label="__('Catatan')" rows="2" />
            </div>

            {{-- Total & Submit --}}
            <div class="rounded-lg border-2 p-4 dark:border-zinc-600">
                <div class="flex justify-between text-xl font-bold">
                    <span>Total</span>
                    <span style="color: var(--primary)">Rp {{ number_format($this->cartTotal, 0, ',', '.') }}</span>
                </div>
                <p class="text-xs text-zinc-500">{{ __('*Termasuk pajak dan service charge') }}</p>
            </div>

            <button wire:click="placeOrder" class="w-full rounded-lg py-3 text-lg font-bold text-white" style="background-color: var(--primary)">
                {{ __('Pesan Sekarang') }}
            </button>
        </div>
    @else
        {{-- Menu View --}}
        <div class="mx-auto max-w-4xl p-4">
            {{-- Categories --}}
            <div class="mb-4 flex gap-2 overflow-x-auto pb-2">
                <button wire:click="$set('filterCategory', '')" class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-medium {{ $filterCategory === '' ? 'text-white' : 'bg-zinc-100 dark:bg-zinc-800' }}" style="{{ $filterCategory === '' ? 'background-color: var(--primary)' : '' }}">
                    {{ __('Semua') }}
                </button>
                @foreach ($this->categories as $cat)
                    <button wire:click="$set('filterCategory', '{{ $cat->id }}')" class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-medium {{ $filterCategory == $cat->id ? 'text-white' : 'bg-zinc-100 dark:bg-zinc-800' }}" style="{{ $filterCategory == $cat->id ? 'background-color: var(--primary)' : '' }}">
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>

            {{-- Menu Items --}}
            <div class="space-y-3">
                @foreach ($this->menuItems as $item)
                    <div wire:key="pub-item-{{ $item->id }}" class="flex gap-4 rounded-xl border p-4 dark:border-zinc-700">
                        @if ($item->effective_image)
                            <img src="{{ Storage::url($item->effective_image) }}" alt="{{ $item->name }}" class="h-24 w-24 rounded-lg object-cover">
                        @endif
                        <div class="flex-1">
                            <h3 class="font-semibold">{{ $item->name }}</h3>
                            @if ($item->description)
                                <p class="mt-1 text-sm text-zinc-500 line-clamp-2">{{ $item->description }}</p>
                            @endif
                            @if ($item->modifiers->where('is_active', true)->isNotEmpty())
                                <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">
                                    <flux:icon.sparkles class="inline size-3" /> {{ __('Tersedia tambahan') }}
                                </p>
                            @endif

                            @if ($item->variants->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($item->variants->where('is_active', true) as $variant)
                                        <button wire:click="addToCart({{ $item->id }}, {{ $variant->id }})" class="rounded-lg border px-3 py-1.5 text-sm hover:border-current" style="color: var(--primary)">
                                            {{ $variant->name }} - Rp {{ number_format($variant->price, 0, ',', '.') }}
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-lg font-bold" style="color: var(--primary)">Rp {{ number_format($item->base_price, 0, ',', '.') }}</span>
                                    <button wire:click="addToCart({{ $item->id }})" class="rounded-full p-2 text-white" style="background-color: var(--primary)">
                                        <flux:icon.plus class="size-5" />
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Floating Cart Button --}}
        @if (! empty($cart))
            <div class="fixed bottom-4 left-0 right-0 z-20 mx-auto max-w-4xl px-4">
                <button wire:click="$set('showCart', true)" class="flex w-full items-center justify-between rounded-xl px-6 py-4 text-white shadow-lg" style="background-color: var(--primary)">
                    <div class="flex items-center gap-2">
                        <flux:icon.shopping-cart class="size-5" />
                        <span class="font-bold">{{ $this->cartCount }} item</span>
                    </div>
                    <span class="text-lg font-bold">Rp {{ number_format($this->cartTotal, 0, ',', '.') }}</span>
                </button>
            </div>
        @endif
    @endif

    {{-- Modifier Selection Modal --}}
    <flux:modal wire:model="showModifierModal" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Pilih Tambahan') }}</flux:heading>
                <p class="text-sm text-zinc-500">{{ $pendingItemName }} - Rp {{ number_format($pendingItemPrice, 0, ',', '.') }}</p>
            </div>

            <div class="space-y-2">
                @foreach ($availableModifiers as $modId => $mod)
                    <label wire:key="pub-mod-{{ $modId }}" class="flex cursor-pointer items-center justify-between rounded-lg border p-3 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model.live="selectedModifiers.{{ $modId }}" class="size-5 rounded" style="accent-color: var(--primary)" />
                            <span class="font-medium">{{ $mod['name'] }}</span>
                        </div>
                        @if ($mod['price'] > 0)
                            <span class="text-sm font-semibold" style="color: var(--primary)">+Rp {{ number_format($mod['price'], 0, ',', '.') }}</span>
                        @else
                            <span class="text-sm text-zinc-400">Gratis</span>
                        @endif
                    </label>
                @endforeach
            </div>

            <flux:textarea wire:model="itemNotes" :label="__('Catatan item')" rows="2" :placeholder="__('Contoh: tidak pedas, extra sambal')" />

            <div class="flex items-center justify-between rounded-lg border-2 p-3 dark:border-zinc-600">
                <span class="font-medium">{{ __('Total per item') }}</span>
                <span class="text-lg font-bold" style="color: var(--primary)">
                    Rp {{ number_format($pendingItemPrice + collect($selectedModifiers)->filter()->keys()->sum(fn ($id) => $availableModifiers[$id]['price'] ?? 0), 0, ',', '.') }}
                </span>
            </div>

            <div class="flex gap-2">
                <flux:button wire:click="$set('showModifierModal', false)" class="flex-1">{{ __('Batal') }}</flux:button>
                <flux:button wire:click="confirmModifiers" variant="primary" class="flex-1">{{ __('Tambah ke Keranjang') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
