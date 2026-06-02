<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CartProduct Model
 *
 * ERD: cart_products (id PK, updated_at, product_name, product_image,
 *                      shop_name, shop_id, product_id FK)
 *
 * Represents one product line inside a Cart.
 * Product details (name, image, shop info, price) are DENORMALIZED here:
 * captured at the moment the buyer adds the item, so price/name changes
 * in Product Service won't retroactively affect in-progress carts.
 *
 * @property int         $id
 * @property int         $cart_id           FK → carts.id
 * @property int         $product_id        Cross-service ref → Product Service
 * @property string      $product_name      Snapshot from Product Service
 * @property string|null $product_image     Snapshot from Product Service
 * @property string      $shop_name         Snapshot from Product Service
 * @property int         $shop_id           Cross-service ref → Product Service
 * @property int         $unit_price        Price in IDR at time of adding
 * @property int         $quantity
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Cart $cart
 */
class CartProduct extends Model
{
    /**
     * Table name matches ERD.
     */
    protected $table = 'cart_products';

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_name',
        'product_image',
        'shop_name',
        'shop_id',
        'unit_price',
        'quantity',
    ];

    protected $casts = [
        'cart_id'    => 'integer',
        'product_id' => 'integer',
        'shop_id'    => 'integer',
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
     * Line subtotal = unit_price × quantity.
     */
    public function getSubtotalAttribute(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
