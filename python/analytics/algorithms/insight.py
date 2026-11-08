"""Insight otomatis: templat INS-01..INS-06 (modul terpisah dari statistik inti).

Insight BUKAN generasi bahasa: kalimat adalah templat deterministik yang diisi angka
dari hasil fungsi lain (summary, funnel, trend, interest, compare). Modul ini tidak
menghitung data baru dan dapat dimatikan tanpa merusak FR-01..FR-06.

Guardrail (dokumen revisi §5):
  - kata sebab ("karena", "disebabkan", "akibat", "penyebab", dst.) dilarang;
  - rekomendasi tindakan dilarang (hanya daftar pemeriksaan tetap pada INS-03,
    disimpan di field `checklist`, bukan di dalam kalimat);
  - tiap kalimat menyebut periode dan filter asal data;
  - bila data belum cukup, kalimat diganti "belum cukup data untuk disimpulkan";
  - tiap insight membawa `source` dan `numbers` agar bisa ditelusur ke angka sumber.

Format angka mengikuti locale id-ID: titik ribuan, koma desimal, tanggal "12 Okt 2026".

Fungsi murni dan deterministik. Hanya stdlib.
"""

from __future__ import annotations

import re
from datetime import date
from typing import Any

from ._common import ALGORITHM_VERSION, ORIGIN_LABELS, AnalyticsInputError, name_key, unique

INSUFFICIENT_PHRASE = "belum cukup data untuk disimpulkan"
CHECKLIST_INS03 = "Periksa harga, ketersediaan, dan catatan aktivitas"
MAX_ITEMS = 5  # batas jumlah kalimat per templat yang berulang (INS-03/04/05); mudah diubah

_BULAN = ("Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des")
_KATA_TERLARANG = re.compile(r"\b(karena\w*|disebabkan|menyebabkan|penyebab\w*|akibat\w*|sebab\w*)\b", re.IGNORECASE)

# Templat persis dari PRD §10.7 (INS-01..INS-06)
TEMPLATES = {
    "INS-01": "Penjualan tertinggi pada {tgl}: Rp {nilai}, tertinggi dari {n} hari pada periode ini.",
    "INS-02": "Penurunan terbesar: {tahap_a} ke {tahap_b}, {selisih} event ({persen}%).",
    "INS-03": "{produk}: {V} kali dilihat, {C} masuk keranjang, {P} pembelian ({r_b}%; interval 95%: {L}\u2013{U}%).",
    "INS-04": "Perubahan {objek} ({A} dibanding {B}) belum dapat dibedakan dari variasi acak (p = {p}).",
    "INS-05": "{produk} menunjukkan kecenderungan {arah} dengan bukti {kekuatan} selama {n} minggu (perubahan sekitar {r}%).",
    "INS-06": "{n_normal} transaksi normal + {n_sim} transaksi simulasi = {total} transaksi berhasil.",
}


# ---------------------------------------------------------------------------
# Format angka (id-ID)
# ---------------------------------------------------------------------------

def fmt_int(n: int | float) -> str:
    """1250000 -> '1.250.000'."""
    return f"{int(round(n)):,}".replace(",", ".")


def fmt_dec(x: float, digits: int = 1) -> str:
    """12.34 -> '12,3'."""
    return f"{x:.{digits}f}".replace(".", ",")


def fmt_p(p: float) -> str:
    """0.4944 -> '0,494'; di bawah 0,001 -> '< 0,001'."""
    return "< 0,001" if p < 0.001 else f"{p:.3f}".replace(".", ",")


def fmt_date(iso: str | date) -> str:
    """'2026-10-12' -> '12 Okt 2026'."""
    d = iso if isinstance(iso, date) else date.fromisoformat(iso)
    return f"{d.day} {_BULAN[d.month - 1]} {d.year}"


def _rentang(period: dict) -> str:
    awal, akhir = fmt_date(period["start"]), fmt_date(period["end"])
    return awal if awal == akhir else f"{awal} s.d. {akhir}"


