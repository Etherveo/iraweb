import pytest

from algorithms._common import AnalyticsInputError
from algorithms.compare import compare_periods
from algorithms.funnel import analyze_funnel
from algorithms.insight import (CHECKLIST_INS03, INSUFFICIENT_PHRASE, TEMPLATES, build_insights, check_guardrails,
                                fmt_date, fmt_dec, fmt_int, fmt_p)
from algorithms.interest import analyze_interest
from algorithms.summary import summarize
from algorithms.trend import analyze_trend
from helpers import MON, NOISE, assert_pure, day, period_weeks, product, row, trend_inputs, weekly_rows

PERIOD = period_weeks(1)
KONTEKS = "Periode 5 Jan 2026 s.d. 11 Jan 2026, asal data: Semua."
KATA_TINDAKAN = ("sebaiknya", "disarankan", "harus", "segera", "rekomendasi", "perlu dilakukan", "lakukan")


def by_kode(hasil, kode):
    return [i for i in hasil["insights"] if i["id"] == kode]


# ------------------------------------------------------------------ format angka id-ID

def test_format_angka_id_id():
    assert fmt_int(1250000) == "1.250.000" and fmt_int(0) == "0" and fmt_int(999) == "999"
    assert fmt_dec(12.34) == "12,3" and fmt_dec(0.0) == "0,0" and fmt_dec(7.126, 2) == "7,13"
    assert fmt_p(0.4944) == "0,494" and fmt_p(0.0004) == "< 0,001"
    assert fmt_date("2026-10-12") == "12 Okt 2026" and fmt_date("2026-05-01") == "1 Mei 2026"


# ------------------------------------------------------------------ teks persis per templat

def test_ins_01_teks_persis():
    s = summarize([row(day(2), 1, units=250, price=5000), row(day(0), 1, units=10, price=5000)], [product(1)], PERIOD)
    [i] = by_kode(build_insights(summary=s), "INS-01")
    assert i["text"] == ("Penjualan tertinggi pada 7 Jan 2026: Rp 1.250.000, tertinggi dari 7 hari pada periode ini. "
                         + KONTEKS)
    assert i["numbers"] == {"date": "2026-01-07", "revenue_idr": 1250000, "n_days": 7}
    assert i["source"] == {"fr": "FR-01", "anl": "ANL-14"} and i["kind"] == "fact"


def test_ins_02_teks_persis():
    f = analyze_funnel([row(day(0), 1, units=90, views=1000, carts=400, bookings=120)], [product(1)], PERIOD)
    [i] = by_kode(build_insights(funnel=f), "INS-02")
    assert i["text"] == "Penurunan terbesar: Dilihat ke Keranjang, 600 event (60,0%). " + KONTEKS


def test_ins_03_teks_persis_dan_checklist_terpisah_dari_kalimat():
    prods = [product(1, "Buku Tulis")]
    h = analyze_interest([row(day(0), 1, views=50, carts=30, bookings=5)], prods, PERIOD)
    [i] = by_kode(build_insights(interest=h), "INS-03")
    assert i["text"] == ("Buku Tulis: 50 kali dilihat, 30 masuk keranjang, 0 pembelian "
                         "(0,0%; interval 95%: 0,0\u20137,1%). " + KONTEKS)
    assert i["checklist"] == CHECKLIST_INS03 and CHECKLIST_INS03 not in i["text"]
    assert i["numbers"]["views"] == 50 and i["numbers"]["purchases"] == 0


def test_ins_04_teks_persis_dua_periode():
    pb = period_weeks(1, start=day(7))
    h = compare_periods([row(day(0), 1, units=42)], [row(day(7), 1, units=35)], [product(1)], PERIOD, pb)
    ins = by_kode(build_insights(compare=h), "INS-04")
    assert ins[0]["text"] == ("Perubahan total unit terjual (42 dibanding 35) belum dapat dibedakan dari variasi acak "
                              "(p = 0,494). Periode A 5 Jan 2026 s.d. 11 Jan 2026 dibanding periode B "
                              "12 Jan 2026 s.d. 18 Jan 2026, asal data: Semua.")
    assert 0.49 < ins[0]["numbers"]["p"] < 0.50
    assert len(ins) <= 5


