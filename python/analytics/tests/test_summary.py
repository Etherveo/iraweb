import pytest

from algorithms._common import BELUM_DAPAT_DIHITUNG, AnalyticsInputError
from algorithms.summary import summarize
from helpers import MON, assert_pure, day, period_weeks, product, row

PERIOD = period_weeks(1)           # 5-11 Jan 2026
PREV_START = day(-7)


def _toko():
    prods = [product(i, n) for i, n in ((1, "Pensil"), (2, "Buku"), (3, "Penghapus"), (4, "Spidol"),
                                        (5, "Tinta"), (6, "Kertas"), (7, "Lem"))]
    prods.append(product(8, "Lama", active=False))
    facts = [row(day(0), 1, units=12, price=2000), row(day(1), 2, units=12, price=8000),
             row(day(2), 3, units=7, price=1000), row(day(3), 4, units=5, price=4000),
             row(day(4), 5, units=3, price=6000), row(day(5), 6, units=2, price=2500),
             row(day(5), 8, units=1, price=1000)]
    return prods, facts


def test_total_unit_penjualan_dan_pembelian():
    prods, facts = _toko()
    h = assert_pure(summarize, facts, prods, PERIOD)
    k = h["kpis"]
    assert k["revenue_idr"]["value"] == 12 * 2000 + 12 * 8000 + 7000 + 20000 + 18000 + 5000 + 1000
    assert k["units_sold"]["value"] == 42 and k["purchases"]["value"] == 42


def test_transaksi_dari_order_totals_bukan_jumlah_per_produk():
    prods, facts = _toko()
    ot = [{"date": day(i).isoformat(), "orders_paid": n} for i, n in ((0, 8), (1, 9), (2, 7))]
    h = summarize(facts, prods, PERIOD, order_totals=ot)
    assert h["kpis"]["transactions"]["value"] == 24 and h["kpis"]["purchases"]["value"] == 42
    assert h["transactions_differ_from_purchases"] is True


def test_tanpa_order_totals_transaksi_belum_dapat_dihitung_bukan_nol():
    prods, facts = _toko()
    h = summarize(facts, prods, PERIOD)
    t = h["kpis"]["transactions"]
    assert t["value"] is None and t["text"] == BELUM_DAPAT_DIHITUNG
    assert t["vs_previous"]["available"] is False
    assert h["transactions_differ_from_purchases"] is None
    assert any("transaksi belum dapat dihitung" in w.lower() for w in h["warnings"])
    assert h["kpis"]["units_sold"]["value"] == 42           # hitungan per produk tetap jalan


def test_top5_urutan_unit_lalu_penjualan_lalu_nama():
    prods, facts = _toko()
    h = summarize(facts, prods, PERIOD)
    # Buku & Pensil sama-sama 12 unit; penjualan Buku (96.000) > Pensil (24.000)
    assert [x["name"] for x in h["top_selling"]] == ["Buku", "Pensil", "Penghapus", "Spidol", "Tinta"]
    assert [x["rank"] for x in h["top_selling"]] == [1, 2, 3, 4, 5]


def test_top5_seri_unit_diurutkan_penjualan_menurun_bukan_nama():
    prods = [product(1, "Alfa"), product(2, "Zeta")]
    facts = [row(day(0), 1, units=5, price=1000), row(day(0), 2, units=5, price=10000)]
    h = summarize(facts, prods, PERIOD)
    assert [x["name"] for x in h["top_selling"]] == ["Zeta", "Alfa"]       # penjualan lebih besar menang atas nama


def test_top5_seri_penuh_diurutkan_nama_dan_kurang_dari_lima_ditampilkan_apa_adanya():
    prods = [product(1, "Zeta"), product(2, "Alfa"), product(3, "Mu")]
    facts = [row(day(0), 1, units=3, price=1000), row(day(0), 2, units=3, price=1000)]
    h = summarize(facts, prods, PERIOD)
    assert [x["name"] for x in h["top_selling"]] == ["Alfa", "Zeta"]       # hanya yang terjual, seri -> nama


def test_lima_terendah_dari_produk_aktif_termasuk_nol_dan_nonaktif_dikecualikan():
    prods, facts = _toko()
    h = summarize(facts, prods, PERIOD)
    assert [x["name"] for x in h["least_selling"]] == ["Lem", "Kertas", "Tinta", "Spidol", "Penghapus"]
    assert h["least_selling"][0]["units_sold"] == 0 and h["least_selling"][0]["units_text"] == "0 terjual"
    assert "Lama" not in [x["name"] for x in h["least_selling"]]           # nonaktif tanpa penjualan tidak ikut
    # seri unit sama (0) diurutkan menurut nama
    prods2 = [product(1, "Zebra"), product(2, "Apel"), product(3, "Mangga")]
    h2 = summarize([], prods2, PERIOD)
    assert [x["name"] for x in h2["least_selling"]] == ["Apel", "Mangga", "Zebra"]


