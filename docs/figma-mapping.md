# Pemetaan Figma — Ira-IPPL (Design canvas)

**Sumber Figma:** https://www.figma.com/design/L36MtFC0W90vyfvZwKcMyl/Ira-IPPL?node-id=0-1&p=f&t=Tswme1GQrTqvp2SG-0
**FileKey:** `L36MtFC0W90vyfvZwKcMyl` — **Halaman:** Page 1 (`0:1`) saja — **Diakses:** 2026-11-04 lewat konektor Figma yang terhubung (akun Ihsan Benawan).
**File ini adalah versi Design canvas, BUKAN Figma Make.** Versi Make adalah prototipe React terpisah dan tidak diaudit di sini; jangan jadikan kode React Make sebagai basis Laravel.

> **Cara pakai dokumen ini:** Dokumen ini adalah peta, bukan kode. Kode React/Tailwind yang dihasilkan Figma WAJIB dikonversi ke Blade + Tailwind + Alpine + Chart.js sesuai `docs/UI.md` dan `AGENTS.md`/`CLAUDE.md`. Jangan tempel kode React Figma mentah-mentah, jangan pakai posisi absolut kalau layout responsif Blade bisa menyatakannya, dan jangan pakai URL aset Figma sementara sebagai sumber runtime — URL itu kedaluwarsa setelah 7 hari. Pakai export PNG di `docs/figma/exports/` sebagai referensi visual yang tahan lama.

## Ringkasan hasil audit

- **71 frame tingkat atas** di Page 1: 67 layar/keadaan yang diekspor + 3 frame internal `Building StateSheets` + 1 placeholder `visual-reference` (400x300, bukan layar). Keduanya terakhir dikecualikan dari export.
- **Cakupan:** Akun/masuk, Storefront, Admin (katalog/stok + analitik), simulasi pembayaran, dan keadaan sistem — lengkap untuk permukaan 1–3 di `docs/UI.md` §9 dan alur W1–W7 di `docs/Blueprint-Workflow.md`.
- **GAP UTAMA — Panel Developer TIDAK ADA di file Figma ini.** Tidak ada frame Login Developer, Buat Akun Simulasi, Transaksi user-first, Transaksi period-first, atau Jejak/Audit & Reset. Layar-layar itu (UI.md §9.5, Workflow §7.3, W8–W11) harus dibangun dari `docs/UI.md` + `docs/Blueprint-Workflow.md` + `docs/PRD.md` tanpa referensi Figma, dengan penanda permanen SIMULATOR. Jangan mengarang layar Developer dari layar Admin.
- **Token yang terverifikasi dari konteks Dashboard desktop (`3:172447`):** lavender `#6655F5` (mark toko, nav aktif), latar `#e9ebf8` / `#eaecf8`, teks `#171a36` / `#515a8c`, nav aktif `#e8e5ff`, bayangan ganda neumorphism (`-4px -4px 8px white, 4px 4px 8px #d1d5e7`), radius 12–14px, tipografi Manrope (ExtraBold/Bold) + Inter (Regular). Ini cocok dengan penyimpangan blueprint §13 yang dicatat di `docs/UI.md` §16, bukan dengan default generic. Variabel Figma (`get_variable_defs`) untuk frame itu mengembalikan kosong — token di file ini tertanam sebagai fill/font di node, bukan sebagai variabel bernama, jadi jangan mencari variabel Figma yang tidak ada.
- **Ikon:** gaya Lucide sebagai SVG (mis. layout-dashboard, filter, chart-bar, package, tags, clipboard-list, credit-card, users, log-out, search, calendar-days, info, trending-up). Export PNG sudah merender ikon-ikon itu; SVG ikon individual tidak diekspor terpisah di paket ini, pakai ikon Lucide yang setara di Blade bila perlu.
- **Yang TIDAK diverifikasi untuk semua frame dalam audit ini:** isi teks tiap layar (mis. apakah ada login sosial, dark-mode toggle, nama/brand lain, atau mata uang selain Rp di layar masuk). Konteks detail hanya diambil untuk Dashboard desktop; sisanya dari metadata frame + nama. Larangan di `docs/UI.md` §15 tetap berlaku penuh saat coding: tanpa login sosial, tanpa dark-mode toggle, tanpa halaman Profil/Settings, tanpa branding Amazon/NovaApp, tanpa `$`, tanpa nama Sarah/Jessica, tanpa upsell Premium, tanpa foto produk sebagai pengganti komponen. Verifikasi layar masuk sebelum coding auth.

