import pytest

from algorithms._common import BELUM_DAPAT_DIHITUNG, AnalyticsInputError
from algorithms.category import (PRICE_BINS, analyze_category, compare_categories, largest_remainder,
                                 price_group_of)
from helpers import NOISE, assert_pure, day, period_weeks, product, row, weekly_rows

PERIOD = period_weeks(2)


# ------------------------------------------------------------------ largest remainder

def test_largest_remainder_jumlah_tepat_1000():
    assert largest_remainder([1, 1, 1]) == [334, 333, 333]
    assert largest_remainder([50, 30, 20]) == [500, 300, 200]
    assert largest_remainder([1, 0, 0]) == [1000, 0, 0]
    assert largest_remainder([7]) == [1000]


def test_largest_remainder_sisa_terbesar_menang():
    # 2/3 -> 666,67 ; 1/3 -> 333,33 : sisa 1 unit ke bagian dengan sisa terbesar (yang 2/3)
    assert largest_remainder([2, 1]) == [667, 333]
    # sisa sama: indeks lebih kecil lebih dulu
    assert largest_remainder([1, 1, 1, 1, 1, 1, 1]) == [143, 143, 143, 143, 143, 143, 142]


@pytest.mark.parametrize("nilai", [
    [3, 5, 7, 11, 13], [999_999, 1, 1], [1] * 13, [123456, 654321, 111111, 98765], [0, 5, 0, 9, 1],
    [17, 17, 17], [10_000_000, 3, 7, 9_999_999],
])
def test_largest_remainder_selalu_tepat_100_persen(nilai):
    hasil = largest_remainder(nilai)
    assert sum(hasil) == 1000 and all(isinstance(x, int) and x >= 0 for x in hasil)
    # tiap bagian berbeda paling banyak 1 satuan dari porsi eksaknya
    for v, h in zip(nilai, hasil):
        assert abs(h - v * 1000 / sum(nilai)) < 1


def test_largest_remainder_total_nol_atau_negatif_galat():
    with pytest.raises(AnalyticsInputError):
        largest_remainder([0, 0])
    with pytest.raises(AnalyticsInputError):
        largest_remainder([5, -1])


# ------------------------------------------------------------------ binning harga

@pytest.mark.parametrize("harga,indeks", [(0, 0), (9_999, 0), (10_000, 1), (49_999, 1), (50_000, 2), (1_000_000, 2)])
def test_batas_binning_harga(harga, indeks):
    assert price_group_of(harga) == indeks


def test_label_binning_sesuai_spesifikasi():
    assert [b[2] for b in PRICE_BINS] == ["<Rp10.000", "Rp10.000\u201349.999", "\u2265Rp50.000"]


# ------------------------------------------------------------------ analyze_category

def _toko():
    prods = [product(1, "Pulpen", category_id=1, price=3000), product(2, "Buku", category_id=2, price=12000),
             product(3, "Tas", category_id=3, price=50000), product(4, "Kertas", category_id=4, price=9999),
             product(5, "Sepi", category_id=5, price=5000)]
    cats = [{"id": i, "name": n} for i, n in ((1, "Alat Tulis"), (2, "Buku"), (3, "Tas"), (4, "Kertas"), (5, "Lainnya"))]
    facts = [row(day(0), 1, units=10, price=3000), row(day(1), 2, units=3, price=12000),
             row(day(2), 3, units=1, price=50000), row(day(3), 4, units=7, price=9999)]
    return prods, cats, facts


def test_porsi_kategori_tepat_100_dan_kategori_nol_tetap_tampil():
    prods, cats, facts = _toko()
    h = assert_pure(analyze_category, facts, prods, PERIOD, categories=cats)
    assert len(h["categories"]) == 5
    assert sum(c["share_revenue"]["tenths"] for c in h["categories"]) == 1000
    assert sum(c["share_units"]["tenths"] for c in h["categories"]) == 1000
    assert sum(c["share_revenue"]["pct"] for c in h["categories"]) == pytest.approx(100.0)
    sepi = next(c for c in h["categories"] if c["category_id"] == 5)
    assert sepi["revenue_idr"] == 0 and sepi["has_sales"] is False and sepi["share_revenue"]["pct"] == 0.0
    assert h["categories"][-1]["category_id"] == 5                # kategori tanpa penjualan di urutan akhir
    assert h["totals"]["revenue_idr"] == 10 * 3000 + 3 * 12000 + 50000 + 7 * 9999


def test_tanpa_penjualan_sama_sekali_porsi_none():
    prods, cats, _ = _toko()
    h = analyze_category([], prods, PERIOD, categories=cats)
    assert all(c["share_revenue"] is None for c in h["categories"])
    assert h["totals"]["revenue_idr"] == 0


def test_satu_kategori_bersales_diberi_catatan():
    prods, cats, _ = _toko()
    h = analyze_category([row(day(0), 1, units=4)], prods, PERIOD, categories=cats)
    assert any("belum bermakna" in w for w in h["warnings"])
    assert next(c for c in h["categories"] if c["category_id"] == 1)["share_revenue"]["pct"] == 100.0


def test_transaksi_per_kategori_dari_by_category_boleh_melebihi_total():
    prods, cats, facts = _toko()
    ot = [{"date": day(0).isoformat(), "orders_paid": 10,
           "by_category": [{"category_id": 1, "orders_paid": 8}, {"category_id": 2, "orders_paid": 7}]}]
    h = analyze_category(facts, prods, PERIOD, categories=cats, order_totals=ot)
    k = {c["category_id"]: c["transactions"] for c in h["categories"]}
    assert k[1] == 8 and k[2] == 7 and k[3] == 0
    assert sum(v for v in k.values() if v is not None) == 15 > h["totals"]["transactions"] == 10
    assert not any("kategori" in w and "melebihi total" in w for w in h["warnings"])   # bukan galat