def _konteks(periods: list[tuple[str, dict]], origin_filter: str) -> str:
    """Kalimat konteks: periode + filter asal data (wajib pada tiap insight)."""
    label = ORIGIN_LABELS.get(origin_filter, origin_filter)
    if len(periods) == 1:
        return f"Periode {_rentang(periods[0][1])}, asal data: {label}."
    bagian = " dibanding ".join(f"periode {nama} {_rentang(p)}" for nama, p in periods)
    return f"{bagian[0].upper() + bagian[1:]}, asal data: {label}."


def check_guardrails(text: str) -> list[str]:
    """Daftar kata terlarang (kata sebab) yang ditemukan pada `text`; kosong = lolos."""
    return [m.group(0) for m in _KATA_TERLARANG.finditer(text)]


def _insight(kode: str, kalimat: str, periods: list[tuple[str, dict]], origin_filter: str, *,
             fr: str, anl: str, numbers: dict, insufficient: bool = False, extra: dict | None = None) -> dict:
    teks = f"{kalimat} {_konteks(periods, origin_filter)}"
    item = {
        "id": kode,
        "kind": "insufficient" if insufficient else "fact",
        "text": teks,
        "periods": {nama: p for nama, p in periods},
        "origin_filter": origin_filter,
        "source": {"fr": fr, "anl": anl},
        "numbers": numbers,
        "guardrail": {"forbidden_words": check_guardrails(teks), "mentions_period_and_filter": True},
    }
    if extra:
        item.update(extra)
    return item


def _cukup(kode: str, topik: str, periods, origin_filter, *, fr: str, anl: str, numbers: dict | None = None) -> dict:
    return _insight(kode, f"{topik}: {INSUFFICIENT_PHRASE}.", periods, origin_filter,
                    fr=fr, anl=anl, numbers=numbers or {}, insufficient=True)


# ---------------------------------------------------------------------------
# Templat per insight
# ---------------------------------------------------------------------------

def ins_01(summary: dict) -> list[dict]:
    """Penjualan harian tertinggi dalam periode (sumber: summary.peak_day)."""
    per, asal = [("", summary["period"])], summary["origin_filter"]
    puncak = summary.get("peak_day")
    if not puncak:
        return [_cukup("INS-01", "Penjualan harian tertinggi", per, asal, fr="FR-01", anl="ANL-14")]
    kalimat = TEMPLATES["INS-01"].format(
        tgl=fmt_date(puncak["date"]), nilai=fmt_int(puncak["revenue_idr"]), n=puncak["n_days"])
    return [_insight("INS-01", kalimat, per, asal, fr="FR-01", anl="ANL-14", numbers=dict(puncak))]


def ins_02(funnel: dict) -> list[dict]:
    """Penurunan antar tahap funnel terbesar (sumber: funnel.largest_drop)."""
    per, asal = [("", funnel["period"])], funnel["origin_filter"]
    if funnel["stages"][0]["count"] == 0:
        return [_cukup("INS-02", "Penurunan antar tahap funnel", per, asal, fr="FR-03", anl="ANL-32")]
    drop = funnel.get("largest_drop")
    if not drop:
        return []  # ada data tetapi tidak ada tahap yang turun: tidak ada yang dilaporkan
    kalimat = TEMPLATES["INS-02"].format(
        tahap_a=drop["from_label"], tahap_b=drop["to_label"],
        selisih=fmt_int(drop["diff"]), persen=fmt_dec(drop["ratio"] * 100))
    return [_insight("INS-02", kalimat, per, asal, fr="FR-03", anl="ANL-32", numbers=dict(drop))]