## Pola komponen yang berulang (dari metadata frame)

- **Bilah atas:** Merek ("Ira Stationery") + Peran (Admin Toko / Pembeli).
- **Pengantar halaman:** Judul + Keterangan, plus catatan "data contoh" dengan ikon info di banyak layar.
- **Panel konten:** kartu neumorphism dengan padding, judul, dan aksi di dalamnya.
- **Sidebar Admin:** Identitas toko (mark `i.` lavender + nama toko + peran), navigasi bergrup (mis. grup ANALITIK: Dashboard, Tren, Funnel, Perbandingan, Aktivitas, Kategori).
- **Tombol aksi:** label + ikon (arrow-left, sliders-horizontal, dll.), radius besar, bayangan neumorphism.
- **Grafik analitik:** Chart (bar/line, tanpa sumbu penuh di beberapa kartu), filter periode, dan tabel sebagai alternatif grafik — sesuai Workflow W7 (filter periode + asal data harus memperbarui grafik dan tabel bersamaan).
- **Keadaan:** layar "Keadaan …" (mobile saja, sangat panjang) adalah kumpulan state, bukan satu layar navigasi — pakai sebagai referensi state (loading, kosong, galat, sukses, kedaluwarsa) saat mengimplementasikan layar terkait, jangan jadikan satu route.

## Inventaris frame → surface → export

Export: `docs/figma/exports/<slug>.png` (PNG, skala 1, dari `download_assets`). NodeId dalam format API (`123:456`). Ukuran adalah ukuran frame Figma (desktop 1280 lebar, mobile 390 lebar).

### Akun / Masuk (6 frame)

| Layar Figma | NodeId | Ukuran | Export |
|---|---|---|---|
| Daftar pembeli — desktop | `3:102950` | 1280x1115 | `docs/figma/exports/daftar-pembeli-desktop.png` |
| Daftar pembeli — mobile | `3:103000` | 390x991 | `docs/figma/exports/daftar-pembeli-mobile.png` |
| Login Admin — desktop | `3:172374` | 1280x960 | `docs/figma/exports/login-admin-desktop.png` |
| Login Admin — mobile | `3:172417` | 390x844 | `docs/figma/exports/login-admin-mobile.png` |
| Masuk pembeli — desktop | `3:102884` | 1280x960 | `docs/figma/exports/masuk-pembeli-desktop.png` |
| Masuk pembeli — mobile | `3:102922` | 390x844 | `docs/figma/exports/masuk-pembeli-mobile.png` |

### Storefront (Pembeli) (11 frame)

| Layar Figma | NodeId | Ukuran | Export |
|---|---|---|---|
| Checkout — desktop | `3:103851` | 1280x1265 | `docs/figma/exports/checkout-desktop.png` |
| Checkout — mobile | `3:104000` | 390x2243 | `docs/figma/exports/checkout-mobile.png` |
| Detail pesanan — desktop | `3:104844` | 1280x1380 | `docs/figma/exports/detail-pesanan-desktop.png` |
| Detail pesanan — mobile | `3:104961` | 390x2100 | `docs/figma/exports/detail-pesanan-mobile.png` |
| Katalog — desktop | `3:103040` | 1280x2555 | `docs/figma/exports/katalog-desktop.png` |
| Katalog — mobile | `3:103276` | 390x3272 | `docs/figma/exports/katalog-mobile.png` |
| Keadaan katalog, keranjang & checkout — mobile | `3:106453` | 390x7404 | `docs/figma/exports/keadaan-katalog-keranjang-checkout-mobile.png` |
| Keranjang — desktop | `3:103646` | 1280x960 | `docs/figma/exports/keranjang-desktop.png` |
| Keranjang — mobile | `3:103750` | 390x1524 | `docs/figma/exports/keranjang-mobile.png` |
| Riwayat pesanan — desktop | `3:104501` | 1280x1629 | `docs/figma/exports/riwayat-pesanan-desktop.png` |
| Riwayat pesanan — mobile | `3:104679` | 390x3345 | `docs/figma/exports/riwayat-pesanan-mobile.png` |

