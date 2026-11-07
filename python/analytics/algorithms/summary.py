"""FR-01 Dashboard Analitik Penjualan.

Definisi (dokumen revisi §4):
  Penjualan (Rp)      = Σ revenue_idr (subtotal item paid)
  Transaksi berhasil  = jumlah PESANAN paid unik -> dari blok `order_totals`
                        (bukan Σ orders_paid per produk: pesanan multi-produk terhitung ganda).
                        Tanpa `order_totals`: "Belum dapat dihitung" + warning.
  Unit terjual        = Σ units_sold
  Pembelian (event)   = Σ purchases (satu event per item paid)
  Transaksi dan pembelian tampil berdampingan; `transactions_differ_from_purchases`
  memberi tahu bila berbeda.

KPI vs periode sebelumnya: perbandingan deskriptif memakai definisi FR-04
(A = periode ini, B = periode sebelumnya; selisih = A - B, persen = (A - B)/B) plus
tanda signifikansi uji binomial untuk unit, pembelian, dan transaksi. Penjualan (Rp)
hanya deskriptif. Periode sebelumnya = periode berpanjang sama tepat sebelumnya; datanya
dikirim sebagai `previous_facts` (format sama dengan `facts`) dan `previous_order_totals`.

Fungsi murni dan deterministik. Hanya stdlib.
"""

from __future__ import annotations

from datetime import date, timedelta

from ._common import (
    ALGORITHM_VERSION,
    AUTO_DAY_MAX_DAYS,
    AUTO_WEEK_MAX_DAYS,
    BELUM_DAPAT_DIHITUNG,
    TIMEZONE,
    AnalyticsInputError,
    aggregate_by_product,
    build_buckets,
    check_order_totals_consistency,
    make_period,
    name_key,
    normalize_facts,
    normalize_order_totals,
    normalize_products,
    period_out,
    pick_granularity,
    previous_period,
    product_name,
    stock_warning,
    total_fields,
    unique,
    validate_origin_filter,
)
from .compare import ALPHA, compare_metric

TOP_N = 5


def _tidak_tersedia(value, alasan: str) -> dict:
    return {"available": False, "text": BELUM_DAPAT_DIHITUNG, "reason": alasan, "current": value}


