<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Application\Commands\AddToCartCommand;
use App\Modules\Order\Application\Commands\AddToCartHandler;
use App\Modules\Order\Application\Commands\RemoveFromCartCommand;
use App\Modules\Order\Application\Commands\RemoveFromCartHandler;
use App\Modules\Order\Application\Queries\GetCartHandler;
use App\Modules\Order\Application\Queries\GetCartQuery;
use App\Modules\Order\Http\Requests\AddToCartRequest;
use App\Modules\Order\Http\Resources\CartItemResource;
use App\Modules\Order\Http\Resources\CartResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private AddToCartHandler $addToCartHandler,
        private RemoveFromCartHandler $removeFromCartHandler,
        private GetCartHandler $getCartHandler,
    ) {}

    public function viewCart(Request $request): CartResource
    {
        $cart = $this->getCartHandler->handle(new GetCartQuery($request->user()->id));
        return new CartResource($cart);
    }

    public function addItem(AddToCartRequest $request): CartItemResource
    {
        $data = $request->validated();
        $userId = $request->user()->id;
        $cartItem = $this->addToCartHandler->handle(new AddToCartCommand($userId, $data['product_id'], $data['quantity']));

        return new CartItemResource($cartItem);
    }

    public function removeItem(Request $request, int $id): JsonResponse
    {
        $this->removeFromCartHandler->handle(new RemoveFromCartCommand($request->user()->id, $id));

        return new JsonResponse(null,JsonResponse::HTTP_NO_CONTENT);
    }
}
