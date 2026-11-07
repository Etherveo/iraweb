"""Uji skala: input sebesar toko sungguhan harus selesai cepat, deterministik, dan JSON-aman."""

import json
import time
from datetime import timedelta

from algorithms import (analyze_category, analyze_funnel, analyze_interest, analyze_trend, build_insights,
                        compare_periods, summarize)
from helpers import MON, NOISE, day, product


def _toko_besar(n_prod=60, n_hari=182):
    prods = [product(i, f"Produk {i:03d}", category_id=i % 6 + 1, price=2000 + 700 * i) for i in range(1, n_prod + 1)]
    facts, ot = [], []
    for h in range(n_hari):
        tgl = day(h)
        orders = 0
        for pid in range(1, n_prod + 1):
            dasar = 1 + (pid * 7) % 9
            tren = (h // 7) * (1 if pid % 5 == 0 else (-1 if pid % 7 == 0 else 0)) // 4
            u = max(0, dasar + NOISE[(h + pid) % 12] // 4 + tren)
            if u == 0 and (h + pid) % 3:
                continue
            facts.append({"date": tgl.isoformat(), "product_id": pid, "units_sold": u, "revenue_idr": u * (2000 + 700 * pid),
                          "orders_paid": u, "views": 12 * u + 3, "add_to_carts": 4 * u + 1, "bookings": 2 * u,
                          "purchases": u, "cancels": u // 5})
            orders += u
        ot.append({"date": tgl.isoformat(), "orders_paid": max(1, orders * 2 // 3),
                   "by_category": [{"category_id": c, "orders_paid": max(0, orders // 8)} for c in range(1, 7)]})
    return prods, facts, ot


def test_semua_fungsi_cepat_dan_json_aman_pada_data_besar():
    prods, facts, ot = _toko_besar()
    per = {"start": MON.isoformat(), "end": (MON + timedelta(days=181)).isoformat()}
    pa = {"start": MON.isoformat(), "end": (MON + timedelta(days=90)).isoformat()}
    pb = {"start": (MON + timedelta(days=91)).isoformat(), "end": (MON + timedelta(days=181)).isoformat()}
    fa = [f for f in facts if f["date"] <= pa["end"]]
    fb = [f for f in facts if f["date"] >= pb["start"]]
    t0 = time.perf_counter()
    s = summarize(facts, prods, per, order_totals=ot)
    f = analyze_funnel(facts, prods, per)
    t = analyze_trend(facts, prods, per)
    i = analyze_interest(facts, prods, per)
    c = analyze_category(facts, prods, per, order_totals=ot)
    k = compare_periods(fa, fb, prods, pa, pb)
    ins = build_insights(summary=s, funnel=f, trend=t, interest=i, compare=k)
    lama = time.perf_counter() - t0
    for hasil in (s, f, t, i, c, k, ins):
        json.dumps(hasil, allow_nan=False)
    assert lama < 5.0, f"terlalu lambat: {lama:.2f} detik"
    assert t["weeks_full"] == 26 and t["n_tested"] > 30
    labels = {p["label"] for p in t["products"]}
    assert "Naik" in labels or "Turun" in labels                      # data memang memuat tren yang ditanam
    assert sum(x["share_revenue"]["tenths"] for x in c["categories"]) == 1000
    assert t == analyze_trend(list(reversed(facts)), prods, per)       # deterministik terhadap urutan baris
