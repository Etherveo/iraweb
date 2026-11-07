"""Utilitas bersama untuk modul algoritma Trend Analytics (referensi, stdlib saja).

Isi: konstanta, galat input, penguraian tanggal WITA, normalisasi `facts` /
`products` / `order_totals`, bucket waktu, dan helper rasio.

Semua fungsi MURNI: tidak membaca database, file, environment, atau jaringan,
dan tidak mengubah argumen yang diterima.
"""

from __future__ import annotations

import math
import re
from datetime import date, datetime, timedelta
from typing import Any, Iterable

# ---------------------------------------------------------------------------
# Konstanta
# ---------------------------------------------------------------------------

ALGORITHM_VERSION = "ira-trend-analytics-ref/0.1.0"
TIMEZONE = "Asia/Makassar"  # WITA, UTC+8. Tanggal pada `facts` sudah tanggal WITA.

BELUM_DAPAT_DIHITUNG = "Belum dapat dihitung"  # teks seragam untuk basis nol (K7)
STRIP = "\u2014"  # "—" untuk pembagi nol pada funnel (ANL-32)

ORIGIN_FILTERS = ("all", "normal", "developer_simulation")
ORIGIN_LABELS = {
    "all": "Semua",
    "normal": "Hanya normal",
    "developer_simulation": "Hanya simulasi",
}

STOCK_NOTE = (
    "Simulator tidak memodelkan stok: penjualan rendah pada data simulasi "
    "tidak boleh disimpulkan sebagai stok habis."
)

# Kolom numerik pada setiap baris `facts` (blueprint §11.3). Kolom yang hilang dianggap 0.
FACT_FIELDS = (
    "units_sold",
    "revenue_idr",
    "orders_paid",
    "views",
    "add_to_carts",
    "bookings",
    "purchases",
    "cancels",
)

# Granularitas otomatis (ANL-14: "default menyesuaikan panjang periode").
# Batas ini ASUMSI referensi, mudah diubah lewat parameter `summarize(...)`.
AUTO_DAY_MAX_DAYS = 31
AUTO_WEEK_MAX_DAYS = 183

_ISO_DATE = re.compile(r"^\d{4}-\d{2}-\d{2}$")


class AnalyticsInputError(ValueError):
    """Input tidak sesuai kontrak (bentuk salah, nilai negatif, periode terbalik, dst.).

    Pembungkus HTTP sebaiknya menerjemahkan galat ini menjadi respons 422,
    bukan angka nol (ANL-06).
    """


# ---------------------------------------------------------------------------
# Tanggal dan periode
# ---------------------------------------------------------------------------

def parse_date(value: Any, field: str = "date") -> date:
    """Terima `date` atau string 'YYYY-MM-DD' (tanggal WITA); selain itu galat."""
    if isinstance(value, datetime):
        raise AnalyticsInputError(f"{field}: harus tanggal (YYYY-MM-DD), bukan datetime.")
    if isinstance(value, date):
        return value
    if isinstance(value, str) and _ISO_DATE.match(value):
        try:
            return date.fromisoformat(value)
        except ValueError:
            pass
    raise AnalyticsInputError(f"{field}: tanggal tidak valid ({value!r}); format YYYY-MM-DD.")


def make_period(period: Any, name: str = "period") -> dict:
    """Validasi {'start','end'} (inklusif) dan kembalikan dict berisi date + jumlah hari."""
    if not isinstance(period, dict) or "start" not in period or "end" not in period:
        raise AnalyticsInputError(f"{name}: harus berisi 'start' dan 'end'.")
    start = parse_date(period["start"], f"{name}.start")
    end = parse_date(period["end"], f"{name}.end")
    if start > end:
        raise AnalyticsInputError(f"{name}: 'start' ({start}) tidak boleh setelah 'end' ({end}).")
    return {"start": start, "end": end, "days": (end - start).days + 1}


def period_out(period: dict) -> dict:
    """Bentuk periode yang aman untuk JSON."""
    return {"start": period["start"].isoformat(), "end": period["end"].isoformat(), "days": period["days"]}


def previous_period(period: dict) -> dict:
    """Periode berpanjang sama yang berakhir sehari sebelum `period` dimulai (ANL-46)."""
    days = period["days"]
    end = period["start"] - timedelta(days=1)
    start = end - timedelta(days=days - 1)
    return {"start": start, "end": end, "days": days}


def periods_overlap(a: dict, b: dict) -> bool:
    return a["start"] <= b["end"] and b["start"] <= a["end"]


def monday_of(d: date) -> date:
    """Senin pada minggu yang memuat `d` (minggu = Senin-Minggu)."""
    return d - timedelta(days=d.weekday())


