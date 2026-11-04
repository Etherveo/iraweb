# UI.md — Ira Stationery: E-commerce dengan Pendekatan Simulator

| Atribut | Isi |
| --- | --- |
| Tujuan | Spesifikasi antarmuka (visual, komponen, gerak, layar, dan keadaan) untuk web toko Ira Stationery, yang spesifik ke Ira — bukan panduan UI generik. |
| Sumber utama gaya | `Neumorphism.md` (Light Neumorphism: warna, bayangan, tipografi, radius, komponen) dan `Motion.md` (sistem animasi: durasi, easing, reveal, interaksi) di folder proyek ini. |
| Sumber utama fungsi | `PRD.md` (versi Claude, sudah diselaraskan 2026-11-02: SQLite, WITA, tanpa starter kit) dan `blueprint.md` (versi Claude, sudah diselaraskan 2026-11-02) di folder proyek ini. Peta layar dan ID fitur di dokumen ini merujuk ke keduanya. |
| Dokumen pendamping | `Blueprint-Workflow.md` (alur kerja per permukaan, tanpa backend), `DESIGN.md` (aturan UI generik — hanya sebagai rujukan fallback), `Ira-discussion.md`, `Ira-payment-gateway-simulator.md`. |
| Referensi gambar tambahan | `visual-reference.png` (showcase komponen/dashboard — lihat §20). Gambar ini adalah referensi visual saja — nama, merek, mata uang, fitur, dan teks di dalamnya **bukan** fitur Ira, kecuali yang secara eksplisit dinyatakan diadopsi di §20. |
| Hirarki bila bertentangan | 1) PRD/blueprint menang untuk **apa yang harus ada** (fitur, layar, peran, status, label SIMULASI, larangan logo bank/e-wallet, larangan layar profil/pengaturan pembeli). 2) Dokumen ini menang untuk **bagaimana tampilannya** (palet, bayangan, tipografi, radius, gerak) — termasuk di mana ia menyimpang dari token lama blueprint §13 (lihat §17). 3) `DESIGN.md` hanya dipakai bila dokumen ini dan PRD/blueprint diam. |
| Stack implementasi | Laravel + Blade + Tailwind CSS + Alpine.js + Chart.js (dibundel, tanpa CDN). Bukan React/shadcn. |
| Bahasa dan format | Bahasa Indonesia di seluruh UI. Format: `Rp 1.250.000`, tanggal `12 Okt 2026`, jam WITA (Asia/Makassar, UTC+8; waktu disimpan UTC). Tema terang saja, tanpa mode gelap. |
| Status | Draf untuk direview Ren, 2026-11-02. Referensi visual tambahan dari Instagram masih boleh menyempurnakan dokumen ini nanti, tetapi tidak ditunggu lagi untuk versi ini. |

> Catatan Figma Make: dokumen ini adalah spesifikasi visual dan interaksi yang bebas framework. Bila dibuat di Figma Make, hasilnya adalah prototipe React/Next.js — ia **bukan** kode Blade Laravel. Untuk masuk ke aplikasi Laravel, komponen di sini harus di-porting manual ke Blade + Tailwind + Alpine.js, dan grafiknya ke Chart.js (bukan Recharts). Jangan menganggap prototipe Figma sebagai backend: tidak ada autentikasi riil, basis data, webhook, atau servis Python di prototipe.

---

## 1. Prinsip desain Ira

1. **Lembut tapi jelas.** Tampilan Light Neumorphism: permukaan lembut, bayangan ganda yang halus, sudut membulat. Kedalaman adalah pendukung, bukan pengganti hierarki. Judul, angka, dan aksi utama harus langsung terbaca tanpa menebak dari bayangan saja.
2. **Fungsi dulu, dekorasi belakangan.** Setiap efek visual dan setiap animasi harus menjelaskan sesuatu: memberi umpan balik, menunjukkan perubahan keadaan, atau mengarahkan perhatian. Bila tidak menjelaskan apa-apa, jangan dipakai.
3. **Empat permukaan tidak boleh tertukar.** Storefront terasa ramah dan produk-pertama, Admin padat dan analitis, Panel Developer utilitarian dan selalu berlabel SIMULATOR, halaman simulasi pembayaran mobile-first dan kontras tinggi. Pembeda visual tiap permukaan ada di §9 dan wajib dipertahankan.
4. **SIMULASI tidak boleh samar.** Semua yang berasal dari simulator — metode pembayaran, QR, VA, data simulasi, halaman simulasi — selalu berlabel teks SIMULASI/SIMULATOR plus ikon, bukan hanya warna. Label ini tidak boleh dihilangkan demi estetika.
5. **Data contoh adalah fiksi.** Semua angka, nama produk, dan tren di prototipe/mock adalah data contoh fiktif untuk demo, bukan penjualan riil Ira. Jangan pernah menampilkannya sebagai data toko yang sebenarnya.
6. **Gerak halus, tidak mengganggu.** Gerak mengikuti `Motion.md`: halus, halus secukupnya, konsisten, dan menghormati `prefers-reduced-motion`. Animasi tidak boleh menyembunyikan konten penting, menghambat navigasi, atau menjadi satu-satunya cara mengakses fitur.
7. **Satu warna dasar, banyak nuansa.** Mengikuti prinsip Neumorphism (§1 butir 7 dan §4.3): bekerja dengan satu warna dasar dan nuansanya (monochromatic), bukan banyak warna kuat yang berebut perhatian. Warna aksen hanya untuk status, data, dan penanda — bukan untuk setiap elemen.

## 2. Identitas visual: palet warna

Palet ini diambil dari `Neumorphism.md` dan menjadi satu-satunya sumber nilai warna UI. Komponen tidak memakai nilai mentah di luar token di bawah (kecuali area QR, lihat §11).

### 2.1 Warna utama

| Token | HEX | Penggunaan |
| --- | --- | --- |
| Primary | `#6655F5` | Tombol utama, CTA, tautan, ikon aktif |
| Primary Hover | `#5745E8` | Hover tombol utama |
| Primary Active | `#4938D4` | Keadaan aktif/tekan |
| Primary Light | `#E8E5FF` | Highlight, selected, navigasi aktif |
| Primary Soft | `#F0EEFF` | Latar aksen |
| Primary Dark | `#39318F` | Teks/elemen kontras bila perlu |

Gradien hanya sebagai aksen, bukan latar seluruh halaman:

| Nama | Gradien |
| --- | --- |
| Primary Gradient | `#8172FF → #5946EF` |
| Lavender Gradient | `#F0EEFF → #DCD9FF` |
| Blue Gradient | `#E8F1FF → #D5E5FF` |
| Purple Gradient | `#EDE6FF → #D9CCFF` |
| Cyan Gradient | `#E1F8F7 → #C9F0EF` |

Contoh: `background: linear-gradient(135deg, #8172FF, #5946EF);` untuk tombol utama.

### 2.2 Latar dan permukaan

| Token | HEX | Penggunaan |
| --- | --- | --- |
| Background | `#E9EBF8` | Latar utama halaman |
| Background Soft | `#EFF0FA` | Area sekunder |
| Surface | `#EAECF8` | Kartu dan panel |
| Surface Light | `#F4F5FC` | Area elevated (mis. area grafik agar data tetap terbaca) |
| Surface Dark | `#DDE1F1` | Area inset, trek progres |
| Surface Hover | `#E4E7F6` | Hover pada permukaan |

Catatan penting dari `Neumorphism.md`: latar dan permukaan sengaja berdekatan nilainya. Perbedaan kedalaman dibentuk oleh bayangan, bukan border kontras. Karena itu, untuk tabel dan grafik, jangan memaksakan efek neumorphism berlebihan — pakai latar bersih, divider halus, dan header kontras jelas (lihat §7.6 dan §12).

### 2.3 Teks dan netral

