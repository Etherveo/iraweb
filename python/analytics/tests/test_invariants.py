"""Invarian lintas-FR (dokumen revisi §6): angka yang sama harus muncul konsisten di semua modul.

Data berasal dari simulator pesanan kecil (tests/helpers.py) yang mencatat kebenaran dasar
(jumlah pesanan unik, jumlah item, unit, penjualan) sebelum menurunkan `facts` dan `order_totals`.
"""

import pytest

from algorithms.category import analyze_category
from algorithms.compare import compare_periods
from algorithms.funnel import analyze_funnel
from algorithms.interest import analyze_interest
from algorithms.summary import summarize
from algorithms.trend import analyze_trend
from helpers import day, make_orders, orders_to_data, period_weeks, product

PRICES = {1: 3000, 2: 8000, 3: 15000, 4: 55000, 5: 9999}
CATS = {1: 1, 2: 1, 3: 2, 4: 3, 5: 2}
PERIOD = period_weeks(2)


@pytest.fixture(scope="module")
def dunia():
    orders = make_orders(n_days=14, per_day=6)
    facts, ot, truth = orders_to_data(orders, PRICES, CATS)
    prods = [product(pid, f"P{pid}", category_id=CATS[pid], price=PRICES[pid]) for pid in PRICES]
    cats = [{"id": c, "name": f"Kat {c}"} for c in (1, 2, 3)]
    return {"orders": orders, "facts": facts, "ot": ot, "truth": truth, "prods": prods, "cats": cats}


def test_data_uji_memang_memuat_pesanan_multi_produk_dan_multi_kategori(dunia):
    t = dunia["truth"]
    assert t["orders"] < t["items"]                                   # ada pesanan berisi >1 produk
    assert any(len({CATS[p] for p, _q in o["items"]}) > 1 for o in dunia["orders"])


def test_i1_pembelian_event_sama_dengan_item_paid_dan_tidak_ada_peringatan_invarian(dunia):
    s = summarize(dunia["facts"], dunia["prods"], PERIOD, order_totals=dunia["ot"])
    t = dunia["truth"]
    assert s["kpis"]["purchases"]["value"] == t["items"]
    assert sum(f["orders_paid"] for f in dunia["facts"]) == t["items"]   # orders_paid per produk = item paid
    assert not any("Invarian" in w or "tidak konsisten" in w for w in s["warnings"])


def test_i2_transaksi_adalah_pesanan_unik_bukan_jumlah_per_produk(dunia):
    s = summarize(dunia["facts"], dunia["prods"], PERIOD, order_totals=dunia["ot"])
    t = dunia["truth"]
    assert s["kpis"]["transactions"]["value"] == t["orders"]
    assert s["kpis"]["transactions"]["value"] < sum(f["orders_paid"] for f in dunia["facts"])
    assert s["transactions_differ_from_purchases"] is True
    # menjumlahkan orders_paid per produk akan salah: inilah cacat §11.7 lama
    assert sum(f["orders_paid"] for f in dunia["facts"]) != t["orders"]


def test_i3_penjualan_dan_unit_sama_di_fr01_dan_fr06(dunia):
    s = summarize(dunia["facts"], dunia["prods"], PERIOD, order_totals=dunia["ot"])
    c = analyze_category(dunia["facts"], dunia["prods"], PERIOD, categories=dunia["cats"], order_totals=dunia["ot"])
    t = dunia["truth"]
    assert s["kpis"]["revenue_idr"]["value"] == t["revenue"] == sum(k["revenue_idr"] for k in c["categories"])
    assert s["kpis"]["units_sold"]["value"] == t["units"] == sum(k["units_sold"] for k in c["categories"])
    assert sum(g["revenue_idr"] for g in c["price_groups"]) == t["revenue"]
    assert sum(k["share_revenue"]["tenths"] for k in c["categories"]) == 1000
    assert sum(k["share_units"]["tenths"] for k in c["categories"]) == 1000


