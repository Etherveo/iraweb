# Prompt — Polish UI Panel Simulator M4 (Argon)

## Tugas
Polish tampilan panel Developer (panel simulator M4) supaya selevel dengan halaman lain yang sudah dipoles Tahap 1–7 — tanpa mengubah perilaku, data, atau backend apa pun.

## Kenapa
Panel simulator M4 baru selesai dibangun Rin (commit terakhir `e641b52`, branch `feature/m4-simulator`, 10 view + 8 partial + shell developer + CSS `.developer-page`). Fungsinya sudah lengkap dan gate laptop lolos (235 tes Pest hijau, 0 deprecated). Tampilannya dibangun cepat mengikuti token, belum lewat satu putaran polish seperti halaman pembeli/admin. Keputusan Ren 2026-11-08: **M4 tetap di branch sampai polish selesai** — polish dikerjakan dari branch `feature/m4-simulator` (bukan dari `main`), lalu di-merge kembali ke `feature/m4-simulator`; baru setelah itu Ren merge `feature/m4-simulator` ke `main`. Prompt ini diperbarui Rin 2026-11-08 mengikuti keputusan itu.

## Yang dipoles (views + CSS saja)
Semua di `resources/views/developer/` (10 halaman view, 915 baris — 9 baru di M4, 1 sudah ada sejak M0+M1 yaitu `akun-simulasi-index.blade.php` — + 8 partial, 233 baris):
- `dashboard-index.blade.php` — Beranda Simulator (stat grid + run terakhir)
- `akun-simulasi-index.blade.php` + `akun-create.blade.php` — daftar & buat akun simulasi
- `tx-user.blade.php` + `tx-period.blade.php` + `funnel.blade.php` — form transaksi & funnel
- `preview.blade.php` — pratinjau eksekusi (tabel rencana + tombol jalankan)
- `run-index.blade.php` + `run-show.blade.php` — jejak run & detail run
- `reset-index.blade.php` — reset per-run & reset semua
- 8 partial di `resources/views/developer/partials/` (`_account-picker`, `_action-label`, `_distribution-fields`, `_period-fields`, `_product-picker`, `_seed-fields`, `_status-chip`, `_status-mix-fields`)
- `resources/css/app.css` — sistem `.developer-page` yang sudah ada (mulai ~baris 1168: `.developer-head`, `.developer-stat-grid`, `.developer-table`, `.developer-form`, `.developer-kv`, tombol `.btn-primary/.btn-secondary/.btn-danger` scoped developer) + `resources/js/app.js` hanya kalau interaksi yang sudah ada perlu diperbaiki (jangan tambah perilaku baru).
- `resources/views/layouts/app.blade.php` — HANYA bagian shell developer (sidebar + SimBanner) kalau tampilannya perlu dirapikan; jangan ubah struktur/route-nya.

Acuan visual: `docs/UI.md` §16 (lavender #6655F5, Manrope+Inter, radius 20–24, neumorphic, dual shadow) + halaman pembeli/admin hasil Tahap 1–7 sebagai contoh yang sudah benar. **Panel Developer TIDAK ada di Figma** — jangan cari padanannya di Figma, dan jangan samakan dengan halaman pembeli; aksen oranye SIMULASI (bukan lavender) memang disengaja di panel ini, pertahankan.

Yang diperiksa/diperbaiki dalam polish ini:
1. Konsistensi dengan halaman lain: jarak, tipografi, radius, bayangan, tombol, dan kartu seragam dengan pola Tahap 1–7.
2. Mobile: cek viewport sempit — stat grid, form 2 kolom (`.developer-field-grid`), tabel (`.developer-table-wrap` harus tetap bisa di-scroll horizontal, bukan meluber), sidebar developer + hamburger.
3. Tabel & daftar panjang: header tabel, zebra/hover kalau polanya ada di halaman lain, state kosong (mis. belum ada run) tetap jelas.
4. Form: label, hint, pesan galat per field, input dipertahankan saat galat — ikuti pola form pembeli/admin yang sudah ada; jangan ubah nama field, validasi, atau route.
5. Status & jejak: chip status, label aksi, seed, dan waktu (WITA) harus tetap terbaca dan jujur — jangan sembunyikan atau permak info audit.
6. Aksesibilitas: focus-visible pada tombol/input (sudah ada di `.developer-page`, pastikan tidak hilang), kontras teks, tombol hanya-ikon harus punya nama aksesibel.

## Yang TIDAK boleh disentuh
- JANGAN ubah controller, FormRequest, route, service, model, migrasi, config, atau file tes apa pun. Backend M4 sudah selesai dan teruji — polish ini views + CSS (+ JS minimal) saja, sama seperti Tahap 1–7 dulu.
- JANGAN ubah SimBanner permanen ("⚠ SIMULASI — data eksperimental", `layouts/app.blade.php` ~baris 255) — banner itu harus selalu terlihat, tidak bisa ditutup.
- JANGAN ubah mekanisme reset: konfirmasi ketik-frasa `HAPUS RUN` (reset satu run) dan `HAPUS SEMUA SIMULASI` (reset semua) harus tetap seperti itu — jangan dipermudah, jangan ganti jadi klik biasa.
- JANGAN ubah halaman pratinjau jadi menulis data — pratinjau tetap tanpa tulis, eksekusi tetap lewat tombol "Jalankan simulasi ini".
- JANGAN ubah teks/angka yang di-assert tes (label aksi, status run, seed) dan JANGAN edit `docs/PRD.md` / `docs/blueprint.md` / `AGENTS.md` / `CLAUDE.md`.
- JANGAN ubah halaman pembeli, admin, payments, atau shop — hanya panel developer.

## Verifikasi
- `npm run build` harus sukses.
- `./vendor/bin/pest` harus tetap 235 tes hijau (190 lama + 45 baru M4) — polish tidak boleh memerahkan tes.
- Cek manual di browser (login sebagai developer): buka ke-10 halaman panel, cek desktop + mobile sempit, cek form pratinjau → eksekusi → jejak run → reset (cukup sampai halaman konfirmasi reset, JANGAN benar-benar menjalankan reset semua di database yang berisi data penting — pakai database tes/lokal saja).
- Laporkan hasil build + Pest + daftar halaman yang dicek di laporan akhir.

## Git (kamu yang kerjakan)
- Branch `feature/polish-panel-simulator` SUDAH dibuat Ren dari `feature/m4-simulator` terbaru (commit `e641b52`) — jangan buat baru, langsung `git checkout feature/polish-panel-simulator` lalu `git pull`. M4 belum merge ke `main`, dan memang begitu maunya (keputusan Ren 2026-11-08: tetap di branch sampai polish selesai). JANGAN branch dari `main`.
- Commit + push ke branch, lalu BERHENTI dan tunggu kata "aman" dari Ren.
- Setelah Ren bilang aman: `git checkout feature/m4-simulator` → `git pull` → `git merge feature/polish-panel-simulator` (harus fast-forward) → `git push origin feature/m4-simulator` → `git push origin --delete feature/polish-panel-simulator` → `git branch -d feature/polish-panel-simulator` → laporkan commit `feature/m4-simulator` terakhir. Setelah itu Ren sendiri yang merge `feature/m4-simulator` ke `main` — BUKAN kamu.
- JANGAN merge sebelum Ren bilang aman.
