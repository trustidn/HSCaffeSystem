<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'slug',
        'description',
        'base_price',
        'image',
        'sort_order',
        'is_active',
        'is_available',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    /**
     * Get the category this item belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all variants for this menu item.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(MenuVariant::class)->orderBy('sort_order');
    }

    /**
     * Get all recipes (ingredient requirements) for this menu item.
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * Get all modifiers available for this menu item.
     */
    public function modifiers(): BelongsToMany
    {
        return $this->belongsToMany(MenuModifier::class, 'menu_item_modifier');
    }

    /**
     * Get the effective image: item's own image, or fallback to category default image.
     */
    public function getEffectiveImageAttribute(): ?string
    {
        return $this->image ?? $this->category?->image;
    }

    /**
     * Get the display price (base price or lowest variant price).
     */
    public function getDisplayPriceAttribute(): string
    {
        if ($this->variants->isNotEmpty()) {
            return number_format($this->variants->min('price'), 0, ',', '.');
        }

        return number_format($this->base_price, 0, ',', '.');
    }
}
