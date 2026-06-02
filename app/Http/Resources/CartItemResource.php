<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CartItemResource
 *
 * Transforms a CartItem model into a consistent JSON shape
 * returned to the API Gateway / frontend client.
 */
class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'product_id'        => $this->product_id,
            'product_name'      => $this->product_name,
            'product_image_url' => $this->product_image_url,
            'unit_price'        => $this->unit_price,
            'unit_price_fmt'    => 'Rp ' . number_format($this->unit_price, 0, ',', '.'),
            'quantity'          => $this->quantity,
            'subtotal'          => $this->subtotal,
            'subtotal_fmt'      => 'Rp ' . number_format($this->subtotal, 0, ',', '.'),
            'notes'             => $this->notes,
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
