<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CartResource — matches ERD carts fields + embedded products.
 */
class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ERD fields
            'id'          => $this->id,
            'user_id'     => $this->user_id,
            'updated_at'  => $this->updated_at?->toISOString(),
            'created_at'  => $this->created_at?->toISOString(),

            // Computed
            'item_count'  => $this->item_count,
            'subtotal'    => $this->subtotal,
            'subtotal_fmt'=> 'Rp ' . number_format($this->subtotal, 0, ',', '.'),

            // Embedded products (cart_products rows)
            'products'    => CartProductResource::collection($this->whenLoaded('products')),
        ];
    }
}