| Token | HEX | Penggunaan |
| --- | --- | --- |
| Text Primary | `#171A36` | Judul dan teks utama |
| Text Secondary | `#515A8C` | Deskripsi dan informasi |
| Text Muted | `#858CAD` | Placeholder dan metadata |
| Text Disabled | `#AEB3CA` | Teks nonaktif |
| Border Subtle | `#DCE0F0` | Pemisah elemen |
| Divider | `#D7DBED` | Garis pemisah |

Warna teks per elemen: judul utama `#171A36`, teks isi `#515A8C`, teks sekunder `#737B9F`, placeholder `#9299B6`, tautan `#6655F5`, nonaktif `#AEB3CA`.

### 2.4 Aksen: status dan data

| Token | HEX | Penggunaan |
| --- | --- | --- |
| Blue | `#438BFA` | Informasi dan aktivitas |
| Blue Light | `#DCEBFF` | Latar informasi |
| Purple | `#8875FA` | Highlight sekunder |
| Cyan | `#55C9D5` | Data dan indikator |
| Green | `#25B993` | Sukses dan tren positif |
| Green Light | `#D9F5EC` | Latar sukses |
| Orange | `#F5A443` | Peringatan dan perhatian |
| Orange Light | `#FFF0DA` | Latar peringatan |
| Red | `#E76B82` | Galat dan aksi destruktif |
| Red Light | `#FCE4E9` | Latar galat |

Aturan status (tidak boleh dilanggar): status selalu ditampilkan sebagai **warna + ikon + label teks** (`StatusChip`). Jangan hanya mengandalkan warna. Penanda SIMULASI memakai pasangan peringatan (Orange `#F5A443` pada Orange Light `#FFF0DA`) disertai ikon dan teks "SIMULASI" — lihat §11. Badge SIMULASI pada baris data simulasi di Admin memakai pasangan yang sama.

### 2.5 Token CSS (satu sumber)

Simpan di `resources/css/tokens.css` dan petakan ke tema Tailwind. Komponen tidak memakai nilai mentah.

```css
:root {
  /* Primary */
  --color-primary: #6655F5;
  --color-primary-hover: #5745E8;
  --color-primary-active: #4938D4;
  --color-primary-light: #E8E5FF;
  --color-primary-soft: #F0EEFF;

  /* Background */
  --color-background: #E9EBF8;
  --color-background-soft: #EFF0FA;

  /* Surface */
  --color-surface: #EAECF8;
  --color-surface-light: #F4F5FC;
  --color-surface-dark: #DDE1F1;

  /* Text */
  --color-text-primary: #171A36;
  --color-text-secondary: #515A8C;
  --color-text-muted: #858CAD;
  --color-text-disabled: #AEB3CA;

  /* Accent */
  --color-blue: #438BFA;
  --color-purple: #8875FA;
  --color-cyan: #55C9D5;
  --color-green: #25B993;
  --color-orange: #F5A443;
  --color-red: #E76B82;

  /* Shadows */
  --shadow-xs: 3px 3px 6px #D2D6E7, -3px -3px 6px #FFFFFF;
  --shadow-sm: 5px 5px 10px #D1D5E7, -5px -5px 10px #FFFFFF;
  --shadow-md: 8px 8px 16px #CDD2E5, -8px -8px 16px #FFFFFF;
  --shadow-lg: 12px 12px 24px #CBD0E3, -12px -12px 24px #FFFFFF;
  --shadow-inset: inset 4px 4px 8px #D1D5E7, inset -4px -4px 8px #FFFFFF;

  /* Radius */
  --radius-sm: 10px;
  --radius-md: 14px;
  --radius-lg: 20px;
  --radius-xl: 24px;
  --radius-2xl: 30px;

  /* Typography */
  --font-heading: "Manrope", sans-serif;
  --font-body: "Inter", sans-serif;

  /* Motion */
  --ease-standard: cubic-bezier(0.2, 0.8, 0.2, 1);
  --ease-smooth: cubic-bezier(0.25, 0.1, 0.25, 1);
  --ease-enter: cubic-bezier(0, 0, 0.2, 1);
  --ease-exit: cubic-bezier(0.4, 0, 1, 1);
}
```

## 3. Tipografi

- **Judul (heading), judul halaman, judul kartu, label navigasi, angka statistik yang ditonjolkan: Manrope.** Modern geometric sans-serif, bersih dan elegan, cocok untuk dashboard modern.
- **Isi (body), label formulir, input, isi tabel, deskripsi, metadata: Inter.** Sangat mudah dibaca, proporsinya dirancang untuk antarmuka digital, konsisten pada informasi padat.

| Elemen | Font | Ukuran | Weight | Line Height |
| --- | --- | --- | --- | --- |
| Display | Manrope | 40px | 800 | 1.15 |
| H1 | Manrope | 32px | 800 | 1.2 |
| H2 | Manrope | 26px | 750 (atau 700/800 bila variable font tidak mendukung) | 1.25 |
| H3 | Manrope | 21px | 700 | 1.3 |
| H4 | Manrope | 18px | 700 | 1.35 |
| Body Large | Inter | 16px | 400 | 1.6 |
| Body | Inter | 14px | 400 | 1.6 |
| Body Small | Inter | 13px | 400 | 1.5 |
| Caption | Inter | 12px | 400 | 1.4 |
| Button | Manrope | 14px | 700 | 1.2 |
| Statistic | Manrope | 28px | 800 | 1.2 |

Ukuran kontrol mengikuti aturan ini: teks isi 16px, 14px pada tabel padat. Jangan memakai teks terlalu kecil pada latar pastel. Judul halaman selalu memakai Text Primary, bukan warna aksen, agar hierarki tidak berebut dengan status dan grafik.

## 4. Radius, spasi, dan bayangan

### 4.1 Radius

| Token | Nilai | Penggunaan |
| --- | --- | --- |
| Radius XS | 6px | Elemen kecil |
| Radius SM | 10px | Badge |
| Radius MD | 14px | Input dan tombol |
| Radius LG | 20px | Kartu |
| Radius XL | 24px | Panel besar |
| Radius 2XL | 30px | Panel hero |
| Radius Full | 9999px | Pill dan avatar |

Ringkasan praktis: tombol 14–18px, kartu 20–24px, modal 24px, input 14–16px.

### 4.2 Spasi

Basis kelipatan 4px: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64px. Padding kartu 20–24px, jarak antar-section 32–48px, jarak antar-field formulir 16px, jarak internal komponen 8–16px.

### 4.3 Sistem bayangan neumorphism

Bayangan ganda lembut (gelap ke arah bawah-kanan, terang ke arah atas-kiri) adalah ciri utama gaya ini:

| Token | Nilai CSS | Penggunaan |
| --- | --- | --- |
| Shadow XS | `3px 3px 6px #D2D6E7, -3px -3px 6px #FFFFFF` | Elemen kecil |
| Shadow SM | `5px 5px 10px #D1D5E7, -5px -5px 10px #FFFFFF` | Tombol |
| Shadow MD | `8px 8px 16px #CDD2E5, -8px -8px 16px #FFFFFF` | Kartu |
| Shadow LG | `12px 12px 24px #CBD0E3, -12px -12px 24px #FFFFFF` | Panel besar |
| Shadow Inset | `inset 4px 4px 8px #D1D5E7, inset -4px -4px 8px #FFFFFF` | Input, search bar, area formulir, kontrol terpilih, trek progres |

Keadaan tombol:

- Default: Shadow SM.
- Hover: bayangan mengecil sedikit (`3px 3px 7px #D1D5E7, -3px -3px 7px #FFFFFF`), tombol sedikit terangkat.
- Active/Pressed: inset (`inset 3px 3px 6px #D1D5E7, inset -3px -3px 6px #FFFFFF`), plus skala 0.97 (lihat §8).

Bayangan **tidak boleh** menjadi satu-satunya penanda interaksi, selected, atau status. Selalu ada juga perubahan warna, ikon, label, atau outline fokus yang terlihat.

## 5. Ikonografi

