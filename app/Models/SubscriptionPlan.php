<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionPlanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'duration_months',
        'price',
        'description',
        'features',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'duration_months' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get all subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get formatted duration label.
     */
    public function durationLabel(): string
    {
        return match ($this->duration_months) {
            1 => '1 Bulan',
            3 => '3 Bulan',
            6 => '6 Bulan',
            12 => '1 Tahun',
            default => $this->duration_months.' Bulan',
        };
    }

    /**
     * Get formatted price.
     */
    public function formattedPrice(): string
    {
        return 'Rp '.number_format($this->price, 0, ',', '.');
    }

    /**
     * Get price per month for comparison.
     */
    public function pricePerMonth(): float
    {
        return $this->duration_months > 0 ? $this->price / $this->duration_months : $this->price;
    }
}
