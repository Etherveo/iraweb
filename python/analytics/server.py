#!/usr/bin/env python3
"""HTTP wrapper (M5) di atas modul ``algorithms/`` AI B (referensi, stdlib saja).

Pembungkus ini BUKAN bagian modul AI B: ia hanya menerjemahkan HTTP <-> fungsi
murni di ``algorithms/``. Fungsi-fungsi itu tidak boleh diedit (lihat README).

Menjalankan (dari folder ini)::

    ANALYTICS_INTERNAL_KEY=<kunci-lokal> python3 server.py

Lalu dengar di ``127.0.0.1:8001`` (atau ``ANALYTICS_HOST`` / ``ANALYTICS_PORT``).

Kontrak
-------
- Satu endpoint POST per analisis (satu endpoint = satu fungsi AI B):

    POST /v1/summary   -> summarize         (FR-01)
    POST /v1/trend     -> analyze_trend     (FR-02)
    POST /v1/funnel    -> analyze_funnel    (FR-03)
    POST /v1/compare   -> compare_periods   (FR-04)
    POST /v1/interest  -> analyze_interest  (FR-05)
    POST /v1/category  -> analyze_category  (FR-06)
    POST /v1/category-compare -> compare_categories (FR-06 ANL-63)
    POST /v1/insight   -> build_insights    (INS-01..06)

  Body JSON memakai nama parameter yang sama dengan fungsi Python-nya
  (mis. ``facts``, ``products``, ``period``, ``order_totals``). Respons 200
  adalah dict hasil fungsi itu apa adanya (memuat ``algorithm_version``,
  ``warnings``, dst.).
- ``GET /health`` -> ``{"status": "ok", "algorithm_version": ...}`` (tanpa auth;
  service hanya dengar di loopback, jadi ini aman).
- Auth: header ``X-Internal-Key`` harus sama dengan env
  ``ANALYTICS_INTERNAL_KEY``. Kunci salah / tidak ada -> 401. Server MENOLAK
  start bila env kunci kosong (fail-closed: service internal tanpa kunci
  tidak boleh jalan tanpa autentikasi).
- Galat: JSON bukan object / kunci tak dikenal / kunci wajib hilang /
  ``AnalyticsInputError`` -> 422 ``{"error": "validation", "message": ...}``.
  Path tak dikenal -> 404; method bukan POST (kecuali GET /health) -> 405;
  body > 10 MB -> 413; galat tak terduga -> 500 ``{"error": "internal"}``
  TANPA traceback di respons (traceback hanya ke stderr).
- Tanggal pada request adalah TANGGAL KALENDER WITA (``YYYY-MM-DD``) apa
  adanya — server ini TIDAK melakukan konversi zona waktu apa pun
  (bukan +7, bukan +8); konversi WITA<->UTC adalah tugas pemanggil
  (Laravel, lihat ``AnalyticsFactsBuilder``).
- Tanpa DB/file/network selain socket listen + membaca kunci dari env.
  Stdlib saja; tidak ada dependensi baru.
"""

from __future__ import annotations

import json
import os
import sys
import traceback
from datetime import date, datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

# Impor `algorithms` dari folder script ini, apa pun cwd pemanggil.
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from algorithms import (  # noqa: E402
    ALGORITHM_VERSION,
    AnalyticsInputError,
    analyze_category,
    analyze_funnel,
    analyze_interest,
    analyze_trend,
    build_insights,
    compare_categories,
    compare_periods,
    summarize,
)

DEFAULT_HOST = "127.0.0.1"
DEFAULT_PORT = 8001
MAX_BODY_BYTES = 10 * 1024 * 1024  # facts <= ~15.000 baris; 10 MB longgar.