def ins_03(interest: dict, max_items: int = MAX_ITEMS) -> list[dict]:
    """Produk berlabel "Perlu ditinjau" (sumber: interest.highlights)."""
    per, asal = [("", interest["period"])], interest["origin_filter"]
    aktif = [x for x in interest["products"] if x["is_active"]]
    if aktif and not any(x["sufficient_data"] for x in aktif):
        return [_cukup("INS-03", "Produk dengan minat tinggi dan pembelian rendah", per, asal, fr="FR-05", anl="ANL-53")]
    hasil = []
    for x in interest["highlights"][:max_items]:
        iv = x["interval"]
        kalimat = TEMPLATES["INS-03"].format(
            produk=x["name"], V=fmt_int(x["views"]), C=fmt_int(x["add_to_carts"]), P=fmt_int(x["purchases"]),
            r_b=fmt_dec(x["ratio"] * 100), L=fmt_dec(iv["low"] * 100), U=fmt_dec(iv["high"] * 100))
        hasil.append(_insight(
            "INS-03", kalimat, per, asal, fr="FR-05", anl="ANL-53",
            numbers={"product_id": x["product_id"], "views": x["views"], "add_to_carts": x["add_to_carts"],
                     "purchases": x["purchases"], "ratio": x["ratio"], "low": iv["low"], "high": iv["high"]},
            extra={"checklist": CHECKLIST_INS03}))
    return hasil


def _kandidat_ins04(compare: dict, alpha: float) -> tuple[list[tuple[str, dict]], int]:
    """(daftar (objek, metrik) dengan p >= alpha dan perubahan != 0, jumlah metrik yang bisa diuji)."""
    kandidat: list[tuple[str, dict]] = []
    teruji = 0
    total = compare["totals"]
    for kunci, objek in (("transactions", "jumlah transaksi"), ("units_sold", "total unit terjual"),
                         ("purchases", "total pembelian")):
        m = total.get(kunci)
        if m and m.get("available", True) and m.get("p") is not None:
            teruji += 1
            if m["p"] >= alpha and m["a"] != m["b"]:
                kandidat.append((objek, m))
    produk: list[tuple[float, tuple, str, dict]] = []
    for p in compare["by_product"]:
        for kunci, awal in (("units_sold", "unit terjual"), ("transactions", "transaksi"), ("purchases", "pembelian")):
            m = p[kunci]
            if m.get("p") is None:
                continue
            teruji += 1
            if m["p"] >= alpha and m["a"] != m["b"]:
                produk.append((-abs(m["per_day_change"]), name_key(p["name"]), f"{awal} {p['name']}", m))
    produk.sort(key=lambda t: (t[0], t[1], t[2]))
    kandidat.extend((objek, m) for _s, _n, objek, m in produk)
    return kandidat, teruji


def ins_04(compare: dict, max_items: int = MAX_ITEMS, alpha: float = 0.05) -> list[dict]:
    """Perubahan dengan p >= 0,05 (sumber: compare). Total lebih dulu, lalu produk menurut besar perubahan."""
    per = [("A", compare["period_a"]), ("B", compare["period_b"])]
    asal = compare["origin_filter"]
    kandidat, teruji = _kandidat_ins04(compare, alpha)
    if compare.get("empty") or teruji == 0:
        return [_cukup("INS-04", "Perubahan antar periode", per, asal, fr="FR-04", anl="ANL-43")]
    hasil = []
    for objek, m in kandidat[:max_items]:
        kalimat = TEMPLATES["INS-04"].format(objek=objek, A=fmt_int(m["a"]), B=fmt_int(m["b"]), p=fmt_p(m["p"]))
        hasil.append(_insight("INS-04", kalimat, per, asal, fr="FR-04", anl="ANL-43",
                              numbers={"a": m["a"], "b": m["b"], "p": m["p"], "objek": objek}))
    return hasil


