<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CartItem Model
 *
 * A single product line inside a Cart.
 * Product data is denormalized: the price is captured at the moment
 * the item is added, so price changes in Product Service won't affect
 * an in-progress cart.
 *
 * @property int         $id
 * @property int         $cart_id
 * @property int         $product_id       ID from Product Service
 * @property string      $product_name     Snapshot
 * @property string|null $product_image_url
 * @property int         $unit_price       Price in IDR at time of adding
 * @property int         $quantity
 * @property string|null $notes
 * @property-read Cart   $cart
 */
class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_name',
        'product_image_url',
        'unit_price',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'unit_price' => 'integer',
        'quantity'   => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    // ── Computed ───────────────────────────────────────────────────────────────

    /**
     * Line-item subtotal = unit_price × quantity.
     */
    public function getSubtotalAttribute(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
