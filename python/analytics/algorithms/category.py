"""FR-06 Analisis Kategori / Karakteristik Produk.

- Agregasi per kategori (`category_id`): penjualan (Rp), unit, pembelian, transaksi.
- Porsi kategori dengan metode largest remainder: jumlah TEPAT 100,0% (satuan 0,1%,
  aritmetika bilangan bulat). Porsi hanya untuk penjualan (Rp) dan unit.
- Jumlah transaksi per kategori berasal dari `order_totals.by_category` (pesanan unik
  yang memuat minimal satu item paid pada kategori itu). Jumlah antar kategori BOLEH
  melebihi total hari itu; invarian "total = Σ kategori" TIDAK berlaku untuk transaksi.
- Tren per kategori memakai algoritma FR-02 (trend.classify_groups) dengan BH atas
  kategori. Awal deret kategori = aktivitas pertama kategori itu di facts.
- Kelompok harga: binning TETAP (bukan k-means) agar batasnya sama antar periode.
- Matriks kategori x dua periode (`compare_categories`) bersifat deskriptif.

Fungsi murni dan deterministik. Hanya stdlib.
"""

from __future__ import annotations

from datetime import date, timedelta

from ._common import (
    ALGORITHM_VERSION,
    BELUM_DAPAT_DIHITUNG,
    TIMEZONE,
    AnalyticsInputError,
    aggregate_by_product,
    build_buckets,
    check_order_totals_consistency,
    has_activity,
    make_period,
    name_key,
    normalize_facts,
    normalize_order_totals,
    normalize_products,
    period_out,
    periods_overlap,
    pick_granularity,
    stock_warning,
    unique,
    validate_origin_filter,
    week_buckets,
)
from .compare import compare_metric
from .trend import (
    ALPHA,
    MIN_AVG_PER_WEEK,
    N_MIN_WEEKS,
    Q_STRONG,
    R_MIN,
    classify_groups,
)

# Binning harga TETAP: (batas bawah inklusif, batas atas eksklusif, label). None = tak terbatas.
PRICE_BINS: tuple[tuple[int | None, int | None, str], ...] = (
    (None, 10_000, "<Rp10.000"),
    (10_000, 50_000, "Rp10.000\u201349.999"),
    (50_000, None, "\u2265Rp50.000"),
)
UNCATEGORIZED_NAME = "Tanpa kategori"
SINGLE_CATEGORY_NOTE = (
    "Hanya satu kategori yang memiliki penjualan pada periode ini; "
    "perbandingan antar-kategori belum bermakna."
)


def largest_remainder(values: list[int], total_units: int = 1000) -> list[int]:
    """Bagi `total_units` (default 1000 = 100,0% dalam satuan 0,1%) secara proporsional.

    Setiap bagian = lantai(nilai * total / jumlah); sisa unit diberikan kepada bagian dengan
    sisa pembagian terbesar (seri -> indeks lebih kecil). Hanya bilangan bulat, jadi tepat.
    Jumlah nilai harus > 0.
    """
    jumlah = sum(values)
    if jumlah <= 0:
        raise AnalyticsInputError("largest_remainder: jumlah nilai harus > 0.")
    if any(v < 0 for v in values):
        raise AnalyticsInputError("largest_remainder: nilai tidak boleh negatif.")
    dasar: list[int] = []
    sisa: list[int] = []
    for v in values:
        q, r = divmod(v * total_units, jumlah)
        dasar.append(q)
        sisa.append(r)
    kurang = total_units - sum(dasar)
    urutan = sorted(range(len(values)), key=lambda i: (-sisa[i], i))
    for i in urutan[:kurang]:
        dasar[i] += 1
    return dasar


def _shares(values: list[int]) -> list[dict | None]:
    """Porsi per nilai: {'tenths': int, 'pct': float}; None bila total 0."""
    if sum(values) <= 0:
        return [None] * len(values)
    return [{"tenths": t, "pct": t / 10} for t in largest_remainder(values)]


def _daftar_kategori(prods: dict, categories: list[dict] | None) -> dict[int, str]:
    """{category_id: nama} dari `categories` (opsional) dan dari produk."""
    nama: dict[int, str] = {}
    if categories is not None:
        if not isinstance(categories, (list, tuple)):
            raise AnalyticsInputError("categories: harus berupa daftar objek.")
        for i, c in enumerate(categories):
            if not isinstance(c, dict) or not isinstance(c.get("id"), int) or isinstance(c.get("id"), bool):
                raise AnalyticsInputError(f"categories[{i}]: harus objek dengan 'id' bulat.")
            nama[c["id"]] = c["name"] if isinstance(c.get("name"), str) and c["name"].strip() else f"Kategori {c['id']}"
    for p in prods.values():
        cid = p["category_id"]
        if cid is not None and cid not in nama:
            nama[cid] = f"Kategori {cid}"
    return nama