def week_buckets(start: date, end: date) -> tuple[list[date], list[dict]]:
    """Pisahkan minggu Senin-Minggu pada [start, end] menjadi minggu PENUH dan PARSIAL.

    Mengembalikan (daftar Senin minggu penuh, daftar minggu parsial). Minggu parsial
    hanya ada di tepi periode; ia dicatat tetapi tidak pernah diklasifikasi (FR-02).
    """
    penuh: list[date] = []
    parsial: list[dict] = []
    senin = monday_of(start)
    while senin <= end:
        minggu = senin + timedelta(days=6)
        if senin >= start and minggu <= end:
            penuh.append(senin)
        else:
            awal, akhir = max(senin, start), min(minggu, end)
            parsial.append({
                "start": awal.isoformat(),
                "end": akhir.isoformat(),
                "days": (akhir - awal).days + 1,
            })
        senin += timedelta(days=7)
    return penuh, parsial


def pick_granularity(
    days: int,
    granularity: str = "auto",
    day_max: int = AUTO_DAY_MAX_DAYS,
    week_max: int = AUTO_WEEK_MAX_DAYS,
) -> str:
    """Pilih 'day' / 'week' / 'month'. 'auto' memakai panjang periode."""
    if granularity in ("day", "week", "month"):
        return granularity
    if granularity != "auto":
        raise AnalyticsInputError(f"granularity: harus day, week, month, atau auto (diterima {granularity!r}).")
    if days <= day_max:
        return "day"
    if days <= week_max:
        return "week"
    return "month"


def build_buckets(start: date, end: date, granularity: str) -> list[dict]:
    """Bucket berurutan yang menutup [start, end]; bucket tepi yang terpotong ditandai `partial`."""
    hasil: list[dict] = []
    if granularity == "day":
        d = start
        while d <= end:
            hasil.append({"start": d, "end": d, "partial": False})
            d += timedelta(days=1)
    elif granularity == "week":
        senin = monday_of(start)
        while senin <= end:
            minggu = senin + timedelta(days=6)
            awal, akhir = max(senin, start), min(minggu, end)
            hasil.append({"start": awal, "end": akhir, "partial": not (senin >= start and minggu <= end)})
            senin += timedelta(days=7)
    elif granularity == "month":
        tahun, bulan = start.year, start.month
        while date(tahun, bulan, 1) <= end:
            awal_bulan = date(tahun, bulan, 1)
            tahun_next, bulan_next = (tahun + 1, 1) if bulan == 12 else (tahun, bulan + 1)
            akhir_bulan = date(tahun_next, bulan_next, 1) - timedelta(days=1)
            awal, akhir = max(awal_bulan, start), min(akhir_bulan, end)
            hasil.append({"start": awal, "end": akhir, "partial": not (awal_bulan >= start and akhir_bulan <= end)})
            tahun, bulan = tahun_next, bulan_next
    else:
        raise AnalyticsInputError(f"granularity: tidak dikenal ({granularity!r}).")
    return hasil


# ---------------------------------------------------------------------------
# Normalisasi input
# ---------------------------------------------------------------------------

def validate_origin_filter(origin_filter: str) -> str:
    if origin_filter not in ORIGIN_FILTERS:
        raise AnalyticsInputError(
            f"origin_filter: harus salah satu dari {', '.join(ORIGIN_FILTERS)} (diterima {origin_filter!r})."
        )
    return origin_filter


def _to_int(value: Any, field: str, *, minimum: int | None = None) -> int:
    if isinstance(value, bool) or not isinstance(value, (int, float)):
        raise AnalyticsInputError(f"{field}: harus bilangan bulat (diterima {value!r}).")
    if isinstance(value, float):
        if not math.isfinite(value) or value != int(value):
            raise AnalyticsInputError(f"{field}: harus bilangan bulat (diterima {value!r}).")
        value = int(value)
    if minimum is not None and value < minimum:
        raise AnalyticsInputError(f"{field}: tidak boleh kurang dari {minimum} (diterima {value}).")
    return int(value)


