<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// PRD-01..07. No business logic here: validation is in FormRequests,
// writes are in CatalogService / StockService. Stock changes never go
// through update(): they have their own form and service (PRD-05).
class ProductController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly StockService $stock,
    ) {}

    public function index(Request $request): View
    {
        $products = Product::query()
            ->with('category')
            ->when($request->filled('kategori'), fn ($q) => $q->where('category_id', $request->input('kategori')))
            ->when(in_array($request->input('status'), ['aktif', 'nonaktif'], true),
                fn ($q) => $q->where('is_active', $request->input('status') === 'aktif'))
            ->when($request->filled('cari'), function ($q) use ($request) {
                $term = '%'.mb_strtolower((string) $request->input('cari')).'%';
                $q->whereRaw('lower(name) LIKE ?', [$term]);
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.produk-index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.produk-form', [
            'product' => null,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $actor = Auth::guard('web')->user();
        $product = $this->catalog->createProduct($request->validated(), $actor);

        return redirect()->route('admin.produk.index')
            ->with('status', 'Produk “'.$product->name.'” ditambahkan.');
    }

    public function edit(int $produk): View
    {
        return view('admin.produk-form', [
            'product' => Product::findOrFail($produk),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(ProductRequest $request, int $produk): RedirectResponse
    {
        $product = $this->catalog->updateProduct(Product::findOrFail($produk), $request->validated());

        return redirect()->route('admin.produk.index')
            ->with('status', 'Produk “'.$product->name.'” diperbarui.');
    }

    public function toggle(int $produk): RedirectResponse
    {
        $product = $this->catalog->toggleProduct(Product::findOrFail($produk));

        return redirect()->route('admin.produk.index')
            ->with('status', $product->is_active
                ? 'Produk “'.$product->name.'” diaktifkan.'
                : 'Produk “'.$product->name.'” dinonaktifkan.');
    }

    public function stockForm(int $produk): View
    {
        return view('admin.produk-stok-form', ['product' => Product::findOrFail($produk)]);
    }

    public function adjustStock(AdjustStockRequest $request, int $produk): RedirectResponse
    {
        $product = Product::findOrFail($produk);
        $actor = Auth::guard('web')->user();

        $this->stock->adjust(
            $product,
            (int) $request->validated('stock_on_hand'),
            $actor,
            $request->validated('note'),
        );

        return redirect()->route('admin.produk.index')
            ->with('status', 'Stok produk “'.$product->name.'” diperbarui.');
    }

    public function history(int $produk): View
    {
        $product = Product::findOrFail($produk);
        $movements = $product->stockMovements()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.produk-riwayat', [
            'product' => $product,
            'movements' => $movements,
        ]);
    }
}
