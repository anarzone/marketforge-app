<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['user_id', 'quantity', 'product_id', 'unit_price_minor', 'currency'];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