def ins_05(trend: dict, max_items: int = MAX_ITEMS) -> list[dict]:
    """Tren FR-02 berlabel Naik/Turun (sumber: trend.products)."""
    per, asal = [("", trend["period"])], trend["origin_filter"]
    aktif = [x for x in trend["products"] if x["is_active"]]
    if aktif and all(x["label_code"] == "data_belum_cukup" for x in aktif):
        return [_cukup("INS-05", "Kecenderungan tren produk", per, asal, fr="FR-02", anl="ANL-22")]
    arah = [x for x in trend["products"] if x["label_code"] in ("naik", "turun")]
    arah.sort(key=lambda x: (-abs(x["r"]), name_key(x["name"]), x["product_id"]))
    hasil = []
    for x in arah[:max_items]:
        kalimat = TEMPLATES["INS-05"].format(
            produk=x["name"], arah=x["label"].lower(), kekuatan=x["strength"], n=x["n"],
            r=fmt_dec(abs(x["r"]) * 100))
        hasil.append(_insight(
            "INS-05", kalimat, per, asal, fr="FR-02", anl="ANL-22",
            numbers={"product_id": x["product_id"], "n": x["n"], "r": x["r"], "beta": x["beta"],
                     "p": x["p"], "q": x["q"]}))
    return hasil


def ins_06(origin_transactions: dict, period: dict) -> list[dict]:
    """Ringkasan asal data: transaksi normal + simulasi = total (sumber: dua pemanggilan summarize)."""
    per = [("", period)]
    n_normal = origin_transactions.get("normal")
    n_sim = origin_transactions.get("developer_simulation")
    if n_normal is None or n_sim is None:
        return [_cukup("INS-06", "Ringkasan asal data", per, "all", fr="FR-01", anl="ANL-11")]
    total = n_normal + n_sim
    kalimat = TEMPLATES["INS-06"].format(n_normal=fmt_int(n_normal), n_sim=fmt_int(n_sim), total=fmt_int(total))
    return [_insight("INS-06", kalimat, per, "all", fr="FR-01", anl="ANL-11",
                     numbers={"normal": n_normal, "developer_simulation": n_sim, "total": total})]


# ---------------------------------------------------------------------------
# Titik masuk
# ---------------------------------------------------------------------------

def build_insights(
    *,
    summary: dict | None = None,
    funnel: dict | None = None,
    trend: dict | None = None,
    interest: dict | None = None,
    compare: dict | None = None,
    origin_transactions: dict | None = None,
    period: dict | None = None,
    max_items: int = MAX_ITEMS,
    alpha: float = 0.05,
) -> dict:
    """Susun insight dari hasil fungsi lain; sumber yang tidak dikirim dilewati.

    origin_transactions (untuk INS-06): {'normal': n, 'developer_simulation': m} yang diperoleh
    pemanggil dari dua pemanggilan `summarize` (filter 'normal' dan 'developer_simulation');
    nilai None -> kalimat "belum cukup data". `period` (atau summary['period']) dipakai INS-06.
    Mengembalikan {'algorithm_version', 'insights', 'warnings'}.
    """
    insights: list[dict] = []
    if summary is not None:
        insights += ins_01(summary)
    if funnel is not None:
        insights += ins_02(funnel)
    if interest is not None:
        insights += ins_03(interest, max_items)
    if compare is not None:
        insights += ins_04(compare, max_items, alpha)
    if trend is not None:
        insights += ins_05(trend, max_items)
    if origin_transactions is not None:
        periode = period or (summary["period"] if summary else None)
        if periode is None:
            raise AnalyticsInputError("INS-06 membutuhkan `period` (atau `summary` yang memuat periode).")
        insights += ins_06(origin_transactions, periode)

    warnings: list[str] = []
    for x in insights:
        if x["guardrail"]["forbidden_words"]:
            warnings.append(
                f"{x['id']}: kalimat memuat kata terlarang {x['guardrail']['forbidden_words']} "
                "(kemungkinan dari nama produk); periksa sebelum ditampilkan."
            )
    if summary is not None and origin_transactions is not None:
        total_summary = summary["kpis"]["transactions"]["value"]
        ns, nn = origin_transactions.get("developer_simulation"), origin_transactions.get("normal")
        if (total_summary is not None and ns is not None and nn is not None and summary["origin_filter"] == "all"
                and total_summary != ns + nn):
            warnings.append("INS-06: normal + simulasi tidak sama dengan total transaksi pada summary (filter Semua).")
    return {"algorithm_version": ALGORITHM_VERSION, "insights": insights, "warnings": unique(warnings)}