def _validasi_bins(bins) -> None:
    if not bins:
        raise AnalyticsInputError("price_bins: tidak boleh kosong.")
    for i, (lo, hi, label) in enumerate(bins):
        if i > 0 and bins[i - 1][1] != lo:
            raise AnalyticsInputError("price_bins: batas harus bersambung (batas atas = batas bawah berikutnya).")
        if lo is not None and hi is not None and lo >= hi:
            raise AnalyticsInputError("price_bins: batas bawah harus lebih kecil dari batas atas.")
    if bins[0][0] is not None or bins[-1][1] is not None:
        raise AnalyticsInputError("price_bins: kelompok pertama tanpa batas bawah dan terakhir tanpa batas atas.")


def price_group_of(price: int, bins=PRICE_BINS) -> int:
    """Indeks kelompok harga untuk `price` (batas bawah inklusif, batas atas eksklusif)."""
    for i, (lo, hi, _label) in enumerate(bins):
        if (lo is None or price >= lo) and (hi is None or price < hi):
            return i
    raise AnalyticsInputError(f"price_bins tidak mencakup harga {price}.")


def analyze_category(
    facts: list[dict],
    products: list[dict],
    period: dict,
    *,
    origin_filter: str = "all",
    categories: list[dict] | None = None,
    order_totals: list[dict] | None = None,
    metric: str = "units",
    granularity: str = "auto",
    price_bins=PRICE_BINS,
    n_min_weeks: int = N_MIN_WEEKS,
    min_avg_per_week: float = MIN_AVG_PER_WEEK,
    alpha: float = ALPHA,
    r_min: float = R_MIN,
    q_strong: float = Q_STRONG,
) -> dict:
    """Performa per kategori, porsi, tren per kategori, deret waktu, dan kelompok harga."""
    validate_origin_filter(origin_filter)
    if metric not in ("units", "revenue"):
        raise AnalyticsInputError(f"metric: harus 'units' atau 'revenue' (diterima {metric!r}).")
    _validasi_bins(price_bins)
    warnings: list[str] = []
    per = make_period(period)
    prods = normalize_products(products)
    rows = normalize_facts(facts, per, warnings)
    ot = normalize_order_totals(order_totals, per, warnings)
    check_order_totals_consistency(rows, ot, warnings)
    gran = pick_granularity(per["days"], granularity)
    nama_kat = _daftar_kategori(prods, categories)

    def kategori_dari(pid: int) -> int | None:
        p = prods.get(pid)
        return p["category_id"] if p else None

    tanpa_kategori = sorted({pid for (_d, pid) in rows if kategori_dari(pid) is None})
    if tanpa_kategori:
        warnings.append(
            f"{len(tanpa_kategori)} produk pada facts tidak punya kategori (tidak ada di products atau "
            "category_id kosong); dijumlahkan ke 'Tanpa kategori' agar total tetap cocok."
        )

    # Agregasi per kategori (None = tanpa kategori) dan deret harian/bucket
    agregat: dict[int | None, dict] = {cid: {"revenue_idr": 0, "units_sold": 0, "purchases": 0} for cid in nama_kat}
    harian_unit: dict[int | None, dict[date, int]] = {}
    harian_nilai: dict[int | None, dict[date, int]] = {}
    aktivitas_pertama: dict[int | None, date] = {}
    for (d, pid), v in rows.items():
        cid = kategori_dari(pid)
        a = agregat.setdefault(cid, {"revenue_idr": 0, "units_sold": 0, "purchases": 0})
        a["revenue_idr"] += v["revenue_idr"]
        a["units_sold"] += v["units_sold"]
        a["purchases"] += v["purchases"]
        harian_unit.setdefault(cid, {})
        harian_nilai.setdefault(cid, {})
        harian_unit[cid][d] = harian_unit[cid].get(d, 0) + v["units_sold"]
        harian_nilai[cid][d] = harian_nilai[cid].get(d, 0) + v["revenue_idr"]
        if has_activity(v) and (cid not in aktivitas_pertama or d < aktivitas_pertama[cid]):
            aktivitas_pertama[cid] = d

    urutan_id = sorted(agregat, key=lambda c: (c is None, -agregat[c]["revenue_idr"],
                                               name_key(nama_kat.get(c, UNCATEGORIZED_NAME))))
    porsi_rp = _shares([agregat[c]["revenue_idr"] for c in urutan_id])
    porsi_unit = _shares([agregat[c]["units_sold"] for c in urutan_id])

    # Tren per kategori: BH atas kategori yang dikenal (kategori 'Tanpa kategori' tidak diklasifikasi)
    penuh, parsial = week_buckets(per["start"], per["end"])
    kat_tren = [c for c in urutan_id if c is not None]
    grup = [{
        "key": c, "name": nama_kat[c], "eligible": True,
        "daily_units": harian_unit.get(c, {}), "daily_value": harian_nilai.get(c, {}),
        "start_date": aktivitas_pertama.get(c),
        "start_source": "first_activity" if c in aktivitas_pertama else "none",
    } for c in kat_tren]
    tren = classify_groups(
        grup, penuh, metric=metric, n_min_weeks=n_min_weeks, min_avg_per_week=min_avg_per_week,
        alpha=alpha, r_min=r_min, q_strong=q_strong,
    )
    tren_per_kat = {r["key"]: r for r in tren}

    bucket = build_buckets(per["start"], per["end"], gran)

    def deret(cid):
        out = []
        for b in bucket:
            hari = [b["start"] + timedelta(days=i) for i in range((b["end"] - b["start"]).days + 1)]
            out.append({
                "bucket_start": b["start"].isoformat(), "bucket_end": b["end"].isoformat(), "partial": b["partial"],
                "revenue_idr": sum(harian_nilai.get(cid, {}).get(h, 0) for h in hari),
                "units_sold": sum(harian_unit.get(cid, {}).get(h, 0) for h in hari),
            })
        return out

    hasil_kat: list[dict] = []
    for idx, cid in enumerate(urutan_id):
        a = agregat[cid]
        if ot is None or ot["by_category"] is None or cid is None:
            trx, trx_teks = None, BELUM_DAPAT_DIHITUNG
        else:
            trx, trx_teks = ot["by_category"].get(cid, 0), None
        item = {
            "category_id": cid,
            "name": nama_kat.get(cid, UNCATEGORIZED_NAME),
            "revenue_idr": a["revenue_idr"],
            "units_sold": a["units_sold"],
            "purchases": a["purchases"],
            "transactions": trx,
            "transactions_text": trx_teks,
            "share_revenue": porsi_rp[idx],
            "share_units": porsi_unit[idx],
            "has_sales": a["units_sold"] > 0 or a["revenue_idr"] > 0,
            "series": deret(cid),
        }
        if cid is not None:
            t = tren_per_kat[cid]
            item["trend"] = {k: t[k] for k in (
                "label_code", "label", "strength", "n", "beta", "r", "p", "q", "change_abs",
                "avg_units_per_week", "series", "start_source", "start_date", "trimmed_weeks")}
        else:
            item["trend"] = None
        hasil_kat.append(item)

    if ot is None:
        warnings.append("Jumlah transaksi per kategori belum dapat dihitung: order_totals tidak tersedia.")
    elif ot["by_category"] is None:
        warnings.append("Jumlah transaksi per kategori belum dapat dihitung: order_totals tanpa by_category.")
    if sum(1 for k in hasil_kat if k["has_sales"]) == 1:
        warnings.append(SINGLE_CATEGORY_NOTE)
    if len(penuh) < n_min_weeks:
        warnings.append(
            f"Data belum cukup untuk tren kategori: periode hanya memuat {len(penuh)} minggu penuh "
            f"(minimal {n_min_weeks})."
        )
    if any(k["trend"] and k["trend"]["label_code"] in ("turun", "tidak_ada_penjualan", "data_terlalu_jarang")
           for k in hasil_kat):
        catatan = stock_warning(origin_filter)
        if catatan:
            warnings.append(catatan)

    # Kelompok harga (binning tetap)
    per_produk = aggregate_by_product(rows)
    kel = [{
        "label": label, "min_price": lo, "max_price_exclusive": hi,
        "product_count": 0, "active_product_count": 0,
        "revenue_idr": 0, "units_sold": 0, "purchases": 0,
        "transactions": None, "transactions_text": BELUM_DAPAT_DIHITUNG,
    } for lo, hi, label in price_bins]
    tanpa_harga = 0
    for pid, p in prods.items():
        if p["price_idr"] is None:
            tanpa_harga += 1
            continue
        g = kel[price_group_of(p["price_idr"], price_bins)]
        g["product_count"] += 1
        g["active_product_count"] += 1 if p["is_active"] else 0
        v = per_produk.get(pid)
        if v:
            g["revenue_idr"] += v["revenue_idr"]
            g["units_sold"] += v["units_sold"]
            g["purchases"] += v["purchases"]
    if tanpa_harga:
        warnings.append(f"{tanpa_harga} produk tanpa price_idr tidak masuk kelompok harga.")
    tak_dikenal = sorted(set(per_produk) - set(prods))
    if tak_dikenal:
        warnings.append(f"{len(tak_dikenal)} produk pada facts tidak ada di products dan tidak masuk kelompok harga.")
    sh_rp, sh_unit = _shares([g["revenue_idr"] for g in kel]), _shares([g["units_sold"] for g in kel])
    for g, a, b in zip(kel, sh_rp, sh_unit):
        g["share_revenue"], g["share_units"] = a, b

    return {
        "algorithm_version": ALGORITHM_VERSION,
        "timezone": TIMEZONE,
        "period": period_out(per),
        "origin_filter": origin_filter,
        "metric": metric,
        "granularity": gran,
        "trend_granularity": "week",
        "weeks_full": len(penuh),
        "partial_weeks": parsial,
        "totals": {
            "revenue_idr": sum(a["revenue_idr"] for a in agregat.values()),
            "units_sold": sum(a["units_sold"] for a in agregat.values()),
            "purchases": sum(a["purchases"] for a in agregat.values()),
            "transactions": None if ot is None else ot["total"],
            "transactions_text": BELUM_DAPAT_DIHITUNG if ot is None else None,
        },
        "categories": hasil_kat,
        "n_trend_tested": sum(1 for r in tren if r["p"] is not None),
        "price_groups": kel,
        "warnings": unique(warnings),
    }


