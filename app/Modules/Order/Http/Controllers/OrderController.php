<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Application\Commands\PlaceOrderCommand;
use App\Modules\Order\Application\Commands\PlaceOrderHandler;
use App\Modules\Order\Http\Resources\OrderResource;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private PlaceOrderHandler $placeOrderHandler,
    ) {}

    public function checkout(Request $request): OrderResource
    {
        $order = $this->placeOrderHandler->handle(new PlaceOrderCommand($request->user()->id));

        return new OrderResource($order);
    }
}