def test_transaksi_per_kategori_belum_dapat_dihitung_tanpa_order_totals_atau_by_category():
    prods, cats, facts = _toko()
    h = analyze_category(facts, prods, PERIOD, categories=cats)
    assert all(c["transactions"] is None and c["transactions_text"] == BELUM_DAPAT_DIHITUNG for c in h["categories"])
    assert h["totals"]["transactions"] is None
    ot = [{"date": day(0).isoformat(), "orders_paid": 5}]                   # tanpa by_category
    h2 = analyze_category(facts, prods, PERIOD, categories=cats, order_totals=ot)
    assert all(c["transactions"] is None for c in h2["categories"]) and h2["totals"]["transactions"] == 5
    assert any("tanpa by_category" in w for w in h2["warnings"])


def test_produk_tanpa_kategori_menjaga_total_tetap_cocok():
    prods, cats, facts = _toko()
    h = analyze_category(facts + [row(day(5), 999, units=2, price=1000)], prods, PERIOD, categories=cats)
    assert h["categories"][-1]["category_id"] is None and h["categories"][-1]["name"] == "Tanpa kategori"
    assert h["totals"]["units_sold"] == 10 + 3 + 1 + 7 + 2
    assert any("tidak punya kategori" in w for w in h["warnings"])
    assert h["categories"][-1]["trend"] is None


def test_kelompok_harga_binning_tetap():
    prods, cats, facts = _toko()
    h = analyze_category(facts, prods, PERIOD, categories=cats)
    g = {x["label"]: x for x in h["price_groups"]}
    assert g["<Rp10.000"]["product_count"] == 3          # 3000, 9999, 5000
    assert g["Rp10.000\u201349.999"]["product_count"] == 1
    assert g["\u2265Rp50.000"]["product_count"] == 1     # 50.000 tepat masuk kelompok tertinggi
    assert g["<Rp10.000"]["units_sold"] == 17 and g["\u2265Rp50.000"]["units_sold"] == 1
    assert sum(x["share_revenue"]["tenths"] for x in h["price_groups"]) == 1000
    assert g["<Rp10.000"]["transactions"] is None


def test_tren_per_kategori_bh_atas_kategori():
    weeks = 12
    prods = [product(i, f"P{i}", category_id=i) for i in (1, 2, 3)]
    cats = [{"id": i, "name": f"Kat {i}"} for i in (1, 2, 3)]
    naik = [round(100 * 1.10 ** i) + NOISE[i] for i in range(weeks)]
    facts = (weekly_rows(1, naik) + weekly_rows(2, [50 + NOISE[i] for i in range(weeks)])
             + weekly_rows(3, [round(300 * 0.92 ** i) + NOISE[i] for i in range(weeks)]))
    h = assert_pure(analyze_category, facts, prods, period_weeks(weeks), categories=cats)
    label = {c["category_id"]: c["trend"]["label"] for c in h["categories"]}
    assert label == {1: "Naik", 2: "Tidak ada tren jelas", 3: "Turun"}
    assert h["n_trend_tested"] == 3 and h["trend_granularity"] == "week"
    pendek = analyze_category(facts, prods, period_weeks(6), categories=cats)
    assert all(c["trend"]["label"] == "Data belum cukup" for c in pendek["categories"])
    assert any("tren kategori" in w for w in pendek["warnings"])


def test_deret_waktu_per_kategori_mengisi_bucket_kosong_nol():
    prods, cats, facts = _toko()
    h = analyze_category(facts, prods, PERIOD, categories=cats, granularity="day")
    seri = next(c for c in h["categories"] if c["category_id"] == 1)["series"]
    assert len(seri) == 14 and seri[0]["units_sold"] == 10 and sum(s["units_sold"] for s in seri[1:]) == 0


def test_price_bins_tidak_valid_ditolak():
    prods, cats, facts = _toko()
    with pytest.raises(AnalyticsInputError):
        analyze_category(facts, prods, PERIOD, price_bins=((None, 10, "a"), (20, None, "b")))   # celah
    with pytest.raises(AnalyticsInputError):
        analyze_category(facts, prods, PERIOD, price_bins=())


def test_compare_categories_matriks_dua_periode():
    prods, cats, _ = _toko()
    fa = [row(day(0), 1, units=10, price=3000), row(day(1), 2, units=2, price=12000)]
    fb = [row(day(7), 1, units=5, price=3000), row(day(8), 3, units=1, price=50000)]
    pa, pb = period_weeks(1), period_weeks(1, start=day(7))
    h = assert_pure(compare_categories, fa, fb, prods, pa, pb, categories=cats)
    baris = {c["category_id"]: c for c in h["categories"]}
    assert baris[1]["units_sold"]["change"] == 5 and baris[1]["units_sold"]["change_ratio"] == pytest.approx(1.0)
    # basis = B: kategori 2 hanya terjual di A (B = 0) -> persen belum dapat dihitung; kategori 3 hanya di B -> -100%
    assert baris[2]["units_sold"]["change_ratio"] is None
    assert baris[2]["units_sold"]["change_ratio_text"] == BELUM_DAPAT_DIHITUNG
    assert baris[3]["units_sold"]["change_ratio"] == pytest.approx(-1.0)
    assert baris[2]["revenue_idr"]["descriptive_only"] is True and baris[2]["revenue_idr"]["p"] is None
    assert len(h["categories"]) == 5