def compare_categories(
    facts_a: list[dict],
    facts_b: list[dict],
    products: list[dict],
    period_a: dict,
    period_b: dict,
    *,
    origin_filter: str = "all",
    categories: list[dict] | None = None,
    baseline: str = "B",
) -> dict:
    """Matriks kategori x dua periode dengan selisih (ANL-63; deskriptif, tanpa uji)."""
    validate_origin_filter(origin_filter)
    warnings: list[str] = []
    pa, pb = make_period(period_a, "period_a"), make_period(period_b, "period_b")
    prods = normalize_products(products)
    rows_a = normalize_facts(facts_a, pa, warnings, "facts_a")
    rows_b = normalize_facts(facts_b, pb, warnings, "facts_b")
    nama_kat = _daftar_kategori(prods, categories)
    if pa["days"] != pb["days"]:
        warnings.append(
            f"Panjang periode berbeda (A: {pa['days']} hari, B: {pb['days']} hari): "
            "bandingkan perubahan per hari, bukan hanya persen mentah."
        )
    if periods_overlap(pa, pb):
        warnings.append("Periode A dan B tumpang tindih; selisihnya tidak independen.")

    def per_kat(rows):
        out: dict[int | None, dict] = {}
        for (_d, pid), v in rows.items():
            p = prods.get(pid)
            cid = p["category_id"] if p else None
            a = out.setdefault(cid, {"revenue_idr": 0, "units_sold": 0})
            a["revenue_idr"] += v["revenue_idr"]
            a["units_sold"] += v["units_sold"]
        return out

    ka, kb = per_kat(rows_a), per_kat(rows_b)
    ids = sorted(set(nama_kat) | {c for c in (set(ka) | set(kb)) if c is not None})
    if None in ka or None in kb:
        ids.append(None)
        warnings.append("Ada penjualan pada produk tanpa kategori; ditampilkan sebagai 'Tanpa kategori'.")
    nol = {"revenue_idr": 0, "units_sold": 0}
    baris = []
    for cid in ids:
        va, vb = ka.get(cid, nol), kb.get(cid, nol)
        baris.append({
            "category_id": cid,
            "name": nama_kat.get(cid, UNCATEGORIZED_NAME),
            "revenue_idr": dict(compare_metric(va["revenue_idr"], vb["revenue_idr"], pa["days"], pb["days"],
                                               baseline=baseline), descriptive_only=True),
            "units_sold": compare_metric(va["units_sold"], vb["units_sold"], pa["days"], pb["days"],
                                         baseline=baseline),
        })
    baris.sort(key=lambda x: (x["category_id"] is None, name_key(x["name"])))
    return {
        "algorithm_version": ALGORITHM_VERSION,
        "timezone": TIMEZONE,
        "origin_filter": origin_filter,
        "baseline": baseline,
        "period_a": period_out(pa),
        "period_b": period_out(pb),
        "categories": baris,
        "warnings": unique(warnings),
    }
