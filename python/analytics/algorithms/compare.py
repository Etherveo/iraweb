"""FR-04 Perbandingan Dua Periode: selisih, persen, laju per hari, uji binomial eksak.

Konvensi arah (penting, mudah dibalik lewat `baseline`):
  A = periode yang dilihat (mis. minggu ini), B = periode pembanding (mis. minggu lalu;
  ANL-46 mengisi B dengan periode tepat sebelum A). Perubahan = A dibanding B:
      selisih = A - B,   persen = (A - B) / B   (B = 0 -> "Belum dapat dihitung")
  Dokumen revisi menulis "B=0 -> Belum dapat dihitung", jadi B adalah basis.
  Pakai baseline="A" bila arahnya ingin dibalik (selisih = B - A, persen = (B - A) / A).

Uji binomial eksak dua sisi (jumlah kejadian diasumsikan Poisson dan saling bebas):
  N = A + B, p0 = dA / (dA + dB), statistik = A.
  p-value = jumlah peluang semua hasil yang peluangnya <= peluang hasil teramati
  (metode "jumlah peluang", sama dengan scipy.stats.binomtest).

Total penjualan (Rp) hanya deskriptif (tanpa uji).

Fungsi murni dan deterministik. Hanya stdlib.
"""

from __future__ import annotations

import math
from datetime import date

from ._common import (
    ALGORITHM_VERSION,
    BELUM_DAPAT_DIHITUNG,
    TIMEZONE,
    AnalyticsInputError,
    aggregate_by_product,
    check_order_totals_consistency,
    make_period,
    name_key,
    normalize_facts,
    normalize_order_totals,
    normalize_products,
    period_out,
    periods_overlap,
    product_name,
    safe_ratio,
    total_fields,
    unique,
    validate_origin_filter,
)

ALPHA = 0.05
SIGNIFICANT_LABEL = "Berbeda dari variasi acak"
NOT_SIGNIFICANT_LABEL = "Belum dapat dibedakan dari variasi acak"
POISSON_CAVEAT = (
    "Kejadian diasumsikan Poisson dan saling bebas; pelanggan berulang membuat p-value "
    "cenderung optimistis."
)
_REL_TOL = 1e-7  # toleransi relatif pembanding peluang (sama dengan scipy)


# ---------------------------------------------------------------------------
# Uji binomial eksak dua sisi
# ---------------------------------------------------------------------------

def _log_pmf(i: int, n: int, p: float) -> float:
    return (
        math.lgamma(n + 1) - math.lgamma(i + 1) - math.lgamma(n - i + 1)
        + i * math.log(p) + (n - i) * math.log1p(-p)
    )


def binomial_two_sided_p(k: int, n: int, p0: float) -> float:
    """p-value dua sisi uji binomial eksak (metode jumlah-peluang).

    Peluang pmf unimodal, jadi himpunan hasil dengan peluang <= peluang teramati adalah
    dua ekor: [0..a] dan [b..n]. Batas a dan b dicari dengan pencarian biner, lalu
    tiap ekor dijumlahkan dengan rekurens rasio sampai suku-sukunya dapat diabaikan.
    Waktu ~O(log n + sqrt(n)), aman untuk jumlah kejadian besar.
    """
    if not 0 < p0 < 1:
        raise AnalyticsInputError(f"p0 harus di antara 0 dan 1 (diterima {p0}).")
    if n < 0 or k < 0 or k > n:
        raise AnalyticsInputError(f"k={k} dan n={n} tidak valid untuk uji binomial.")
    if n == 0:
        return 1.0

    def lp(i: int) -> float:
        return _log_pmf(i, n, p0)

    batas = lp(k) + _REL_TOL
    modus = min(n, int(math.floor((n + 1) * p0)))
    if lp(modus) <= batas:  # hasil teramati ada di puncak: semua hasil memenuhi
        return 1.0

    lo, hi = -1, modus  # ekor bawah: awalan [0..a] yang memenuhi
    while hi - lo > 1:
        tengah = (lo + hi) // 2
        if lp(tengah) <= batas:
            lo = tengah
        else:
            hi = tengah
    a = lo
    lo, hi = modus, n + 1  # ekor atas: akhiran [b..n] yang memenuhi
    while hi - lo > 1:
        tengah = (lo + hi) // 2
        if lp(tengah) <= batas:
            hi = tengah
        else:
            lo = tengah
    b = hi

    rasio = p0 / (1 - p0)
    bawah = 0.0
    if a >= 0:
        suku = math.exp(lp(a))
        i = a
        while i >= 0 and suku > 0.0:
            bawah += suku
            if suku < bawah * 1e-17:
                break
            suku *= i / (n - i + 1) / rasio
            i -= 1
    atas = 0.0
    if b <= n:
        suku = math.exp(lp(b))
        i = b
        while i <= n and suku > 0.0:
            atas += suku
            if suku < atas * 1e-17:
                break
            suku *= (n - i) / (i + 1) * rasio
            i += 1
    return min(1.0, bawah + atas)