- **Lucide Icons, gaya outline saja**, di seluruh UI. Jangan mencampur gaya ikon dalam satu halaman.
- Ukuran: default 20px, kecil 16px, besar 24px. Stroke 1.8–2px.
- Warna: Primary atau Text Secondary, sesuai konteks. Ikon status mengikuti warna statusnya, tetapi selalu didampingi label teks.
- Ikon metode pembayaran bersifat generik: `qr-code` untuk QRIS, `wallet` untuk e-wallet, `landmark` untuk Virtual Account. Nama metode ditulis sebagai teks. **Tanpa logo dan tanpa warna merek bank/e-wallet.**

## 6. Komponen

Komponen dipakai ulang sebagai varian, bukan dibuat baru per layar: `Button(variant, size, state)`, `Input(state)`, `StatusChip`, `DataTable`, `StatCard`, `EmptyState`, `Dialog`, `Toast`, `Countdown`, `CopyButton`, `JsonPanel`, `SimBadge`, `SimBanner`.

### 6.1 Kartu (Card)

- Radius 20–24px, latar Surface, Shadow MD, padding 20–24px.
- Hindari border yang terlalu kontras. Hover pada kartu interaktif: naik sedikit (`translateY(-4px)`) dengan bayangan membesar, durasi 200ms (hanya bila `hover: hover`).
- Kartu statistik (`StatCard`): angka besar Manrope 28px/800, label kecil, tren ditandai ikon + teks (mis. "Naik 12%") — bukan warna saja.

### 6.2 Tombol (Button)

- Radius 14–18px, tinggi 42–48px, teks Manrope 14px/700.
- Tombol utama: Primary Gradient, teks putih. Tombol sekunder: Surface dengan Shadow SM. Tombol destruktif: Red dengan latar Red Light bila berupa aksi sekunder yang berbahaya.
- Keadaan: default, hover, pressed (inset + skala 0.97), focus-visible (outline Primary yang jelas, offset terlihat), disabled (opacity + kursor tidak-aktif), loading (spinner + label proses, ukuran tombol dipertahankan, pengiriman ganda dicegah).
- Label aksi spesifik dan konsisten dalam Bahasa Indonesia, mis. "Bayar Sekarang (Simulasi)", "Simulasikan Gagal", "Kembali ke Pesanan", "Batalkan pesanan", "Simpan perubahan". Jangan memakai label generik seperti "Submit" atau "OK" pada aksi utama.

### 6.3 Input dan formulir

- Radius 14–16px, latar Surface, bayangan inset, tinggi 44–48px.
- Fokus: outline Primary yang terlihat jelas. Galat: pesan galat per field, dihubungkan ke field (`aria-describedby`), bukan hanya border merah.
- Setiap input memiliki label yang terlihat. Placeholder bukan pengganti label. Target sentuh minimal 44×44px di ponsel.
- Search bar memakai gaya input inset yang sama. Filter dan pencarian di Admin/Developer memakai kontrol yang sama, bukan gaya baru per layar.

### 6.4 Modal dan dialog

- Radius 24px, latar Surface, Shadow LG, overlay gelap transparan secukupnya.
- Fokus keyboard dijebak di dalam modal dan dikembalikan saat modal ditutup. Tombol Escape menutup modal. Judul modal jelas, aksi konfirmasi dan batal selalu berlabel spesifik.
- Dialog konfirmasi destruktif (hapus produk, nonaktifkan akun, reset simulator) selalu menjelaskan dampaknya sebelum aksi dijalankan. Reset simulator memakai konfirmasi ketik frasa (lihat §10.4).

### 6.5 Navigasi

- Navigasi aktif: latar Primary Light, ikon Primary, label Text Primary.
- Sidebar (desktop) memakai Surface dengan bayangan lembut, dikelompokkan sesuai §13. Di tablet menjadi rail ikon, di ponsel menjadi tab bawah + "Lainnya" (lihat §13).
- Jangan menambahkan layar profil/pengaturan pembeli ke navigasi — PRD tidak memilikinya. Contoh dropdown "My Profile / Settings / Logout" di `Motion.md` adalah contoh generik dan **tidak** boleh diadopsi sebagai layar Ira. Yang ada di Ira hanyalah masuk, daftar, keluar, dan (bila diperlukan) menu akun minimal untuk keluar — tanpa halaman profil/pengaturan.

### 6.6 Tabel

Untuk tabel data, jangan menerapkan efek neumorphism berlebihan:

- Latar bersih (Surface Light atau putih lembut untuk area tabel), divider halus, header kontras jelas, hover ringan, badge status dengan warna aksen + ikon + label.
- Tabel padat memakai teks 14px. Kolom aksi selalu terlihat, bukan hanya saat hover. Paginasi, filter status/tanggal, dan keadaan kosong/tidak ada hasil/galat + coba lagi selalu tersedia.
- Di ponsel, tabel menjadi kartu (bukan tabel yang menggulir ke samping tanpa batas).

### 6.7 Penanda simulator

- `SegmentedControl`: pilihan tersegmentasi (mis. Day/Week/Month pada gambar NovaApp) — dipakai di Ira untuk pilihan cepat periode analitik (Hari ini, 7 hari, 30 hari, Bulan ini, Bulan lalu) dan untuk tab status daftar (Semua/Aktif/Draf/Nonaktif) di Admin, dengan segmen terpilih memakai Primary dan bayangan inset ringan.
- `Stepper`: kontrol jumlah minus–angka–plus (dari showcase NovaApp) — dipakai untuk kontrol jumlah di detail produk dan keranjang, dengan target sentuh ≥ 44px per tombol dan angka yang selalu terbaca.
- `SimBanner`: bilah permanen di Panel Developer — teks "SIMULATOR — data eksperimental" + ikon, latar peringatan, selalu terlihat, tidak bisa ditutup.
- `SimBadge`: badge kecil pada baris data simulasi di Admin, dan pada kartu metode pembayaran di checkout — teks "SIMULASI" + ikon, pasangan warna peringatan.
- Judul halaman Panel Developer selalu berawalan "Simulator" (mis. "Simulator — Buat Akun"). Judul Admin tidak memakai awalan itu. Perbedaan ini wajib, agar kedua permukaan tidak tertukar.

## 7. Sistem gerak (motion)

Sumber: `Motion.md`. Prinsip: gerak menjelaskan, merespons, dan mengarahkan — tidak pernah mengalihkan perhatian.

### 7.1 Token durasi dan easing

| Token | Durasi | Penggunaan |
| --- | --- | --- |
| Instant | 100ms | Tekan tombol, mikro-interaksi |
| Fast | 150ms | Hover, transisi ikon |
| Normal | 200ms | Dropdown, toggle |
| Moderate | 300ms | Modal, accordion |
| Relaxed | 450ms | Entrance kartu, transisi panel |
| Slow | 600ms | Scroll reveal |
| Cinematic | 800ms | Hero, dekorasi (pakai hemat) |

Easing: `--ease-standard: cubic-bezier(0.2, 0.8, 0.2, 1)` sebagai default, `--ease-smooth: cubic-bezier(0.25, 0.1, 0.25, 1)`, `--ease-enter: cubic-bezier(0, 0, 0.2, 1)` untuk masuk, `--ease-exit: cubic-bezier(0.4, 0, 1, 1)` untuk keluar. Hover tidak lebih dari 200ms, dropdown 150–250ms, modal 200–350ms, scroll reveal 450–650ms. Jangan memakai durasi panjang untuk interaksi yang sering dilakukan.

### 7.2 Gerak per komponen

