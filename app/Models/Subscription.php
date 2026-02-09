<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'subscription_plan_id',
        'starts_at',
        'ends_at',
        'price_paid',
        'status',
        'payment_reference',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'price_paid' => 'decimal:2',
            'status' => SubscriptionStatus::class,
        ];
    }

    /**
     * Get the tenant for this subscription.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the subscription plan.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Check if subscription is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active
            && $this->ends_at->isFuture();
    }

    /**
     * Check if subscription is in trial.
     */
    public function isTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trial
            && $this->ends_at->isFuture();
    }

    /**
     * Get remaining days.
     */
    public function remainingDays(): int
    {
        return max(0, (int) now()->diffInDays($this->ends_at, false));
    }

    /**
     * Check if subscription is expiring soon (within 7 days).
     */
    public function isExpiringSoon(): bool
    {
        return $this->isActive() && $this->remainingDays() <= 7;
    }
}