def significance_label(p: float | None, alpha: float = ALPHA) -> str | None:
    """p < alpha -> 'Berbeda dari variasi acak'; selain itu 'Belum dapat dibedakan dari variasi acak'."""
    if p is None:
        return None
    return SIGNIFICANT_LABEL if p < alpha else NOT_SIGNIFICANT_LABEL


# ---------------------------------------------------------------------------
# Perbandingan satu metrik
# ---------------------------------------------------------------------------

def compare_metric(
    a: float,
    b: float,
    days_a: int,
    days_b: int,
    *,
    test: bool = False,
    alpha: float = ALPHA,
    baseline: str = "B",
) -> dict:
    """Bandingkan nilai A dan B.

    Mengembalikan selisih, persen (None + teks seragam bila basis nol), laju per hari
    dan perubahannya (wajib dilihat bila panjang periode berbeda, ANL-42), serta uji
    binomial bila `test=True` (hanya untuk cacah bilangan bulat).
    """
    if baseline not in ("A", "B"):
        raise AnalyticsInputError("baseline: harus 'A' atau 'B'.")
    if days_a < 1 or days_b < 1:
        raise AnalyticsInputError("Panjang periode harus minimal 1 hari.")
    if baseline == "B":
        sekarang, dasar, hari_s, hari_d = a, b, days_a, days_b
    else:
        sekarang, dasar, hari_s, hari_d = b, a, days_b, days_a
    selisih = sekarang - dasar
    persen = safe_ratio(selisih, dasar)
    laju_a, laju_b = a / days_a, b / days_b
    laju_s, laju_d = (laju_a, laju_b) if baseline == "B" else (laju_b, laju_a)
    selisih_hari = laju_s - laju_d
    persen_hari = safe_ratio(selisih_hari, laju_d)

    hasil = {
        "a": a, "b": b, "days_a": days_a, "days_b": days_b, "baseline": baseline,
        "change": selisih,
        "change_ratio": persen,
        "change_ratio_text": BELUM_DAPAT_DIHITUNG if persen is None else None,
        "rate_a": laju_a, "rate_b": laju_b,
        "per_day_change": selisih_hari,
        "per_day_change_ratio": persen_hari,
        "per_day_change_ratio_text": BELUM_DAPAT_DIHITUNG if persen_hari is None else None,
        "lengths_differ": days_a != days_b,
        "p": None, "significance": None, "significant": None,
    }
    if test:
        if int(a) != a or int(b) != b:
            raise AnalyticsInputError("Uji binomial hanya untuk cacah bilangan bulat.")
        total = int(a) + int(b)
        if total == 0:
            hasil["significance"] = BELUM_DAPAT_DIHITUNG  # tidak ada kejadian di kedua periode
        else:
            p = binomial_two_sided_p(int(a), total, days_a / (days_a + days_b))
            hasil["p"] = p
            hasil["significance"] = significance_label(p, alpha)
            hasil["significant"] = p < alpha
    return hasil


def _tidak_tersedia(a: float | None, b: float | None, alasan: str) -> dict:
    return {"available": False, "a": a, "b": b, "text": BELUM_DAPAT_DIHITUNG, "reason": alasan}


# ---------------------------------------------------------------------------
# FR-04 lengkap
# ---------------------------------------------------------------------------