| Komponen | Animasi | Durasi |
| --- | --- | --- |
| Konten halaman | Fade Up saat masuk | 350ms |
| Section | Fade Up | 600ms |
| Kartu | Fade Up + stagger (jeda 60–120ms antar-elemen, maks total ~400ms, hanya pada kelompok yang berhubungan visual) | 500ms |
| Tombol | Press (skala 0.97 + inset) + hover | 150–200ms |
| Ikon | Scale 1.05–1.10 / rotasi maks 10–15° bila sesuai | 150ms |
| Dropdown | Fade + translate (masuk dari bawah/atas, skala 0.95 → 1) | 200ms |
| Modal | Overlay fade 200ms + panel fade + skala 0.95 → 1 + geser Y 12px → 0 | 250ms |
| Accordion | Fade + translate | 200ms |
| Tooltip | Fade + translate | 150ms |
| Toggle/switch | Geser knob + perubahan warna bersamaan | 200ms |
| Skeleton | Shimmer (gradien bergerak) | 1500ms |
| Spinner | Putar | 700ms linear |
| Entrance halaman | Fade + translate Y 8px → 0 | 350ms |

Implementasi di Blade memakai Alpine.js untuk state (`x-show`, `x-transition`, `x-cloak`) pada dropdown, modal, accordion, toggle, dan tooltip. Navigasi Laravel tetap navigasi halaman penuh (bukan transisi lintas halaman yang berkesinambungan): yang dianimasikan adalah entrance konten halaman baru, bukan penundaan navigasi. Jangan menghambat navigasi dengan animasi yang terlalu lama.

Scroll reveal: default Fade Up (opacity 0 → 1, Y +24px → 0) memakai `IntersectionObserver` (threshold 0.12, rootMargin bawah -40px), hanya dianimasikan satu kali per elemen, lewat atribut `data-reveal` (`up`/`down`/`left`/`right`/`zoom`). Variasi lain hanya bila konteksnya memang memerlukan arah berbeda. Jangan menerapkan stagger pada seluruh isi halaman.

Hover: tombol sedikit terangkat (`translateY(-0.5)`) dengan bayangan membesar dan warna bergeser ke Primary Hover; kartu interaktif terangkat 4px; ikon membesar 1.05–1.10. Semua hover memakai transisi halus, dan hover **bukan** satu-satunya cara mengakses fitur — semua aksi hover juga tersedia lewat klik/fokus keyboard.

### 7.3 Aksesibilitas gerak dan performa

- Hormati `prefers-reduced-motion: reduce`: semua durasi animasi/transisi menjadi 0.01ms, scroll menjadi `auto`, dan elemen reveal langsung terlihat (opacity 1, transform none).
- Prioritaskan properti `transform` dan `opacity` untuk animasi. Hindari menganimasikan `width`, `height`, `top`, `left`, margin, padding, atau bayangan yang berubah sangat besar secara terus-menerus. Jangan memakai `will-change` pada semua elemen.
- Hitung mundur pembayaran tidak diumumkan tiap detik oleh screen reader. Perubahan status pembayaran dan hasil salin diumumkan lewat `aria-live` yang sopan.
- Jangan memakai animasi sebagai pengganti loading state yang sebenarnya: loading harus mengikuti status request yang sesungguhnya, bukan timer simulasi.

## 8. Empat permukaan

| Permukaan | Pengguna | Karakter | Pembeda wajib |
| --- | --- | --- | --- |
| **Storefront** | Pembeli | Ramah, produk-pertama; kartu produk dengan placeholder ikon + gradien per kategori (tanpa foto produk) | Bilah atas dengan pencarian dan keranjang; kartu produk dominan; tanpa penanda simulator kecuali pada pembayaran |
| **Admin Toko** | Admin Toko | Padat, tabel dan grafik; sidebar bergrup | Tanpa penanda simulator di chrome-nya, kecuali badge SIMULASI pada baris data simulasi; grafik dan tabel adalah pusatnya |
| **Panel Developer** | Developer | Padat, utilitarian; formulir bersegmen | Bilah permanen "SIMULATOR — data eksperimental", sidebar berwarna berbeda dari Admin, judul halaman berawalan "Simulator" |
| **Halaman simulasi pembayaran** | Pemegang token (sering dari HP) | Mobile-first, satu kolom, kontras tinggi | Label "SIMULASI PEMBAYARAN" besar di atas lipatan; panel HTTP di bawah aksi; QR/VA sangat terbaca |

Archetype per permukaan (dari blueprint §13.1, dipertahankan): Storefront = e-commerce/katalog, Admin = back-office + analytics SaaS, Developer = developer tool, halaman simulasi = fintech mobile-first. Dials kepadatan/emosi/gerak mengikuti blueprint §13.1 (Storefront D5·E5·M3, Admin D6·E4·M2, Developer D7·E3·M2, halaman simulasi D5·E3·M2) — dalam bahasa dokumen ini: Storefront paling ramah dan paling banyak gerak halus, Admin dan Developer lebih padat dan geraknya lebih hemat, halaman simulasi paling fokus dan minim distraksi.

## 9. Peta layar

Peta ini mengikuti blueprint §13.4. Setiap layar wajib memiliki keadaan yang tercantum; bila keadaan itu tidak ada, layar belum selesai. ID fitur merujuk ke PRD.

### 9.1 Akun dan masuk (semua peran)

| Layar | Isi utama | Keadaan wajib | Fitur |
| --- | --- | --- | --- |
| Masuk / Daftar (pembeli, admin, developer) | Layar terbagi: panel merek + kartu formulir ≤ 400px; formulir terpisah per peran (tiga form login terpisah, bukan satu form dengan pilihan peran yang membingungkan) | Galat per field, loading tombol, akun nonaktif | ACC-01..04 |
| Akun nonaktif / galat akun | Pesan jelas + satu aksi kembali/masuk ulang | — | ACC-07 |

Gaya: kartu formulir neumorphism (Surface, Shadow MD, radius 24px), input inset, tombol utama gradien. Panel merek memakai gradien lavender/biru sebagai aksen, bukan latar penuh yang mengganggu keterbacaan formulir. Referensi visual: kartu sign-in pada gambar referensi (§20) — logo di tengah atas dalam kartu squircle lembut, judul tebal "Selamat Datang Kembali" + subjudul "Masuk untuk melanjutkan" dalam Bahasa Indonesia, input inset dengan ikon di kiri (email dan kata sandi) dan ikon mata untuk tampil/sembunyikan kata sandi, tombol utama gradien dengan panah, dan dekorasi lingkaran lembut di latar yang tidak mengganggu formulir. Yang tetap **tidak** dipakai: login sosial (Google/Discord/Facebook), toggle mode gelap, dan tagline gaming — logo Ira adalah mark toko Ira, bukan logo gambar referensi.

### 9.2 Storefront (Pembeli)

| Layar | Isi utama | Keadaan wajib | Fitur |
| --- | --- | --- | --- |
| Katalog | Grid kartu produk + sidebar kategori + pencarian | Loading (skeleton), kosong, tidak ditemukan, galat | SHP-01..04 |
| Detail produk | Placeholder ikon + gradien per kategori + info + kontrol jumlah + tombol tambah ke keranjang | Habis, galat tambah ke keranjang | SHP-02, CRT-01 |
| Keranjang | Tabel/kartu item + ringkasan lengket (subtotal, total) | Kosong, item bermasalah (stok tidak cukup, produk tidak aktif) | CRT-01..06 |
| Checkout + pilih metode | Ringkasan pesanan + enam kartu metode berlabel SIMULASI (QRIS, Dana, GoPay, VA BRI, VA BCA, VA BNI) | Belum memilih metode, submit ganda dicegah, stok tidak cukup | ORD-01, ORD-02, PAY-02 |
| Instruksi pembayaran | QR besar atau VA besar + hitung mundur + tombol alternatif (salin, kembali ke pesanan) | Aktif, kedaluwarsa, peringatan bila APP_URL tidak bisa dijangkau HP | PAY-07, 09, 11..14 |
| Riwayat pesanan & detail | Daftar pesanan + detail dengan linimasa status | Kosong | ORD-08, 09, PAY-49, 50 |
| Hasil berhasil / gagal / kedaluwarsa | Kartu hasil + ringkasan + aksi lanjut (kembali ke katalog/pesanan, coba lagi bila berlaku) | Tiga varian hasil | PAY-45..47 |
| Struk simulasi | Dokumen cetak (CSS print), label SIMULASI tercetak | — | PAY-48 |

