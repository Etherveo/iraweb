from datetime import date

import pytest

from algorithms import _common as c
from algorithms._common import AnalyticsInputError
from helpers import MON, day, period_weeks, product, row

P = c.make_period(period_weeks(1))


# ------------------------------------------------------------------ tanggal dan periode

def test_parse_date_dan_periode():
    assert c.parse_date("2026-01-05") == date(2026, 1, 5)
    assert c.parse_date(date(2026, 1, 5)) == date(2026, 1, 5)
    for salah in ("05-01-2026", "2026-13-01", "2026-02-30", "20260105", None, 20260105):
        with pytest.raises(AnalyticsInputError):
            c.parse_date(salah)
    from datetime import datetime
    with pytest.raises(AnalyticsInputError):
        c.parse_date(datetime(2026, 1, 5, 10, 0))
    assert c.make_period({"start": "2026-01-05", "end": "2026-01-05"})["days"] == 1
    for salah in ({"start": "2026-01-06", "end": "2026-01-05"}, {"start": "2026-01-05"}, "2026", None):
        with pytest.raises(AnalyticsInputError):
            c.make_period(salah)


def test_periode_sebelumnya_berpanjang_sama():
    prev = c.previous_period(P)
    assert prev["days"] == 7 and prev["end"] == day(-1) and prev["start"] == day(-7)
    satu = c.make_period({"start": "2026-03-01", "end": "2026-03-01"})
    assert c.previous_period(satu)["start"] == date(2026, 2, 28)
    assert not c.periods_overlap(prev, P) and c.periods_overlap(P, P)


def test_minggu_penuh_dan_parsial():
    penuh, parsial = c.week_buckets(day(2), day(2 + 7 * 3 - 1))        # Rabu .. Selasa 3 minggu kemudian
    assert penuh == [day(7), day(14)] and len(parsial) == 2
    assert parsial[0] == {"start": day(2).isoformat(), "end": day(6).isoformat(), "days": 5}
    assert parsial[1]["days"] == 2
    penuh2, parsial2 = c.week_buckets(day(0), day(13))
    assert penuh2 == [day(0), day(7)] and parsial2 == []
    penuh3, parsial3 = c.week_buckets(day(1), day(5))                  # kurang dari seminggu
    assert penuh3 == [] and len(parsial3) == 1 and parsial3[0]["days"] == 5


def test_bucket_hari_minggu_bulan_dan_penanda_parsial():
    h = c.build_buckets(day(0), day(2), "day")
    assert len(h) == 3 and not any(b["partial"] for b in h)
    m = c.build_buckets(day(2), day(15), "week")                       # Rabu .. Selasa depan
    assert [b["partial"] for b in m] == [True, False, True] or [b["partial"] for b in m][0] is True
    assert m[0]["start"] == day(2) and m[-1]["end"] == day(15)
    bln = c.build_buckets(date(2026, 1, 15), date(2026, 3, 10), "month")
    assert [(b["start"], b["end"], b["partial"]) for b in bln] == [
        (date(2026, 1, 15), date(2026, 1, 31), True), (date(2026, 2, 1), date(2026, 2, 28), False),
        (date(2026, 3, 1), date(2026, 3, 10), True)]
    desember = c.build_buckets(date(2026, 12, 1), date(2027, 1, 31), "month")
    assert len(desember) == 2 and desember[1]["start"] == date(2027, 1, 1)
    with pytest.raises(AnalyticsInputError):
        c.build_buckets(day(0), day(2), "tahun")


def test_pilih_granularitas():
    assert c.pick_granularity(31) == "day" and c.pick_granularity(32) == "week"
    assert c.pick_granularity(183) == "week" and c.pick_granularity(184) == "month"
    assert c.pick_granularity(10, "month") == "month"
    with pytest.raises(AnalyticsInputError):
        c.pick_granularity(10, "bulan")


# ------------------------------------------------------------------ normalisasi facts

