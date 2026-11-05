<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// SHP-01..04. Buyer catalog only: active products, never inactive ones.
// EVT-02 is deliberately NOT implemented here: opening a product detail
// does NOT record a view event in M2, because the events table is M3a.
// (See the comment in show(); do not claim in the UI that views are
// recorded.) There is no cart in M2: the detail button is a clearly
// labelled M3 placeholder, with no POST cart route behind it.
class CatalogController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        // SHP-03: keyword 2-100 characters. Too short / too long is a
        // validation error back to the same listing URL (without the
        // bad keyword), not a silent ignore and not a crash.
        if ($request->filled('q')) {
            $length = mb_strlen((string) $request->input('q'));
            if ($length < 2 || $length > 100) {
                return redirect()->to($request->url())
                    ->withErrors(['q' => 'Kata kunci pencarian minimal 2 dan maksimal 100 karakter.'])
                    ->withInput($request->except('q'));
            }
        }

        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->when($request->filled('kategori'), fn ($q) => $q->where('category_id', $request->input('kategori')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->escapedLikeTerm((string) $request->input('q'));
                $q->where(function ($inner) use ($term) {
                    $inner->whereRaw("lower(name) LIKE ? ESCAPE '\\'", [$term])
                        ->orWhereRaw("lower(description) LIKE ? ESCAPE '\\'", [$term]);
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('shop.katalog-index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function show(int $produk): View
    {
        // SHP-04: an inactive product and a missing product return the
        // SAME 404. Resolving inside the active scope is what makes
        // that true; route-model binding alone would leak inactive ones.
        // EVT-02 (record a view event) is added in M3a, not here:
        // there is no events table in M2 and this method writes nothing.
        $product = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->findOrFail($produk);

        return view('shop.produk-detail', ['product' => $product]);
    }

    // SHP-03: % and _ in the keyword are literal characters, not LIKE
    // wildcards, so searching "100%" does not match every product.
    private function escapedLikeTerm(string $keyword): string
    {
        $escaped = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            mb_strtolower($keyword),
        );

        return '%'.$escaped.'%';
    }
}
