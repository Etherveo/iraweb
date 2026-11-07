"""FR-03 Analisis Funnel: view -> add_to_cart -> booking -> purchase.

Menghitung jumlah event (bukan pengguna unik), rasio antar tahap dan terhadap
view, penurunan terbesar, serta anomali (tahap lebih besar dari tahap
sebelumnya) yang ditandai, tidak disembunyikan (ANL-35).

Catatan batas data: `facts` hanya memuat jumlah `cancels` total, sehingga
booking yang tidak berlanjut TIDAK bisa dipilah dibatalkan/kedaluwarsa/menunggu
(ANL-35). Yang dikembalikan: selisih booking-pembelian (`bookings_not_purchased`)
dan total `cancels`.

Fungsi murni dan deterministik. Hanya stdlib.
"""

from __future__ import annotations

from datetime import datetime, timedelta, timezone
from typing import Any

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
    total_fields,
    unique,
    validate_origin_filter,
)

# (kode tahap, kolom facts, label tampil)
STAGES = (
    ("view", "views", "Dilihat"),
    ("add_to_cart", "add_to_carts", "Keranjang"),
    ("booking", "bookings", "Booking"),
    ("purchase", "purchases", "Pembelian"),
)

ANOMALY_NOTE = (
    "Tahap lebih besar dari tahap sebelumnya (mis. karena batas periode); "
    "ditandai sebagai catatan data, tidak disembunyikan."
)


def _ratio(numerator: int, denominator: int) -> tuple[float | None, str | None]:
    """(nilai, teks). Pembagi nol -> (None, '—'), bukan error dan bukan tak hingga."""
    nilai = safe_ratio(numerator, denominator)
    return nilai, (STRIP if nilai is None else None)


def build_funnel(counts: dict[str, int]) -> dict:
    """Susun empat tahap, rasio, penurunan terbesar, dan anomali dari jumlah event.

    counts: {'views','add_to_carts','bookings','purchases'}.
    - ratio_prev  : tahap / tahap sebelumnya (tahap pertama: tidak berlaku)
    - ratio_view  : tahap / view
    - Penurunan terbesar = selisih absolut event terbesar antar dua tahap berurutan
      (seri -> transisi yang lebih awal); hanya transisi yang benar-benar turun.
    """
    tahap: list[dict] = []
    anomali: list[dict] = []
    drop_terbesar: dict | None = None
    views = counts["views"]
    for i, (kode, kolom, label) in enumerate(STAGES):
        jumlah = counts[kolom]
        if i == 0:
            r_prev, t_prev = None, None
        else:
            r_prev, t_prev = _ratio(jumlah, counts[STAGES[i - 1][1]])
        r_view, t_view = _ratio(jumlah, views) if i > 0 else (1.0 if views > 0 else None, None if views > 0 else STRIP)
        sebelumnya = counts[STAGES[i - 1][1]] if i > 0 else None
        ada_anomali = i > 0 and jumlah > sebelumnya
        tahap.append({
            "stage": kode, "label": label, "count": jumlah,
            "ratio_prev": r_prev, "ratio_prev_text": t_prev,
            "ratio_view": r_view, "ratio_view_text": t_view,
            "anomaly": ada_anomali,
        })
        if ada_anomali:
            anomali.append({
                "from_stage": STAGES[i - 1][0], "to_stage": kode,
                "from_count": sebelumnya, "to_count": jumlah, "note": ANOMALY_NOTE,
            })
        if i > 0 and sebelumnya > jumlah:
            selisih = sebelumnya - jumlah
            if drop_terbesar is None or selisih > drop_terbesar["diff"]:
                drop_terbesar = {
                    "from_stage": STAGES[i - 1][0], "to_stage": kode,
                    "from_label": STAGES[i - 1][2], "to_label": label,
                    "from_count": sebelumnya, "to_count": jumlah,
                    "diff": selisih, "ratio": selisih / sebelumnya,
                }
    return {"stages": tahap, "largest_drop": drop_terbesar, "anomalies": anomali}


def _counts(v: dict) -> dict[str, int]:
    return {kolom: v[kolom] for _k, kolom, _l in STAGES}