def compare_periods(
    facts_a: list[dict],
    facts_b: list[dict],
    products: list[dict],
    period_a: dict,
    period_b: dict,
    *,
    origin_filter: str = "all",
    order_totals_a: list[dict] | None = None,
    order_totals_b: list[dict] | None = None,
    top_n: int = 5,
    alpha: float = ALPHA,
    baseline: str = "B",
) -> dict:
    """Bandingkan dua periode: total, per produk (dengan uji), dan peringkat perubahan.

    Jumlah transaksi total memakai `order_totals` (pesanan paid unik); tanpa blok itu pada
    salah satu periode hasilnya 'Belum dapat dihitung' + warning (bukan 0, bukan Σ per produk).
    Transaksi PER PRODUK = `orders_paid` per produk (pesanan unik yang memuat produk itu).
    Peringkat perubahan produk memakai selisih unit PER HARI agar adil bila panjang berbeda.
    """
    validate_origin_filter(origin_filter)
    if baseline not in ("A", "B"):
        raise AnalyticsInputError("baseline: harus 'A' atau 'B'.")
    warnings: list[str] = []
    pa, pb = make_period(period_a, "period_a"), make_period(period_b, "period_b")
    prods = normalize_products(products)
    rows_a = normalize_facts(facts_a, pa, warnings, "facts_a")
    rows_b = normalize_facts(facts_b, pb, warnings, "facts_b")
    ot_a = normalize_order_totals(order_totals_a, pa, warnings, "order_totals_a")
    ot_b = normalize_order_totals(order_totals_b, pb, warnings, "order_totals_b")
    check_order_totals_consistency(rows_a, ot_a, warnings)
    check_order_totals_consistency(rows_b, ot_b, warnings)

    if pa["days"] != pb["days"]:
        warnings.append(
            f"Panjang periode berbeda (A: {pa['days']} hari, B: {pb['days']} hari): "
            "bandingkan perubahan per hari, bukan hanya persen mentah."
        )
    if periods_overlap(pa, pb):
        warnings.append("Periode A dan B tumpang tindih; selisihnya tidak independen.")
    kosong = not rows_a and not rows_b
    if kosong:
        warnings.append("Kedua periode tidak memiliki data pada filter ini.")

    tot_a, tot_b = total_fields(rows_a), total_fields(rows_b)

    def metrik(a, b, test):
        return dict(compare_metric(a, b, pa["days"], pb["days"], test=test, alpha=alpha, baseline=baseline),
                    available=True)

    totals = {
        "revenue_idr": dict(metrik(tot_a["revenue_idr"], tot_b["revenue_idr"], False), descriptive_only=True),
        "units_sold": metrik(tot_a["units_sold"], tot_b["units_sold"], True),
        "purchases": metrik(tot_a["purchases"], tot_b["purchases"], True),
    }
    if ot_a is None or ot_b is None:
        totals["transactions"] = _tidak_tersedia(
            None if ot_a is None else ot_a["total"], None if ot_b is None else ot_b["total"],
            "Blok order_totals tidak dikirim untuk salah satu atau kedua periode.",
        )
        warnings.append(
            "Jumlah transaksi belum dapat dihitung karena order_totals tidak tersedia "
            "(bukan 0 dan bukan jumlah per produk)."
        )
    else:
        totals["transactions"] = metrik(ot_a["total"], ot_b["total"], True)
    totals["transactions_differ_from_purchases"] = (
        None if ot_a is None or ot_b is None
        else (ot_a["total"] != tot_a["purchases"] or ot_b["total"] != tot_b["purchases"])
    )

    per_a, per_b = aggregate_by_product(rows_a), aggregate_by_product(rows_b)
    nol = {f: 0 for f in tot_a}
    daftar: list[dict] = []
    for pid in sorted(set(per_a) | set(per_b)):
        va, vb = per_a.get(pid, nol), per_b.get(pid, nol)
        if not any(x[f] for x in (va, vb) for f in ("units_sold", "orders_paid", "purchases", "revenue_idr")):
            continue  # hanya views/keranjang: bukan bahan perbandingan penjualan
        p = prods.get(pid)
        daftar.append({
            "product_id": pid,
            "name": product_name(prods, pid),
            "category_id": p["category_id"] if p else None,
            "is_active": p["is_active"] if p else None,
            "units_sold": metrik(va["units_sold"], vb["units_sold"], True),
            "transactions": metrik(va["orders_paid"], vb["orders_paid"], True),
            "purchases": metrik(va["purchases"], vb["purchases"], True),
            "revenue_idr": dict(metrik(va["revenue_idr"], vb["revenue_idr"], False), descriptive_only=True),
        })

    def kunci_naik(x):
        return (-x["units_sold"]["per_day_change"], name_key(x["name"]), x["product_id"])

    def kunci_turun(x):
        return (x["units_sold"]["per_day_change"], name_key(x["name"]), x["product_id"])

    # Arah peringkat mengikuti `baseline`: per_day_change sudah bertanda sesuai arah itu.
    naik = sorted((x for x in daftar if x["units_sold"]["per_day_change"] > 0), key=kunci_naik)[:top_n]
    turun = sorted((x for x in daftar if x["units_sold"]["per_day_change"] < 0), key=kunci_turun)[:top_n]

    def ringkas(x):
        return {
            "product_id": x["product_id"], "name": x["name"],
            "units_a": x["units_sold"]["a"], "units_b": x["units_sold"]["b"],
            "per_day_change": x["units_sold"]["per_day_change"],
            "change": x["units_sold"]["change"],
            "change_ratio": x["units_sold"]["change_ratio"],
            "change_ratio_text": x["units_sold"]["change_ratio_text"],
            "significance": x["units_sold"]["significance"],
        }

    return {
        "algorithm_version": ALGORITHM_VERSION,
        "timezone": TIMEZONE,
        "origin_filter": origin_filter,
        "baseline": baseline,
        "period_a": period_out(pa),
        "period_b": period_out(pb),
        "empty": kosong,
        "totals": totals,
        "by_product": daftar,
        "biggest_increase": [ringkas(x) for x in naik],
        "biggest_decrease": [ringkas(x) for x in turun],
        "caveats": [POISSON_CAVEAT],
        "warnings": unique(warnings),
    }
