<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// M3a CRT-01..06. Cart logic lives here, not in controllers. Every
// method scopes by the verified session user (ACC-09): a cart item is
// only ever touched through its owner's cart.
class CartService
{
    public function __construct(private readonly ActivityRecorder $events) {}

    public function cartFor(User $user): Cart
    {
        return Cart::query()->firstOrCreate(['user_id' => $user->id]);
    }

    // CRT-04: the cart with items + products, and the blocked items
    // (CRT-05) that must keep checkout closed until fixed or removed.
    public function viewFor(User $user): array
    {
        $cart = $this->cartFor($user);
        $cart->load(['items.product']);

        $blocked = $cart->items
            ->filter(fn (CartItem $item) => $item->isBlocked())
            ->values();

        return [
            'cart' => $cart,
            'items' => $cart->items,
            'total_idr' => $cart->totalIdr(),
            'item_count' => $cart->itemCount(),
            'blocked' => $blocked,
        ];
    }

    // CRT-01: add (merging when the product is already in the cart).
    // Failures (inactive product, quantity out of range, merged total
    // above 99 or above available stock) throw and write NO event.
    public function add(User $user, Product $product, int $quantity): CartItem
    {
        $product = $product->fresh();

        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk sudah tidak aktif dan tidak dapat ditambahkan ke keranjang.',
            ]);
        }

        if ($quantity < 1 || $quantity > 99) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah harus antara 1 dan 99.',
            ]);
        }

        return DB::transaction(function () use ($user, $product, $quantity) {
            $cart = $this->cartFor($user);
            $existing = $cart->items()->where('product_id', $product->id)->first();
            $merged = $quantity + (int) ($existing?->quantity ?? 0);

            if ($merged > 99) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah total di keranjang untuk produk ini maksimal 99.',
                ]);
            }

            if ($merged > $product->fresh()->stock_available) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok tersedia ('.$product->fresh()->stock_available.') kurang dari jumlah yang diminta ('.$merged.').',
                ]);
            }

            if ($existing !== null) {
                $existing->update(['quantity' => $merged]);
                $item = $existing->fresh();
            } else {
                $item = $cart->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ]);
            }

            // EVT-03: only on a successful add.
            $this->events->recordAddToCart($user, $product);

            return $item;
        });
    }

    // CRT-02: change quantity. Out-of-range values are rejected and the
    // old quantity is kept (nothing is written). No event is recorded.
    public function updateQuantity(User $user, CartItem $item, int $quantity): CartItem
    {
        $this->assertOwnItem($user, $item);

        if ($quantity < 1 || $quantity > 99) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah harus antara 1 dan 99.',
            ]);
        }

        $product = $item->product->fresh();

        if ($quantity > $product->stock_available) {
            throw ValidationException::withMessages([
                'quantity' => 'Stok tersedia ('.$product->stock_available.') kurang dari jumlah yang diminta ('.$quantity.').',
            ]);
        }

        $item->update(['quantity' => $quantity]);

        return $item->fresh();
    }

    // CRT-03: remove an item owned by this user. No event.
    public function remove(User $user, CartItem $item): void
    {
        $this->assertOwnItem($user, $item);
        $item->delete();
    }

    // ORD-02: empty the cart after a successful checkout. The caller
    // (CheckoutService) owns the surrounding transaction.
    public function clear(User $user): void
    {
        $this->cartFor($user)->items()->delete();
    }

    private function assertOwnItem(User $user, CartItem $item): void
    {
        $cart = $this->cartFor($user);

        if ((int) $item->cart_id !== (int) $cart->id) {
            // Same response as a missing item (ACC-09): never reveal
            // whether another account's item exists.
            throw new ModelNotFoundException();
        }
    }
}