Catatan Storefront:

- Kartu produk: placeholder ikon kategori + gradien (Lavender/Blue/Purple/Cyan sesuai kategori), nama produk, harga Rp, stok singkat, tombol tambah. Tanpa foto produk — itu asumsi yang sudah diputuskan, bukan kekurangan yang harus ditambal dengan gambar acak.
- Pencarian: input inset dengan ikon pencarian, hasil diperbarui dengan jelas, kata kunci di-escape, keadaan "tidak ditemukan" menampilkan saran yang membantu (periksa ejaan, coba kategori lain), bukan halaman kosong.
- Keranjang: ringkasan lengket di desktop, ringkasan di bawah daftar di ponsel. Perubahan jumlah langsung memperbarui subtotal dengan umpan balik yang terlihat (bukan diam-diam).
- Checkout: enam kartu metode memakai ikon generik + nama teks + badge SIMULASI. Tidak ada logo/warna merek bank/e-wallet. Kartu terpilih memakai Primary Light + outline Primary + bayangan inset ringan, agar selected tidak hanya mengandalkan bayangan.

### 9.3 Halaman simulasi pembayaran (pemegang token)

Ini layar paling penting untuk demo, dan paling ketat aturannya. Lihat detail penuh di §11. Ringkasnya:

| Layar | Isi utama (satu kolom, mobile-first) | Keadaan wajib | Fitur |
| --- | --- | --- | --- |
| Halaman simulasi | Label SIMULASI PEMBAYARAN → ringkasan (nominal, metode, referensi) → hitung mundur → 3 aksi (Bayar Sekarang (Simulasi), Simulasikan Gagal, kembali) → panel HTTP | Pending, berhasil, gagal, kedaluwarsa, final/dipakai ulang, tidak ditemukan | PAY-15..23, 37..42 |

Urutan tidak boleh dibalik: label simulasi dan ringkasan harus terlihat sebelum aksi, dan panel HTTP berada di bawah aksi — bukan di atasnya. Di ponsel, aksi menempel dekat bawah agar mudah dijangkau, tetapi tidak menutupi ringkasan atau hitung mundur.

### 9.4 Admin Toko

| Layar | Isi utama | Keadaan wajib | Fitur |
| --- | --- | --- | --- |
| Dashboard | Header + filter periode/asal data; 2 kartu KPI; grafik tren + daftar terlaris/terendah | Kosong, galat servis, loading | ANL-11..15 |
| Tren (FR-02) | Kontrol filter di atas, grafik + tabel alternatif, tautan "Lihat sumber" | Kosong, "data belum cukup", galat servis | ANL-21..24 |
| Funnel (FR-03) | Batang horizontal empat tahap (dilihat → keranjang → booking → pembelian) dengan jumlah dan persen | Kosong, "data belum cukup", galat servis | ANL-31..35 |
| Perbandingan periode (FR-04) | Dua periode yang dibandingkan, grafik + tabel selisih | Kosong, "data belum cukup", galat servis | ANL-41..46 |
| Aktivitas & Minat (FR-05) | Grafik aktivitas + tabel riwayat aktivitas pelanggan | Kosong, "data belum cukup", galat servis | ANL-51..55 |
| Kategori / Karakteristik (FR-06) | Grafik kategori + tabel karakteristik produk | Kosong, "data belum cukup", galat servis | ANL-61..65 |
| Kategori produk, Produk (daftar/formulir), Riwayat stok | Daftar/formulir standar Admin | Kosong, validasi, konfirmasi hapus | CAT, PRD |
| Pesanan, Pembayaran (+ detail & jejak) | Daftar berfilter; detail dengan linimasa dan panel HTTP read-only | Kosong | ORD-10..13, PAY-51, 52 |
| Akun pembeli | Daftar + detail, dialog nonaktifkan | Kosong, dialog nonaktifkan | ACC-11..13 |

Catatan Admin:

- Komposisi dashboard mengikuti gambar referensi (§20): sidebar kiri bergrup, bilah atas dengan pencarian, sapaan + konteks hari, baris kartu KPI dengan tren persen dan mini-sparkline, tabel daftar di tengah dengan tab status dan chip status, serta kolom kanan berisi aksi cepat, grafik performa, dan kategori teratas. Yang diadopsi hanyalah komposisinya — bukan isi gambar referensi: sidebar Ira adalah Analitik/Toko/Transaksi/Akun, pencariannya mencari produk Ira, mata uangnya Rp (bukan $), KPI-nya mengikuti ANL-11..15, dan tidak ada kartu upsell apa pun.
- Filter periode dan asal data (semua / normal / simulasi) selalu di atas grafik, identik di FR-01..FR-06, dan pilihannya tersimpan di URL sehingga dapat dibagikan dan bertahan saat refresh (ANL-01).
- Setiap grafik memiliki tombol "Lihat sebagai tabel" sebagai alternatif aksesibel, dan tautan "Lihat sumber" ke transaksi/event yang mendasarinya. Grafik tanpa tabel alternatif belum selesai.
- Badge SIMULASI muncul pada baris data yang berasal dari simulator, tetapi chrome Admin sendiri tidak berlabel simulator — itu pembeda dengan Panel Developer.
- Tabel Admin memakai aturan §6.6: latar bersih, bukan neumorphism berlebihan, agar data padat tetap terbaca.

### 9.5 Panel Developer (Simulator)

| Layar | Isi utama | Keadaan wajib | Fitur |
| --- | --- | --- | --- |
| Beranda Simulator | Ringkasan aksi simulator + tautan ke tiap aksi + peringatan data eksperimental | Kosong (belum ada run) | SIM-02.. |
| Simulator — Buat Akun | Formulir bersegmen (jumlah, biodata acak/deterministik, benih bila deterministik) → pratinjau → konfirmasi | Pratinjau, berhasil, gagal (rollback), batas terlampaui (maks 100/request) | SIM-02..10 |
| Simulator — Transaksi per User | Pilih user → pilih periode → atur jumlah/distribusi → pratinjau → konfirmasi | Pratinjau, berhasil, gagal (rollback), batas terlampaui | SIM-11..27 |
| Simulator — Transaksi per Periode | Pilih periode/rentang → pilih satu user atau banyak user → atur pengali/distribusi → pratinjau → konfirmasi | Pratinjau, berhasil, gagal (rollback), batas terlampaui | SIM-28..30 |
| Simulator — Funnel Eksplisit | Formulir funnel empat tahap → pratinjau → konfirmasi | Pratinjau, berhasil, gagal (rollback) | SIM-31..33 |
| Simulator — Akun Simulasi | Daftar akun simulasi berfilter | Kosong | SIM-35.. |
| Simulator — Jejak & Audit | Daftar jejak/audit run simulator (append-only) | Kosong | SIM-36..37 |
| Simulator — Reset | Konfirmasi ketik frasa + penjelasan dampak (hanya data simulasi yang dihapus, data normal dilindungi) | Konfirmasi, berhasil, gagal | SIM-38..41 |

Catatan Developer:

- Pola wajib untuk semua aksi yang menulis data: **formulir bersegmen → pratinjau → konfirmasi → hasil**. Tidak ada aksi tulis yang langsung dieksekusi tanpa pratinjau, dan tidak ada aksi yang fire-and-forget tanpa status run yang terlacak.
- Pratinjau menampilkan ringkasan yang akan dibuat (jumlah akun/transaksi/event, periode, asal data) dalam bahasa yang bisa dibaca manusia, bukan dump JSON mentah.
- Batas dan aturan (100 akun/request, batas transaksi/event per request, maks 366 hari, dsb.) ditampilkan di dekat formulir sebelum submit, bukan hanya sebagai pesan galat sesudahnya.
- Reset adalah aksi paling berbahaya di sistem ini: dialognya memakai latar peringatan, menjelaskan dengan spesifik apa yang dihapus dan apa yang **tidak** dihapus (data normal), dan meminta pengguna mengetik frasa konfirmasi — tombol reset tetap nonaktif sampai frasa tepat.

