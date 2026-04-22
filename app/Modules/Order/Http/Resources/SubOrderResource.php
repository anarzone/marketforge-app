<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_id' => $this->seller_id,
            'status' => $this->status->value,
            'subtotal_minor' => $this->subtotal_minor,
            'currency' => $this->currency->value,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}