def test_ins_04_tidak_muncul_bila_semua_perubahan_signifikan():
    pb = period_weeks(1, start=day(7))
    h = compare_periods([row(day(0), 1, units=300)], [row(day(7), 1, units=50)], [product(1)], PERIOD, pb)
    assert by_kode(build_insights(compare=h), "INS-04") == []


def test_ins_05_teks_persis():
    prods, facts, period = trend_inputs([10, 12, 9, 14, 13, 15, 14, 18, 16, 19, 21, 20], target_name="Pensil 2B")
    t = analyze_trend(facts, prods, period)
    [i] = by_kode(build_insights(trend=t), "INS-05")
    assert i["text"] == ("Pensil 2B menunjukkan kecenderungan naik dengan bukti kuat selama 12 minggu "
                         "(perubahan sekitar 72,9%). Periode 5 Jan 2026 s.d. 29 Mar 2026, asal data: Semua.")
    assert i["numbers"]["n"] == 12 and i["numbers"]["q"] < 0.01


def test_ins_05_arah_turun_memakai_nilai_mutlak():
    turun = [round(300 * 0.92 ** i) + NOISE[i] for i in range(12)]
    prods, facts, period = trend_inputs(turun, target_name="Lem")
    [i] = by_kode(build_insights(trend=analyze_trend(facts, prods, period)), "INS-05")
    assert "kecenderungan turun dengan bukti kuat" in i["text"] and "sekitar 88" in i["text"]


def test_ins_06_teks_persis_dan_periode_dari_summary():
    s = summarize([], [], PERIOD)
    [i] = by_kode(build_insights(summary=s, origin_transactions={"normal": 120, "developer_simulation": 80}), "INS-06")
    assert i["text"] == "120 transaksi normal + 80 transaksi simulasi = 200 transaksi berhasil. " + KONTEKS
    [j] = by_kode(build_insights(origin_transactions={"normal": 1, "developer_simulation": 2}, period=PERIOD), "INS-06")
    assert "1 transaksi normal + 2 transaksi simulasi = 3 transaksi berhasil." in j["text"]
    with pytest.raises(AnalyticsInputError):
        build_insights(origin_transactions={"normal": 1, "developer_simulation": 2})


# ------------------------------------------------------------------ "belum cukup data untuk disimpulkan"

def test_fallback_belum_cukup_data():
    s = summarize([], [product(1)], PERIOD)
    f = analyze_funnel([], [product(1)], PERIOD)
    kurang = analyze_interest([row(day(0), 1, views=10)], [product(1)], PERIOD)
    pb = period_weeks(1, start=day(7))
    c = compare_periods([], [], [product(1)], PERIOD, pb)
    prods, facts, period = trend_inputs([10] * 6)
    t = analyze_trend(facts, prods, period)
    hasil = build_insights(summary=s, funnel=f, interest=kurang, compare=c, trend=t,
                           origin_transactions={"normal": None, "developer_simulation": 3}, period=PERIOD)
    kode = sorted(i["id"] for i in hasil["insights"])
    assert kode == ["INS-01", "INS-02", "INS-03", "INS-04", "INS-05", "INS-06"]
    for i in hasil["insights"]:
        assert i["kind"] == "insufficient" and INSUFFICIENT_PHRASE in i["text"]
        assert "Periode" in i["text"] and "asal data:" in i["text"]


def test_tidak_ada_fallback_bila_data_cukup_tetapi_tidak_ada_yang_perlu_dilaporkan():
    prods, facts, period = trend_inputs([50, 52, 49, 51, 50, 48, 52, 50, 51, 49, 50, 51])
    assert by_kode(build_insights(trend=analyze_trend(facts, prods, period)), "INS-05") == []


# ------------------------------------------------------------------ guardrail

