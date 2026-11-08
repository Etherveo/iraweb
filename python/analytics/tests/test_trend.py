import math
from datetime import timedelta

import pytest

from algorithms._common import AnalyticsInputError
from algorithms.trend import analyze_trend, benjamini_hochberg, mann_kendall, sen_slope
from helpers import (MON, NOISE, assert_pure, by_id, day, filler_products, period_weeks, product, row,
                     trend_inputs, weekly_rows)

NAIK = [10, 12, 9, 14, 13, 15, 14, 18, 16, 19, 21, 20]
DATAR = [12, 10, 13, 11, 12, 10, 11, 12, 13, 11, 12, 11]


def label_of(target_weekly, **kw):
    prods, facts, period = trend_inputs(target_weekly, **kw.pop("inputs", {}))
    hasil = assert_pure(analyze_trend, facts, prods, period, **kw)
    return by_id(hasil["products"], 1), hasil


# ------------------------------------------------------------------ golden statistik

def test_mann_kendall_golden_naik():
    m = mann_kendall(NAIK)
    assert m["s"] == 53
    assert m["var"] == pytest.approx(211.6667, abs=1e-3)   # 12*11*29/18 - koreksi satu ikatan (14)
    assert m["z"] == pytest.approx(3.574, abs=1e-3)
    assert m["p"] == pytest.approx(0.0004, abs=1e-4)
    assert sen_slope(NAIK) == pytest.approx(1.0)


def test_mann_kendall_golden_datar():
    m = mann_kendall(DATAR)
    assert m["s"] == 2
    assert m["var"] == pytest.approx(193.333, abs=1e-3)
    assert m["z"] == pytest.approx(0.072, abs=1e-3)
    assert m["p"] == pytest.approx(0.943, abs=1e-3)


def test_mann_kendall_ties_hand_computed():
    # [1,1,2,2,3]: Var = (5*4*15 - 2*1*9 - 2*1*9) / 18 = 264/18
    assert mann_kendall([1, 1, 2, 2, 3])["var"] == pytest.approx(264 / 18)


def test_mann_kendall_extremes_and_symmetry():
    n = 9
    naik = list(range(n))
    assert mann_kendall(naik)["s"] == n * (n - 1) // 2
    assert mann_kendall(naik[::-1])["s"] == -n * (n - 1) // 2
    deret = [3, 1, 4, 1, 5, 9, 2, 6]
    assert mann_kendall(deret)["s"] == -mann_kendall(deret[::-1])["s"]


def test_mann_kendall_semua_sama_tidak_error():
    m = mann_kendall([5] * 10)
    assert m["s"] == 0 and m["p"] == 1.0 and m["z"] == 0.0


def test_sen_slope_linear_dan_tahan_outlier():
    assert sen_slope([2, 4, 6, 8, 10, 12, 14, 16]) == pytest.approx(2.0)
    assert sen_slope([1, 2, 3, 4, 5, 6, 7, 8, 9, 100]) == pytest.approx(1.0)   # outlier tidak menggeser median


def test_benjamini_hochberg_contoh_diketahui():
    q = benjamini_hochberg([0.01, 0.04, 0.03, 0.005])
    assert q == pytest.approx([0.02, 0.04, 0.04, 0.02])
    assert benjamini_hochberg([]) == []
    assert benjamini_hochberg([0.5]) == [0.5]
    # q >= p, q <= 1, dan urutan q mengikuti urutan p
    p = [0.9, 0.001, 0.2, 0.049, 0.6]
    q = benjamini_hochberg(p)
    assert all(qi >= pi - 1e-15 and qi <= 1.0 for qi, pi in zip(q, p))
    assert [q[i] for i in sorted(range(5), key=lambda i: p[i])] == sorted(q)


# ------------------------------------------------------------------ skenario jawaban-diketahui

