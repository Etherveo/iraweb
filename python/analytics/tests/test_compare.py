import time
from math import comb

import pytest

from algorithms._common import BELUM_DAPAT_DIHITUNG, AnalyticsInputError
from algorithms.compare import (NOT_SIGNIFICANT_LABEL, SIGNIFICANT_LABEL, binomial_two_sided_p, compare_metric,
                                compare_periods, significance_label)
from helpers import MON, assert_pure, day, period_weeks, product, row


def brute(k, n, p):
    pm = [comb(n, i) * p ** i * (1 - p) ** (n - i) for i in range(n + 1)]
    return sum(x for x in pm if x <= pm[k] * (1 + 1e-7))


# ------------------------------------------------------------------ binomial eksak

def test_golden_panjang_sama_42_vs_35():
    assert binomial_two_sided_p(42, 77, 0.5) == pytest.approx(0.494, abs=1e-3)


def test_golden_periode_berbeda_42_dalam_7_hari_vs_35_dalam_10_hari():
    p = binomial_two_sided_p(42, 77, 7 / 17)
    assert p == pytest.approx(0.0202, abs=1e-4)
    assert p < 0.05                                      # kesimpulan golden: berbeda


def test_binomial_sama_dengan_penjumlahan_brute_force():
    for n in (1, 2, 5, 10, 37, 77, 150):
        for p0 in (0.1, 0.3, 0.5, 7 / 17, 0.9):
            for k in range(n + 1):
                assert binomial_two_sided_p(k, n, p0) == pytest.approx(brute(k, n, p0), abs=1e-12)


def test_binomial_kasus_tepi():
    assert binomial_two_sided_p(0, 0, 0.5) == 1.0
    assert binomial_two_sided_p(0, 10, 0.5) == pytest.approx(2 * 0.5 ** 10)
    assert binomial_two_sided_p(10, 10, 0.5) == pytest.approx(2 * 0.5 ** 10)
    assert binomial_two_sided_p(5, 10, 0.5) == 1.0       # tepat di puncak
    with pytest.raises(AnalyticsInputError):
        binomial_two_sided_p(11, 10, 0.5)
    with pytest.raises(AnalyticsInputError):
        binomial_two_sided_p(1, 10, 1.0)


def test_binomial_n_besar_cepat_dan_stabil():
    t0 = time.perf_counter()
    assert binomial_two_sided_p(500_000, 1_000_000, 0.5) == 1.0
    assert 0 <= binomial_two_sided_p(502_000, 1_000_000, 0.5) < 0.0001
    assert binomial_two_sided_p(0, 1_000_000, 0.5) == 0.0 or binomial_two_sided_p(0, 1_000_000, 0.5) < 1e-300
    assert time.perf_counter() - t0 < 2.0


def test_label_signifikansi():
    assert significance_label(0.049) == SIGNIFICANT_LABEL
    assert significance_label(0.05) == NOT_SIGNIFICANT_LABEL
    assert significance_label(None) is None


# ------------------------------------------------------------------ compare_metric

def test_compare_metric_dasar_a_dibanding_b():
    m = compare_metric(120, 100, 7, 7)
    assert m["change"] == 20 and m["change_ratio"] == pytest.approx(0.2) and m["change_ratio_text"] is None
    assert not m["lengths_differ"]
    assert m["per_day_change_ratio"] == pytest.approx(0.2)


def test_basis_nol_belum_dapat_dihitung_bukan_tak_hingga():
    m = compare_metric(50, 0, 7, 7)
    assert m["change"] == 50 and m["change_ratio"] is None
    assert m["change_ratio_text"] == BELUM_DAPAT_DIHITUNG
    assert m["per_day_change_ratio"] is None and m["per_day_change_ratio_text"] == BELUM_DAPAT_DIHITUNG


def test_periode_berbeda_panjang_dinormalisasi_per_hari():
    m = compare_metric(42, 35, 7, 10)
    assert m["lengths_differ"]
    assert m["rate_a"] == pytest.approx(6.0) and m["rate_b"] == pytest.approx(3.5)
    assert m["change_ratio"] == pytest.approx(7 / 35)                  # persen mentah: +20%
    assert m["per_day_change_ratio"] == pytest.approx((6 - 3.5) / 3.5)  # per hari: +71,4%


def test_baseline_dapat_dibalik():
    m = compare_metric(120, 100, 7, 7, baseline="A")
    assert m["change"] == -20 and m["change_ratio"] == pytest.approx(-20 / 120)
    with pytest.raises(AnalyticsInputError):
        compare_metric(1, 1, 7, 7, baseline="C")


def test_uji_hanya_untuk_cacah_dan_tanpa_kejadian_belum_dapat_dihitung():
    m = compare_metric(0, 0, 7, 7, test=True)
    assert m["p"] is None and m["significance"] == BELUM_DAPAT_DIHITUNG
    with pytest.raises(AnalyticsInputError):
        compare_metric(10.5, 3, 7, 7, test=True)
    m2 = compare_metric(42, 35, 7, 10, test=True)
    assert m2["significant"] is True and m2["significance"] == SIGNIFICANT_LABEL


