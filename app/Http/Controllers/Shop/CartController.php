<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// M3a CRT-01..06. No business logic here: validation is in
// FormRequests, writes and stock checks are in CartService. A cart
// item is always resolved through the acting user's own cart, so
// another account's item id behaves exactly like a missing one
// (ACC-09).
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function index(): View
    {
        return view('shop.keranjang-index', $this->cart->viewFor(Auth::guard('web')->user()));
    }

    public function store(AddToCartRequest $request): RedirectResponse
    {
        $product = Product::findOrFail((int) $request->validated('product_id'));
        $item = $this->cart->add(
            Auth::guard('web')->user(),
            $product,
            (int) $request->validated('quantity'),
        );

        return redirect()->route('keranjang.index')
            ->with('status', 'Produk “'.$item->product->name.'” ditambahkan ke keranjang.');
    }

    public function update(UpdateCartItemRequest $request, int $item): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $cartItem = $this->cart->cartFor($user)->items()->findOrFail($item);

        $this->cart->updateQuantity($user, $cartItem, (int) $request->validated('quantity'));

        return redirect()->route('keranjang.index')
            ->with('status', 'Jumlah item diperbarui.');
    }

    public function destroy(int $item): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $cartItem = $this->cart->cartFor($user)->items()->findOrFail($item);

        $this->cart->remove($user, $cartItem);

        return redirect()->route('keranjang.index')
            ->with('status', 'Item dihapus dari keranjang.');
    }
}
