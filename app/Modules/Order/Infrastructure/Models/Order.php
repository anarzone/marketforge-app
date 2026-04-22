<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Models;

use App\Modules\SharedKernel\Domain\ValueObjects\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'total_minor',
        'currency',
        'placed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_minor' => 'integer',
            'currency' => Currency::class,
            'placed_at' => 'datetime',
        ];
    }

    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