def test_golden_naik_kuat():
    r, hasil = label_of(NAIK)
    assert (r["label"], r["strength"]) == ("Naik", "kuat")
    assert r["n"] == 12 and r["beta"] == pytest.approx(1.0)
    assert r["r"] == pytest.approx(1.0 * 11 / (sum(NAIK) / 12))
    assert r["q"] < 0.01
    assert hasil["n_tested"] == 13                      # target + 12 filler berp-value


def test_golden_datar_tidak_ada_tren_jelas():
    r, _ = label_of(DATAR)
    assert r["label"] == "Tidak ada tren jelas" and r["strength"] is None


def test_skenario_stabil():
    r, _ = label_of([50, 52, 49, 51, 50, 48, 52, 50, 51, 49, 50, 51])
    assert r["label"] == "Tidak ada tren jelas"


def test_naik_lambat_pendek_tidak_diklaim_naik_tapi_panjang_terdeteksi():
    lambat12 = [round(100 * 1.01 ** i) + NOISE[i] for i in range(12)]
    r12, _ = label_of(lambat12)
    assert r12["label"] == "Tidak ada tren jelas"       # r ~ +11% tetapi bukti lemah (p ~ 0,24)
    assert r12["r"] > 0.10 and r12["p"] > 0.2

    lambat52 = [round(100 * 1.01 ** i) + NOISE[i % 12] for i in range(52)]
    r52, _ = label_of(lambat52, inputs={"n_filler": 12})
    assert r52["label"] == "Naik"


def test_naik_cepat_dan_turun():
    cepat = [round(100 * 1.10 ** i) + NOISE[i] for i in range(12)]
    turun = [round(300 * 0.92 ** i) + NOISE[i] for i in range(12)]
    rc, _ = label_of(cepat)
    rt, _ = label_of(turun)
    assert (rc["label"], rc["strength"]) == ("Naik", "kuat")
    assert (rt["label"], rt["strength"]) == ("Turun", "kuat")
    assert rt["r"] < -0.10


def test_musiman_tidak_dianggap_tren():
    r, _ = label_of([60, 80, 100, 80] * 3)
    assert r["label"] == "Tidak ada tren jelas"


def test_lonjakan_tunggal_tidak_dianggap_tren():
    r, _ = label_of([20] * 6 + [200] + [20] * 5)
    assert r["label"] == "Tidak ada tren jelas"
    assert r["beta"] == 0.0


def test_rata_rata_kurang_dari_satu_per_minggu():
    r, _ = label_of([1, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0])
    assert r["label"] == "Data terlalu jarang" and r["p"] is None
    assert r["avg_units_per_week"] == pytest.approx(0.25)
    assert len(r["series"]) == 12                      # deret tetap dikembalikan


def test_tidak_ada_penjualan():
    prods, facts, period = trend_inputs([0] * 12)
    hasil = assert_pure(analyze_trend, facts, prods, period)
    r = by_id(hasil["products"], 1)
    assert r["label"] == "Tidak ada penjualan" and r["total_units"] == 0


def test_periode_kurang_dari_8_minggu_deret_tetap_ada():
    r, hasil = label_of([10, 12, 15, 18, 20, 25])
    assert r["label"] == "Data belum cukup" and r["n"] == 6
    assert len(r["series"]) == 6
    assert any("Data belum cukup untuk tren" in w for w in hasil["warnings"])
    # tepat 8 minggu sudah boleh diklasifikasi
    r8, _ = label_of([10, 12, 15, 18, 20, 25, 30, 33])
    assert r8["label"] in ("Naik", "Tidak ada tren jelas") and r8["p"] is not None


# ------------------------------------------------------------------ produk baru / active_since

BARU = [20] * 11       # produk mulai minggu ke-6 (indeks 5) pada periode 16 minggu


def _produk_baru(active_since=None):
    weeks = 16
    fprods, frows = filler_products(12, weeks=weeks)
    prods = [product(1, "Baru", active_since=active_since)] + fprods
    facts = weekly_rows(1, BARU, first_week=5) + frows
    return prods, facts, period_weeks(weeks)