def summarize(
    facts: list[dict],
    products: list[dict],
    period: dict,
    *,
    origin_filter: str = "all",
    order_totals: list[dict] | None = None,
    previous_facts: list[dict] | None = None,
    previous_order_totals: list[dict] | None = None,
    granularity: str = "auto",
    top_n: int = TOP_N,
    alpha: float = ALPHA,
    auto_day_max: int = AUTO_DAY_MAX_DAYS,
    auto_week_max: int = AUTO_WEEK_MAX_DAYS,
    baseline: str = "B",
) -> dict:
    """Ringkasan FR-01: KPI, Top N terlaris, N paling sedikit terjual, deret penjualan, hari puncak."""
    validate_origin_filter(origin_filter)
    if top_n < 1:
        raise AnalyticsInputError("top_n: harus >= 1.")
    warnings: list[str] = []
    per = make_period(period)
    prev = previous_period(per)
    prods = normalize_products(products)
    rows = normalize_facts(facts, per, warnings)
    ot = normalize_order_totals(order_totals, per, warnings)
    check_order_totals_consistency(rows, ot, warnings)
    gran = pick_granularity(per["days"], granularity, auto_day_max, auto_week_max)

    total = total_fields(rows)
    transaksi = None if ot is None else ot["total"]
    if ot is None:
        warnings.append(
            "Jumlah transaksi belum dapat dihitung karena order_totals tidak tersedia "
            "(bukan 0 dan bukan jumlah per produk)."
        )

    # --- Periode sebelumnya (KPI vs periode sebelumnya) ---
    rows_prev = ot_prev = None
    if previous_facts is not None:
        rows_prev = normalize_facts(previous_facts, prev, warnings, "previous_facts")
        ot_prev = normalize_order_totals(previous_order_totals, prev, warnings, "previous_order_totals")
        check_order_totals_consistency(rows_prev, ot_prev, warnings)
    else:
        warnings.append(
            "Perubahan KPI vs periode sebelumnya belum dapat dihitung karena previous_facts tidak dikirim."
        )
    total_prev = total_fields(rows_prev) if rows_prev is not None else None

    def kpi(nilai, nilai_prev, test: bool, alasan_kosong: str) -> dict:
        if nilai is None or nilai_prev is None:
            return _tidak_tersedia(nilai, alasan_kosong)
        return dict(compare_metric(nilai, nilai_prev, per["days"], prev["days"], test=test, alpha=alpha,
                                   baseline=baseline), available=True, descriptive_only=not test)

    pf = "previous_facts tidak dikirim."
    kpis = {
        "revenue_idr": {
            "value": total["revenue_idr"],
            "vs_previous": kpi(total["revenue_idr"], None if total_prev is None else total_prev["revenue_idr"], False, pf),
        },
        "transactions": {
            "value": transaksi,
            "text": BELUM_DAPAT_DIHITUNG if transaksi is None else None,
            "vs_previous": kpi(
                transaksi, None if ot_prev is None else ot_prev["total"], True,
                "order_totals periode ini atau periode sebelumnya tidak tersedia.",
            ),
        },
        "units_sold": {
            "value": total["units_sold"],
            "vs_previous": kpi(total["units_sold"], None if total_prev is None else total_prev["units_sold"], True, pf),
        },
        "purchases": {
            "value": total["purchases"],
            "vs_previous": kpi(total["purchases"], None if total_prev is None else total_prev["purchases"], True, pf),
        },
    }
    if previous_facts is not None and ot is not None and ot_prev is None:
        warnings.append(
            "Perubahan jumlah transaksi vs periode sebelumnya belum dapat dihitung: "
            "previous_order_totals tidak dikirim."
        )

    # --- Produk terlaris dan paling sedikit terjual ---
    per_produk = aggregate_by_product(rows)

    def baris_produk(pid: int) -> dict:
        v = per_produk.get(pid, {"units_sold": 0, "revenue_idr": 0})
        return {
            "product_id": pid, "name": product_name(prods, pid),
            "units_sold": v["units_sold"], "revenue_idr": v["revenue_idr"],
            "units_text": f"{v['units_sold']} terjual",
        }

    terjual = [baris_produk(pid) for pid, v in per_produk.items() if v["units_sold"] > 0]
    terjual.sort(key=lambda x: (-x["units_sold"], -x["revenue_idr"], name_key(x["name"]), x["product_id"]))
    terlaris = [dict(x, rank=i + 1) for i, x in enumerate(terjual[:top_n])]

    aktif = [baris_produk(pid) for pid, p in prods.items() if p["is_active"]]
    aktif.sort(key=lambda x: (x["units_sold"], name_key(x["name"]), x["product_id"]))
    terendah = [dict(x, rank=i + 1) for i, x in enumerate(aktif[:top_n])]
    if terendah:
        catatan = stock_warning(origin_filter)
        if catatan:
            warnings.append(catatan)

    # --- Deret penjualan (bucket kosong = 0) dan hari puncak ---
    harian_rp: dict[date, int] = {}
    harian_unit: dict[date, int] = {}
    for (d, _pid), v in rows.items():
        harian_rp[d] = harian_rp.get(d, 0) + v["revenue_idr"]
        harian_unit[d] = harian_unit.get(d, 0) + v["units_sold"]
    seri = []
    for b in build_buckets(per["start"], per["end"], gran):
        hari = [b["start"] + timedelta(days=i) for i in range((b["end"] - b["start"]).days + 1)]
        seri.append({
            "bucket_start": b["start"].isoformat(), "bucket_end": b["end"].isoformat(), "partial": b["partial"],
            "revenue_idr": sum(harian_rp.get(h, 0) for h in hari),
            "units_sold": sum(harian_unit.get(h, 0) for h in hari),
        })
    puncak = None
    if harian_rp and max(harian_rp.values()) > 0:
        nilai_maks = max(harian_rp.values())
        tanggal = min(d for d, v in harian_rp.items() if v == nilai_maks)
        puncak = {"date": tanggal.isoformat(), "revenue_idr": nilai_maks, "n_days": per["days"]}

    kosong = not rows
    if kosong:
        warnings.append("Tidak ada data penjualan atau aktivitas pada periode dan filter ini.")

    return {
        "algorithm_version": ALGORITHM_VERSION,
        "timezone": TIMEZONE,
        "period": period_out(per),
        "previous_period": period_out(prev),
        "origin_filter": origin_filter,
        "baseline": baseline,
        "empty": kosong,
        "kpis": kpis,
        "transactions_differ_from_purchases": None if transaksi is None else transaksi != total["purchases"],
        "top_selling": terlaris,
        "least_selling": terendah,
        "sales_series": {"granularity": gran, "buckets": seri},
        "peak_day": puncak,
        "warnings": unique(warnings),
    }
