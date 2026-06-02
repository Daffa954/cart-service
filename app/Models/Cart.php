<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cart Model
 *
 * ERD: carts (id PK, user_id UNIQUE, updated_at)
 *
 * One cart per user — enforced via unique(user_id).
 * Cart is created on first GET /api/cart and never deleted.
 * Products are managed via the cart_products pivot table.
 *
 * @property int         $id
 * @property int         $user_id      Cross-service ref to Auth Service (no local FK)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read CartProduct[] $products
 */
class Cart extends Model
{
    protected $fillable = [
        'user_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    /**
     * All products currently in this cart.
     */
    public function products(): HasMany
    {
        return $this->hasMany(CartProduct::class);
    }

    // ── Computed Attributes ────────────────────────────────────────────────────

    /**
     * Subtotal = sum of (unit_price × quantity) across all products.
     */
    public function getSubtotalAttribute(): int
    {
        return $this->products->sum(fn (CartProduct $p) => $p->subtotal);
    }

    /**
     * Total number of individual units in the cart.
     */
    public function getItemCountAttribute(): int
    {
        return $this->products->sum('quantity');
    }
}