### Admin — Katalog & stok (18 frame)

| Layar Figma | NodeId | Ukuran | Export |
|---|---|---|---|
| Atur stok — desktop | `3:189768` | 1280x1385 | `docs/figma/exports/atur-stok-desktop.png` |
| Atur stok — mobile | `3:189893` | 390x1534 | `docs/figma/exports/atur-stok-mobile.png` |
| Detail produk — desktop | `3:103501` | 1280x1139 | `docs/figma/exports/detail-produk-desktop.png` |
| Detail produk — mobile | `3:103574` | 390x1726 | `docs/figma/exports/detail-produk-mobile.png` |
| Kategori produk — desktop | `3:188137` | 1280x1214 | `docs/figma/exports/kategori-produk-desktop.png` |
| Kategori produk — mobile | `3:188330` | 390x1790 | `docs/figma/exports/kategori-produk-mobile.png` |
| Produk — desktop | `3:188851` | 1280x1642 | `docs/figma/exports/produk-desktop.png` |
| Produk — mobile | `3:189086` | 390x2512 | `docs/figma/exports/produk-mobile.png` |
| Riwayat stok — desktop | `3:189980` | 1280x1486 | `docs/figma/exports/riwayat-stok-desktop.png` |
| Riwayat stok — mobile | `3:190172` | 390x2266 | `docs/figma/exports/riwayat-stok-mobile.png` |
| Tambah kategori — desktop | `3:188475` | 1280x1148 | `docs/figma/exports/tambah-kategori-desktop.png` |
| Tambah kategori — mobile | `3:188588` | 390x1209 | `docs/figma/exports/tambah-kategori-mobile.png` |
| Tambah produk — desktop | `3:189314` | 1280x1598 | `docs/figma/exports/tambah-produk-desktop.png` |
| Tambah produk — mobile | `3:189441` | 390x1719 | `docs/figma/exports/tambah-produk-mobile.png` |
| Ubah kategori — desktop | `3:188663` | 1280x1148 | `docs/figma/exports/ubah-kategori-desktop.png` |
| Ubah kategori — mobile | `3:188776` | 390x1209 | `docs/figma/exports/ubah-kategori-mobile.png` |
| Ubah produk — desktop | `3:189530` | 1280x1672 | `docs/figma/exports/ubah-produk-desktop.png` |
| Ubah produk — mobile | `3:189668` | 390x1834 | `docs/figma/exports/ubah-produk-mobile.png` |

### Admin — Analitik (FR-01–FR-06) (14 frame)