def test_produk_baru_tidak_naik_palsu_dengan_active_since():
    prods, facts, period = _produk_baru(active_since=day(35).isoformat())
    r = by_id(assert_pure(analyze_trend, facts, prods, period)["products"], 1)
    assert r["start_source"] == "active_since" and r["trimmed_weeks"] == 5 and r["n"] == 11
    assert r["label"] == "Tidak ada tren jelas"
    assert r["warnings"] == []


def test_produk_baru_fallback_aktivitas_pertama_dicatat_di_warnings():
    prods, facts, period = _produk_baru()
    hasil = assert_pure(analyze_trend, facts, prods, period)
    r = by_id(hasil["products"], 1)
    assert r["start_source"] == "first_activity" and r["n"] == 11
    assert r["label"] == "Tidak ada tren jelas"
    assert r["warnings"] and "active_since kosong" in r["warnings"][0]
    assert any("aktivitas pertama" in w for w in hasil["warnings"])


def test_tanpa_pemotongan_produk_baru_memang_tampak_naik_palsu():
    # Kontrol: produk baru (9 minggu nol, lalu 7 minggu konstan) tampak "Naik" bila minggu nol ikut
    # dihitung. Dengan pemotongan, ia dinilai "Data belum cukup" (n = 7 < 8), bukan "Naik" palsu.
    weeks = 16
    fprods, frows = filler_products(12, weeks=weeks)
    facts = weekly_rows(1, [20] * 7, first_week=9) + frows
    period = period_weeks(weeks)
    tanpa_potong = by_id(analyze_trend(facts, [product(1, "Baru", active_since="2025-12-01")] + fprods,
                                       period)["products"], 1)
    assert tanpa_potong["trimmed_weeks"] == 0 and tanpa_potong["n"] == 16
    assert tanpa_potong["label"] == "Naik"
    dengan_potong = by_id(analyze_trend(facts, [product(1, "Baru", active_since=day(63).isoformat())] + fprods,
                                        period)["products"], 1)
    assert dengan_potong["n"] == 7 and dengan_potong["label"] == "Data belum cukup"


def test_produk_baru_dengan_kurang_dari_8_minggu_aktif():
    weeks = 12
    fprods, frows = filler_products(12, weeks=weeks)
    prods = [product(1, "Baru", active_since=day(35).isoformat())] + fprods     # aktif minggu ke-6 dari 12
    facts = weekly_rows(1, [20] * 7, first_week=5) + frows
    r = by_id(analyze_trend(facts, prods, period_weeks(weeks))["products"], 1)
    assert r["label"] == "Data belum cukup" and r["n"] == 7


def test_aktivitas_apa_pun_menentukan_awal_bukan_hanya_penjualan():
    weeks = 16
    fprods, frows = filler_products(12, weeks=weeks)
    prods = [product(1, "Baru")] + fprods
    facts = [row(day(7 * 3), 1, views=5)] + weekly_rows(1, [20] * 12, first_week=4) + frows   # view di minggu ke-4
    r = by_id(analyze_trend(facts, prods, period_weeks(weeks))["products"], 1)
    assert r["start_date"] == day(21).isoformat() and r["n"] == 13


def test_produk_tanpa_aktivitas_sama_sekali():
    prods, facts, period = trend_inputs([10] * 12)
    prods.append(product(77, "Sepi"))
    hasil = analyze_trend(facts, prods, period)
    assert by_id(hasil["products"], 77)["label"] == "Tidak ada penjualan"
    pendek = analyze_trend(facts, prods, period_weeks(6))
    assert by_id(pendek["products"], 77)["label"] == "Data belum cukup"


# ------------------------------------------------------------------ invarian dan kasus tepi

def test_label_tidak_berubah_karena_subset_produk():
    prods, facts, period = trend_inputs(NAIK)
    semua = analyze_trend(facts, prods, period)
    subset = analyze_trend(facts, prods, period, product_ids=[1])
    a, b = by_id(semua["products"], 1), by_id(subset["products"], 1)
    assert len(subset["products"]) == 1
    for k in ("label", "q", "p", "r", "strength"):
        assert a[k] == b[k]
    assert subset["n_tested"] == semua["n_tested"]       # keluarga BH tetap semua produk aktif


