<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// CRUD katalog di Panel Developer (keputusan Ren 2026-11-10): form yang
// sama dengan admin, ditambah kolom release_date di form produk.
// Atur stok + riwayat stok tetap admin-only. Validasi di ProductRequest,
// tulis di CatalogService — sama seperti Admin\ProductController.
class ProductController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

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

        return view('developer.produk-index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('developer.produk-form', [
            'product' => null,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $actor = Auth::guard('developer')->user();
        $product = $this->catalog->createProduct($request->validated(), $actor);

        return redirect()->route('developer.produk.index')
            ->with('status', 'Produk “'.$product->name.'” ditambahkan.');
    }

    public function edit(int $produk): View
    {
        return view('developer.produk-form', [
            'product' => Product::findOrFail($produk),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(ProductRequest $request, int $produk): RedirectResponse
    {
        $product = $this->catalog->updateProduct(Product::findOrFail($produk), $request->validated());

        return redirect()->route('developer.produk.index')
            ->with('status', 'Produk “'.$product->name.'” diperbarui.');
    }

    public function toggle(int $produk): RedirectResponse
    {
        $product = $this->catalog->toggleProduct(Product::findOrFail($produk));

        return redirect()->route('developer.produk.index')
            ->with('status', $product->is_active
                ? 'Produk “'.$product->name.'” diaktifkan.'
                : 'Produk “'.$product->name.'” dinonaktifkan.');
    }
}