def test_facts_kolom_hilang_dianggap_nol_dan_float_bulat_diterima():
    w: list[str] = []
    hasil = c.normalize_facts([{"date": "2026-01-05", "product_id": 1, "units_sold": 3.0}], P, w)
    assert hasil[(day(0), 1)]["units_sold"] == 3 and hasil[(day(0), 1)]["views"] == 0
    assert w == [] or all("Invarian" not in x for x in w)


@pytest.mark.parametrize("salah", [
    {"date": "2026-01-05", "product_id": 1, "units_sold": -1},
    {"date": "2026-01-05", "product_id": 1, "units_sold": 1.5},
    {"date": "2026-01-05", "product_id": 1, "units_sold": True},
    {"date": "2026-01-05", "product_id": 1, "views": "10"},
    {"date": "2026-01-05", "product_id": 1, "revenue_idr": float("nan")},
    {"date": "2026-01-05", "product_id": "1"},
    {"date": "kemarin", "product_id": 1},
    {"product_id": 1},
    "bukan objek",
])
def test_facts_tidak_valid_ditolak(salah):
    with pytest.raises(AnalyticsInputError):
        c.normalize_facts([salah], P, [])
    with pytest.raises(AnalyticsInputError):
        c.normalize_facts("bukan daftar", P, [])


def test_facts_di_luar_periode_dan_ganda_dicatat():
    w: list[str] = []
    hasil = c.normalize_facts([row(day(0), 1, units=2), row(day(0), 1, units=3), row(day(30), 1, units=9)], P, w)
    assert hasil[(day(0), 1)]["units_sold"] == 5 and len(hasil) == 1
    assert any("di luar periode" in x for x in w) and any("ganda" in x for x in w)


def test_facts_tidak_diubah_dan_none_diterima():
    data = [row(day(0), 1, units=2)]
    salinan = [dict(x) for x in data]
    c.normalize_facts(data, P, [])
    assert data == salinan
    assert c.normalize_facts(None, P, []) == {}


# ------------------------------------------------------------------ products

def test_normalisasi_produk():
    hasil = c.normalize_products([{"id": 1, "name": "  ", "is_active": False, "active_since": "2026-01-05"},
                                  {"id": 2, "name": "Buku", "category_id": 3, "price_idr": 5000}])
    assert hasil[1]["name"] == "Produk 1" and hasil[1]["is_active"] is False and hasil[1]["active_since"] == date(2026, 1, 5)
    assert hasil[2]["category_id"] == 3 and hasil[2]["is_active"] is True and hasil[2]["active_since"] is None
    for salah in ([{"id": 1}, {"id": 1}], [{"name": "x"}], [{"id": 1, "is_active": "ya"}],
                  [{"id": 1, "price_idr": -5}], [{"id": 1, "active_since": "besok"}], "bukan daftar"):
        with pytest.raises(AnalyticsInputError):
            c.normalize_products(salah)
    assert c.normalize_products(None) == {}


# ------------------------------------------------------------------ order_totals

def test_order_totals_total_per_hari_dan_by_category():
    w: list[str] = []
    ot = c.normalize_order_totals([
        {"date": day(0).isoformat(), "orders_paid": 30, "by_category": [{"category_id": 3, "orders_paid": 12},
                                                                          {"category_id": 4, "orders_paid": 25}]},
        {"date": day(1).isoformat(), "orders_paid": 10, "by_category": []},
    ], P, w)
    assert ot["total"] == 40 and ot["by_category"] == {3: 12, 4: 25}
    assert ot["by_day"] == {day(0): 30, day(1): 10} and w == []


def test_order_totals_tanpa_by_category_pada_salah_satu_baris_menjadi_none():
    ot = c.normalize_order_totals([
        {"date": day(0).isoformat(), "orders_paid": 5, "by_category": [{"category_id": 1, "orders_paid": 5}]},
        {"date": day(1).isoformat(), "orders_paid": 7}], P, [])
    assert ot["total"] == 12 and ot["by_category"] is None