def test_i3b_transaksi_per_kategori_tidak_aditif_tetapi_tiap_kategori_tidak_melebihi_total(dunia):
    c = analyze_category(dunia["facts"], dunia["prods"], PERIOD, categories=dunia["cats"], order_totals=dunia["ot"])
    total = dunia["truth"]["orders"]
    per_kat = [k["transactions"] for k in c["categories"]]
    assert all(0 <= n <= total for n in per_kat)
    assert sum(per_kat) > total                                    # satu pesanan bisa masuk dua kategori: diharapkan
    # kebenaran dasar per kategori dihitung dari pesanan
    for k in c["categories"]:
        benar = sum(1 for o in dunia["orders"] if any(CATS[p] == k["category_id"] for p, _q in o["items"]))
        assert k["transactions"] == benar


def test_i4_funnel_pembelian_sama_dengan_ringkasan_dan_minat(dunia):
    s = summarize(dunia["facts"], dunia["prods"], PERIOD)
    f = analyze_funnel(dunia["facts"], dunia["prods"], PERIOD)
    i = analyze_interest(dunia["facts"], dunia["prods"], PERIOD)
    items = dunia["truth"]["items"]
    assert f["stages"][3]["count"] == s["kpis"]["purchases"]["value"] == sum(p["purchases"] for p in i["products"]) == items
    assert f["stages"][0]["count"] == i["total_views"] == 2 * items
    assert f["anomalies"] == []


def test_i5_deret_harian_fr01_sama_dengan_jumlah_kategori_dan_deret_mingguan_fr02(dunia):
    s = summarize(dunia["facts"], dunia["prods"], PERIOD, granularity="day")
    c = analyze_category(dunia["facts"], dunia["prods"], PERIOD, categories=dunia["cats"], granularity="day")
    for idx, bucket in enumerate(s["sales_series"]["buckets"]):
        assert bucket["units_sold"] == sum(k["series"][idx]["units_sold"] for k in c["categories"])
        assert bucket["revenue_idr"] == sum(k["series"][idx]["revenue_idr"] for k in c["categories"])
    mingguan = summarize(dunia["facts"], dunia["prods"], PERIOD, granularity="week")["sales_series"]["buckets"]
    t = analyze_trend(dunia["facts"], dunia["prods"], PERIOD, product_ids=list(PRICES))
    for w, bucket in enumerate(mingguan):
        assert bucket["units_sold"] == sum(p["series"][w]["units"] for p in t["products"] if len(p["series"]) == 2)


def test_i6_dua_periode_berurutan_menjumlah_ke_total(dunia):
    pa, pb = period_weeks(1), period_weeks(1, start=day(7))
    fa = [f for f in dunia["facts"] if f["date"] < day(7).isoformat()]
    fb = [f for f in dunia["facts"] if f["date"] >= day(7).isoformat()]
    h = compare_periods(fa, fb, dunia["prods"], pa, pb)
    assert h["totals"]["revenue_idr"]["a"] + h["totals"]["revenue_idr"]["b"] == dunia["truth"]["revenue"]
    assert h["totals"]["units_sold"]["a"] + h["totals"]["units_sold"]["b"] == dunia["truth"]["units"]
    assert h["totals"]["purchases"]["a"] + h["totals"]["purchases"]["b"] == dunia["truth"]["items"]


def test_label_tren_tidak_bergantung_pada_pilihan_produk(dunia):
    semua = analyze_trend(dunia["facts"], dunia["prods"], PERIOD)
    for pid in PRICES:
        satu = analyze_trend(dunia["facts"], dunia["prods"], PERIOD, product_ids=[pid])
        a = next(p for p in semua["products"] if p["product_id"] == pid)
        b = satu["products"][0]
        assert (a["label"], a["q"], a["p"]) == (b["label"], b["q"], b["p"])


def test_facts_tidak_konsisten_dilaporkan(dunia):
    rusak = [dict(dunia["facts"][0], purchases=dunia["facts"][0]["purchases"] + 1)] + dunia["facts"][1:]
    s = summarize(rusak, dunia["prods"], PERIOD)
    assert any("Invarian data dilanggar" in w for w in s["warnings"])
    salah_ot = [dict(dunia["ot"][0], orders_paid=999)] + dunia["ot"][1:]
    s2 = summarize(dunia["facts"], dunia["prods"], PERIOD, order_totals=salah_ot)
    assert any("order_totals tidak konsisten" in w for w in s2["warnings"])
