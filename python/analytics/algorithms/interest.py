"""FR-05 Analisis Aktivitas dan Minat Produk.

Per produk: V = dilihat, C = keranjang, B = booking, P = pembelian (event per item).
  r_b = P / V (V > 0);  [L, U] = interval Wilson 95% untuk P dari V.
  V < 20            -> "Data belum cukup".
  "Perlu ditinjau"  : V >= 20, r_b <= 5% DAN U <= 10%.
                      Urut: selisih (C - P) menurun, seri -> nama menaik.
  "Pembanding"      : r_b >= 10% DAN P >= 10, maksimal 3, urut P menurun
                      (memakai rasio titik, bukan batas bawah interval; ANL-56).
Tidak ada koreksi berganda untuk daftar sorotan: ini penyaringan untuk ditinjau
manusia, bukan kesimpulan statistik per produk; interval tampil di tiap baris.

Sorotan dan pembanding hanya mempertimbangkan produk AKTIF.

Fungsi murni dan deterministik. Hanya stdlib.
"""

from __future__ import annotations

from statistics import NormalDist

from ._common import (
    ALGORITHM_VERSION,
    STRIP,
    TIMEZONE,
    AnalyticsInputError,
    aggregate_by_product,
    make_period,
    name_key,
    normalize_facts,
    normalize_products,
    period_out,
    product_name,
    safe_ratio,
    stock_warning,
    unique,
    validate_origin_filter,
)

# Ambang default (U-26, K6). Dikalibrasi ulang setelah ada data riil.
MIN_VIEWS = 20
MAX_RATIO = 0.05          # rasio beli <= 5%
MAX_UPPER = 0.10          # batas atas Wilson <= 10%
COMPARATOR_MIN_RATIO = 0.10
COMPARATOR_MIN_PURCHASES = 10
COMPARATOR_MAX = 3
CONFIDENCE = 0.95

REVIEW = "Perlu ditinjau"
COMPARATOR = "Pembanding"
INSUFFICIENT = "Data belum cukup"
NOT_HIGHLIGHTED = "Tidak disorot"
_EPS = 1e-12


def wilson_interval(successes: int, trials: int, confidence: float = CONFIDENCE) -> tuple[float, float] | None:
    """Interval Wilson (skor) untuk proporsi. None bila trials = 0 atau successes > trials."""
    if trials <= 0 or successes < 0 or successes > trials:
        return None
    z = NormalDist().inv_cdf(1 - (1 - confidence) / 2)
    ph = successes / trials
    penyebut = 1 + z * z / trials
    pusat = (ph + z * z / (2 * trials)) / penyebut
    setengah = z * ((ph * (1 - ph) / trials + z * z / (4 * trials * trials)) ** 0.5) / penyebut
    bawah = 0.0 if successes == 0 else max(0.0, pusat - setengah)      # batas eksak: k=0 -> 0
    atas = 1.0 if successes == trials else min(1.0, pusat + setengah)  # batas eksak: k=n -> 1
    return bawah, atas


def analyze_interest(
    facts: list[dict],
    products: list[dict],
    period: dict,
    *,
    origin_filter: str = "all",
    category_id: int | None = None,
    min_views: int = MIN_VIEWS,
    max_ratio: float = MAX_RATIO,
    max_upper: float = MAX_UPPER,
    comparator_min_ratio: float = COMPARATOR_MIN_RATIO,
    comparator_min_purchases: int = COMPARATOR_MIN_PURCHASES,
    comparator_max: int = COMPARATOR_MAX,
    confidence: float = CONFIDENCE,
) -> dict:
    """Ringkasan minat per produk, daftar "Perlu ditinjau", dan daftar pembanding.

    category_id (opsional) menyaring seluruh keluaran ke satu kategori (ANL-54).
    """
    validate_origin_filter(origin_filter)
    warnings: list[str] = []
    per = make_period(period)
    prods = normalize_products(products)
    rows = normalize_facts(facts, per, warnings)
    agregat = aggregate_by_product(rows)

    def terpilih(pid: int) -> bool:
        if category_id is None:
            return True
        p = prods.get(pid)
        return p is not None and p["category_id"] == category_id

    semua_id = sorted({pid for pid in set(prods) | set(agregat) if terpilih(pid)})
    baris: list[dict] = []
    for pid in semua_id:
        v = agregat.get(pid, {})
        V, C, B, P = (v.get(k, 0) for k in ("views", "add_to_carts", "bookings", "purchases"))
        p = prods.get(pid)
        aktif = p["is_active"] if p else False
        rasio = safe_ratio(P, V)
        interval = wilson_interval(P, V, confidence) if V > 0 else None
        anomali = P > V
        item = {
            "product_id": pid,
            "name": product_name(prods, pid),
            "category_id": p["category_id"] if p else None,
            "is_active": aktif if p else None,
            "views": V, "add_to_carts": C, "bookings": B, "purchases": P,
            "ratio": rasio,
            "ratio_text": STRIP if rasio is None else None,
            "interval": None if interval is None else {"low": interval[0], "high": interval[1], "level": confidence},
            "cart_minus_purchase": C - P,
            "sufficient_data": V >= min_views,
            "status": None,
            "needs_review": False,
            "is_comparator": False,
            "anomaly": anomali,
        }
        if anomali:
            item["note"] = "Pembelian melebihi jumlah dilihat pada periode ini (mis. batas periode); interval tidak dihitung."
        if V < min_views:
            item["status"] = INSUFFICIENT
        elif (aktif and p is not None and rasio is not None and rasio <= max_ratio + _EPS
              and interval is not None and interval[1] <= max_upper + _EPS):
            item["status"] = REVIEW
            item["needs_review"] = True
        else:
            item["status"] = NOT_HIGHLIGHTED
        # Pembanding dinilai terpisah: rasio titik dan jumlah minimum, tanpa syarat V >= min_views.
        if (aktif and p is not None and rasio is not None
                and rasio >= comparator_min_ratio - _EPS and P >= comparator_min_purchases):
            item["is_comparator"] = True
        baris.append(item)

    sorotan = sorted(
        (x for x in baris if x["needs_review"]),
        key=lambda x: (-x["cart_minus_purchase"], name_key(x["name"]), x["product_id"]),
    )
    pembanding = sorted(
        (x for x in baris if x["is_comparator"]),
        key=lambda x: (-x["purchases"], name_key(x["name"]), x["product_id"]),
    )[:comparator_max]

    dilihat = [x for x in baris if x["views"] > 0]
    if any(x["anomaly"] for x in baris):
        warnings.append("Ada produk dengan pembelian melebihi jumlah dilihat; ditandai sebagai catatan data.")
    if not agregat:
        warnings.append("Tidak ada aktivitas pada periode dan filter ini.")
    if sorotan:
        catatan = stock_warning(origin_filter)
        if catatan:
            warnings.append(catatan)

    return {
        "algorithm_version": ALGORITHM_VERSION,
        "timezone": TIMEZONE,
        "period": period_out(per),
        "origin_filter": origin_filter,
        "category_id": category_id,
        "params": {
            "min_views": min_views, "max_ratio": max_ratio, "max_upper": max_upper,
            "comparator_min_ratio": comparator_min_ratio,
            "comparator_min_purchases": comparator_min_purchases,
            "comparator_max": comparator_max, "confidence": confidence,
        },
        "products_viewed": len(dilihat),
        "total_views": sum(x["views"] for x in baris),
        "products": baris,
        "highlights": sorotan,
        "comparators": pembanding,
        "warnings": unique(warnings),
    }