# ------------------------------------------------------------------ compare_periods

PA, PB = period_weeks(1), period_weeks(1, start=day(7))


def _data():
    prods = [product(1, "Pensil"), product(2, "Buku"), product(3, "Penghapus", active=False)]
    fa = [row(day(0), 1, units=30, price=2000), row(day(1), 2, units=10, price=8000),
          row(day(2), 3, units=5, price=1000)]
    fb = [row(day(7), 1, units=20, price=2000), row(day(8), 2, units=10, price=8000),
          row(day(9), 3, units=9, price=1000), row(day(10), 1, views=3)]
    return prods, fa, fb


def test_compare_periods_total_dan_per_produk():
    prods, fa, fb = _data()
    ota = [{"date": day(0).isoformat(), "orders_paid": 40}]
    otb = [{"date": day(7).isoformat(), "orders_paid": 30}]
    hasil = assert_pure(compare_periods, fa, fb, prods, PA, PB, order_totals_a=ota, order_totals_b=otb)
    t = hasil["totals"]
    assert t["units_sold"]["a"] == 45 and t["units_sold"]["b"] == 39
    assert t["transactions"]["a"] == 40 and t["transactions"]["b"] == 30        # dari order_totals
    assert t["revenue_idr"]["descriptive_only"] is True and t["revenue_idr"]["p"] is None
    assert t["units_sold"]["p"] is not None and t["purchases"]["p"] is not None
    pensil = next(p for p in hasil["by_product"] if p["product_id"] == 1)
    assert pensil["units_sold"]["a"] == 30 and pensil["units_sold"]["b"] == 20
    assert pensil["units_sold"]["significance"] in (SIGNIFICANT_LABEL, NOT_SIGNIFICANT_LABEL)
    assert hasil["caveats"] and "Poisson" in hasil["caveats"][0]


def test_tanpa_order_totals_transaksi_belum_dapat_dihitung_bukan_nol_bukan_jumlah_produk():
    prods, fa, fb = _data()
    hasil = compare_periods(fa, fb, prods, PA, PB)
    tr = hasil["totals"]["transactions"]
    assert tr["available"] is False and tr["text"] == BELUM_DAPAT_DIHITUNG
    assert tr["a"] is None and tr["b"] is None
    assert any("transaksi belum dapat dihitung" in w.lower() for w in hasil["warnings"])
    assert hasil["totals"]["units_sold"]["available"]                   # metrik lain tetap jalan
    assert hasil["by_product"][0]["transactions"]["a"] is not None       # per produk tetap ada


def test_peringatan_panjang_berbeda_tumpang_tindih_dan_kosong():
    prods, fa, fb = _data()
    h1 = compare_periods(fa, fb, prods, PA, period_weeks(2, start=day(7)))
    assert any("Panjang periode berbeda" in w for w in h1["warnings"])
    h2 = compare_periods(fa, fb, prods, PA, {"start": day(3).isoformat(), "end": day(9).isoformat()})
    assert any("tumpang tindih" in w for w in h2["warnings"])
    h3 = compare_periods([], [], prods, PA, PB)
    assert h3["empty"] is True and any("tidak memiliki data" in w for w in h3["warnings"])
    assert h3["totals"]["units_sold"]["significance"] == BELUM_DAPAT_DIHITUNG


def test_peringkat_perubahan_produk():
    prods, fa, fb = _data()
    hasil = compare_periods(fa, fb, prods, PA, PB)
    assert [x["product_id"] for x in hasil["biggest_increase"]] == [1]        # 30 -> 20 per 7 hari (A lebih tinggi)
    assert [x["product_id"] for x in hasil["biggest_decrease"]] == [3]        # 5 vs 9
    assert hasil["biggest_increase"][0]["per_day_change"] > 0 > hasil["biggest_decrease"][0]["per_day_change"]


def test_produk_hanya_views_tidak_masuk_perbandingan_penjualan():
    prods, fa, fb = _data()
    fb2 = fb + [row(day(11), 2, views=99)]
    ids = [p["product_id"] for p in compare_periods(fa, fb2, prods, PA, PB)["by_product"]]
    assert ids == [1, 2, 3]


def test_order_totals_tidak_konsisten_diberi_peringatan():
    prods, fa, fb = _data()
    salah = [{"date": day(0).isoformat(), "orders_paid": 999}]     # lebih besar dari Σ orders_paid per produk
    hasil = compare_periods(fa, fb, prods, PA, PB, order_totals_a=salah, order_totals_b=[])
    assert any("tidak konsisten" in w for w in hasil["warnings"])


def test_input_tidak_valid():
    prods, fa, fb = _data()
    with pytest.raises(AnalyticsInputError):
        compare_periods(fa, fb, prods, {"start": "x", "end": "y"}, PB)
    with pytest.raises(AnalyticsInputError):
        compare_periods(fa, fb, prods, PA, PB, baseline="Z")
