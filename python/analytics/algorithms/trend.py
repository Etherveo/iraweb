"""FR-02 Analisis Tren: Mann-Kendall + Sen's slope + koreksi Benjamini-Hochberg.

Alur per produk (dokumen revisi §5, FR-02):
  1. Bucket mingguan Senin-Minggu WITA; minggu parsial di tepi periode dicatat,
     tidak diklasifikasi; minggu tanpa penjualan diisi 0.
  2. Minggu sebelum produk aktif pertama kali dipotong (hindari "naik" palsu).
  3. n < N_MIN -> "Data belum cukup"; total unit 0 -> "Tidak ada penjualan";
     rata-rata < 1 unit/minggu -> "Data terlalu jarang".
  4. Mann-Kendall (koreksi ikatan) + Sen's slope; r = beta*(n-1)/rata-rata.
  5. BH atas SEMUA produk aktif yang punya p-value pada filter yang sama.
  6. q < 0,05 dan r >= +10% -> Naik; q < 0,05 dan r <= -10% -> Turun;
     selain itu "Tidak ada tren jelas".  Kekuatan: q < 0,01 kuat, selain itu cukup.

Fungsi murni dan deterministik. Hanya stdlib.
"""

from __future__ import annotations

import math
import statistics
from collections import Counter
from datetime import date, timedelta
from typing import Any

from ._common import (
    ALGORITHM_VERSION,
    TIMEZONE,
    AnalyticsInputError,
    has_activity,
    make_period,
    name_key,
    normalize_facts,
    normalize_products,
    period_out,
    stock_warning,
    unique,
    validate_origin_filter,
    week_buckets,
)

# Ambang default (U-25, K1/K2). Mudah diubah lewat argumen fungsi.
N_MIN_WEEKS = 8          # minimum minggu penuh untuk klasifikasi
MIN_AVG_PER_WEEK = 1.0   # rata-rata unit/minggu di bawah ini -> "Data terlalu jarang"
ALPHA = 0.05             # ambang q (setelah BH) untuk Naik/Turun
R_MIN = 0.10             # |r| minimum (10%) untuk Naik/Turun
Q_STRONG = 0.01          # q di bawah ini -> bukti "kuat"

LABELS = {
    "naik": "Naik",
    "turun": "Turun",
    "tidak_ada_tren_jelas": "Tidak ada tren jelas",
    "data_belum_cukup": "Data belum cukup",
    "tidak_ada_penjualan": "Tidak ada penjualan",
    "data_terlalu_jarang": "Data terlalu jarang",
    "produk_nonaktif": "Produk nonaktif",  # tambahan referensi: produk nonaktif tidak ikut BH
}


# ---------------------------------------------------------------------------
# Statistik dasar
# ---------------------------------------------------------------------------

def mann_kendall(values: list[float]) -> dict:
    """Uji Mann-Kendall dua sisi dengan koreksi ikatan dan koreksi kontinuitas.

    S = jumlah tanda(x_j - x_i) untuk i<j
    Var(S) = [n(n-1)(2n+5) - sum t(t-1)(2t+5)] / 18   (t = ukuran kelompok nilai kembar)
    Z = (S-1)/sqrt(Var) bila S>0; (S+1)/sqrt(Var) bila S<0; 0 bila S=0
    p = 2*(1 - Phi(|Z|))
    """
    n = len(values)
    s = 0
    for i in range(n - 1):
        vi = values[i]
        for j in range(i + 1, n):
            selisih = values[j] - vi
            if selisih > 0:
                s += 1
            elif selisih < 0:
                s -= 1
    ikatan = Counter(values)
    var = n * (n - 1) * (2 * n + 5) / 18 - sum(
        t * (t - 1) * (2 * t + 5) for t in ikatan.values() if t > 1
    ) / 18
    if var <= 0:  # semua nilai sama (atau n terlalu kecil): tidak ada bukti tren
        z, p = 0.0, 1.0
    else:
        akar = math.sqrt(var)
        z = (s - 1) / akar if s > 0 else (s + 1) / akar if s < 0 else 0.0
        p = min(1.0, math.erfc(abs(z) / math.sqrt(2)))
    return {"s": s, "var": var, "z": z, "p": p}