| Layar Figma | NodeId | Ukuran | Export |
|---|---|---|---|
| Aktivitas & Minat analitik — desktop | `3:174308` | 1280x2598 | `docs/figma/exports/aktivitas-minat-analitik-desktop.png` |
| Aktivitas & Minat analitik — mobile | `3:174607` | 390x3788 | `docs/figma/exports/aktivitas-minat-analitik-mobile.png` |
| Dashboard analitik — desktop | `3:172447` | 1280x2377 | `docs/figma/exports/dashboard-analitik-desktop.png` |
| Dashboard analitik — mobile | `3:172731` | 390x3654 | `docs/figma/exports/dashboard-analitik-mobile.png` |
| Funnel analitik — desktop | `3:173499` | 1280x2089 | `docs/figma/exports/funnel-analitik-desktop.png` |
| Funnel analitik — mobile | `3:173710` | 390x2739 | `docs/figma/exports/funnel-analitik-mobile.png` |
| Kategori analitik — desktop | `3:174884` | 1280x2632 | `docs/figma/exports/kategori-analitik-desktop.png` |
| Kategori analitik — mobile | `3:175142` | 390x3369 | `docs/figma/exports/kategori-analitik-mobile.png` |
| Keadaan & lembar analitik — desktop | `3:175371` | 1280x3008 | `docs/figma/exports/keadaan-lembar-analitik-desktop.png` |
| Keadaan & lembar analitik — mobile | `3:175504` | 390x5476 | `docs/figma/exports/keadaan-lembar-analitik-mobile.png` |
| Perbandingan analitik — desktop | `3:173884` | 1280x2262 | `docs/figma/exports/perbandingan-analitik-desktop.png` |
| Perbandingan analitik — mobile | `3:174112` | 390x2881 | `docs/figma/exports/perbandingan-analitik-mobile.png` |
| Tren analitik — desktop | `3:172992` | 1280x2308 | `docs/figma/exports/tren-analitik-desktop.png` |
| Tren analitik — mobile | `3:173262` | 390x2947 | `docs/figma/exports/tren-analitik-mobile.png` |

### Simulasi pembayaran (pemegang token) (16 frame)

| Layar Figma | NodeId | Ukuran | Export |
|---|---|---|---|
| Detail pembayaran — desktop | `3:105449` | 1280x2106 | `docs/figma/exports/detail-pembayaran-desktop.png` |
| Detail pembayaran — mobile | `3:105565` | 390x3068 | `docs/figma/exports/detail-pembayaran-mobile.png` |
| Hasil berhasil — desktop | `3:105680` | 1280x1344 | `docs/figma/exports/hasil-berhasil-desktop.png` |
| Hasil berhasil — mobile | `3:105749` | 390x1451 | `docs/figma/exports/hasil-berhasil-mobile.png` |
| Hasil gagal — desktop | `3:105817` | 1280x1414 | `docs/figma/exports/hasil-gagal-desktop.png` |
| Hasil gagal — mobile | `3:105889` | 390x1581 | `docs/figma/exports/hasil-gagal-mobile.png` |
| Hasil kedaluwarsa — desktop | `3:105960` | 1280x1276 | `docs/figma/exports/hasil-kedaluwarsa-desktop.png` |
| Hasil kedaluwarsa — mobile | `3:106027` | 390x1383 | `docs/figma/exports/hasil-kedaluwarsa-mobile.png` |
| Instruksi QR — desktop | `3:104151` | 1280x1138 | `docs/figma/exports/instruksi-qr-desktop.png` |
| Instruksi QR — mobile | `3:104233` | 390x2097 | `docs/figma/exports/instruksi-qr-mobile.png` |
| Instruksi VA — desktop | `3:104319` | 1280x1403 | `docs/figma/exports/instruksi-va-desktop.png` |
| Instruksi VA — mobile | `3:104408` | 390x2407 | `docs/figma/exports/instruksi-va-mobile.png` |
| Riwayat pembayaran — desktop | `3:105077` | 1280x1774 | `docs/figma/exports/riwayat-pembayaran-desktop.png` |
| Riwayat pembayaran — mobile | `3:105270` | 390x3677 | `docs/figma/exports/riwayat-pembayaran-mobile.png` |
| Struk simulasi — desktop | `3:106093` | 1280x1516 | `docs/figma/exports/struk-simulasi-desktop.png` |
| Struk simulasi — mobile | `3:106182` | 390x1777 | `docs/figma/exports/struk-simulasi-mobile.png` |

### Keadaan sistem (state sheets, mobile saja) (2 frame)

| Layar Figma | NodeId | Ukuran | Export |
|---|---|---|---|
| Keadaan akun & sistem — mobile | `3:106270` | 390x5869 | `docs/figma/exports/keadaan-akun-sistem-mobile.png` |
| Keadaan pembayaran & pesanan — mobile | `3:106682` | 390x10264 | `docs/figma/exports/keadaan-pembayaran-pesanan-mobile.png` |

### Internal (bukan layar) (4 frame)