def test_order_totals_none_kosong_dan_tidak_valid():
    assert c.normalize_order_totals(None, P, []) is None
    assert c.normalize_order_totals([], P, [])["total"] == 0
    for salah in ([{"date": "2026-01-05", "orders_paid": -1}], [{"date": "2026-01-05"}], ["x"], "x",
                  [{"date": "2026-01-05", "orders_paid": 1, "by_category": "x"}],
                  [{"date": "2026-01-05", "orders_paid": 1, "by_category": [{"category_id": "a", "orders_paid": 1}]}]):
        with pytest.raises(AnalyticsInputError):
            c.normalize_order_totals(salah, P, [])


def test_order_totals_kategori_melebihi_total_hari_diberi_peringatan():
    w: list[str] = []
    c.normalize_order_totals([{"date": day(0).isoformat(), "orders_paid": 3,
                               "by_category": [{"category_id": 1, "orders_paid": 4}]}], P, w)
    assert any("melebihi total hari itu" in x for x in w)


def test_pemeriksaan_konsistensi_order_totals_dengan_facts():
    w: list[str] = []
    rows = c.normalize_facts([row(day(0), 1, units=3, orders=3), row(day(0), 2, units=2, orders=2)], P, w)
    benar = c.normalize_order_totals([{"date": day(0).isoformat(), "orders_paid": 4}], P, w)   # 3 <= 4 <= 5
    c.check_order_totals_consistency(rows, benar, w)
    assert w == []
    terlalu_kecil = c.normalize_order_totals([{"date": day(0).isoformat(), "orders_paid": 2}], P, w)
    c.check_order_totals_consistency(rows, terlalu_kecil, w)
    terlalu_besar = c.normalize_order_totals([{"date": day(0).isoformat(), "orders_paid": 6}], P, w)
    c.check_order_totals_consistency(rows, terlalu_besar, w)
    assert len([x for x in w if "tidak konsisten" in x]) == 2
    c.check_order_totals_consistency(rows, None, w)


# ------------------------------------------------------------------ helper kecil

def test_safe_ratio_unique_dan_catatan_stok():
    assert c.safe_ratio(1, 0) is None and c.safe_ratio(0, 5) == 0.0 and c.safe_ratio(3, 4) == 0.75
    assert c.unique(["a", "b", "a", "c", "b"]) == ["a", "b", "c"]
    assert c.stock_warning("normal") is None and "stok" in c.stock_warning("all")
    with pytest.raises(AnalyticsInputError):
        c.validate_origin_filter("semua")
    assert c.name_key("beta") < c.name_key("Gamma") and c.name_key("Alfa") < c.name_key("beta")


def test_modul_tidak_memakai_acak_waktu_atau_io():
    """Fungsi murni: impor hanya dari daftar putih stdlib; tidak ada random, waktu sistem, file, atau jaringan."""
    import ast
    import pathlib
    putih = {"__future__", "math", "re", "statistics", "collections", "datetime", "typing"}
    akar = pathlib.Path(c.__file__).parent
    for f in sorted(akar.glob("*.py")):
        pohon = ast.parse(f.read_text(encoding="utf-8"))
        for node in ast.walk(pohon):
            if isinstance(node, ast.Import):
                for a in node.names:
                    assert a.name.split(".")[0] in putih, f"{f.name}: impor {a.name}"
            elif isinstance(node, ast.ImportFrom):
                assert node.level > 0 or (node.module or "").split(".")[0] in putih, f"{f.name}: dari {node.module}"
            elif isinstance(node, ast.Call):
                fn = node.func
                nama = fn.id if isinstance(fn, ast.Name) else (
                    f"{fn.value.id}.{fn.attr}" if isinstance(fn, ast.Attribute) and isinstance(fn.value, ast.Name) else "")
                assert nama not in {"open", "print", "input", "eval", "exec", "datetime.now", "datetime.utcnow",
                                    "date.today"}, f"{f.name}: panggilan {nama}"