### 9.6 Layar sistem

| Layar | Isi utama | Fitur |
| --- | --- | --- |
| 403 / 404 / 419 / 429 / 500 | Pesan jelas dalam Bahasa Indonesia + satu tombol kembali (ke halaman sebelumnya atau beranda sesuai peran) | NFR-16 |
| Toast | Pesan singkat sukses/gagal/salinan berhasil, dengan ikon + teks, hilang otomatis tetapi cukup lama untuk dibaca | — |
| Dialog konfirmasi | Judul, penjelasan dampak, aksi batal + aksi konfirmasi berlabel spesifik | — |
| Keadaan kosong / galat / loading | EmptyState dengan ikon + penjelasan + satu aksi yang membantu; galat dengan tombol coba lagi; loading dengan skeleton (bukan spinner kosong pada daftar) | — |

## 10. Spesifik pembayaran simulasi

### 10.1 Label dan kejujuran visual

- Label "SIMULASI PEMBAYARAN" besar, di atas lipatan, pada halaman simulasi. Label "SIMULASI — bukan QR pembayaran riil" di luar area modul QR (bukan menutupi QR). Label "Nomor VA SIMULASI — bukan nomor rekening" di dekat nomor VA.
- Semua panel HTTP berjudul "Simulated HTTP Request & Response" dengan catatan "signature dummy" yang tampak. Tidak ada elemen yang menyiratkan uang riil berpindah.
- Setelah status final (berhasil/gagal/kedaluwarsa), tombol aksi diganti pesan status + tautan lanjut. Tidak ada kontrol yang menyiratkan perubahan masih mungkin.

### 10.2 QR

- SVG inline, minimal 240px, area quiet putih yang bersih. **Area QR harus putih polos, tanpa bayangan neumorphism, gradien, atau latar lavender di dalam/di sekitar modul QR** — bayangan hanya boleh di luar kartu yang memuat QR, agar QR tetap mudah dipindai kamera/Google Lens.
- Pita SIMULASI berada di luar area modul QR. Elemen `<title>` dan teks alternatif QR memuat kata SIMULASI.
- QR mengkodekan URL halaman simulator (mis. `/pay/sim/<payment-token>`), bukan payload QRIS riil. Nominal tidak berasal dari URL — nominal berasal dari pesanan.

### 10.3 Virtual Account

- Nomor VA besar, font monospace tabular, dikelompokkan agar mudah dibaca. Tombol salin menyalin tanpa spasi. Format dummy VA yang final masih hal terbuka (lihat §16) — UI harus menampilkan format dummy apa pun yang diputuskan nanti tanpa mengasumsikan format bank riil.
- Metode VA tidak memakai logo/warna bank. Nama bank (BRI/BCA/BNI) hanya sebagai teks pada kartu metode dan ringkasan.

### 10.4 Panel HTTP

- Tiga tab: Pembuatan pembayaran, Callback, Timeline. Blok kode `readonly` dengan tombol salin JSON dan salin cURL. JSON dirender sebagai teks yang di-escape (bukan HTML mentah).
- Di ponsel, blok kode menggulir horizontal di dalam wadahnya; halaman tidak boleh menggulir ke samping.
- Di Admin (detail pembayaran), panel HTTP yang sama tampil dalam mode read-only.

### 10.5 Hitung mundur dan status

- Hitung mundur tampil jelas (menit:detik) di halaman instruksi dan halaman simulasi. Durasi kedaluwarsa mengikuti proposal PRD/blueprint (30 menit) sampai diputuskan final — UI tidak mengunci angka ini di banyak tempat; ia berasal dari satu konfigurasi (`PAYMENT_EXPIRY_MINUTES`).
- Status pembayaran dan pesanan masing-masing memiliki tampilan untuk setiap nilai status (PRD §9), selalu warna + ikon + label. Perubahan status diumumkan lewat `aria-live` yang sopan; hitung mundur tidak diumumkan tiap detik.

## 11. Grafik analitik

- **Pustaka: Chart.js dibundel**, satu komponen pembungkus untuk semua FR (ANL-03). Jangan memakai Recharts atau pustaka grafik lain di implementasi Laravel.
- Setiap grafik wajib: sumbu berlabel dan bersatuan, tooltip, legenda, palet dari token aksen (§2.4), keadaan kosong/galat, dan tombol "Lihat sebagai tabel".
- **Dashboard (FR-01):** 2 kartu KPI + grafik tren + daftar terlaris/terendah. **Tren (FR-02):** grafik garis/batang tren penjualan. **Funnel (FR-03):** batang horizontal empat tahap dengan jumlah dan persen per tahap, yang menyusut dari dilihat ke pembelian pada data contoh yang sehat. **Perbandingan (FR-04):** dua periode berdampingan dengan selisih yang jelas. **Aktivitas & Minat (FR-05):** grafik aktivitas + tabel riwayat aktivitas. **Kategori (FR-06):** grafik kategori/karakteristik; donut hanya bila irisan ≤ 5.
- Area grafik memakai latar Surface Light (`#F4F5FC`) atau kartu putih lembut di dalam kartu neumorphism, agar garis dan batang tetap kontras terhadap latar lavender. Jangan menaruh grafik langsung di atas latar gradien atau bayangan ganda yang mengganggu keterbacaan.
- "Data belum cukup" adalah keadaan yang berbeda dari "kosong": ia menjelaskan kenapa grafik belum bisa dihitung (mis. periode terlalu pendek, belum ada transaksi paid) dan apa yang bisa dilakukan (pilih periode lain, jalankan simulator). Jangan menampilkan grafik kosong tanpa penjelasan.
- Galat servis analitik (mis. servis Python tidak merespons) menampilkan pesan galat + tombol coba lagi, bukan grafik kosong atau angka nol yang menyesatkan.

## 12. Navigasi dan responsif

Satu sistem tata letak adaptif (flex/grid, tanpa lebar piksel tetap). Layar diperiksa pada ≈390 / 768 / 1280 px. Target sentuh minimal 44×44px di ponsel.

| Permukaan | Desktop (≥1024) | Tablet (768–1023) | Ponsel (360–767) |
| --- | --- | --- | --- |
| Storefront | Bilah atas: Katalog, Keranjang, Pesanan, Pembayaran, Akun | Sama | Tab bawah (≤5 item) |
| Admin | Sidebar bergrup: *Analitik* (Dashboard, Tren, Funnel, Perbandingan, Aktivitas & Minat, Kategori) · *Toko* (Produk, Kategori produk) · *Transaksi* (Pesanan, Pembayaran) · *Akun* (Akun pembeli) | Rail ikon | Tab bawah 4 teratas + "Lainnya"; tabel menjadi kartu; filter ke bottom sheet |
| Developer | Sidebar 8 item (Beranda, Buat Akun, Transaksi per User, Transaksi per Periode, Funnel, Akun Simulasi, Jejak/Audit, Reset) | Rail ikon | Tab bawah + "Lainnya" |
| Halaman simulasi | Kolom tengah di dalam kartu | Sama | Satu kolom penuh, aksi menempel dekat bawah |

- Sidebar Admin dan Developer harus berbeda warna/penanda, agar tidak tertukar — Developer selalu dengan bilah SIMULATOR permanen.
- Filter di Admin/Developer: di desktop di atas konten, di ponsel menjadi bottom sheet (bukan dropdown kecil yang sulit dipakai).
- Sticky header: header dapat menyusut sedikit saat scroll (padding berkurang, bayangan muncul) dengan transisi 300ms, tetapi tidak boleh menyembunyikan navigasi utama.

## 13. Aksesibilitas

Neumorphism memiliki kelemahan alami: latar, permukaan, dan bayangan berdekatan sehingga kontras bisa rendah. Dokumen ini mengatasinya dengan aturan berikut, yang wajib dipatuhi — bukan saran:

