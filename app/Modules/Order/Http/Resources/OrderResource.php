<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'total_minor' => $this->total_minor,
            'currency' => $this->currency->value,
            'placed_at' => $this->placed_at->toIso8601String(),
            'sub_orders' => SubOrderResource::collection($this->whenLoaded('subOrders')),
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        match ($request->method()) {
            'POST' => $response->setStatusCode(JsonResponse::HTTP_CREATED),
            default => null,
        };
    }
}