def sen_slope(values: list[float]) -> float:
    """Sen's slope: median dari (x_j - x_i)/(j - i) untuk semua i<j (satuan per minggu)."""
    n = len(values)
    lereng = [(values[j] - values[i]) / (j - i) for i in range(n - 1) for j in range(i + 1, n)]
    return float(statistics.median(lereng))


def benjamini_hochberg(p_values: list[float]) -> list[float]:
    """Nilai q Benjamini-Hochberg, selaras dengan urutan masukan."""
    m = len(p_values)
    if m == 0:
        return []
    urutan = sorted(range(m), key=lambda i: p_values[i])
    q = [0.0] * m
    sebelumnya = 1.0
    for peringkat in range(m, 0, -1):
        i = urutan[peringkat - 1]
        nilai = min(sebelumnya, p_values[i] * m / peringkat)
        q[i] = nilai
        sebelumnya = nilai
    return q


# ---------------------------------------------------------------------------
# Inti klasifikasi (dipakai FR-02 per produk dan FR-06 per kategori)
# ---------------------------------------------------------------------------

def _weekly(daily: dict[date, float], mondays: list[date]) -> list[float]:
    return [sum(daily.get(m + timedelta(days=i), 0) for i in range(7)) for m in mondays]


def classify_groups(
    groups: list[dict],
    mondays_full: list[date],
    *,
    metric: str = "units",
    n_min_weeks: int = N_MIN_WEEKS,
    min_avg_per_week: float = MIN_AVG_PER_WEEK,
    alpha: float = ALPHA,
    r_min: float = R_MIN,
    q_strong: float = Q_STRONG,
) -> list[dict]:
    """Klasifikasikan sekumpulan deret (produk atau kategori) dengan satu keluarga BH.

    Setiap grup: {key, name, eligible, daily_units, daily_value, start_date, start_source}.
    `eligible=False` (mis. produk nonaktif) -> tidak dianalisis dan tidak masuk keluarga BH.
    Hanya grup yang punya p-value yang dihitung dalam m (jumlah hipotesis).
    """
    hasil: list[dict] = []
    for g in groups:
        mulai = g.get("start_date")
        kept = [m for m in mondays_full if mulai is None or m >= mulai]
        n = len(kept)
        unit_mingguan = _weekly(g["daily_units"], kept)
        nilai_mingguan = unit_mingguan if metric == "units" else _weekly(g["daily_value"], kept)
        total_unit = sum(unit_mingguan)
        rata_unit = total_unit / n if n else 0.0
        seri = [
            {
                "week_start": m.isoformat(),
                "week_end": (m + timedelta(days=6)).isoformat(),
                "units": u,
                "value": v,
            }
            for m, u, v in zip(kept, unit_mingguan, nilai_mingguan)
        ]
        r: dict[str, Any] = {
            "key": g["key"],
            "name": g["name"],
            "label_code": None,
            "label": None,
            "strength": None,
            "n": n,
            "beta": None,
            "r": None,
            "p": None,
            "q": None,
            "s": None,
            "z": None,
            "var": None,
            "mean": None,
            "change_abs": None,
            "total_units": total_unit,
            "avg_units_per_week": rata_unit,
            "series": seri,
            "trimmed_weeks": len(mondays_full) - n,
            "start_date": mulai.isoformat() if mulai else None,
            "start_source": g.get("start_source", "none"),
            "warnings": [],
            "_tested": False,
        }
        if not g.get("eligible", True):
            r["label_code"] = "produk_nonaktif"
        elif n < n_min_weeks:
            r["label_code"] = "data_belum_cukup"
        elif total_unit == 0:
            r["label_code"] = "tidak_ada_penjualan"
        elif rata_unit < min_avg_per_week:
            r["label_code"] = "data_terlalu_jarang"
        else:
            rata = sum(nilai_mingguan) / n
            if rata <= 0:  # metrik nilai nol padahal ada unit: basis nol, tidak bisa dihitung
                r["label_code"] = "tidak_ada_penjualan"
            else:
                mk = mann_kendall(nilai_mingguan)
                beta = sen_slope(nilai_mingguan)
                r.update({
                    "beta": beta,
                    "r": beta * (n - 1) / rata,
                    "p": mk["p"],
                    "s": mk["s"],
                    "z": mk["z"],
                    "var": mk["var"],
                    "mean": rata,
                    "change_abs": beta * (n - 1),
                    "_tested": True,
                })
        hasil.append(r)

    diuji = [r for r in hasil if r["_tested"]]
    q_values = benjamini_hochberg([r["p"] for r in diuji])
    for r, q in zip(diuji, q_values):
        r["q"] = q
        if q < alpha and r["r"] >= r_min:
            r["label_code"] = "naik"
        elif q < alpha and r["r"] <= -r_min:
            r["label_code"] = "turun"
        else:
            r["label_code"] = "tidak_ada_tren_jelas"
        if r["label_code"] in ("naik", "turun"):
            r["strength"] = "kuat" if q < q_strong else "cukup"
    for r in hasil:
        r["label"] = LABELS[r["label_code"]]
        del r["_tested"]
    return hasil


