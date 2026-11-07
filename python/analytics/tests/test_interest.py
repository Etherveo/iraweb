import pytest

from algorithms.interest import REVIEW, INSUFFICIENT, NOT_HIGHLIGHTED, analyze_interest, wilson_interval
from helpers import assert_pure, day, period_weeks, product, row

PERIOD = period_weeks(1)


def pct(x):
    return round(x * 100, 2)


def test_wilson_golden():
    lo, hi = wilson_interval(42, 320)
    assert (pct(lo), pct(hi)) == (9.86, 17.27)
    lo, hi = wilson_interval(0, 50)
    assert lo == 0.0 and pct(hi) == 7.13
    lo, hi = wilson_interval(0, 20)
    assert lo == 0.0 and pct(hi) == 16.11


def test_wilson_batas_dan_nilai_tak_valid():
    assert wilson_interval(0, 0) is None
    assert wilson_interval(5, 4) is None
    lo, hi = wilson_interval(10, 10)
    assert hi == pytest.approx(1.0) and 0 < lo < 1
    lo, hi = wilson_interval(5, 100, confidence=0.99)
    l95, h95 = wilson_interval(5, 100)
    assert lo < l95 and hi > h95                     # interval 99% lebih lebar


def build(daftar):
    """daftar: [(pid, V, C, B, P, kwargs_produk)] -> (products, facts)."""
    prods, facts = [], []
    for pid, V, C, B, P, kw in daftar:
        prods.append(product(pid, kw.pop("name", f"P{pid}"), **kw))
        facts.append(row(day(0), pid, units=P, views=V, carts=C, bookings=B))
    return prods, facts


def status_of(hasil, pid):
    return next(p for p in hasil["products"] if p["product_id"] == pid)


def test_sorotan_perlu_ditinjau_dan_batas_data():
    prods, facts = build([
        (1, 50, 30, 10, 0, {}),        # V>=20, rasio 0%, U=7,13% <= 10%  -> Perlu ditinjau
        (2, 20, 10, 5, 0, {}),         # rasio 0% tetapi U=16,11% > 10%   -> tidak disorot
        (3, 19, 15, 5, 0, {}),         # V < 20                           -> Data belum cukup
        (4, 200, 90, 30, 8, {}),       # rasio 4% <= 5%, U ~ 7,7%         -> Perlu ditinjau
        (5, 100, 50, 20, 5, {}),       # rasio 5%, U ~ 11,2% > 10%        -> tidak disorot
    ])
    h = assert_pure(analyze_interest, facts, prods, PERIOD)
    s = {p["product_id"]: p["status"] for p in h["products"]}
    assert s == {1: REVIEW, 2: NOT_HIGHLIGHTED, 3: INSUFFICIENT, 4: REVIEW, 5: NOT_HIGHLIGHTED}
    # urut selisih (C - P) menurun: produk 4 (90-8=82) mendahului produk 1 (30-0=30)
    assert [x["product_id"] for x in h["highlights"]] == [4, 1]
    p1 = status_of(h, 1)
    assert (pct(p1["interval"]["low"]), pct(p1["interval"]["high"])) == (0.0, 7.13)
    assert p1["interval"]["level"] == 0.95 and p1["ratio"] == 0.0


def test_sorotan_seri_diurutkan_menurut_nama():
    prods, facts = build([(1, 60, 30, 5, 0, {"name": "Zebra"}), (2, 60, 30, 5, 0, {"name": "Apel"})])
    h = analyze_interest(facts, prods, PERIOD)
    assert [x["name"] for x in h["highlights"]] == ["Apel", "Zebra"]


def test_pembanding_rasio_dan_pembelian_minimum_maksimal_tiga():
    prods, facts = build([
        (1, 100, 50, 30, 12, {}),      # 12%, P=12
        (2, 60, 40, 20, 9, {}),        # 15% tetapi P=9 < 10 -> bukan
        (3, 30, 20, 18, 15, {}),       # 50%, P=15
        (4, 100, 50, 30, 25, {}),      # 25%, P=25
        (5, 80, 40, 20, 10, {}),       # 12,5%, P=10 (tepat batas)
        (6, 500, 100, 20, 30, {}),     # 6% < 10% -> bukan
    ])
    h = analyze_interest(facts, prods, PERIOD)
    assert [x["product_id"] for x in h["comparators"]] == [4, 3, 1]        # P menurun, maks 3
    assert status_of(h, 5)["is_comparator"] and not status_of(h, 2)["is_comparator"]
    assert not status_of(h, 6)["is_comparator"]


def test_produk_nonaktif_tidak_disorot_dan_tidak_jadi_pembanding():
    prods, facts = build([(1, 100, 50, 10, 0, {"active": False}), (2, 100, 50, 30, 40, {"active": False})])
    h = analyze_interest(facts, prods, PERIOD)
    assert h["highlights"] == [] and h["comparators"] == []
    assert status_of(h, 1)["is_active"] is False


def test_pembagi_nol_dan_anomali_pembelian_melebihi_dilihat():
    prods, facts = build([(1, 0, 0, 0, 0, {}), (2, 5, 5, 5, 8, {})])
    h = analyze_interest(facts + [row(day(1), 1, views=0)], prods, PERIOD)
    p1, p2 = status_of(h, 1), status_of(h, 2)
    assert p1["ratio"] is None and p1["ratio_text"] == "\u2014" and p1["interval"] is None
    assert p2["anomaly"] and p2["interval"] is None and "note" in p2
    assert any("melebihi" in w for w in h["warnings"])


def test_ringkasan_minat_dan_filter_kategori():
    prods = [product(1, "A", category_id=1), product(2, "B", category_id=2)]
    facts = [row(day(0), 1, views=40, units=2), row(day(1), 2, views=60, units=1), row(day(2), 2)]
    h = analyze_interest(facts, prods, PERIOD)
    assert h["products_viewed"] == 2 and h["total_views"] == 100
    k = analyze_interest(facts, prods, PERIOD, category_id=2)
    assert [p["product_id"] for p in k["products"]] == [2] and k["total_views"] == 60


def test_catatan_stok_hanya_bila_ada_sorotan_dan_data_simulasi():
    prods, facts = build([(1, 60, 30, 5, 0, {})])
    assert any("stok" in w for w in analyze_interest(facts, prods, PERIOD)["warnings"])
    assert any("stok" in w for w in analyze_interest(facts, prods, PERIOD, origin_filter="developer_simulation")["warnings"])
    assert not any("stok" in w for w in analyze_interest(facts, prods, PERIOD, origin_filter="normal")["warnings"])
    p2, f2 = build([(1, 60, 30, 5, 30, {})])
    assert not any("stok" in w for w in analyze_interest(f2, p2, PERIOD)["warnings"])


def test_keadaan_kosong():
    h = analyze_interest([], [product(1)], PERIOD)
    assert h["products_viewed"] == 0 and h["highlights"] == []
    assert any("Tidak ada aktivitas" in w for w in h["warnings"])