def test_kpi_vs_periode_sebelumnya_dengan_uji():
    prods, facts = _toko()
    prev = [row(day(-7), 1, units=20, price=2000), row(day(-6), 2, units=10, price=8000)]
    ot = [{"date": day(0).isoformat(), "orders_paid": 30}]
    ot_prev = [{"date": day(-7).isoformat(), "orders_paid": 20}]
    h = assert_pure(summarize, facts, prods, PERIOD, previous_facts=prev, order_totals=ot, previous_order_totals=ot_prev)
    assert h["previous_period"] == {"start": day(-7).isoformat(), "end": day(-1).isoformat(), "days": 7}
    u = h["kpis"]["units_sold"]["vs_previous"]
    assert u["a"] == 42 and u["b"] == 30 and u["change"] == 12 and u["change_ratio"] == pytest.approx(0.4)
    assert u["p"] is not None and u["significance"] is not None
    rp = h["kpis"]["revenue_idr"]["vs_previous"]
    assert rp["descriptive_only"] is True and rp["p"] is None
    tr = h["kpis"]["transactions"]["vs_previous"]
    assert tr["a"] == 30 and tr["b"] == 20 and tr["p"] is not None


def test_tanpa_previous_facts_perubahan_belum_dapat_dihitung_dengan_warning():
    prods, facts = _toko()
    h = summarize(facts, prods, PERIOD)
    for kunci in ("revenue_idr", "units_sold", "purchases"):
        v = h["kpis"][kunci]["vs_previous"]
        assert v["available"] is False and v["text"] == BELUM_DAPAT_DIHITUNG
    assert any("previous_facts" in w for w in h["warnings"])


def test_previous_facts_tanpa_order_totals_sebelumnya():
    prods, facts = _toko()
    ot = [{"date": day(0).isoformat(), "orders_paid": 30}]
    h = summarize(facts, prods, PERIOD, order_totals=ot, previous_facts=[row(day(-7), 1, units=1)])
    assert h["kpis"]["transactions"]["vs_previous"]["available"] is False
    assert any("previous_order_totals" in w for w in h["warnings"])
    assert h["kpis"]["units_sold"]["vs_previous"]["available"] is True


def test_deret_penjualan_bucket_kosong_diisi_nol_dan_granularitas_otomatis():
    prods, facts = _toko()
    h = summarize(facts, prods, PERIOD)
    assert h["sales_series"]["granularity"] == "day" and len(h["sales_series"]["buckets"]) == 7
    assert h["sales_series"]["buckets"][6]["revenue_idr"] == 0 and h["sales_series"]["buckets"][6]["units_sold"] == 0
    assert sum(b["revenue_idr"] for b in h["sales_series"]["buckets"]) == h["kpis"]["revenue_idr"]["value"]

    panjang = {"start": MON.isoformat(), "end": day(89).isoformat()}                      # 90 hari -> mingguan
    assert summarize(facts, prods, panjang)["sales_series"]["granularity"] == "week"
    setahun = {"start": "2026-01-01", "end": "2026-12-31"}
    bulanan = summarize(facts, prods, setahun)["sales_series"]
    assert bulanan["granularity"] == "month" and len(bulanan["buckets"]) == 12
    assert bulanan["buckets"][0]["partial"] is False
    eksplisit = summarize(facts, prods, setahun, granularity="week")["sales_series"]
    assert eksplisit["granularity"] == "week" and eksplisit["buckets"][0]["partial"] is True    # 1 Jan = Kamis


def test_hari_puncak_seri_memilih_tanggal_paling_awal():
    prods = [product(1)]
    facts = [row(day(2), 1, units=5, price=1000), row(day(4), 1, units=5, price=1000), row(day(1), 1, units=1, price=1000)]
    h = summarize(facts, prods, PERIOD)
    assert h["peak_day"] == {"date": day(2).isoformat(), "revenue_idr": 5000, "n_days": 7}


def test_keadaan_kosong():
    h = summarize([], [product(1)], PERIOD)
    assert h["empty"] is True and h["peak_day"] is None and h["top_selling"] == []
    assert h["kpis"]["revenue_idr"]["value"] == 0
    assert any("Tidak ada data" in w for w in h["warnings"])


def test_catatan_stok_pada_data_simulasi():
    prods, facts = _toko()
    assert any("stok" in w for w in summarize(facts, prods, PERIOD, origin_filter="developer_simulation")["warnings"])
    assert not any("stok" in w for w in summarize(facts, prods, PERIOD, origin_filter="normal")["warnings"])


def test_input_tidak_valid():
    prods, facts = _toko()
    with pytest.raises(AnalyticsInputError):
        summarize(facts, prods, PERIOD, top_n=0)
    with pytest.raises(AnalyticsInputError):
        summarize(facts, prods, PERIOD, granularity="tahun")
    with pytest.raises(AnalyticsInputError):
        summarize([{"date": "2026-01-05", "product_id": 1, "units_sold": "banyak"}], prods, PERIOD)
