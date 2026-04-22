<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Models;

use App\Modules\Order\Domain\Enums\SubOrderStatus;
use App\Modules\SharedKernel\Domain\ValueObjects\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubOrder extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'status',
        'subtotal_minor',
        'currency',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SubOrderStatus::class,
            'subtotal_minor' => 'integer',
            'currency' => Currency::class,
        ];
    }

    public function transitionTo(SubOrderStatus $target): void
    {
        $this->status->transitionTo($target);
        $this->status = $target;
        $this->save();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
