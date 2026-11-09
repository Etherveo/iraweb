<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

// UI polish Tahap 3: public storefront home at `/`. Shows up to three
// sections of active products (Terlaris, Terbaru, Teratas) and never
// records view events or renders buy forms.
class HomeController extends Controller
{
    public function index(): View
    {
        // Keputusan Ren 2026-11-09 (kalkulasi-terlaris.md): Terlaris memakai
        // threshold 3 unit bersih dalam 30 hari terakhir. Jendela dihitung
        // dari orders.paid_at (UTC, sama seperti penyimpanan); pesanan batal
        // tidak pernah mencapai dibayar, jadi tidak terhitung. Pengembalian
        // dana tidak ada di sistem ini, jadi tidak ada yang dikurangi.
        $cutoff = now()->subDays(30);

        $bestSellers = Product::query()
            ->with('category')
            ->select('products.*')
            ->selectRaw('SUM(order_items.quantity) as sold_quantity, SUM(order_items.subtotal_idr) as sold_revenue_idr')
            ->join('order_items', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('products.is_active', true)
            ->where('orders.paid_at', '>=', $cutoff)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.order_id', 'order_items.order_id')
                    ->where('payments.status', 'paid');
            })
            ->groupBy('products.id')
            ->havingRaw('SUM(order_items.quantity) >= 3')
            ->orderByDesc('sold_quantity')
            ->orderByDesc('sold_revenue_idr')
            ->orderBy('products.name')
            ->limit(10)
            ->get();

        $newestProducts = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $topProducts = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(10)
            ->get();

        return view('shop.home-index', [
            'bestSellers' => $bestSellers,
            'newestProducts' => $newestProducts,
            'topProducts' => $topProducts,
        ]);
    }
}

