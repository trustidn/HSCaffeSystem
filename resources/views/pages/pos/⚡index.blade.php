<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MenuVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Table;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('POS / Kasir')] class extends Component {
    public string $filterCategory = '';
    public string $search = '';

    /** @var array<int, array{id: int, name: string, variant_id: ?int, variant_name: ?string, price: float, quantity: int, modifiers: array, notes: string}> */
    public array $cart = [];

    public string $orderType = 'dine_in';
    public string $tableId = '';
    public string $customerName = '';
    public string $customerPhone = '';
    public string $deliveryAddress = '';
    public string $orderNotes = '';

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

    // Payment
    public bool $showPaymentModal = false;
    public bool $showSuccessModal = false;
    public string $paymentMethod = 'cash';
    public string $amountPaid = '';
    public ?int $currentOrderId = null;
    public string $lastOrderNumber = '';

    #[Computed]
    public function categories()
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function menuItems()
    {
        return MenuItem::query()
            ->with(['variants', 'modifiers', 'category'])
            ->where('is_active', true)
            ->where('is_available', true)
            ->when($this->filterCategory, fn ($q) => $q->where('category_id', $this->filterCategory))
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function tables()
    {
        return Table::query()->where('status', 'available')->orderBy('number')->get();
    }

    public function addToCart(int $menuItemId, ?int $variantId = null): void
    {
        $item = MenuItem::with(['variants', 'modifiers'])->findOrFail($menuItemId);
        $variant = $variantId ? $item->variants->find($variantId) : null;
        $price = $variant ? (float) $variant->price : (float) $item->base_price;

        // If item has active modifiers, show modifier selection modal
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

        // No modifiers — add directly
        $this->addItemDirectly($menuItemId, $variantId, $item->name, $variant?->name, $price, [], '');
    }

    public function confirmModifiers(): void
    {
        $modifiers = [];
        $modifierPrice = 0;

        foreach ($this->selectedModifiers as $modId => $selected) {
            if ($selected && isset($this->availableModifiers[$modId])) {
                $mod = $this->availableModifiers[$modId];
                $modifiers[$modId] = $mod;
                $modifierPrice += $mod['price'];
            }
        }

        $this->addItemDirectly(
            $this->pendingMenuItemId,
            $this->pendingVariantId,
            '',
            null,
            $this->pendingItemPrice,
            $modifiers,
            $this->itemNotes,
        );

        $this->showModifierModal = false;
    }

    protected function addItemDirectly(int $menuItemId, ?int $variantId, string $name, ?string $variantName, float $basePrice, array $modifiers, string $notes): void
    {
        // Fetch name if not provided (from modifier flow)
        if (! $name) {
            $item = MenuItem::with('variants')->findOrFail($menuItemId);
            $variant = $variantId ? $item->variants->find($variantId) : null;
            $name = $item->name;
            $variantName = $variant?->name;
        }

        $modifierPrice = collect($modifiers)->sum('price');
        $totalPrice = $basePrice + $modifierPrice;

        // Build unique cart key including modifiers
        $modKey = $modifiers ? '-m'.implode(',', array_keys($modifiers)) : '';
        $cartKey = $menuItemId.'-'.($variantId ?? 0).$modKey;

        if (isset($this->cart[$cartKey]) && $this->cart[$cartKey]['notes'] === $notes) {
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

    public function updateQuantity(string $cartKey, int $quantity): void
    {
        if ($quantity <= 0) {
            unset($this->cart[$cartKey]);
        } else {
            $this->cart[$cartKey]['quantity'] = $quantity;
        }
    }

    public function removeFromCart(string $cartKey): void
    {
        unset($this->cart[$cartKey]);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->orderNotes = '';
        $this->customerName = '';
        $this->customerPhone = '';
        $this->deliveryAddress = '';
        $this->tableId = '';
    }

    #[Computed]
    public function cartSubtotal(): float
    {
        return collect($this->cart)->sum(fn ($item) => $item['price'] * $item['quantity']);
    }

    #[Computed]
    public function cartTax(): float
    {
        $tenant = auth()->user()->tenant;

        return $this->cartSubtotal * ($tenant->tax_rate / 100);
    }

    #[Computed]
    public function cartServiceCharge(): float
    {
        $tenant = auth()->user()->tenant;

        return $this->cartSubtotal * ($tenant->service_charge_rate / 100);
    }

    #[Computed]
    public function cartTotal(): float
    {
        return $this->cartSubtotal + $this->cartTax + $this->cartServiceCharge;
    }

    public function placeOrder(): void
    {
        if (empty($this->cart)) {
            return;
        }

        $rules = [
            'customerName' => ['nullable', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]*$/'],
            'orderNotes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->orderType === 'delivery') {
            $rules['deliveryAddress'] = ['required', 'string', 'max:500'];
        }

        $this->validate($rules, [
            'customerPhone.regex' => 'Format nomor telepon tidak valid.',
            'deliveryAddress.required' => 'Alamat delivery wajib diisi.',
        ]);

        $tenant = auth()->user()->tenant;

        // Server-side: validate and recalculate all cart prices from DB
        $validatedCart = $this->validateAndRecalculateCart($tenant);
        if ($validatedCart === null) {
            return;
        }

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'order_number' => Order::generateOrderNumber($tenant->id),
            'table_id' => $this->tableId ?: null,
            'user_id' => auth()->id(),
            'type' => $this->orderType,
            'status' => OrderStatus::Confirmed->value,
            'payment_status' => PaymentStatus::Unpaid->value,
            'notes' => $this->orderNotes ? \Illuminate\Support\Str::limit($this->orderNotes, 1000) : null,
            'delivery_address' => $this->deliveryAddress ? \Illuminate\Support\Str::limit($this->deliveryAddress, 500) : null,
            'confirmed_at' => now(),
        ]);

        // Handle customer
        if ($this->customerName || $this->customerPhone) {
            $customer = \App\Models\Customer::firstOrCreate(
                ['tenant_id' => $tenant->id, 'phone' => $this->customerPhone ?: null],
                ['name' => $this->customerName ?: 'Guest']
            );
            $order->update(['customer_id' => $customer->id]);
        }

        // Create order items from validated cart
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

        $this->currentOrderId = $order->id;
        $this->lastOrderNumber = $order->order_number;
        $this->amountPaid = (string) $order->fresh()->total;
        $this->showSuccessModal = true;
    }

    /**
     * Validate all cart items server-side and recalculate prices from DB.
     *
     * @return array<int, array{menu_item_id: int, variant_id: ?int, name: string, variant_name: ?string, unit_price: float, quantity: int, notes: ?string, modifiers: array}>|null
     */
    protected function validateAndRecalculateCart($tenant): ?array
    {
        $validated = [];

        foreach ($this->cart as $cartItem) {
            $menuItemId = (int) ($cartItem['id'] ?? 0);
            $variantId = $cartItem['variant_id'] ?? null;
            $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));

            $menuItem = MenuItem::with(['variants', 'modifiers'])
                ->where('is_active', true)
                ->where('is_available', true)
                ->find($menuItemId);

            if (! $menuItem) {
                session()->flash('error', 'Menu "' . ($cartItem['name'] ?? 'Unknown') . '" tidak tersedia lagi.');

                return null;
            }

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
                'notes' => \Illuminate\Support\Str::limit($cartItem['notes'] ?? '', 500),
                'modifiers' => $validatedModifiers,
            ];
        }

        return $validated;
    }

    public function openPaymentModal(): void
    {
        $this->showSuccessModal = false;
        $this->showPaymentModal = true;
    }

    public function closeSuccessAndReset(): void
    {
        $this->showSuccessModal = false;
        $this->clearCart();
        $this->currentOrderId = null;
        $this->lastOrderNumber = '';
    }

    public function processPayment(): void
    {
        $order = Order::findOrFail($this->currentOrderId);

        \App\Models\Payment::create([
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'method' => $this->paymentMethod,
            'amount' => $order->total,
            'received_by' => auth()->id(),
        ]);

        $order->update([
            'payment_status' => PaymentStatus::Paid->value,
        ]);

        $this->showPaymentModal = false;
        $this->clearCart();
        $this->currentOrderId = null;
        $this->lastOrderNumber = '';

        session()->flash('success', __('Pembayaran berhasil! Pesanan diteruskan ke dapur.'));
    }
}; ?>