def normalize_products(products: Any) -> dict[int, dict]:
    """Ubah daftar produk menjadi {id: {...}}. Field opsional `active_since` (WITA) diterima."""
    hasil: dict[int, dict] = {}
    if products is None:
        return hasil
    if not isinstance(products, (list, tuple)):
        raise AnalyticsInputError("products: harus berupa daftar objek.")
    for i, p in enumerate(products):
        if not isinstance(p, dict) or "id" not in p:
            raise AnalyticsInputError(f"products[{i}]: harus objek dengan 'id'.")
        pid = _to_int(p["id"], f"products[{i}].id")
        if pid in hasil:
            raise AnalyticsInputError(f"products: id {pid} muncul lebih dari sekali.")
        nama = p.get("name")
        kategori = p.get("category_id")
        harga = p.get("price_idr")
        aktif = p.get("is_active", True)
        if not isinstance(aktif, bool):
            raise AnalyticsInputError(f"products[{i}].is_active: harus true/false.")
        active_since = p.get("active_since")
        hasil[pid] = {
            "id": pid,
            "name": nama if isinstance(nama, str) and nama.strip() else f"Produk {pid}",
            "category_id": None if kategori is None else _to_int(kategori, f"products[{i}].category_id"),
            "price_idr": None if harga is None else _to_int(harga, f"products[{i}].price_idr", minimum=0),
            "is_active": aktif,
            "active_since": None if active_since in (None, "") else parse_date(active_since, f"products[{i}].active_since"),
        }
    return hasil


def product_name(products: dict[int, dict], pid: int) -> str:
    p = products.get(pid)
    return p["name"] if p else f"Produk {pid}"


def name_key(nama: str) -> tuple[str, str]:
    """Kunci urut nama yang deterministik (tanpa beda huruf besar/kecil, lalu persis)."""
    return (nama.casefold(), nama)


def normalize_facts(facts: Any, period: dict, warnings: list[str], name: str = "facts") -> dict[tuple[date, int], dict]:
    """Validasi `facts` dan kembalikan {(tanggal, product_id): {kolom: int}}.

    - Kolom yang hilang dianggap 0; nilai negatif atau bukan angka -> AnalyticsInputError.
    - Baris di luar periode diabaikan dan dicatat di `warnings` (tidak diam-diam).
    - Baris ganda untuk (tanggal, produk) yang sama dijumlahkan dan dicatat.
    - Invarian `purchases == orders_paid` per baris diperiksa; pelanggaran hanya jadi peringatan.
    """
    if facts is None:
        return {}
    if not isinstance(facts, (list, tuple)):
        raise AnalyticsInputError(f"{name}: harus berupa daftar objek.")
    baris: dict[tuple[date, int], dict] = {}
    luar = ganda = 0
    for i, row in enumerate(facts):
        if not isinstance(row, dict):
            raise AnalyticsInputError(f"{name}[{i}]: harus objek.")
        d = parse_date(row.get("date"), f"{name}[{i}].date")
        pid = _to_int(row.get("product_id"), f"{name}[{i}].product_id")
        nilai = {f: _to_int(row.get(f, 0), f"{name}[{i}].{f}", minimum=0) for f in FACT_FIELDS}
        if d < period["start"] or d > period["end"]:
            luar += 1
            continue
        kunci = (d, pid)
        if kunci in baris:
            ganda += 1
            for f in FACT_FIELDS:
                baris[kunci][f] += nilai[f]
        else:
            baris[kunci] = nilai
    if luar:
        warnings.append(f"{luar} baris {name} berada di luar periode dan diabaikan.")
    if ganda:
        warnings.append(f"{ganda} baris {name} ganda untuk (tanggal, produk) yang sama dijumlahkan.")
    tak_cocok = sum(1 for v in baris.values() if v["purchases"] != v["orders_paid"])
    if tak_cocok:
        warnings.append(
            f"Invarian data dilanggar pada {tak_cocok} baris {name}: purchases tidak sama dengan "
            "orders_paid per (hari, produk); periksa penyusunan facts."
        )
    return baris


