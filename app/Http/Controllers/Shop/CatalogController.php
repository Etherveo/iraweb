<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// SHP-01..04, EVT-02 (M3a). Buyer catalog only: active products,
// never inactive ones. Opening a product detail records one 'view'
// event per (account, product) per 30 minutes (ActivityRecorder);
// the listing and search never record views, and Admin/Developer
// never reach this controller (role:pembeli routes only).
class CatalogController extends Controller
{
    public function __construct(private readonly ActivityRecorder $events) {}
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

    public function show(int $produk, Request $request): View
    {
        // SHP-04: an inactive product and a missing product return the
        // SAME 404. Resolving inside the active scope is what makes
        // that true; route-model binding alone would leak inactive ones.
        // EVT-02: opening an ACTIVE product detail as a buyer records a
        // view event (dedup 30 minutes per account+product, inside
        // ActivityRecorder). Inactive/missing products 404 before any
        // event is written.
        $product = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->findOrFail($produk);

        // Catalog is public (UI polish Tahap 2): guests, admins and
        // deactivated buyers can read it, but only an active buyer's
        // visit counts as activity, so analytics stay buyer-only.
        $viewer = $request->user('web');
        if ($viewer !== null && $viewer->isPembeli() && $viewer->is_active) {
            $this->events->recordView(
                $viewer,
                $product,
                $request->session()->getId(),
            );
        }

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