1. Jangan memakai bayangan sebagai satu-satunya penanda interaksi, selected, atau status.
2. Teks harus memenuhi WCAG AA: teks normal minimal 4.5:1, teks besar minimal 3:1, komponen UI dan indikator grafik penting minimal 3:1 terhadap sekitarnya. **Setiap pasangan teks/latar baru wajib diperiksa ulang kontrasnya sebelum dipakai** (termasuk teks sekunder pada latar pastel).
3. Fokus keyboard selalu terlihat: outline Primary yang jelas, bukan hanya perubahan bayangan.
4. Bedakan dengan jelas keadaan disabled, hover, active, dan selected — lewat kombinasi warna, ikon, label, dan outline, bukan hanya bayangan.
5. Jangan hanya mengandalkan warna untuk status: selalu warna + ikon + label.
6. Setiap input memiliki label terlihat; galat dihubungkan ke field (`aria-describedby`); dialog menjebak fokus dan mengembalikannya; `aria-live` sopan untuk perubahan status dan hasil salin.
7. Jangan memakai teks terlalu kecil pada latar pastel. Ukuran minimum isi adalah 13–14px sesuai skala §3, dan teks penting tidak boleh lebih kecil dari itu.
8. Hormati `prefers-reduced-motion` (lihat §7.3). Animasi tidak boleh menjadi satu-satunya cara memahami perubahan keadaan.

## 14. Data contoh (mock)

Semua data contoh di prototipe dan demo adalah fiktif, berlabel contoh, dan tidak pernah disajikan sebagai penjualan riil Ira:

- **Storefront:** produk alat tulis contoh (pulpen, buku tulis, pensil, penggaris, dll.) dengan harga Rp yang masuk akal, stok contoh, dan placeholder ikon + gradien per kategori.
- **Funnel:** data yang menyusut dari dilihat → keranjang → booking → pembelian (bukan funnel yang naik atau datar tanpa penjelasan).
- **Tren:** satu periode naik dan satu periode turun, agar grafik tren tidak selalu terlihat "bagus".
- **Perbandingan:** dua periode yang bisa dibandingkan (mis. bulan ini vs bulan lalu) dengan selisih yang terlihat.
- **Minat tanpa pembelian:** produk yang banyak dilihat/dimasukkan keranjang tetapi sedikit dibeli, untuk mendemonstrasikan FR-05.
- **Simulasi vs normal:** contoh data simulasi selalu ber-badge SIMULASI, dan contoh data normal tidak ber-badge — agar perbedaan asal data terlihat di Admin.
- **Pembayaran:** QR dan VA contoh yang mengarah ke halaman simulator, nominal dari pesanan contoh, hitung mundur yang berjalan, dan panel HTTP dengan JSON/cURL contoh yang jelas-jelas dummy.

## 15. Yang TIDAK dibuat

- Layar profil pembeli dan pengaturan pembeli (tidak ada di PRD; contoh dropdown profil di `Motion.md` tidak diadopsi).
- Mode gelap.
- Foto produk riil (placeholder ikon + gradien saja, sesuai asumsi blueprint).
- Logo dan warna merek bank/e-wallet (QRIS, Dana, GoPay, BRI, BCA, BNI hanya sebagai teks + ikon generik).
- Gateway pembayaran riil, uang riil, atau integrasi ke penyedia pembayaran mana pun.
- Tabel dan grafik dengan efek neumorphism berlebihan yang mengorbankan keterbacaan.
- Animasi dekoratif tanpa tujuan, animasi berdurasi panjang pada interaksi yang sering dilakukan, dan animasi yang menghambat navigasi.
- Starter kit UI bernama apa pun: implementasi adalah Blade + Tailwind + Alpine + Chart.js sesuai stack yang diputuskan, bukan template UI pihak ketiga yang belum diputuskan.
- Semua hal dari gambar referensi yang dinyatakan TIDAK diadopsi di §20: login sosial, toggle mode gelap, tagline gaming, merek gambar referensi, mata uang $, nama dan angka pada gambar referensi, kartu Premium/upsell, panel Settings (Appearance/Notifications/Security/Billing/Help), halaman profil, foto produk/orang dari gambar referensi, dan kontrol dial/knob yang tidak ada di PRD.

## 16. Penyimpangan dari blueprint §13 (dicatat sekali)

Dokumen ini menggantikan token visual lama blueprint §13 untuk implementasi UI. Fungsi, layar, dan aturan dari blueprint tetap berlaku; yang berubah hanyalah nilai visualnya.

| Butir blueprint §13 | Nilai lama | Nilai di UI.md ini | Alasan |
| --- | --- | --- | --- |
| Brand utama | Biru tinta `#2563EB` (brand-600), netral slate | Lavender `#6655F5` (Primary), netral kebiruan `#171A36`/`#515A8C` | `Neumorphism.md` adalah referensi gaya baru yang dipilih Ren; palet lavender/periwinkle menjadi identitas visual Ira |
| Latar / permukaan | `#F8FAFC` / putih `#FFFFFF` / `#F1F5F9` | `#E9EBF8` / `#EAECF8` / `#F4F5FC` | Latar lavender-gray dari `Neumorphism.md`; kedalaman dibentuk bayangan ganda, bukan border kontras |
| Tipografi | Inter saja | Manrope untuk judul/angka statistik + Inter untuk isi | `Neumorphism.md` §4; Manrope memberi karakter dashboard modern tanpa mengorbankan keterbacaan Inter pada isi padat |
| Radius | 10px (r-md), 16px (r-lg) | Tombol 14–18px, kartu 20–24px, modal 24px | `Neumorphism.md` §6; sudut lebih lembut adalah ciri gaya ini |
| Bayangan | Shadow standar sm/md/lg (satu arah, rgba gelap) | Shadow ganda neumorphism XS/SM/MD/LG + inset | `Neumorphism.md` §3; ilusi kedalaman lewat cahaya dan bayangan adalah inti gaya ini |
| Aksen status/grafik | Hijau `#15803D`, kuning `#B45309`, merah `#B91C1C`, dsb. (Appendix C) | Hijau `#25B993`, oranye `#F5A443`, merah `#E76B82`, biru `#438BFA`, cyan `#55C9D5`, ungu `#8875FA` | `Neumorphism.md` §2.4; status tetap warna + ikon + label, dan kontras wajib diperiksa ulang (lihat §13) |
| Gerak | Hanya menyebut `prefers-reduced-motion` dan target sentuh | Sistem gerak penuh dari `Motion.md` (durasi, easing, reveal, stagger, hover, modal, dropdown, accordion, tooltip, toggle, skeleton, spinner, page entrance) | `Motion.md` adalah referensi gerak baru yang dipilih Ren |
| Zona waktu | WIB (di naskah asli Claude) | WITA (Asia/Makassar) | Sudah diselaraskan 2026-11-02 di PRD dan blueprint; dokumen ini mengikuti hasil penyelarasan itu, bukan naskah asli |

Yang **tidak** berubah dari blueprint §13: empat permukaan dan pembedanya, peta layar dan ID fitur, spesifikasi QR/VA/panel HTTP, aturan grafik (Chart.js, tabel alternatif, donut ≤ 5), navigasi responsif per permukaan, dan larangan logo bank/e-wallet serta layar profil/pengaturan.

## 17. File dan implementasi (Laravel)

Struktur yang disarankan (mengikuti `Motion.md` §16 dan token di §2.5):

```
resources/
├── css/
│   ├── app.css          # token animasi, keyframes, transisi global
│   └── tokens.css       # token warna, bayangan, radius, tipografi (§2.5)
├── js/
│   ├── app.js           # entry point, impor modul animasi
│   └── animations/
│       ├── reveal.js            # scroll reveal + IntersectionObserver
│       ├── scroll-progress.js   # indikator progres scroll (bila dipakai)
│       └── page-interactions.js # interaksi khusus halaman
└── views/
    ├── layouts/
    │   └── app.blade.php
    └── components/
        ├── button.blade.php
        ├── card.blade.php
        ├── input.blade.php
        ├── modal.blade.php
        ├── dropdown.blade.php
        ├── accordion.blade.php
        ├── status-chip.blade.php
        ├── stat-card.blade.php
        ├── empty-state.blade.php
        ├── sim-badge.blade.php
        ├── sim-banner.blade.php
        ├── countdown.blade.php
        ├── copy-button.blade.php
        └── json-panel.blade.php
```