def normalize_order_totals(order_totals: Any, period: dict, warnings: list[str], name: str = "order_totals") -> dict | None:
    """Validasi blok `order_totals` (pesanan paid UNIK per hari; keputusan pemilik proyek).

    Mengembalikan None bila blok tidak dikirim. Bila ada, mengembalikan dict:
      total        : jumlah pesanan paid unik pada periode
      by_day       : {tanggal: jumlah}
      by_category  : {category_id: jumlah} atau None bila ada baris tanpa `by_category`
    Jumlah antar kategori BOLEH melebihi total (satu pesanan bisa memuat dua kategori).
    """
    if order_totals is None:
        return None
    if not isinstance(order_totals, (list, tuple)):
        raise AnalyticsInputError(f"{name}: harus berupa daftar objek.")
    by_day: dict[date, int] = {}
    by_cat_day: dict[date, dict[int, int]] = {}
    semua_punya_kategori = True
    luar = 0
    for i, row in enumerate(order_totals):
        if not isinstance(row, dict):
            raise AnalyticsInputError(f"{name}[{i}]: harus objek.")
        d = parse_date(row.get("date"), f"{name}[{i}].date")
        jumlah = _to_int(row.get("orders_paid"), f"{name}[{i}].orders_paid", minimum=0)
        if d < period["start"] or d > period["end"]:
            luar += 1
            continue
        by_day[d] = by_day.get(d, 0) + jumlah
        if "by_category" not in row:
            semua_punya_kategori = False
            continue
        daftar = row["by_category"]
        if not isinstance(daftar, (list, tuple)):
            raise AnalyticsInputError(f"{name}[{i}].by_category: harus berupa daftar objek.")
        for j, item in enumerate(daftar):
            if not isinstance(item, dict):
                raise AnalyticsInputError(f"{name}[{i}].by_category[{j}]: harus objek.")
            cid = _to_int(item.get("category_id"), f"{name}[{i}].by_category[{j}].category_id")
            n = _to_int(item.get("orders_paid"), f"{name}[{i}].by_category[{j}].orders_paid", minimum=0)
            if n > jumlah:
                warnings.append(
                    f"{name}: pesanan kategori {cid} pada {d.isoformat()} ({n}) melebihi total hari itu ({jumlah})."
                )
            harian = by_cat_day.setdefault(d, {})
            harian[cid] = harian.get(cid, 0) + n
    if luar:
        warnings.append(f"{luar} baris {name} berada di luar periode dan diabaikan.")
    by_category: dict[int, int] | None = None
    if semua_punya_kategori:
        by_category = {}
        for harian in by_cat_day.values():
            for cid, n in harian.items():
                by_category[cid] = by_category.get(cid, 0) + n
    return {"total": sum(by_day.values()), "by_day": by_day, "by_category": by_category}


def check_order_totals_consistency(
    rows: dict[tuple[date, int], dict], order_totals: dict | None, warnings: list[str]
) -> None:
    """Pesanan unik per hari harus berada di antara max dan Σ `orders_paid` per produk pada hari itu."""
    if order_totals is None:
        return
    per_hari_maks: dict[date, int] = {}
    per_hari_sum: dict[date, int] = {}
    for (d, _pid), v in rows.items():
        per_hari_maks[d] = max(per_hari_maks.get(d, 0), v["orders_paid"])
        per_hari_sum[d] = per_hari_sum.get(d, 0) + v["orders_paid"]
    hari = set(order_totals["by_day"]) | set(per_hari_sum)
    salah = [d for d in hari
             if not per_hari_maks.get(d, 0) <= order_totals["by_day"].get(d, 0) <= per_hari_sum.get(d, 0)]
    if salah:
        warnings.append(
            f"order_totals tidak konsisten dengan facts pada {len(salah)} hari "
            f"(contoh: {min(salah).isoformat()}); periksa penyusunan data."
        )


def aggregate_by_product(rows: dict[tuple[date, int], dict]) -> dict[int, dict]:
    """Jumlahkan semua kolom per produk pada seluruh periode."""
    hasil: dict[int, dict] = {}
    for (_d, pid), v in rows.items():
        akum = hasil.setdefault(pid, {f: 0 for f in FACT_FIELDS})
        for f in FACT_FIELDS:
            akum[f] += v[f]
    return hasil


def total_fields(rows: dict[tuple[date, int], dict]) -> dict:
    """Jumlahkan semua kolom pada seluruh baris."""
    akum = {f: 0 for f in FACT_FIELDS}
    for v in rows.values():
        for f in FACT_FIELDS:
            akum[f] += v[f]
    return akum


def has_activity(v: dict) -> bool:
    """True bila baris punya aktivitas APA PUN (view, keranjang, booking, pembelian, penjualan, batal)."""
    return any(v[f] > 0 for f in FACT_FIELDS)


# ---------------------------------------------------------------------------
# Helper rasio dan keluaran
# ---------------------------------------------------------------------------

def safe_ratio(numerator: float, denominator: float) -> float | None:
    """numerator/denominator; None bila penyebut 0 (bukan tak hingga)."""
    if denominator == 0:
        return None
    return numerator / denominator


def unique(items: Iterable[str]) -> list[str]:
    """Hapus duplikat dengan menjaga urutan kemunculan pertama."""
    terlihat: set[str] = set()
    hasil: list[str] = []
    for x in items:
        if x not in terlihat:
            terlihat.add(x)
            hasil.append(x)
    return hasil


def stock_warning(origin_filter: str) -> str | None:
    """Catatan stok (SIM-15, K16) bila data memuat simulasi. Pemanggil menentukan 'relevan'."""
    return STOCK_NOTE if origin_filter != "normal" else None


def is_finite_number(x: Any) -> bool:
    return isinstance(x, (int, float)) and not isinstance(x, bool) and math.isfinite(x)
