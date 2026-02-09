<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\Order;

class OrderObserver
{
    /**
     * Handle the Order "saved" event (covers both create and update).
     * Automatically manages table status based on order status transitions.
     */
    public function saved(Order $order): void
    {
        if (! $order->table_id) {
            return;
        }

        if (! $order->wasChanged('status') && ! $order->wasRecentlyCreated) {
            return;
        }

        match ($order->status) {
            OrderStatus::Confirmed,
            OrderStatus::Preparing,
            OrderStatus::Ready,
            OrderStatus::Served => $this->markTableOccupied($order),
            OrderStatus::Completed,
            OrderStatus::Cancelled => $this->releaseTableIfEmpty($order),
            default => null,
        };
    }

    /**
     * Mark the order's table as occupied.
     */
    private function markTableOccupied(Order $order): void
    {
        $table = $order->table;

        if ($table && $table->status !== TableStatus::Occupied) {
            $table->update(['status' => TableStatus::Occupied->value]);
        }
    }

    /**
     * Release the table back to available if no other active orders exist on it.
     */
    private function releaseTableIfEmpty(Order $order): void
    {
        $table = $order->table;

        if (! $table) {
            return;
        }

        $hasActiveOrders = Order::withoutGlobalScopes()
            ->where('table_id', $order->table_id)
            ->where('id', '!=', $order->id)
            ->whereNotIn('status', [
                OrderStatus::Completed->value,
                OrderStatus::Cancelled->value,
            ])
            ->exists();

        if (! $hasActiveOrders) {
            $table->update(['status' => TableStatus::Available->value]);
        }
    }
}
