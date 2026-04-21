<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Models;

use App\Modules\SharedKernel\Domain\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'sub_order_id',
        'seller_id',
        'product_id',
        'quantity',
        'unit_price_minor',
        'subtotal_minor',
        'currency',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'subtotal_minor' => 'integer',
            'currency' => Currency::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }
}
