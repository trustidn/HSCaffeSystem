<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Payment;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Seed orders with items and payments for each active tenant.
     */
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $this->seedOrdersForTenant($tenant);
        }
    }

    private function seedOrdersForTenant(Tenant $tenant): void
    {
        $menuItems = MenuItem::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with('variants')
            ->get();

        if ($menuItems->isEmpty()) {
            return;
        }

        $tables = Table::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->pluck('id')
            ->toArray();

        $customers = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->get();

        $cashiers = User::where('tenant_id', $tenant->id)
            ->whereIn('role', ['cashier', 'owner', 'manager'])
            ->pluck('id')
            ->toArray();

        $paymentMethods = [
            PaymentMethod::Cash,
            PaymentMethod::Cash,
            PaymentMethod::Cash,
            PaymentMethod::Qris,
            PaymentMethod::Qris,
            PaymentMethod::EWallet,
            PaymentMethod::BankTransfer,
            PaymentMethod::Edc,
        ];

        // Generate orders over the past 14 days
        for ($daysAgo = 13; $daysAgo >= 0; $daysAgo--) {
            $date = now()->subDays($daysAgo);
            $ordersPerDay = $daysAgo === 0 ? rand(5, 8) : rand(8, 20);

            for ($i = 0; $i < $ordersPerDay; $i++) {
                $this->createOrder(
                    $tenant,
                    $menuItems,
                    $tables,
                    $customers,
                    $cashiers,
                    $paymentMethods,
                    $date,
                    $daysAgo,
                );
            }
        }
    }

    private function createOrder(
        Tenant $tenant,
        $menuItems,
        array $tables,
        $customers,
        array $cashiers,
        array $paymentMethods,
        $date,
        int $daysAgo,
    ): void {
        $orderType = fake()->randomElement([
            OrderType::DineIn, OrderType::DineIn, OrderType::DineIn,
            OrderType::Takeaway, OrderType::Takeaway,
            OrderType::Delivery,
        ]);

        // Determine status based on how old the order is
        $status = $this->determineStatus($daysAgo);
        $paymentStatus = $this->determinePaymentStatus($status);

        $tableId = null;
        if ($orderType === OrderType::DineIn && ! empty($tables)) {
            $tableId = fake()->randomElement($tables);
        }

        $customer = $customers->isNotEmpty() ? $customers->random() : null;
        $cashierId = ! empty($cashiers) ? fake()->randomElement($cashiers) : null;

        $hour = rand(8, 21);
        $minute = rand(0, 59);
        $orderDate = $date->copy()->setTime($hour, $minute);

        $orderNumber = $this->generateOrderNumberForDate($tenant->id, $orderDate);

        $order = Order::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'order_number' => $orderNumber,
            'table_id' => $tableId,
            'customer_id' => $customer?->id,
            'user_id' => $cashierId,
            'type' => $orderType->value,
            'status' => $status->value,
            'payment_status' => $paymentStatus->value,
            'subtotal' => 0,
            'tax_amount' => 0,
            'service_charge' => 0,
            'discount_amount' => 0,
            'total' => 0,
            'notes' => fake()->optional(0.2)->randomElement([
                'Tolong sedikit pedas',
                'Tanpa es',
                'Minta tissue tambahan',
                'Alergi kacang',
                null,
            ]),
            'delivery_address' => $orderType === OrderType::Delivery
                ? fake()->address()
                : null,
            'confirmed_at' => $this->statusReached($status, OrderStatus::Confirmed) ? $orderDate->copy()->addMinutes(2) : null,
            'preparing_at' => $this->statusReached($status, OrderStatus::Preparing) ? $orderDate->copy()->addMinutes(5) : null,
            'ready_at' => $this->statusReached($status, OrderStatus::Ready) ? $orderDate->copy()->addMinutes(15) : null,
            'served_at' => $this->statusReached($status, OrderStatus::Served) ? $orderDate->copy()->addMinutes(18) : null,
            'completed_at' => $this->statusReached($status, OrderStatus::Completed) ? $orderDate->copy()->addMinutes(45) : null,
            'cancelled_at' => $status === OrderStatus::Cancelled ? $orderDate->copy()->addMinutes(10) : null,
            'created_at' => $orderDate,
            'updated_at' => $orderDate,
        ]);

        // Create 1-5 order items
        $itemCount = rand(1, 5);
        $subtotal = 0;

        for ($j = 0; $j < $itemCount; $j++) {
            $menuItem = $menuItems->random();
            $variant = $menuItem->variants->isNotEmpty() ? $menuItem->variants->random() : null;
            $price = $variant ? (float) $variant->price : (float) $menuItem->base_price;
            $qty = rand(1, 3);
            $itemSubtotal = $price * $qty;

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'menu_item_id' => $menuItem->id,
                'menu_variant_id' => $variant?->id,
                'item_name' => $menuItem->name,
                'variant_name' => $variant?->name,
                'unit_price' => $price,
                'quantity' => $qty,
                'subtotal' => $itemSubtotal,
                'notes' => null,
            ]);

            // Occasionally add a modifier
            if ($menuItem->modifiers->isNotEmpty() && fake()->boolean(30)) {
                $modifier = $menuItem->modifiers->random();
                OrderItemModifier::create([
                    'order_item_id' => $orderItem->id,
                    'menu_modifier_id' => $modifier->id,
                    'modifier_name' => $modifier->name,
                    'price' => $modifier->price,
                ]);
                $itemSubtotal += (float) $modifier->price * $qty;
                $orderItem->update(['subtotal' => $itemSubtotal]);
            }

            $subtotal += $itemSubtotal;
        }

        // Calculate totals
        $taxAmount = $subtotal * ($tenant->tax_rate / 100);
        $serviceCharge = $subtotal * ($tenant->service_charge_rate / 100);
        $total = $subtotal + $taxAmount + $serviceCharge;

        $order->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'service_charge' => $serviceCharge,
            'total' => $total,
        ]);

        // Create payment for paid orders
        if ($paymentStatus === PaymentStatus::Paid) {
            $method = fake()->randomElement($paymentMethods);

            Payment::create([
                'order_id' => $order->id,
                'tenant_id' => $tenant->id,
                'method' => $method->value,
                'amount' => $total,
                'reference' => $method !== PaymentMethod::Cash
                    ? strtoupper(fake()->bothify('REF-####-????'))
                    : null,
                'notes' => null,
                'received_by' => $cashierId,
                'created_at' => $orderDate->copy()->addMinutes(rand(5, 30)),
                'updated_at' => $orderDate->copy()->addMinutes(rand(5, 30)),
            ]);
        }
    }

    private function generateOrderNumberForDate(int $tenantId, $date): string
    {
        $prefix = 'ORD-'.$tenantId.'-'.$date->format('Ymd').'-';

        $lastOrder = Order::withoutGlobalScopes()
            ->where('order_number', 'like', $prefix.'%')
            ->orderByDesc('order_number')
            ->first();

        $sequence = 1;
        if ($lastOrder) {
            $lastSeq = (int) str_replace($prefix, '', $lastOrder->order_number);
            $sequence = $lastSeq + 1;
        }

        return $prefix.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    private function determineStatus(int $daysAgo): OrderStatus
    {
        if ($daysAgo === 0) {
            // Today's orders: mix of statuses
            return fake()->randomElement([
                OrderStatus::Pending,
                OrderStatus::Confirmed,
                OrderStatus::Confirmed,
                OrderStatus::Preparing,
                OrderStatus::Ready,
                OrderStatus::Served,
                OrderStatus::Completed,
                OrderStatus::Completed,
            ]);
        }

        // Older orders are mostly completed
        return fake()->randomElement([
            OrderStatus::Completed,
            OrderStatus::Completed,
            OrderStatus::Completed,
            OrderStatus::Completed,
            OrderStatus::Completed,
            OrderStatus::Completed,
            OrderStatus::Completed,
            OrderStatus::Cancelled,
        ]);
    }

    private function determinePaymentStatus(OrderStatus $status): PaymentStatus
    {
        if ($status === OrderStatus::Cancelled) {
            return PaymentStatus::Unpaid;
        }

        if (in_array($status, [OrderStatus::Completed, OrderStatus::Served])) {
            return PaymentStatus::Paid;
        }

        return fake()->randomElement([
            PaymentStatus::Unpaid,
            PaymentStatus::Unpaid,
            PaymentStatus::Paid,
        ]);
    }

    private function statusReached(OrderStatus $current, OrderStatus $target): bool
    {
        $order = [
            OrderStatus::Pending->value => 0,
            OrderStatus::Confirmed->value => 1,
            OrderStatus::Preparing->value => 2,
            OrderStatus::Ready->value => 3,
            OrderStatus::Served->value => 4,
            OrderStatus::Completed->value => 5,
            OrderStatus::Cancelled->value => -1,
        ];

        if ($current === OrderStatus::Cancelled) {
            return false;
        }

        return ($order[$current->value] ?? 0) >= ($order[$target->value] ?? 0);
    }
}
