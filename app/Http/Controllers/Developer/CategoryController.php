<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// CRUD kategori di Panel Developer (keputusan Ren 2026-11-10): form yang
// sama persis dengan admin (tanpa kolom tambahan — release_date hanya
// untuk produk). Validasi di CategoryRequest, tulis di CatalogService —
// sama seperti Admin\CategoryController.
class CategoryController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    public function index(): View
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('developer.kategori-index', ['categories' => $categories]);
    }

    public function create(): View
    {
        return view('developer.kategori-form', ['category' => null]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = $this->catalog->createCategory($request->validated());

        return redirect()->route('developer.kategori.index')
            ->with('status', 'Kategori “'.$category->name.'” ditambahkan.');
    }

    public function edit(int $kategori): View
    {
        return view('developer.kategori-form', ['category' => Category::findOrFail($kategori)]);
    }

    public function update(CategoryRequest $request, int $kategori): RedirectResponse
    {
        $category = $this->catalog->updateCategory(Category::findOrFail($kategori), $request->validated());

        return redirect()->route('developer.kategori.index')
            ->with('status', 'Kategori “'.$category->name.'” diperbarui.');
    }

    public function destroy(int $kategori): RedirectResponse
    {
        $category = Category::findOrFail($kategori);

        // CAT-03: only an empty category may be deleted. The products
        // FK is RESTRICT as a second line of defence; the clear message
        // here is the first one the developer sees.
        if ($category->products()->exists()) {
            return redirect()->route('developer.kategori.index')
                ->withErrors(['kategori' => 'Kategori “'.$category->name.'” masih memiliki produk, jadi tidak dapat dihapus.']);
        }

        $category->delete();

        return redirect()->route('developer.kategori.index')
            ->with('status', 'Kategori “'.$category->name.'” dihapus.');
    }
}