# endpoint -> (fungsi, kunci body yang diizinkan, kunci body yang wajib ada)
ENDPOINTS = {
    "/v1/summary": (
        summarize,
        {
            "facts", "products", "period", "origin_filter", "order_totals",
            "previous_facts", "previous_order_totals", "granularity", "top_n",
            "alpha", "auto_day_max", "auto_week_max", "baseline",
        },
        {"facts", "products", "period"},
    ),
    "/v1/trend": (
        analyze_trend,
        {
            "facts", "products", "period", "origin_filter", "metric",
            "product_ids", "n_min_weeks", "min_avg_per_week", "alpha",
            "r_min", "q_strong",
        },
        {"facts", "products", "period"},
    ),
    "/v1/funnel": (
        analyze_funnel,
        {"facts", "products", "period", "origin_filter", "product_ids", "category_id"},
        {"facts", "products", "period"},
    ),
    "/v1/compare": (
        compare_periods,
        {
            "facts_a", "facts_b", "products", "period_a", "period_b",
            "origin_filter", "order_totals_a", "order_totals_b", "top_n",
            "alpha", "baseline",
        },
        {"facts_a", "facts_b", "products", "period_a", "period_b"},
    ),
    "/v1/interest": (
        analyze_interest,
        {
            "facts", "products", "period", "origin_filter", "category_id",
            "min_views", "max_ratio", "max_upper", "comparator_min_ratio",
            "comparator_min_purchases", "comparator_max", "confidence",
        },
        {"facts", "products", "period"},
    ),
    "/v1/category": (
        analyze_category,
        {
            "facts", "products", "period", "origin_filter", "categories",
            "order_totals", "metric", "granularity", "price_bins",
            "n_min_weeks", "min_avg_per_week", "alpha", "r_min", "q_strong",
        },
        {"facts", "products", "period"},
    ),
    # M7 ANL-63: matriks kategori x dua periode (compare_categories).
    # Kunci disalin dari signature-nya — TANPA order_totals_* (tidak
    # diterima fungsi ini); ALLOWED_KEYS di AnalyticsClient harus identik.
    "/v1/category-compare": (
        compare_categories,
        {
            "facts_a", "facts_b", "products", "period_a", "period_b",
            "origin_filter", "categories", "baseline",
        },
        {"facts_a", "facts_b", "products", "period_a", "period_b"},
    ),
    "/v1/insight": (
        build_insights,
        {
            "summary", "funnel", "trend", "interest", "compare",
            "origin_transactions", "period", "max_items", "alpha",
        },
        set(),  # semua argumen opsional; hasil kosong bila tak ada sumber.
    ),
}


def _jsonable(value):
    """Fallback encoder: tanggal -> ISO; selain itu biarkan json gagal."""
    if isinstance(value, (date, datetime)):
        return value.isoformat()
    raise TypeError(f"tidak bisa di-JSON-kan: {type(value).__name__}")


def _dumps(payload: dict) -> bytes:
    # allow_nan=False: float tak-hingga/NaN tidak boleh keluar sebagai JSON
    # tak-valid; bila muncul, itu 500 (dicatat), bukan respons rusak.
    return json.dumps(
        payload, ensure_ascii=False, allow_nan=False, default=_jsonable
    ).encode("utf-8")


