"""Pembangun data uji deterministik (tanpa angka acak) untuk modul analitik.

Semua tanggal berbasis MON = Senin, sehingga minggu Senin-Minggu pas dengan periode.
"""

from __future__ import annotations

import copy
import json
from datetime import date, timedelta

MON = date(2026, 1, 5)  # hari Senin
NOISE = [5, -6, 3, -8, 7, -2, -5, 8, -4, 6, -7, 2]  # pola derau tetap (bukan acak)


def day(offset: int, start: date = MON) -> date:
    return start + timedelta(days=offset)


def product(pid, name=None, category_id=1, price=5000, active=True, active_since=None):
    p = {"id": pid, "name": name or f"Produk {pid:02d}", "category_id": category_id,
         "price_idr": price, "is_active": active}
    if active_since is not None:
        p["active_since"] = active_since
    return p


def period_weeks(n_weeks: int, start: date = MON) -> dict:
    return {"start": start.isoformat(), "end": (start + timedelta(days=7 * n_weeks - 1)).isoformat()}


def row(d: date, pid: int, units=0, price=5000, views=0, carts=0, bookings=0, orders=None, cancels=0):
    """Satu baris facts; orders_paid = purchases = `orders` (default = units, satu unit per pesanan)."""
    orders = units if orders is None else orders
    return {"date": d.isoformat(), "product_id": pid, "units_sold": units, "revenue_idr": units * price,
            "orders_paid": orders, "views": views, "add_to_carts": carts, "bookings": bookings,
            "purchases": orders, "cancels": cancels}


def weekly_rows(pid, weekly_units, start: date = MON, price=5000, first_week=0):
    """Satu baris per minggu (pada hari Senin) agar jumlah mingguan = nilai deret."""
    return [row(day(7 * (first_week + i), start), pid, units=u, price=price)
            for i, u in enumerate(weekly_units) if u]


def filler_products(n=12, first_id=100, weeks=12, base=40, start: date = MON):
    """Keluarga produk 'datar' (derau tetap) agar BH punya keluarga hipotesis yang realistis."""
    prods, rows = [], []
    for k in range(n):
        pid = first_id + k
        prods.append(product(pid, f"Filler {k:02d}"))
        rows += weekly_rows(pid, [base + NOISE[(i + k) % 12] for i in range(weeks)], start)
    return prods, rows


def trend_inputs(target_weekly, *, n_filler=12, target_id=1, target_name="Target", start: date = MON,
                 active_since=None, price=5000):
    """(products, facts, period) berisi satu produk target + keluarga filler."""
    weeks = len(target_weekly)
    fprods, frows = filler_products(n_filler, weeks=weeks, start=start)
    prods = [product(target_id, target_name, active_since=active_since)] + fprods
    facts = weekly_rows(target_id, target_weekly, start, price) + frows
    return prods, facts, period_weeks(weeks, start)


def by_id(result_products, pid):
    return next(p for p in result_products if p["product_id"] == pid)


def assert_pure(func, *args, **kwargs):
    """Panggil `func`, pastikan argumen tidak berubah dan hasil bisa jadi JSON (tanpa NaN/inf)."""
    sebelum = copy.deepcopy((args, kwargs))
    hasil = func(*args, **kwargs)
    assert (args, kwargs) == sebelum, "fungsi mengubah argumen masukan"
    json.dumps(hasil, allow_nan=False)
    return hasil


# ---------------------------------------------------------------------------
# Simulator pesanan kecil (deterministik) untuk uji invarian lintas-FR
# ---------------------------------------------------------------------------

def make_orders(n_days=14, per_day=6, start: date = MON):
    """Daftar pesanan paid: [{'date', 'items': [(pid, qty)]}], 1-3 item berbeda per pesanan."""
    kombinasi = [
        [(1, 2)], [(2, 1)], [(1, 1), (2, 3)], [(3, 1)], [(1, 1), (3, 2), (4, 1)],
        [(4, 2)], [(2, 2), (3, 1)], [(5, 1)], [(1, 3), (5, 2)],
    ]
    pesanan = []
    for i in range(n_days):
        for j in range(per_day):
            pesanan.append({"date": day(i, start), "items": kombinasi[(i * 3 + j * 2 + j // 2) % len(kombinasi)]})
    return pesanan


def orders_to_data(orders, prices, categories):
    """Turunkan facts, order_totals, dan kebenaran dasar dari pesanan.

    prices: {pid: harga}; categories: {pid: category_id}.
    Tiap item paid menghasilkan 1 event view, keranjang, booking, dan purchase (funnel konsisten).
    """
    facts: dict[tuple, dict] = {}
    harian: dict[date, dict] = {}
    for o in orders:
        h = harian.setdefault(o["date"], {"orders": 0, "cat": {}})
        h["orders"] += 1
        for cid in {categories[pid] for pid, _q in o["items"]}:
            h["cat"][cid] = h["cat"].get(cid, 0) + 1
        for pid, qty in o["items"]:
            f = facts.setdefault((o["date"], pid), {
                "date": o["date"].isoformat(), "product_id": pid, "units_sold": 0, "revenue_idr": 0,
                "orders_paid": 0, "views": 0, "add_to_carts": 0, "bookings": 0, "purchases": 0, "cancels": 0})
            f["units_sold"] += qty
            f["revenue_idr"] += qty * prices[pid]
            f["orders_paid"] += 1
            f["purchases"] += 1
            f["bookings"] += 1
            f["add_to_carts"] += 1
            f["views"] += 2
    order_totals = [
        {"date": d.isoformat(), "orders_paid": v["orders"],
         "by_category": [{"category_id": c, "orders_paid": n} for c, n in sorted(v["cat"].items())]}
        for d, v in sorted(harian.items())
    ]
    truth = {
        "orders": len(orders),
        "items": sum(len(o["items"]) for o in orders),
        "units": sum(q for o in orders for _p, q in o["items"]),
        "revenue": sum(q * prices[p] for o in orders for p, q in o["items"]),
    }
    return [facts[k] for k in sorted(facts)], order_totals, truth
