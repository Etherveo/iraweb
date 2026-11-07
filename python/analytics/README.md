# Analytics Service — Ira Stationery (M5)

HTTP service internal untuk perhitungan analitik FR-01–FR-06. Laravel mengirim
"facts" (per hari WITA per produk, tanpa data pribadi) dan service mengembalikan
hasil terstruktur. Service ini HANYA dengar di loopback (`127.0.0.1:8001`) dan
wajib pakai header `X-Internal-Key`.

## File

| File | Keterangan |
| --- | --- |
| `server.py` | HTTP wrapper (stdlib saja): `ThreadingHTTPServer`, auth kunci internal, satu endpoint POST per analisis. File ini yang dijalankan. |
| `algorithms/` | Modul algoritma AI B (referensi). Salinan byte-identical dari `about-script/ai-b-output/analytics-service/algorithms/` — **JANGAN DIEDIT**. Isinya fungsi murni (stdlib saja): `summarize`, `analyze_trend`, `analyze_funnel`, `compare_periods`, `analyze_interest`, `analyze_category`, `build_insights` (+ helper `_common`). |
| `tests/` | Tes pytest modul AI B (salinan dari sumber yang sama). Dipakai untuk memeriksa salinan tetap utuh. |
| `pytest.ini` | Konfigurasi pytest modul (`pythonpath = .`, `testpaths = tests`). |

## Asal modul AI B

`algorithms/` + `tests/` disalin dari `about-script/ai-b-output/analytics-service/`
(hasil AI B, status P5 LOLOS 2026-11-07: pytest 172 hijau di VM Python 3.12.3,
172 hijau di laptop Ren Python 3.14.3). Salinan diverifikasi byte-identical
dengan `diff -r` saat penyalinan; bila ada yang meragukan isinya, bandingkan
lagi dengan sumber itu. Perbaikan / perubahan perilaku fungsi analitik TIDAK
dilakukan di sini — kalau perlu, perbaiki di sumber (AI B / script Ren) lalu
salin ulang dan verifikasi lagi.

## Dependensi

Stdlib saja. Python >= 3.10 (modul diuji 3.12.3 dan 3.14.3). Tidak ada akses
database/file/network selain socket listen + membaca kunci dari env.
Tidak ada library yang perlu di-install.

## Cara menjalankan (server)

```bash
# Dari folder ini (iraweb/python/analytics/)
ANALYTICS_INTERNAL_KEY=<kunci-lokal> python3 server.py
# -> Analytics service jalan di http://127.0.0.1:8001

# Host/port lain (opsional; default 127.0.0.1:8001)
ANALYTICS_INTERNAL_KEY=<kunci-lokal> ANALYTICS_HOST=127.0.0.1 ANALYTICS_PORT=8001 python3 server.py
```

Kunci dibaca dari env `ANALYTICS_INTERNAL_KEY` saja. Server MENOLAK start bila
env kunci kosong. Kunci yang sama harus diisi di `.env` Laravel
(`ANALYTICS_INTERNAL_KEY`) — digenerate lokal oleh Ren seperti
`SIMULATOR_WEBHOOK_SECRET`; jangan pernah di-commit.

## Endpoint

| Method & path | Fungsi AI B | Dipakai untuk |
| --- | --- | --- |
| `GET /health` | — | Cek hidup (tanpa auth): `{"status": "ok", "algorithm_version": ...}` |
| `POST /v1/summary` | `summarize` | FR-01 dashboard |
| `POST /v1/trend` | `analyze_trend` | FR-02 tren |
| `POST /v1/funnel` | `analyze_funnel` | FR-03 funnel |
| `POST /v1/compare` | `compare_periods` | FR-04 perbandingan periode |
| `POST /v1/interest` | `analyze_interest` | FR-05 aktivitas/minat |
| `POST /v1/category` | `analyze_category` | FR-06 kategori |
| `POST /v1/insight` | `build_insights` | Insight INS-01..06 (dari hasil fungsi lain) |

Semua endpoint POST wajib header `X-Internal-Key` (salah/tidak ada -> 401).
Body JSON memakai nama parameter yang sama dengan fungsi Python-nya
(mis. `facts`, `products`, `period`, `order_totals`, `previous_facts`).
Respons 200 adalah dict hasil fungsi itu apa adanya.

Contoh (dengan `curl`):

```bash
curl -s -X POST http://127.0.0.1:8001/v1/summary \
  -H "Content-Type: application/json" \
  -H "X-Internal-Key: <kunci-lokal>" \
  -d '{"facts": [], "products": [], "period": {"start": "2026-11-01", "end": "2026-11-07"}}'
```

Galat: body bukan JSON object / kunci tak dikenal / kunci wajib hilang /
input tidak sesuai kontrak -> 422 `{"error": "validation", "message": ...}`;
path tak dikenal -> 404; method lain -> 405; body > 10 MB -> 413;
galat tak terduga -> 500 `{"error": "internal"}` tanpa traceback di respons.

## Cara menjalankan tes modul

```bash
# Dari folder ini; butuh pytest (pip install pytest) — hanya untuk tes, bukan
# dependensi service.
python -m pytest
```

## Catatan zona waktu

Tanggal pada request adalah TANGGAL KALENDER WITA (`YYYY-MM-DD`) apa adanya.
Server ini TIDAK melakukan konversi zona waktu (bukan +7, bukan +8); konversi
WITA<->UTC adalah tugas pemanggil (Laravel, lihat `AnalyticsFactsBuilder`).