def analyze_funnel(
    facts: list[dict],
    products: list[dict],
    period: dict,
    *,
    origin_filter: str = "all",
    product_ids: list[int] | None = None,
    category_id: int | None = None,
) -> dict:
    """Funnel keseluruhan (atau untuk subset produk/kategori) plus tabel funnel per produk (ANL-34)."""
    validate_origin_filter(origin_filter)
    warnings: list[str] = []
    per = make_period(period)
    prods = normalize_products(products)
    rows = normalize_facts(facts, per, warnings)

    if product_ids is not None:
        for pid in product_ids:
            if pid not in prods:
                raise AnalyticsInputError(f"product_ids: produk {pid} tidak ada di daftar products.")

    def terpilih(pid: int) -> bool:
        if product_ids is not None and pid not in product_ids:
            return False
        if category_id is not None:
            p = prods.get(pid)
            if p is None or p["category_id"] != category_id:
                return False
        return True

    rows_terpilih = {k: v for k, v in rows.items() if terpilih(k[1])}
    total = total_fields(rows_terpilih)
    corong = build_funnel(_counts(total))

    per_produk: list[dict] = []
    for pid, agregat in aggregate_by_product(rows_terpilih).items():
        if not any(agregat[k] for k in ("views", "add_to_carts", "bookings", "purchases")):
            continue
        item = build_funnel(_counts(agregat))
        p = prods.get(pid)
        per_produk.append({
            "product_id": pid,
            "name": product_name(prods, pid),
            "category_id": p["category_id"] if p else None,
            "is_active": p["is_active"] if p else None,
            "counts": _counts(agregat),
            "stages": item["stages"],
            "largest_drop": item["largest_drop"],
            "anomalies": item["anomalies"],
        })
    per_produk.sort(key=lambda x: (-x["counts"]["views"], name_key(x["name"]), x["product_id"]))

    if corong["anomalies"]:
        warnings.append("Ada tahap funnel yang lebih besar dari tahap sebelumnya; ditandai sebagai catatan data.")
    if not any(total[k] for k in ("views", "add_to_carts", "bookings", "purchases")):
        warnings.append("Tidak ada event funnel pada periode dan filter ini.")
    warnings.append(
        "Booking yang tidak berlanjut tidak bisa dipilah dibatalkan/kedaluwarsa/menunggu dari facts; "
        "hanya selisih booking-pembelian dan total cancels yang tersedia."
    )

    return {
        "algorithm_version": ALGORITHM_VERSION,
        "timezone": TIMEZONE,
        "period": period_out(per),
        "origin_filter": origin_filter,
        "stages": corong["stages"],
        "largest_drop": corong["largest_drop"],
        "anomalies": corong["anomalies"],
        "bookings_not_purchased": max(0, total["bookings"] - total["purchases"]),
        "cancels": total["cancels"],
        "per_product": per_produk,
        "warnings": unique(warnings),
    }


# ---------------------------------------------------------------------------
# Fungsi bantu KHUSUS TES: dedup event view 30 menit
# ---------------------------------------------------------------------------
# Dedup sebenarnya terjadi di Laravel saat event ditulis (U-17); `facts` tidak
# memuat akun maupun waktu per event, jadi modul analitik menganggap `views`
# pada facts SUDAH ter-dedup. Fungsi di bawah hanya untuk menguji aturan itu.
# Jangan pernah mengirim ID akun asli ke sini: `user_ref` harus referensi ANONIM.

DEDUP_WINDOW_MINUTES = 30
_FIELD_PRIBADI = frozenset({"account_id", "user_id", "customer_id", "email", "name", "phone", "address"})


def _parse_waktu(value: Any, field: str) -> datetime:
    if isinstance(value, datetime):
        waktu = value
    elif isinstance(value, str):
        try:
            waktu = datetime.fromisoformat(value)
        except ValueError:
            raise AnalyticsInputError(f"{field}: waktu tidak valid ({value!r}); gunakan ISO 8601.") from None
    else:
        raise AnalyticsInputError(f"{field}: harus datetime atau string ISO 8601.")
    if waktu.tzinfo is None:  # waktu tanpa zona dianggap UTC
        waktu = waktu.replace(tzinfo=timezone.utc)
    return waktu


def dedup_view_events(events: list[dict], window_minutes: int = DEDUP_WINDOW_MINUTES) -> list[dict]:
    """Event view yang TERCATAT setelah dedup per (pengguna anonim, produk).

    Aturan (mengikuti pencatatan Laravel): sebuah view tidak dicatat bila sudah ada
    view tercatat dengan (pengguna, produk) yang sama kurang dari `window_minutes`
    sebelumnya. Jendela dihitung dari view yang TERCATAT terakhir (bukan dari percobaan
    terakhir); selisih tepat sama dengan jendela dianggap view baru (asumsi referensi).

    events: [{'user_ref': 'anon-xyz', 'product_id': 1, 'occurred_at': datetime|ISO-8601}]
    """
    if window_minutes < 0:
        raise AnalyticsInputError("window_minutes: tidak boleh negatif.")
    jendela = timedelta(minutes=window_minutes)
    siap: list[tuple[datetime, int, dict]] = []
    for i, e in enumerate(events):
        if not isinstance(e, dict):
            raise AnalyticsInputError(f"events[{i}]: harus objek.")
        pribadi = _FIELD_PRIBADI & set(e)
        if pribadi:
            raise AnalyticsInputError(
                f"events[{i}]: memuat field pribadi/ID akun ({', '.join(sorted(pribadi))}); "
                "kirim hanya referensi anonim 'user_ref'."
            )
        ref = e.get("user_ref")
        if not isinstance(ref, str) or not ref.strip() or ref.strip().isdigit():
            raise AnalyticsInputError(
                f"events[{i}].user_ref: harus referensi anonim (string non-angka), bukan ID akun."
            )
        pid = e.get("product_id")
        if isinstance(pid, bool) or not isinstance(pid, int):
            raise AnalyticsInputError(f"events[{i}].product_id: harus bilangan bulat.")
        siap.append((_parse_waktu(e.get("occurred_at"), f"events[{i}].occurred_at"), i, e))
    siap.sort(key=lambda t: (t[0], t[1]))

    terakhir: dict[tuple[str, int], datetime] = {}
    tercatat: list[dict] = []
    for waktu, _i, e in siap:
        kunci = (e["user_ref"], e["product_id"])
        sebelumnya = terakhir.get(kunci)
        if sebelumnya is None or waktu - sebelumnya >= jendela:
            terakhir[kunci] = waktu
            tercatat.append(e)
    return tercatat


def dedup_views(events: list[dict], window_minutes: int = DEDUP_WINDOW_MINUTES) -> int:
    """Jumlah view setelah dedup 30 menit per (pengguna anonim, produk). Khusus tes."""
    return len(dedup_view_events(events, window_minutes))