Aturan implementasi:

- Jangan mendefinisikan inisialisasi animasi yang sama berulang kali di beberapa file. Satu modul per tanggung jawab.
- Untuk interaksi sederhana (dropdown, modal, accordion, toggle), Alpine.js sudah cukup — tidak perlu JavaScript terpisah per komponen.
- Grafik memakai satu komponen pembungkus Chart.js untuk semua FR, dengan palet dari token aksen dan tombol "Lihat sebagai tabel".
- Font dimuat dari Google Fonts (Manrope + Inter) atau dibundel secara lokal bila kebijakan proyek melarang sumber eksternal — keputusan itu dicatat saat M0, bukan di sini.
- Kode contoh di `Neumorphism.md` dan `Motion.md` (mis. tombol "Click Me", dropdown profil, "Welcome to Dashboard" finansial) adalah contoh generik dan **tidak** boleh disalin mentah ke Ira: ganti teks, layar, dan datanya dengan yang ada di §9–§14 dokumen ini.

## 18. Daftar periksa sebelum UI dinyatakan selesai

- [ ] Semua layar di §9 ada, dengan semua keadaan wajibnya (kosong, loading/skeleton, galat + coba lagi, tidak ditemukan, berhasil, gagal, kedaluwarsa, final).
- [ ] Empat permukaan bisa dibedakan sekilas (chrome, sidebar, label SIMULATOR, label SIMULASI PEMBAYARAN).
- [ ] Semua status memakai warna + ikon + label; tidak ada status yang hanya warna.
- [ ] Semua label SIMULASI/SIMULATOR terlihat dan tidak bisa disembunyikan demi estetika.
- [ ] QR dipindai dari HP dan membuka halaman simulator (bukan localhost yang tidak bisa dijangkau HP — lihat risiko B-08 di blueprint).
- [ ] Panel HTTP: tiga tab, salin JSON dan salin cURL berfungsi, JSON di-escape, tidak ada scroll horizontal halaman di ponsel.
- [ ] Setiap grafik memiliki tabel alternatif dan tautan "Lihat sumber"; keadaan "data belum cukup" berbeda dari "kosong".
- [ ] Kontras teks/latar diperiksa ulang untuk semua pasangan baru (WCAG AA).
- [ ] Fokus keyboard terlihat di semua komponen interaktif; modal menjebak fokus; `prefers-reduced-motion` dihormati.
- [ ] Target sentuh ≥ 44×44px di ponsel; layar diperiksa pada ≈390 / 768 / 1280 px.
- [ ] Tidak ada logo bank/e-wallet, tidak ada layar profil/pengaturan pembeli, tidak ada mode gelap, tidak ada foto produk riil.
- [ ] Semua data contoh berlabel fiksi/contoh dan tidak disajikan sebagai penjualan riil Ira.
- [ ] Format selalu Rp, tanggal Indonesia, jam WITA.

## 19. Hal terbuka (bukan keputusan di dokumen ini)

Hal-hal ini tetap terbuka dan tidak boleh diasumsikan sudah diputuskan oleh UI.md:

1. Durasi kedaluwarsa pembayaran final (proposal saat ini 30 menit, dari PRD/blueprint; UI memakai satu konfigurasi, bukan angka yang tersebar).
2. Format dummy VA yang final untuk tiap bank.
3. Apakah halaman simulasi pembayaran mewajibkan login atau cukup token (PRD/blueprint masih membuka opsi ini).
4. Status pesanan dan aturan stok setelah pembayaran gagal (apakah bisa bayar ulang, apakah stok dilepas, dsb. — mengikuti PRD/blueprint, bukan UI).
5. Apakah data simulator tampil berdampingan dengan data normal secara default di Admin, atau harus dipilih lewat filter asal data.
6. Penyempurnaan visual lanjutan dari referensi Instagram Ren berikutnya (bila ada) — dokumen ini adalah versi yang bisa dipakai sekarang, dan referensi baru masuk sebagai revisi token/komponen, bukan sebagai dokumen baru yang terpisah.
7. Apakah font Manrope + Inter dimuat dari Google Fonts atau dibundel lokal (diputuskan saat M0).
8. Pilihan alat uji browser (Playwright vs Dusk, B-05) dan versi pasti Laravel/PHP/Python/Chart.js (diverifikasi saat M0, B-10) — UI.md tidak mengunci versi-versi itu.

## 20. Referensi gambar tambahan

Gambar di bawah ini dipakai sebagai referensi visual tambahan untuk dokumen ini. Aturan membaca tabel di bawah: kolom "Diadopsi" adalah satu-satunya hal yang boleh ditiru dari gambar itu; kolom "Tidak diadopsi" adalah larangan, meskipun gambar itu menampilkannya dengan jelas. Bila ada konflik antara gambar dan PRD/blueprint, PRD/blueprint menang untuk fitur, dan dokumen ini menang untuk gaya.

### Gambar referensi — Showcase komponen dan dashboard

Berkas: `visual-reference.png`

Isi gambar: satu kanvas besar bergaya neumorphism ungu muda yang memuat: (atas) sidebar NovaApp (Dashboard, Analytics, Transactions, Users, Settings, kartu "Premium Plan" dengan tombol Upgrade), dashboard "Welcome back, Sarah!" dengan pencarian, rentang tanggal Apr 24–30, 2025, 4 kartu KPI (Total Sales $12,580, Total Users 8,240, New Orders 1,240, Revenue $45,780, masing-masing dengan tren dan mini-sparkline), grafik "Sales Overview" bulanan, dan "Recent Activity"; (kanan atas) panel "UI Components Showcase" berisi tombol Primary ungu dan Secondary, tombol ikon (search, heart, settings), toggle on/off, segmented Day/Week/Month, input email, select, dan stepper jumlah; (bawah) kartu sign-in "Welcome Back", kartu profil "Jessica Tan, Product Designer" dengan statistik Projects/Followers/Following dan tombol Edit Profile, kartu produk "Wireless Headphones $199" dengan tombol Add to Cart, dan panel Settings (Appearance, Notifications, Security, Billing, Help & Support, Theme toggle).

| Diadopsi untuk Ira | Tidak diadopsi |
| --- | --- |
| Komposisi dashboard (sidebar + KPI dengan sparkline + grafik + aktivitas terbaru); showcase komponen sebagai pola: tombol Primary ungu + Secondary permukaan, tombol ikon, toggle, segmented control, input inset, select, stepper jumlah; kartu sign-in ringkas; kartu produk (area visual + judul + subjudul + harga + tombol keranjang) | Merek NovaApp; mata uang $; nama Sarah/Jessica dan angka-angkanya; kartu "Premium Plan"/Upgrade dan semua upsell; panel Settings dan semua itemnya (Appearance, Notifications, Security, Billing, Help & Support); Theme toggle (mode gelap); tombol Edit Profile dan halaman profil; menu sidebar NovaApp sebagai navigasi Ira (navigasi Ira ada di §9 dan §12); tanggal Apr 2025 sebagai tanggal Ira (Ira memakai tanggal WITA yang sebenarnya); foto headphone/orang sebagai aset Ira (Ira memakai placeholder ikon + gradien) |
| Pemetaan ke Ira | Komponen di §6 (termasuk SegmentedControl dan Stepper yang baru ditambahkan dari gambar ini); dashboard Admin di §9.4; kartu produk Storefront di §9.2; kartu sign-in di §9.1. Segmented control dipakai untuk pilihan cepat periode dan tab status; stepper dipakai untuk kontrol jumlah — keduanya adalah jenis kontrol, bukan fitur baru di luar PRD. |