<div class="flex h-[calc(100vh-4rem)] gap-4">
    {{-- Left: Menu Grid --}}
    <div class="flex flex-1 flex-col overflow-hidden">
        {{-- Category Filter --}}
        <div class="mb-3 flex flex-wrap gap-2">
            <flux:badge as="button" wire:click="$set('filterCategory', '')" :variant="$filterCategory === '' ? 'primary' : 'default'" size="sm">
                {{ __('Semua') }}
            </flux:badge>
            @foreach ($this->categories as $cat)
                <flux:badge as="button" wire:click="$set('filterCategory', '{{ $cat->id }}')" :variant="$filterCategory == $cat->id ? 'primary' : 'default'" size="sm">
                    {{ $cat->name }}
                </flux:badge>
            @endforeach
        </div>

        {{-- Search --}}
        <div class="mb-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Cari menu...')" size="sm" />
        </div>

        {{-- Menu Grid --}}
        <div class="flex-1 overflow-y-auto">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($this->menuItems as $item)
                    <div wire:key="pos-item-{{ $item->id }}">
                        @if ($item->variants->isNotEmpty())
                            {{-- Item with variants --}}
                            <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                                @if ($item->effective_image)
                                    <img src="{{ Storage::url($item->effective_image) }}" alt="{{ $item->name }}" class="h-24 w-full object-cover" />
                                @else
                                    <div class="flex h-24 w-full items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon.photo class="size-8 text-zinc-300 dark:text-zinc-600" />
                                    </div>
                                @endif
                                <div class="p-3">
                                    <div class="mb-2 text-sm font-medium">{{ $item->name }}</div>
                                    <div class="space-y-1">
                                        @foreach ($item->variants as $variant)
                                            <button wire:click="addToCart({{ $item->id }}, {{ $variant->id }})" class="flex w-full items-center justify-between rounded bg-zinc-100 px-2 py-1.5 text-xs hover:bg-indigo-100 hover:text-indigo-700 dark:bg-zinc-800 dark:hover:bg-indigo-950 dark:hover:text-indigo-400">
                                                <span>{{ $variant->name }}</span>
                                                <span class="font-semibold">Rp {{ number_format($variant->price, 0, ',', '.') }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- Simple item --}}
                            <button wire:click="addToCart({{ $item->id }})" class="group w-full overflow-hidden rounded-lg border border-zinc-200 text-left transition hover:border-indigo-400 hover:shadow-sm dark:border-zinc-700 dark:hover:border-indigo-600">
                                @if ($item->effective_image)
                                    <img src="{{ Storage::url($item->effective_image) }}" alt="{{ $item->name }}" class="h-24 w-full object-cover transition group-hover:scale-105" />
                                @else
                                    <div class="flex h-24 w-full items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon.photo class="size-8 text-zinc-300 dark:text-zinc-600" />
                                    </div>
                                @endif
                                <div class="p-3">
                                    <div class="text-sm font-medium">{{ $item->name }}</div>
                                    <div class="text-sm font-bold text-indigo-600 dark:text-indigo-400">Rp {{ number_format($item->base_price, 0, ',', '.') }}</div>
                                </div>
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Right: Cart / Order Summary --}}
    <div class="flex w-80 flex-col rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 lg:w-96">
        <div class="border-b border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Pesanan') }}</flux:heading>

            {{-- Order Type --}}
            <div class="mt-3 flex gap-2">
                @foreach (OrderType::cases() as $type)
                    <flux:badge as="button" wire:click="$set('orderType', '{{ $type->value }}')" :variant="$orderType === $type->value ? 'primary' : 'default'" size="sm">
                        {{ $type->label() }}
                    </flux:badge>
                @endforeach
            </div>

            {{-- Table selection for dine-in --}}
            @if ($orderType === 'dine_in')
                <div class="mt-2">
                    <flux:select wire:model="tableId" size="sm">
                        <option value="">{{ __('Pilih meja...') }}</option>
                        @foreach ($this->tables as $table)
                            <option value="{{ $table->id }}">{{ __('Meja') }} {{ $table->number }} ({{ $table->capacity }} org)</option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            {{-- Customer info --}}
            <div class="mt-2 grid grid-cols-2 gap-2">
                <flux:input wire:model="customerName" size="sm" :placeholder="__('Nama customer')" />
                <flux:input wire:model="customerPhone" size="sm" :placeholder="__('No. HP')" />
            </div>

            @if ($orderType === 'delivery')
                <flux:input wire:model="deliveryAddress" size="sm" :placeholder="__('Alamat delivery')" class="mt-2" />
            @endif
        </div>

        {{-- Cart Items --}}
        <div class="flex-1 overflow-y-auto p-4">
            @forelse ($cart as $key => $item)
                <div wire:key="cart-{{ $key }}" class="mb-3 rounded-lg bg-white p-3 shadow-sm dark:bg-zinc-800">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-sm font-medium">{{ $item['name'] }}</div>
                            @if ($item['variant_name'])
                                <div class="text-xs text-zinc-500">{{ $item['variant_name'] }}</div>
                            @endif
                            @if (! empty($item['modifiers']))
                                @foreach ($item['modifiers'] as $mod)
                                    <div class="text-xs text-indigo-500">+ {{ $mod['name'] }} (+Rp {{ number_format($mod['price'], 0, ',', '.') }})</div>
                                @endforeach
                            @endif
                            @if (! empty($item['notes']))
                                <div class="text-xs italic text-amber-600 dark:text-amber-400">{{ $item['notes'] }}</div>
                            @endif
                            <div class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">Rp {{ number_format($item['price'], 0, ',', '.') }}</div>
                        </div>
                        <button wire:click="removeFromCart('{{ $key }}')" class="text-red-400 hover:text-red-600">
                            <flux:icon.x-mark class="size-4" />
                        </button>
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <button wire:click="updateQuantity('{{ $key }}', {{ $item['quantity'] - 1 }})" class="flex h-7 w-7 items-center justify-center rounded border text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700">-</button>
                        <span class="w-8 text-center text-sm font-medium">{{ $item['quantity'] }}</span>
                        <button wire:click="updateQuantity('{{ $key }}', {{ $item['quantity'] + 1 }})" class="flex h-7 w-7 items-center justify-center rounded border text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700">+</button>
                        <span class="ml-auto text-sm font-bold">Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}</span>
                    </div>
                </div>
            @empty
                <div class="flex h-full items-center justify-center text-zinc-400">
                    <div class="text-center">
                        <flux:icon.shopping-cart class="mx-auto size-12" />
                        <flux:text class="mt-2">{{ __('Keranjang kosong') }}</flux:text>
                    </div>
                </div>
            @endforelse
        </div>

        {{-- Totals & Actions --}}
        @if (! empty($cart))
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between"><span>Subtotal</span><span>Rp {{ number_format($this->cartSubtotal, 0, ',', '.') }}</span></div>
                    <div class="flex justify-between text-zinc-500"><span>Pajak</span><span>Rp {{ number_format($this->cartTax, 0, ',', '.') }}</span></div>
                    @if ($this->cartServiceCharge > 0)
                        <div class="flex justify-between text-zinc-500"><span>Service</span><span>Rp {{ number_format($this->cartServiceCharge, 0, ',', '.') }}</span></div>
                    @endif
                    <div class="flex justify-between border-t pt-1 text-lg font-bold dark:border-zinc-600"><span>Total</span><span>Rp {{ number_format($this->cartTotal, 0, ',', '.') }}</span></div>
                </div>
                <div class="mt-3 flex gap-2">
                    <flux:button wire:click="clearCart" class="flex-1">{{ __('Batal') }}</flux:button>
                    <flux:button variant="primary" wire:click="placeOrder" class="flex-1">{{ __('Proses Order') }}</flux:button>
                </div>
            </div>
        @endif
    </div>

    @if (session('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" x-transition class="fixed bottom-4 right-4 rounded-lg bg-emerald-600 px-4 py-3 text-white shadow-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Modifier Selection Modal --}}
    <flux:modal wire:model="showModifierModal" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $pendingItemName }}</flux:heading>
            <flux:text size="sm">{{ __('Pilih tambahan (opsional):') }}</flux:text>

            <div class="space-y-2">
                @foreach ($availableModifiers as $modId => $mod)
                    <label wire:key="mod-{{ $modId }}" class="flex cursor-pointer items-center justify-between rounded-lg border px-3 py-2 transition {{ ($selectedModifiers[$modId] ?? false) ? 'border-indigo-400 bg-indigo-50 dark:border-indigo-600 dark:bg-indigo-950' : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800' }}">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model.live="selectedModifiers.{{ $modId }}" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm">{{ $mod['name'] }}</span>
                        </div>
                        <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">+Rp {{ number_format($mod['price'], 0, ',', '.') }}</span>
                    </label>
                @endforeach
            </div>

            <flux:input wire:model="itemNotes" size="sm" :placeholder="__('Catatan item (opsional)')" />

            @php
                $modTotal = collect($selectedModifiers)->filter()->keys()->reduce(function ($carry, $modId) use ($availableModifiers) {
                    return $carry + ($availableModifiers[$modId]['price'] ?? 0);
                }, 0);
            @endphp
            <div class="rounded-lg bg-zinc-100 p-3 text-center dark:bg-zinc-800">
                <div class="text-sm text-zinc-500">{{ __('Harga total per item') }}</div>
                <div class="text-lg font-bold">Rp {{ number_format($pendingItemPrice + $modTotal, 0, ',', '.') }}</div>
            </div>

            <div class="flex gap-2">
                <flux:button wire:click="$set('showModifierModal', false)" class="flex-1">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" wire:click="confirmModifiers" class="flex-1">{{ __('Tambah ke Keranjang') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Order Success Modal --}}
    <flux:modal wire:model="showSuccessModal" class="max-w-sm">
        <div class="space-y-5 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                <flux:icon.check class="size-8 text-emerald-600" />
            </div>

            <div>
                <flux:heading size="lg">{{ __('Pesanan Berhasil Dibuat!') }}</flux:heading>
                <flux:text class="mt-1">{{ $lastOrderNumber }}</flux:text>
            </div>

            @if ($currentOrderId)
                @php $currentOrder = \App\Models\Order::find($currentOrderId); @endphp
                @if ($currentOrder)
                    <div class="rounded-lg bg-zinc-100 p-4 dark:bg-zinc-800">
                        <div class="text-2xl font-bold">Rp {{ number_format($currentOrder->total, 0, ',', '.') }}</div>
                    </div>
                @endif
            @endif

            <flux:callout variant="info" icon="information-circle">
                {{ __('Pesanan diteruskan ke dapur untuk diproses. Status: Dikonfirmasi.') }}
            </flux:callout>

            <div class="flex gap-2">
                <flux:button wire:click="closeSuccessAndReset" class="flex-1">
                    {{ __('Pesanan Baru') }}
                </flux:button>
                <flux:button variant="primary" wire:click="openPaymentModal" class="flex-1">
                    {{ __('Bayar Sekarang') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Payment Modal --}}
    <flux:modal wire:model="showPaymentModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Pembayaran') }}</flux:heading>

            @if ($currentOrderId)
                @php $currentOrder = \App\Models\Order::find($currentOrderId); @endphp
                @if ($currentOrder)
                    <div class="rounded-lg bg-zinc-100 p-4 dark:bg-zinc-800">
                        <div class="text-center text-2xl font-bold">Rp {{ number_format($currentOrder->total, 0, ',', '.') }}</div>
                        <div class="text-center text-sm text-zinc-500">{{ $currentOrder->order_number }}</div>
                    </div>
                @endif
            @endif

            <flux:select wire:model="paymentMethod" :label="__('Metode Pembayaran')">
                <option value="cash">{{ __('Cash') }}</option>
                <option value="bank_transfer">{{ __('Transfer Bank') }}</option>
                <option value="qris">{{ __('QRIS') }}</option>
                <option value="e_wallet">{{ __('E-Wallet') }}</option>
                <option value="edc">{{ __('EDC / Kartu') }}</option>
            </flux:select>

            @if ($paymentMethod === 'cash')
                <flux:input wire:model.live="amountPaid" :label="__('Jumlah Bayar (Rp)')" type="number" />
                @if ($currentOrderId && is_numeric($amountPaid))
                    @php
                        $order = \App\Models\Order::find($currentOrderId);
                        $change = $order ? (float) $amountPaid - (float) $order->total : 0;
                    @endphp
                    @if ($change >= 0)
                        <div class="rounded-lg bg-emerald-50 p-3 text-center dark:bg-emerald-900/20">
                            <div class="text-sm text-zinc-500">{{ __('Kembalian') }}</div>
                            <div class="text-xl font-bold text-emerald-600">Rp {{ number_format($change, 0, ',', '.') }}</div>
                        </div>
                    @endif
                @endif
            @endif

            <div class="flex gap-2 pt-2">
                <flux:button wire:click="$set('showPaymentModal', false)" class="flex-1">{{ __('Bayar Nanti') }}</flux:button>
                <flux:button variant="primary" wire:click="processPayment" class="flex-1">{{ __('Bayar Sekarang') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