def _paket_lengkap(origin="all"):
    prods, facts, period = trend_inputs([10, 12, 9, 14, 13, 15, 14, 18, 16, 19, 21, 20])
    t = analyze_trend(facts, prods, period, origin_filter=origin)
    p1 = [product(1, "Buku"), product(2, "Pena")]
    f1 = [row(day(0), 1, views=60, carts=30, bookings=5, units=0), row(day(1), 2, units=9, views=50, carts=20, bookings=10),
          row(day(2), 2, units=3)]
    s = summarize(f1, p1, PERIOD, origin_filter=origin)
    fu = analyze_funnel(f1, p1, PERIOD, origin_filter=origin)
    it = analyze_interest(f1, p1, PERIOD, origin_filter=origin)
    pb = period_weeks(1, start=day(7))
    c = compare_periods(f1, [row(day(7), 2, units=10), row(day(8), 1, units=4)], p1, PERIOD, pb, origin_filter=origin)
    return build_insights(summary=s, funnel=fu, interest=it, compare=c, trend=t,
                          origin_transactions={"normal": 5, "developer_simulation": 7}, period=PERIOD)


def test_tidak_ada_kata_sebab_dan_tiap_kalimat_menyebut_periode_dan_filter():
    hasil = assert_pure(_paket_lengkap)
    assert {i["id"] for i in hasil["insights"]} == {"INS-01", "INS-02", "INS-03", "INS-04", "INS-05", "INS-06"}
    for i in hasil["insights"]:
        assert check_guardrails(i["text"]) == [] and i["guardrail"]["forbidden_words"] == []
        assert "Periode" in i["text"] and "asal data:" in i["text"]
        low = i["text"].lower()
        assert not any(k in low for k in KATA_TINDAKAN), i["text"]
        assert i["source"]["fr"].startswith("FR-") and isinstance(i["numbers"], dict)
    assert hasil["warnings"] == []


def test_templat_statis_bebas_kata_sebab_dan_tindakan():
    for kode, tpl in TEMPLATES.items():
        assert check_guardrails(tpl) == [], kode
        assert not any(k in tpl.lower() for k in KATA_TINDAKAN), kode


def test_detektor_kata_sebab():
    for kata in ("karena", "Karena itu", "disebabkan", "menyebabkan", "akibat", "Akibatnya", "penyebab", "sebab"):
        assert check_guardrails(f"Penjualan turun {kata} sesuatu"), kata
    assert check_guardrails("Penjualan turun 12,3% dibanding minggu lalu.") == []
    assert check_guardrails("Karet penghapus dan sebaran data") == []        # kata mirip tidak ikut terdeteksi


def test_nama_produk_berkata_terlarang_memicu_warning_bukan_error():
    prods = [product(1, "Akibat Baru")]
    h = analyze_interest([row(day(0), 1, views=50, carts=30)], prods, PERIOD)
    hasil = build_insights(interest=h)
    assert any("kata terlarang" in w for w in hasil["warnings"])
    assert hasil["insights"][0]["guardrail"]["forbidden_words"] == ["Akibat"]


def test_checklist_hanya_pada_ins_03():
    hasil = _paket_lengkap()
    for i in hasil["insights"]:
        assert ("checklist" in i) == (i["id"] == "INS-03")


def test_label_filter_asal_data_di_kalimat():
    s = summarize([row(day(0), 1, units=3)], [product(1)], PERIOD, origin_filter="normal")
    [i] = by_kode(build_insights(summary=s), "INS-01")
    assert i["text"].endswith("asal data: Hanya normal.") and i["origin_filter"] == "normal"
    s2 = summarize([row(day(0), 1, units=3)], [product(1)], period_weeks(1), origin_filter="developer_simulation")
    assert by_kode(build_insights(summary=s2), "INS-01")[0]["text"].endswith("asal data: Hanya simulasi.")


def test_batas_jumlah_kalimat_berulang_dan_deterministik():
    prods = [product(i, f"P{i:02d}") for i in range(1, 9)]
    facts = [row(day(0), i, views=60 + i, carts=30, bookings=3) for i in range(1, 9)]
    h = analyze_interest(facts, prods, PERIOD)
    a = build_insights(interest=h, max_items=3)
    assert len(by_kode(a, "INS-03")) == 3
    assert a == build_insights(interest=h, max_items=3)
    assert len(by_kode(build_insights(interest=h), "INS-03")) == 5