def test_bh_memperketat_bila_keluarga_besar():
    # q = p*m/rank: produk berp-value moderat menjadi lebih sulit lolos saat keluarga hipotesis besar
    lemah = [100 + NOISE[i] + 2 * i for i in range(12)]
    p1, f1, per1 = trend_inputs(lemah, n_filler=0)
    p2, f2, per2 = trend_inputs(lemah, n_filler=12)
    sendiri = by_id(analyze_trend(f1, p1, per1)["products"], 1)
    ramai = by_id(analyze_trend(f2, p2, per2)["products"], 1)
    assert sendiri["p"] == pytest.approx(ramai["p"])      # p-value sama
    assert ramai["q"] >= sendiri["q"]                     # q hanya bisa naik saat keluarga membesar


def test_bh_mengubah_label_bukan_hanya_q():
    # p = 0,023 (< 0,05) dan r = +28%: diuji sendirian ia "Naik" (cukup), tetapi di antara 13 hipotesis
    # q = 0,30 sehingga label HARUS "Tidak ada tren jelas". Tanpa BH, 1 dari 13 produk bisa lolos hanya kebetulan.
    deret = [100 + 2 * NOISE[i] + 3 * i for i in range(12)]
    sendiri = by_id(analyze_trend(*(lambda p, f, per: (f, p, per))(*trend_inputs(deret, n_filler=0)))["products"], 1)
    ramai = by_id(analyze_trend(*(lambda p, f, per: (f, p, per))(*trend_inputs(deret, n_filler=12)))["products"], 1)
    assert sendiri["p"] == pytest.approx(0.023, abs=1e-3) and ramai["p"] == pytest.approx(sendiri["p"])
    assert (sendiri["label"], sendiri["strength"]) == ("Naik", "cukup")
    assert ramai["q"] == pytest.approx(0.299, abs=1e-3) and ramai["p"] < 0.05 <= ramai["q"]
    assert ramai["label"] == "Tidak ada tren jelas" and ramai["strength"] is None


def test_kekuatan_bukti_cukup_untuk_q_di_antara_001_dan_005():
    deret = [100 + 2 * NOISE[i] + 5 * i for i in range(12)]
    r = by_id(analyze_trend(*(lambda p, f, per: (f, p, per))(*trend_inputs(deret, n_filler=12)))["products"], 1)
    assert 0.01 <= r["q"] < 0.05 and (r["label"], r["strength"]) == ("Naik", "cukup")


def test_ambang_r_minimum_10_persen():
    # bukti kuat tetapi perubahan kecil (< 10%) -> bukan "Naik"
    kecil = [1000 + (i % 2) + i for i in range(12)]              # kenaikan ~ +1 per minggu pada basis ~1000
    r = by_id(analyze_trend(*(lambda p, f, per: (f, p, per))(*trend_inputs(kecil, n_filler=0)))["products"], 1)
    assert r["q"] < 0.05 and 0 < r["r"] < 0.10 and r["label"] == "Tidak ada tren jelas"


def test_produk_nonaktif_tidak_masuk_keluarga_bh():
    prods, facts, period = trend_inputs(NAIK)
    prods.append(product(55, "Lama", active=False))
    facts = facts + weekly_rows(55, NAIK)
    hasil = analyze_trend(facts, prods, period)
    assert by_id(hasil["products"], 55)["label"] == "Produk nonaktif"
    assert hasil["n_tested"] == 13                       # tidak bertambah