| Layar Figma | NodeId | Ukuran | Export |
|---|---|---|---|
| Building StateSheets | `3:190305` | 1280x1300 | — dikecualikan, bukan layar |
| Building StateSheets | `3:190300` | 1280x1450 | — dikecualikan, bukan layar |
| Building StateSheets | `3:190295` | 1280x1580 | — dikecualikan, bukan layar |
| visual-reference | `4:2` | 400x300 | — dikecualikan, bukan layar |

## Pemetaan ke dokumen acuan

- **Storefront + auth + checkout + instruksi pembayaran + hasil + struk** → Workflow W2 (browse/view), W3 (cart/checkout), W4 (pilih metode + instruksi), W5 (simulasi lewat QR, cabang berhasil/gagal/kedaluwarsa/token dipakai ulang/batal), W6 (riwayat/detail pesanan & pembayaran).
- **Admin katalog/stok** → Workflow W1 (admin menyiapkan toko: kategori, produk, stok, cek katalog dari sisi pembeli).
- **Admin analitik** → Workflow W7 + FR-01 Dashboard, FR-02 Tren, FR-03 Funnel, FR-04 Perbandingan periode, FR-05 Aktivitas & Minat, FR-06 Kategori/Karakteristik. Setiap layar analitik wajib punya filter periode + asal data (normal/simulator/keduanya) dan versi tabel.
- **Panel Developer** → TIDAK ADA di Figma. Workflow W8 (bulk akun maks 100/request), W9 (user-first), W10 (period-first), W11 (audit/reset). Bangun dari dokumen, bukan dari Figma.
- **Simulasi pembayaran** → Dokumen `docs/payment-gateway-simulator.md` + Workflow W5 + UI.md §10. Halaman ini mobile-first, label SIMULASI PEMBAYARAN besar di atas lipatan, hitung mundur, panel HTTP request/response simulasi, tanpa logo/warna bank/e-wallet.

## Catatan push / keterbatasan export (penting untuk Claude di laptop)

- **PNG adalah biner.** PNG TIDAK bisa di-push lewat konektor snapshot GitHub dari VM (snapshot hanya file teks UTF-8, maks 16 MiB total), dan SSH dari VM saat ini diblokir proxy egress VM. Jadi paket export ini disiapkan di VM di `docs/figma/exports/`, dan push ke GitHub yang membawa file biner harus dilakukan dari laptop Ren dengan git biasa (bukan API per file), dalam satu commit, setelah file-file ini disalin ke repo laptop. Dokumen pemetaan ini (teks) bisa di-push lewat snapshot, tetapi jangan push dokumen ini sendirian tanpa export-nya kalau referensinya jadi putus — lebih baik keduanya tiba bersamaan lewat push laptop.
- **Manifest:** `docs/figma/exports/MANIFEST.json` (dihasilkan skrip export) berisi nama, nodeId, file, ukuran, dan status tiap frame. Kalau ada frame berstatus gagal di manifest, jangan mengarang gambarnya — laporkan dan export ulang frame itu saja.
- **Batas tinggi export Figma:** 4 frame keadaan mobile yang sangat panjang dibatasi Figma ke tinggi 4096px sehingga diperkecil (bukan skala 1 penuh): `keadaan-akun-sistem-mobile.png` (273x4096), `keadaan-katalog-keranjang-checkout-mobile.png` (216x4096), `keadaan-lembar-analitik-mobile.png` (298x4096), `keadaan-pembayaran-pesanan-mobile.png` (156x4096). 63 frame lainnya pada skala penuh/near-full. Frame-frame itu adalah kumpulan state, bukan layar tunggal, jadi penurunan skala ini tidak menghalangi coding layar terkait.
- **Sumber kebenaran visual setelah push:** export PNG di repo, bukan URL Figma sementara dan bukan screenshot yang diambil ulang tanpa konteks. Kalau Figma diubah setelah audit ini, audit dan export harus diulang dan dokumen ini diperbarui, jangan diam-diam mencampur versi lama dan baru.
