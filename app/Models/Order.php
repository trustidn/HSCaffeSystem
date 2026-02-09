<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'order_number',
        'table_id',
        'customer_id',
        'user_id',
        'type',
        'status',
        'payment_status',
        'subtotal',
        'tax_amount',
        'service_charge',
        'discount_amount',
        'total',
        'notes',
        'delivery_address',
        'confirmed_at',
        'preparing_at',
        'ready_at',
        'served_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'preparing_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Generate a unique order number for a tenant.
     */
    public static function generateOrderNumber(int $tenantId): string
    {
        $today = now()->format('Ymd');
        $prefix = 'ORD-'.$tenantId.'-'.$today.'-';

        $lastOrder = static::withoutGlobalScopes()
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

    /**
     * Recalculate totals from items.
     */
    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum('subtotal');
        $tenant = $this->tenant;

        $taxAmount = $subtotal * ($tenant->tax_rate / 100);
        $serviceCharge = $subtotal * ($tenant->service_charge_rate / 100);
        $total = $subtotal + $taxAmount + $serviceCharge - $this->discount_amount;

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'service_charge' => $serviceCharge,
            'total' => max(0, $total),
        ]);
    }
}
