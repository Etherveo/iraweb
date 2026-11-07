from datetime import datetime, timedelta, timezone

import pytest

from algorithms._common import STRIP, AnalyticsInputError
from algorithms.funnel import analyze_funnel, build_funnel, dedup_view_events, dedup_views
from helpers import assert_pure, day, period_weeks, product, row

PERIOD = period_weeks(1)


def hitung(v, c, b, p):
    return {"views": v, "add_to_carts": c, "bookings": b, "purchases": p}


def test_rasio_dan_penurunan_terbesar():
    f = build_funnel(hitung(1000, 400, 120, 90))
    rasio_prev = [s["ratio_prev"] for s in f["stages"]]
    assert rasio_prev[0] is None
    assert rasio_prev[1:] == pytest.approx([0.4, 0.3, 0.75])
    assert [s["ratio_view"] for s in f["stages"]] == pytest.approx([1.0, 0.4, 0.12, 0.09])
    d = f["largest_drop"]
    assert (d["from_stage"], d["to_stage"], d["diff"]) == ("view", "add_to_cart", 600)
    assert d["ratio"] == pytest.approx(0.6) and d["from_label"] == "Dilihat" and d["to_label"] == "Keranjang"
    assert f["anomalies"] == []


def test_pembagi_nol_menjadi_none_dan_strip_bukan_error():
    f = build_funnel(hitung(0, 0, 0, 0))
    assert all(s["ratio_view"] is None for s in f["stages"])
    assert [s["ratio_prev_text"] for s in f["stages"][1:]] == [STRIP] * 3
    assert f["largest_drop"] is None
    g = build_funnel(hitung(10, 0, 0, 0))
    assert g["stages"][1]["ratio_prev"] == 0.0 and g["stages"][2]["ratio_prev"] is None
    assert g["stages"][2]["ratio_prev_text"] == STRIP


def test_tahap_lebih_besar_ditandai_bukan_disembunyikan():
    f = build_funnel(hitung(100, 40, 50, 30))             # booking (50) > keranjang (40)
    assert [a["to_stage"] for a in f["anomalies"]] == ["booking"]
    booking = next(s for s in f["stages"] if s["stage"] == "booking")
    assert booking["anomaly"] is True and booking["ratio_prev"] == pytest.approx(1.25)   # rasio tetap tampil
    assert f["largest_drop"]["to_stage"] == "add_to_cart"


def test_penurunan_terbesar_seri_memilih_transisi_lebih_awal():
    f = build_funnel(hitung(100, 60, 20, 0))             # 40, 40, 20 -> seri dua pertama
    assert f["largest_drop"]["to_stage"] == "add_to_cart"


def test_analyze_funnel_total_per_produk_dan_filter():
    prods = [product(1, "Buku", category_id=1), product(2, "Pensil", category_id=2)]
    facts = [row(day(0), 1, units=5, views=100, carts=40, bookings=10), row(day(1), 1, views=50),
             row(day(2), 2, units=2, views=30, carts=5, bookings=2, cancels=1)]
    h = assert_pure(analyze_funnel, facts, prods, PERIOD)
    assert [s["count"] for s in h["stages"]] == [180, 45, 12, 7]
    assert h["cancels"] == 1 and h["bookings_not_purchased"] == 5
    assert [p["product_id"] for p in h["per_product"]] == [1, 2]          # urut views menurun
    sub = analyze_funnel(facts, prods, PERIOD, category_id=2)
    assert [s["count"] for s in sub["stages"]] == [30, 5, 2, 2]
    one = analyze_funnel(facts, prods, PERIOD, product_ids=[1])
    assert one["stages"][0]["count"] == 150
    with pytest.raises(AnalyticsInputError):
        analyze_funnel(facts, prods, PERIOD, product_ids=[404])


def test_keadaan_kosong_dan_catatan_batas_data():
    h = analyze_funnel([], [], PERIOD)
    assert any("Tidak ada event" in w for w in h["warnings"])
    assert any("tidak bisa dipilah" in w for w in h["warnings"])
    assert all(s["count"] == 0 for s in h["stages"]) and h["largest_drop"] is None


# ------------------------------------------------------------------ dedup_views (khusus tes)

T0 = datetime(2026, 1, 5, 10, 0, tzinfo=timezone.utc)


def ev(ref, pid, menit, base=T0):
    return {"user_ref": ref, "product_id": pid, "occurred_at": base + timedelta(minutes=menit)}


def test_dedup_30_menit_dihitung_dari_view_tercatat_terakhir():
    events = [ev("anon-a", 1, 0), ev("anon-a", 1, 10), ev("anon-a", 1, 29),   # tidak dicatat
              ev("anon-a", 1, 30),                                             # tepat 30 menit: dicatat
              ev("anon-a", 1, 50),                                             # 20 menit dari 30: tidak
              ev("anon-a", 1, 61)]                                             # 31 menit dari 30: dicatat
    assert dedup_views(events) == 3


def test_dedup_per_pengguna_dan_per_produk_dan_urutan_masukan():
    events = [ev("anon-a", 1, 5), ev("anon-b", 1, 5), ev("anon-a", 2, 5), ev("anon-a", 1, 0)]
    assert dedup_views(events) == 3                      # a/p1 (0 dan 5 digabung), b/p1, a/p2
    assert dedup_views(list(reversed(events))) == 3
    kept = dedup_view_events(events)
    assert [e["occurred_at"] for e in kept if e["user_ref"] == "anon-a" and e["product_id"] == 1] == [T0 + timedelta(minutes=0)]


def test_dedup_waktu_iso_dan_jendela_lain():
    events = [{"user_ref": "anon-a", "product_id": 1, "occurred_at": "2026-01-05T10:00:00Z"},
              {"user_ref": "anon-a", "product_id": 1, "occurred_at": "2026-01-05T10:20:00+00:00"}]
    assert dedup_views(events) == 1
    assert dedup_views(events, window_minutes=10) == 2
    assert dedup_views([]) == 0


def test_dedup_menolak_id_akun_asli():
    with pytest.raises(AnalyticsInputError, match="anonim"):
        dedup_views([{"user_ref": 12345, "product_id": 1, "occurred_at": T0}])
    with pytest.raises(AnalyticsInputError, match="anonim"):
        dedup_views([{"user_ref": "12345", "product_id": 1, "occurred_at": T0}])
    with pytest.raises(AnalyticsInputError, match="pribadi"):
        dedup_views([{"user_ref": "anon-a", "account_id": 7, "product_id": 1, "occurred_at": T0}])
    with pytest.raises(AnalyticsInputError):
        dedup_views([{"user_ref": "anon-a", "product_id": 1, "occurred_at": "kemarin"}])