class _Handler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    # -- util ---------------------------------------------------------------
    def _send(self, status: int, payload: dict) -> None:
        body = _dumps(payload)
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _error(self, status: int, code: str, message: str) -> None:
        self._send(status, {"error": code, "message": message})

    def log_message(self, fmt: str, *args) -> None:
        # Log akses minimal ke stderr (tanpa body request).
        sys.stderr.write("%s - %s\n" % (self.address_string(), fmt % args))

    # -- auth ---------------------------------------------------------------
    def _authorized(self) -> bool:
        expected = self.server.internal_key
        got = self.headers.get("X-Internal-Key")
        return got is not None and got == expected

    # -- rute ---------------------------------------------------------------
    def do_GET(self) -> None:  # noqa: N802 - nama dari BaseHTTPRequestHandler
        if self.path == "/health":
            self._send(200, {"status": "ok", "algorithm_version": ALGORITHM_VERSION})
        elif self.path.startswith("/v1/"):
            self._error(405, "method_not_allowed", "endpoint ini hanya menerima POST.")
        else:
            self._error(404, "not_found", f"path tidak dikenal: {self.path}")

    def do_POST(self) -> None:  # noqa: N802 - nama dari BaseHTTPRequestHandler
        if self.path not in ENDPOINTS:
            self._error(404, "not_found", f"path tidak dikenal: {self.path}")
            return
        if not self._authorized():
            self._error(401, "unauthorized", "X-Internal-Key salah atau tidak ada.")
            return

        try:
            length = int(self.headers.get("Content-Length") or 0)
        except ValueError:
            self._error(422, "validation", "Content-Length tidak valid.")
            return
        if length > MAX_BODY_BYTES:
            self._error(413, "too_large", f"body melebihi batas {MAX_BODY_BYTES} byte.")
            return
        raw = self.rfile.read(length) if length > 0 else b""

        try:
            body = json.loads(raw.decode("utf-8"))
        except (ValueError, UnicodeDecodeError):
            self._error(422, "validation", "body harus JSON yang valid.")
            return
        if not isinstance(body, dict):
            self._error(422, "validation", "body harus JSON object.")
            return

        func, allowed, required = ENDPOINTS[self.path]
        unknown = sorted(set(body) - allowed)
        if unknown:
            self._error(
                422, "validation",
                f"kunci tak dikenal untuk {self.path}: {', '.join(unknown)}.",
            )
            return
        missing = sorted(required - set(body))
        if missing:
            self._error(
                422, "validation",
                f"kunci wajib hilang untuk {self.path}: {', '.join(missing)}.",
            )
            return

        try:
            result = func(**{k: body[k] for k in allowed if k in body})
        except AnalyticsInputError as exc:
            self._error(422, "validation", str(exc))
            return
        except Exception:  # noqa: BLE001 - 500 generik, traceback ke stderr saja
            traceback.print_exc()
            self._error(500, "internal", "Terjadi kesalahan internal.")
            return

        try:
            self._send(200, result)
        except (TypeError, ValueError):
            # Hasil mengandung nilai yang tidak bisa di-JSON-kan / tak-hingga.
            traceback.print_exc()
            self._error(500, "internal", "Hasil analisis tidak bisa di-JSON-kan.")

    # Method lain -> 405 (kecuali GET /health yang ditangani do_GET).
    def do_PUT(self) -> None:  # noqa: N802
        self._error(405, "method_not_allowed", "hanya GET /health dan POST /v1/*.")

    def do_DELETE(self) -> None:  # noqa: N802
        self._error(405, "method_not_allowed", "hanya GET /health dan POST /v1/*.")

    def do_PATCH(self) -> None:  # noqa: N802
        self._error(405, "method_not_allowed", "hanya GET /health dan POST /v1/*.")


def main() -> int:
    key = os.environ.get("ANALYTICS_INTERNAL_KEY", "")
    if not key:
        print(
            "ANALYTICS_INTERNAL_KEY kosong: isi env dengan kunci lokal "
            "(jangan di-commit), lalu jalankan lagi.",
            file=sys.stderr,
        )
        return 2
    try:
        port = int(os.environ.get("ANALYTICS_PORT", str(DEFAULT_PORT)))
    except ValueError:
        print("ANALYTICS_PORT harus angka.", file=sys.stderr)
        return 2
    if not 1 <= port <= 65535:
        print("ANALYTICS_PORT harus 1-65535.", file=sys.stderr)
        return 2
    host = os.environ.get("ANALYTICS_HOST", DEFAULT_HOST)

    server = ThreadingHTTPServer((host, port), _Handler)
    server.daemon_threads = True
    server.internal_key = key  # type: ignore[attr-defined]
    print(
        f"Analytics service jalan di http://{host}:{port} "
        f"(algoritma {ALGORITHM_VERSION})",
        file=sys.stderr,
    )
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("Berhenti.", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
