<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CartProductResource — matches ERD cart_products fields.
 */
class CartProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'cart_id'       => $this->cart_id,

            // ERD fields
            'product_id'    => $this->product_id,
            'product_name'  => $this->product_name,
            'product_image' => $this->product_image,
            'shop_name'     => $this->shop_name,
            'shop_id'       => $this->shop_id,

            // Functional fields
            'unit_price'    => $this->unit_price,
            'unit_price_fmt'=> 'Rp ' . number_format($this->unit_price, 0, ',', '.'),
            'quantity'      => $this->quantity,
            'subtotal'      => $this->subtotal,
            'subtotal_fmt'  => 'Rp ' . number_format($this->subtotal, 0, ',', '.'),

            'created_at'    => $this->created_at?->toISOString(),
            'updated_at'    => $this->updated_at?->toISOString(),
        ];
    }
}