def test_minggu_parsial_dicatat_tidak_diklasifikasi():
    # periode Rabu s.d. Selasa 9 minggu kemudian: 8 minggu penuh + dua potongan parsial
    start = day(2)
    weeks_full = 8
    fprods, frows = filler_products(5, weeks=weeks_full, start=day(7))
    prods = [product(1, "Target")] + fprods
    facts = weekly_rows(1, [10, 11, 13, 12, 15, 16, 18, 19], start=day(7)) + frows + [row(day(2), 1, units=999)]
    period = {"start": start.isoformat(), "end": (start + timedelta(days=7 * 9 - 1)).isoformat()}
    hasil = analyze_trend(facts, prods, period)
    assert hasil["weeks_full"] == 8 and len(hasil["partial_weeks"]) == 2
    r = by_id(hasil["products"], 1)
    assert r["n"] == 8 and 999 not in [s["units"] for s in r["series"]]   # 999 jatuh di minggu parsial
    assert any("minggu parsial" in w for w in hasil["warnings"])


def test_ikatan_banyak_nol_tidak_error_dan_p_valid():
    r, _ = label_of([1, 0, 0, 0, 0, 3, 0, 0, 4, 0, 5, 6])
    assert 0 <= r["p"] <= 1 and math.isfinite(r["z"])


def test_metrik_revenue():
    prods, facts, period = trend_inputs(NAIK, price=7000)
    hasil = analyze_trend(facts, prods, period, metric="revenue")
    r = by_id(hasil["products"], 1)
    assert r["series"][0]["value"] == 10 * 7000 and r["label"] == "Naik"
    assert r["beta"] == pytest.approx(7000.0)


def test_peringkat_naik_dan_turun_urut_besar_perubahan():
    prods, facts, period = trend_inputs([round(100 * 1.10 ** i) + NOISE[i] for i in range(12)])
    prods.append(product(2, "Turun"))
    facts = facts + weekly_rows(2, [round(300 * 0.92 ** i) + NOISE[i] for i in range(12)])
    hasil = analyze_trend(facts, prods, period)
    assert [x["product_id"] for x in hasil["rankings"]["naik"]] == [1]
    assert [x["product_id"] for x in hasil["rankings"]["turun"]] == [2]
    assert hasil["rankings"]["naik"][0]["r"] > 0 > hasil["rankings"]["turun"][0]["r"]


def test_catatan_stok_hanya_untuk_data_simulasi_dan_hasil_turun():
    turun = [round(300 * 0.92 ** i) + NOISE[i] for i in range(12)]
    prods, facts, period = trend_inputs(turun)
    simulasi = analyze_trend(facts, prods, period, origin_filter="developer_simulation")
    normal = analyze_trend(facts, prods, period, origin_filter="normal")
    assert any("tidak memodelkan stok" in w for w in simulasi["warnings"])
    assert not any("tidak memodelkan stok" in w for w in normal["warnings"])


def test_deterministik_dan_input_tidak_berubah():
    prods, facts, period = trend_inputs(NAIK)
    a = assert_pure(analyze_trend, facts, prods, period)
    b = analyze_trend(list(reversed(facts)), prods, period)
    assert a == b                                        # urutan baris facts tidak berpengaruh


def test_input_tidak_valid_menghasilkan_galat_jelas():
    prods, facts, period = trend_inputs(NAIK)
    with pytest.raises(AnalyticsInputError):
        analyze_trend(facts, prods, {"start": "2026-02-01", "end": "2026-01-01"})
    with pytest.raises(AnalyticsInputError):
        analyze_trend(facts + [dict(facts[0], units_sold=-1)], prods, period)
    with pytest.raises(AnalyticsInputError):
        analyze_trend(facts, prods, period, origin_filter="semua")
    with pytest.raises(AnalyticsInputError):
        analyze_trend(facts, prods, period, product_ids=[999])
    with pytest.raises(AnalyticsInputError):
        analyze_trend(facts, prods, period, metric="profit")


def test_baris_di_luar_periode_dicatat():
    prods, facts, period = trend_inputs(NAIK)
    hasil = analyze_trend(facts + [row(day(-30), 1, units=500)], prods, period)
    assert any("di luar periode" in w for w in hasil["warnings"])
    assert by_id(hasil["products"], 1)["series"][0]["units"] == 10