def rank_changes(results: list[dict]) -> dict[str, list[dict]]:
    """Dua daftar peringkat (ANL-23): Naik (kenaikan terbesar dulu) dan Turun (penurunan terbesar dulu).

    Besar perubahan = r (relatif terhadap rata-rata) dan change_abs = beta*(n-1).
    Hanya deret berlabel Naik/Turun yang masuk; basis nol praktis tidak muncul
    karena deret yang diklasifikasi selalu punya rata-rata > 0.
    """
    def entri(x: dict) -> dict:
        return {
            "key": x["key"], "name": x["name"], "r": x["r"],
            "change_abs": x["change_abs"], "strength": x["strength"], "n": x["n"],
        }

    naik = sorted((x for x in results if x["label_code"] == "naik"),
                  key=lambda x: (-x["r"], -x["change_abs"], name_key(x["name"])))
    turun = sorted((x for x in results if x["label_code"] == "turun"),
                   key=lambda x: (x["r"], x["change_abs"], name_key(x["name"])))
    return {"naik": [entri(x) for x in naik], "turun": [entri(x) for x in turun]}


# ---------------------------------------------------------------------------
# FR-02 per produk
# ---------------------------------------------------------------------------

def analyze_trend(
    facts: list[dict],
    products: list[dict],
    period: dict,
    *,
    origin_filter: str = "all",
    metric: str = "units",
    product_ids: list[int] | None = None,
    n_min_weeks: int = N_MIN_WEEKS,
    min_avg_per_week: float = MIN_AVG_PER_WEEK,
    alpha: float = ALPHA,
    r_min: float = R_MIN,
    q_strong: float = Q_STRONG,
) -> dict:
    """Klasifikasi tren seluruh produk aktif, lalu (opsional) saring keluaran ke `product_ids`.

    BH SELALU dihitung atas semua produk aktif yang punya p-value, jadi label sebuah
    produk tidak berubah hanya karena pengguna memilih subset produk (K2).

    metric: 'units' (default; "permintaan" = kuantitas terjual) atau 'revenue' (Rp).
    Pemeriksaan "Tidak ada penjualan" dan "Data terlalu jarang" selalu memakai unit.
    Awal deret produk = `active_since` bila ada; bila kosong, minggu pertama yang punya
    aktivitas APA PUN di facts (dicatat di warnings). Minggu sebelum itu dipotong.
    """
    validate_origin_filter(origin_filter)
    if metric not in ("units", "revenue"):
        raise AnalyticsInputError(f"metric: harus 'units' atau 'revenue' (diterima {metric!r}).")
    warnings: list[str] = []
    per = make_period(period)
    prods = normalize_products(products)
    rows = normalize_facts(facts, per, warnings)
    penuh, parsial = week_buckets(per["start"], per["end"])

    if product_ids is not None:
        for pid in product_ids:
            if pid not in prods:
                raise AnalyticsInputError(f"product_ids: produk {pid} tidak ada di daftar products.")

    harian_unit: dict[int, dict[date, int]] = {}
    harian_nilai: dict[int, dict[date, int]] = {}
    aktivitas_pertama: dict[int, date] = {}
    for (d, pid), v in rows.items():
        harian_unit.setdefault(pid, {})[d] = v["units_sold"]
        harian_nilai.setdefault(pid, {})[d] = v["revenue_idr"]
        if has_activity(v) and (pid not in aktivitas_pertama or d < aktivitas_pertama[pid]):
            aktivitas_pertama[pid] = d

    tak_dikenal = sorted(set(harian_unit) - set(prods))
    if tak_dikenal:
        warnings.append(
            f"{len(tak_dikenal)} produk pada facts tidak ada di daftar products dan tidak diklasifikasi "
            f"(contoh id: {tak_dikenal[0]})."
        )

    grup: list[dict] = []
    for pid, p in prods.items():
        if p["active_since"] is not None:
            mulai, sumber = p["active_since"], "active_since"
        elif pid in aktivitas_pertama:
            mulai, sumber = aktivitas_pertama[pid], "first_activity"
        else:
            mulai, sumber = None, "none"
        grup.append({
            "key": pid, "name": p["name"], "eligible": p["is_active"],
            "daily_units": harian_unit.get(pid, {}), "daily_value": harian_nilai.get(pid, {}),
            "start_date": mulai, "start_source": sumber,
        })

    hasil = classify_groups(
        grup, penuh, metric=metric, n_min_weeks=n_min_weeks, min_avg_per_week=min_avg_per_week,
        alpha=alpha, r_min=r_min, q_strong=q_strong,
    )
    n_diuji = sum(1 for r in hasil if r["p"] is not None)

    fallback_ids = [r["key"] for r in hasil if r["start_source"] == "first_activity"]
    for r in hasil:
        if r["start_source"] == "first_activity":
            r["warnings"].append(
                "active_since kosong: awal deret ditentukan dari aktivitas pertama di facts "
                f"({r['start_date']})."
            )
    if fallback_ids:
        contoh = ", ".join(str(i) for i in fallback_ids[:10]) + (" dan lainnya" if len(fallback_ids) > 10 else "")
        warnings.append(
            f"Awal deret {len(fallback_ids)} produk ditentukan dari aktivitas pertama di facts "
            f"karena active_since kosong (id: {contoh})."
        )

    if len(penuh) < n_min_weeks:
        warnings.append(
            f"Data belum cukup untuk tren: periode hanya memuat {len(penuh)} minggu penuh "
            f"(minimal {n_min_weeks})."
        )
    if parsial:
        warnings.append(
            f"{len(parsial)} minggu parsial di tepi periode dicatat tetapi tidak diklasifikasi."
        )

    rankings = rank_changes(hasil)
    if any(r["label_code"] in ("turun", "tidak_ada_penjualan", "data_terlalu_jarang") for r in hasil):
        catatan = stock_warning(origin_filter)
        if catatan:
            warnings.append(catatan)

    keluaran: list[dict] = []
    for r in hasil:
        if product_ids is not None and r["key"] not in product_ids:
            continue
        p = prods[r["key"]]
        item = dict(r)
        item["product_id"] = item.pop("key")
        item["category_id"] = p["category_id"]
        item["is_active"] = p["is_active"]
        keluaran.append(item)

    return {
        "algorithm_version": ALGORITHM_VERSION,
        "timezone": TIMEZONE,
        "granularity": "week",
        "period": period_out(per),
        "origin_filter": origin_filter,
        "metric": metric,
        "params": {
            "n_min_weeks": n_min_weeks, "min_avg_per_week": min_avg_per_week,
            "alpha": alpha, "r_min": r_min, "q_strong": q_strong,
        },
        "weeks_full": len(penuh),
        "partial_weeks": parsial,
        "n_tested": n_diuji,
        "products": keluaran,
        "rankings": {
            k: [{**{f: x for f, x in e.items() if f != "key"}, "product_id": e["key"]} for e in v]
            for k, v in rankings.items()
        },
        "warnings": unique(warnings),
    }
