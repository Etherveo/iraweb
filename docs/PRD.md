# PRD — Ira Stationery: E-commerce dengan Pendekatan Simulator

| Atribut | Isi |
| --- | --- |
| Versi / status | 0.1 — **DRAF untuk dikoreksi** |
| Tanggal | 1 November 2026 |
| Penyusun | Claude (sesuai `Ira-discussion.md` §12b: PRD dan blueprint dibuat oleh Claude) |
| Dokumen pendamping | `blueprint.md` (rancangan teknis), `Ira-discussion.md`, `Ira-payment-gateway-simulator.md`, `AGENTS.md`, `DESIGN.md` |
| Stack yang sudah diputuskan | Laravel (PHP) untuk aplikasi; Python sebagai servis kalkulasi terpisah (`Ira-discussion.md` §12b) |
| Catatan penting | Pada tahap ini yang dibangun adalah **simulator**. Tidak ada gateway pembayaran riil yang terhubung dan tidak ada uang riil yang berpindah. |
| Perubahan pemilik proyek 2026-11-02 | **Basis data: SQLite** (menggantikan usulan PostgreSQL pada U-01, agar simpel). **Zona waktu: WITA saja** — waktu disimpan UTC, batas hari/minggu/bulan dan tampilan memakai WITA (Asia/Makassar, UTC+8). **Tidak memakai starter kit** — tahap inisialisasi (M0 pada blueprint) adalah checklist, bukan starter kit bernama. Keputusan ini sudah diterapkan pada PRD ini dan pada `blueprint.md` di folder proyek ini; naskah asli Claude di file kiriman tetap utuh sebagai sumber. |
| Perubahan pemilik proyek 2026-11-05 (K1–K16) | **Keputusan algoritma analitik (dari analisis AI C, ditentukan Rin atas delegasi pemilik proyek, 2026-11-05).** Diterapkan di bawah ini: U-25 (N_MIN tren 8 minggu, label "Tidak ada tren jelas", koreksi Benjamini–Hochberg), U-26/ANL-53 (interval Wilson, hapus "add_to_cart tinggi"), ANL-56 baru (pembanding FR-05), INS-01–INS-06 baru (insight templat), ANL-11 (KPI vs periode sebelumnya), ANL-23/ANL-43 (basis nol → "Belum dapat dihitung"), ANL-42 (normalisasi per hari bila panjang periode berbeda), ANL-24 (transparansi p/q/beta/r). Lokasi kalkulasi dikonfirmasi: **Python servis HTTP internal, bukan CLI, bukan PHP** (sistem berbasis web, semua operasi tanpa CLI kecuali menyalakan server). Simulator tidak memodelkan stok (SIM-15): penjualan rendah pada data uji tidak boleh disimpulkan stok habis. Detail lengkap: `about-script/analisis-algoritma-trend-analytics-REVISI-RIN-2026-11-05.md`. |

---

## 1. Ringkasan dan Tujuan

Ira Stationery adalah web e-commerce alat tulis yang terintegrasi dengan fitur Trend Analytics (FR-01 s.d. FR-06). Data untuk menguji analitik berasal dari simulator, bukan data riil toko. Sistem terdiri dari tiga bagian yang harus tetap terpisah secara jelas:

1. **Sistem Asli** — toko yang bisa dipakai normal dari awal sampai akhir oleh Admin Toko dan Pembeli.
2. **Payment Gateway Simulator** — modul pembayaran yang dipakai **pembeli normal lewat checkout** (QRIS, Dana, GoPay, VA BRI/BCA/BNI). Ia mensimulasikan gateway, bukan menghubungi gateway.
3. **Panel Developer (Sistem Simulator data)** — dipakai **Developer** untuk membuat data eksperimental massal dan backdate (akun, transaksi, event funnel).

Tujuan produk:

- Admin Toko dapat menjalankan toko dan membaca FR-01 s.d. FR-06 tanpa pengolahan manual.
- Pembeli merasakan alur lengkap: cari → keranjang → checkout → pilih metode → bayar (simulasi) → status pesanan berubah.
- Pembayaran bisa didemokan: QR dipindai → halaman simulasi → panel simulasi HTTP request/response dan jejak status.
- Developer dapat mengisi data dalam jumlah besar dan periode lampau sehingga analitik punya bahan uji.
- Arsitektur siap diganti ke gateway riil di masa depan tanpa mengubah checkout, pesanan, dan analitik.

## 2. Sumber, Konvensi, dan Aturan Penelusuran

### 2.1 Kode sumber

| Kode | Arti |
| --- | --- |
| `D§n` | `Ira-discussion.md` bagian n (mis. `D§7.3`). Catatan: file ini memiliki dua bagian bernomor 12; **D§12** = "Arahan untuk Penyusun PRD", **D§12b** = "Keputusan Lanjutan (1 November 2026)". |
| `P§n` | `Ira-payment-gateway-simulator.md` bagian n (mis. `P§4.2`). |
| `FR-0n` | Fitur analisis laporan Fase 1 sebagaimana dirinci pada `D§5`. |
| `AGENTS §n`, `DESIGN §n` | Dipakai **hanya sebagai kendala** (aturan rekayasa dan desain), **bukan** sumber fitur. Lihat §11. |

### 2.2 Aturan penelusuran fitur

- Setiap fitur pada §10 memiliki kolom **Sumber** yang menunjuk ke `D§…`, `P§…`, atau `FR-0n`. Fitur tanpa sumber tidak dimasukkan.
- Kriteria terima (kolom **Kriteria terima**) memuat input, validasi, batas, dan perubahan data. Kolom **Uji**: `BB` = blackbox (alur dari sisi pengguna), `WB` = whitebox (logika/aturan), `BB+WB` = keduanya.
- Penanda **(U-nn)** pada kriteria menunjukkan nilai/aturan yang berasal dari **keputusan usulan** (§7), yaitu hal yang masih terbuka pada kedua file sumber. Semuanya bisa dikoreksi.
- Matriks penelusuran balik (bagian sumber → fitur) ada pada §14.

### 2.3 Prioritas dan tahap

Urutan pembangunan mengikuti `D§6.5` dan prioritas FR (`D§5`): fondasi → katalog → alur pembeli + pencatatan → panel developer dasar → analitik P0 → P1 → P2. Pemetaan fitur ke tahap ada pada §12.

## 3. Ruang Lingkup

### 3.1 Dalam lingkup

- Akun, autentikasi, dan RBAC untuk tiga aktor (`D§6.1`).
- Katalog, kategori, produk, stok, pencarian (`D§6.2`).
- Keranjang, checkout, pembatalan, riwayat pesanan, manajemen pesanan admin (`D§6.3`).
- Pencatatan event dan transaksi sebagai sumber analitik tunggal (`D§6.4`).
- **Payment gateway simulator** lengkap, termasuk ruang arsitektur untuk gateway riil (`P§1–P§9`).
- Panel developer: akun massal, transaksi user-first, transaksi period-first, event funnel, audit, reset (`D§7`).
- Analitik FR-01 s.d. FR-06 beserta komponen lintas fitur (`D§5`).

### 3.2 Bukan scope (eksplisit)

Dari `D§9` dan `P§10`:

- Integrasi payment gateway riil dan otomatisasi status pembayaran riil. Koneksi API riil ke QRIS, Dana, GoPay, BRI, BCA, BNI.
- Payload QRIS EMVCo resmi dan QR resmi e-wallet/bank. QR pada tahap ini hanya meng-encode URL halaman simulasi.
- Perpindahan uang, settlement, refund riil. **Refund simulasi tidak dibangun pada tahap ini** (usulan U-14; `P§10` mengizinkannya hanya bila ditetapkan eksplisit sebagai fitur terpisah).
- Penyimpanan atau penampilan kredensial, API key, atau signature gateway riil.
- Integrasi data riil toko Ira Stationery.
- Indikator/rekomendasi kebutuhan stok yang cerdas (hanya menampilkan stok).
- Analisis produk yang sering dibeli bersamaan.
- Aktor selain Admin Toko, Pembeli, Developer (`D§1` butir 4).

### 3.3 Kandidat yang sengaja TIDAK dimasukkan karena tidak tertelusur

Ini bukan penolakan permanen; tambahkan hanya bila ada persetujuan perubahan scope.

| Kandidat | Alasan tidak masuk |
| --- | --- |
| Lupa/reset password, verifikasi email | Tidak ada pada kedua file. |
| Halaman profil/pengaturan pembeli (`DESIGN §2` menyarankan menambahkannya) | Tidak tertelusur; aturan penelusuran dari pemilik proyek lebih tinggi daripada saran `DESIGN.md` (`AGENTS §2` urutan 1 > 4). |
| Unggah gambar produk | Tidak ada pada kedua file; produk memakai placeholder visual (lihat blueprint §13). |
| Ongkos kirim, pajak, kupon, alamat pengiriman | Tidak ada pada kedua file; total pesanan = jumlah harga × kuantitas. Catatan: tabel `addresses` adalah data profil pembeli simulasi (§2, M4), bukan alamat pengiriman — checkout tidak memakainya. |
| Kredensial login "sebagai" akun lain (impersonation) | Dilarang `AGENTS §9` tanpa persyaratan eksplisit. |
| Refund simulasi | Lihat U-14. |

## 4. Aktor dan Pemisahan

### 4.1 Tiga aktor (`D§1` butir 4, `D§4`)

| Aktor | Ringkas | Sisi |
| --- | --- | --- |
| Admin Toko | Kelola kategori/produk/stok/pesanan, lihat pembayaran (read-only), kelola akun pembeli, baca FR-01 s.d. FR-06. Tidak membuat data simulasi massal. | Sistem Asli |
| Pembeli | Register/login, cari/lihat produk, keranjang, checkout, bayar (simulasi), batal, riwayat pesanan/pembayaran sendiri. Tidak mengakses panel developer maupun analitik toko. | Sistem Asli + Payment Simulator |
| Developer | Login terpisah; membuat akun massal (maks. 100/request), transaksi dan event funnel backdate, audit, reset. Bukan admin dan bukan pembeli normal. | Sistem Simulator (panel developer) |

Pengunjung yang belum login bukan aktor baru; ia hanya dapat mengakses halaman masuk/daftar (usulan U-16) dan halaman simulasi pembayaran lewat token (`P§4.3`).

### 4.2 Tiga hal berbeda yang sama-sama disebut "simulasi" (dijaga terpisah)

| Konsep | Siapa pemakainya | Apa yang dihasilkan | Penanda data |
| --- | --- | --- | --- |
| **Payment Gateway Simulator** (`P`) | Pembeli normal lewat checkout | Satu pembayaran per transaksi, lengkap dengan QR/VA dummy, halaman simulasi, jejak HTTP | `payments.is_simulated = true` (selalu). **Bukan** penanda data panel developer (`P§5.4`). |
| **Panel Developer** (`D§7`) | Developer | Akun, transaksi, event massal dan backdate | `origin = developer_simulation` + `simulation_run_id` pada akun, pesanan, event, pembayaran, riwayat status (`D§2` butir 2). |
| **Sistem Asli** | Admin Toko, Pembeli | Data pemakaian normal | `origin = normal` |

Akibatnya: pesanan yang dibuat pembeli lewat checkout dan dibayar lewat payment simulator tetap `origin = normal`, walaupun pembayarannya simulasi. Pesanan yang dibuat panel developer dan berstatus selesai memiliki pembayaran `paid` yang juga berstempel backdate, dengan `origin = developer_simulation` (`P§5.4`).

## 5. Glosarium dan Definisi Data

| Istilah | Definisi |
| --- | --- |
| Pesanan / transaksi | Satu catatan pesanan dengan satu atau lebih item. "Transaksi" pada `D` = pesanan. |
| Transaksi terhitung | Pesanan yang memiliki pembayaran berstatus `paid` (status pesanan `dibayar`, `diproses`, atau `selesai`). Hanya ini yang dihitung sebagai penjualan dan `purchase` (`P§1` butir 3). |
| Waktu transaksi (untuk analitik) | `paid_at` pembayaran (U-20). |
| Event | Catatan aktivitas: `view`, `add_to_cart`, `booking`, `purchase`, `cancel` (`D§4.2`, `D§6.4`). |
| `origin` | Penanda asal data: `normal` atau `developer_simulation`. |
| Run simulasi | Satu eksekusi aksi panel developer; semua data hasilnya membawa `simulation_run_id`. |
| Periode | Rentang tanggal inklusif, dalam zona waktu WITA (Asia/Makassar) (U-20). |
| Token pembayaran | String acak yang terikat pada satu pembayaran, dipakai pada URL `/pay/sim/<token>`. |
| SIMULASI | Label wajib pada semua tampilan pembayaran, QR, struk, dan jejak. |

## 6. Kontradiksi antar-Sumber dan Resolusinya

Setiap butir menyebut kedua sisi dan interpretasi yang dipilih. Semua dapat dikoreksi pemilik proyek.

| No | Sisi A | Sisi B | Interpretasi yang dipilih |
| --- | --- | --- | --- |
| **K-01** | `D§6.3`: checkout internal "mencatat event booking dan purchase" (tersirat keduanya saat pembelian). | `P§1` butir 3, `P§3.1` butir 2, `P§3.2` butir 5: `booking` dicatat saat pesanan dibuat, `purchase` **hanya** saat pembayaran `paid`. | Ikut `P`: `booking` saat pesanan dibuat, `purchase` saat `paid`. Konsisten dengan definisi `D§7.4` (booking = dipesan, purchase = pembelian selesai) dan `D§8` (jumlah mengecil di tiap tahap). |
| **K-02** | `D§2` butir 4 dan `D§9`: tidak ada pembayaran riil; pembelian hanya "proses pesanan internal (beli/batal)"; integrasi gateway dan "otomatisasi status pembayaran riil" bukan scope. | `P`: ada metode pembayaran, state machine pembayaran, status `menunggu_pembayaran`, halaman simulasi. | Keduanya dipenuhi: langkah pembayaran simulasi adalah langkah **internal** tambahan di antara `booking` dan `purchase`. Tidak ada uang riil dan tidak ada koneksi riil; yang dikeluarkan dari scope adalah gateway **riil**. |
| **K-03** | `D§4.1`, `D§6.3`: Admin Toko "mengubah status pesanan". | `P§8`: Admin tidak mengubah status pembayaran manual; status berubah hanya lewat aksi simulasi pembeli, job kedaluwarsa, atau aksi yang ditetapkan PRD. `P§5.2`: status pesanan terikat status pembayaran. | Admin hanya mengubah **status pemenuhan** setelah terbayar (`dibayar → diproses → selesai`). Admin tidak dapat mengubah status pembayaran, tidak dapat menandai pesanan belum-bayar sebagai terbayar, dan tidak dapat membatalkan pesanan terbayar (refund tidak ada). Admin "melihat pembatalan" (`D§4.1`). |
| **K-04** | `D§2` butir 2: "data simulasi" = data dari panel developer, wajib bisa dibedakan. | `P§5.4`: pembayaran simulasi dari alur pembeli normal **tidak** boleh ditandai sebagai data panel developer. | Dua penanda terpisah: `origin` untuk asal data (dipakai filter/reset/audit) dan `payments.is_simulated` untuk sifat pembayaran (selalu true). Lihat §4.2. |
| **K-05** | `P§5.4`: transaksi selesai buatan developer harus disertai pembayaran `paid` dengan `paid_at` backdate. `P§5.1`/`P§3.2`: `paid` hanya lewat transisi state machine/callback. | `P§4.2` (akhir): jangan menampilkan HTTP palsu yang tidak pernah terjadi. | Generator menulis pembayaran lewat **layanan domain yang sama** (state machine yang sama), dengan pemicu `developer_simulation` dan stempel backdate. Tidak ada callback HTTP dan **tidak ada snapshot HTTP** yang dibuat; jejaknya hanya timeline transisi, dengan catatan eksplisit "dibuat oleh panel developer, tanpa lalu lintas HTTP". |
| **K-06** | `D§6.1`: acuan akses "Pemilik/manajer dan admin mengakses analitik". | `D§1` butir 4 dan `D§4`: hanya tiga aktor, tidak ada aktor lain. | Pemilik/manajer = pengguna dengan peran **Admin Toko**. Tidak ada peran keempat. |
| **K-07** | `D§6.1`: "Register dan login untuk Pembeli, Admin Toko, dan Developer". | `D§3`/`D§4.3`: akses developer terpisah dan tidak boleh dicampur dengan Admin/Pembeli. `AGENTS §9`: jangan menambah self-registration/peran tinggi tanpa persyaratan eksplisit. | Self-registration **hanya untuk Pembeli**. Akun Admin Toko dan Developer dibuat lewat provisioning terkendali (ACC-05); "register" untuk dua peran ini diartikan sebagai penyediaan akun, bukan pendaftaran publik. Usulan U-22. |
| **K-08** | `P§4.2`: contoh path callback `POST /webhooks/payment-simulator`. | `P§7` butir 6: `/webhooks/payments/{provider}` yang bisa dipakai ulang oleh adapter riil. | Path kanonik: `POST /api/v1/webhooks/payments/{provider}` dengan `provider = simulator`. Contoh path pada `P§4.2` diperlakukan sebagai ilustrasi. |
| **K-09** | `P§3.2` butir 4: tombol "Bayar (Simulasi Berhasil)", `P§3.3`: "Gagalkan (Simulasi Gagal)". | `P§4.1` (spesifikasi halaman): "Bayar Sekarang (Simulasi)", "Simulasikan Gagal". | Ikut `P§4.1` karena itu bagian yang memang menspesifikasi antarmuka. |
| **K-10** | `D§7.2`: transaksi buatan developer tetap mengikuti aturan Sistem Asli (produk harus ada, stok dan status konsisten). | `D§7`/`D§1` butir 7: backdate ke periode lampau; produk/harga/stok hari ini tidak mungkin sesuai kondisi masa itu. `D§7.2` sendiri membolehkan pengecualian yang dinyatakan eksplisit di PRD. | Pengecualian simulasi dinyatakan eksplisit pada SIM-15 (stok tidak dikurangi, harga memakai harga saat eksekusi, tanggal pembuatan produk tidak divalidasi). Aturan lain tetap berlaku. Usulan U-21. |
| **K-11** | `D§4.2`/`D§11-B`: Pembeli login lalu mencari dan melihat produk. | `D§6.4`: event minimal memuat "akun/sesi terkait" (menyiratkan kemungkinan sesi anonim). | Alur login-dulu (`D§11-B`). Katalog hanya untuk Pembeli yang sudah login; event selalu terkait akun. Kolom sesi tetap disimpan. Alternatif (katalog publik) ada pada U-16. |
| **K-12** | `D§2` butir 5 dan `P§4.3`: halaman simulasi terbuka dengan token, bahkan dari HP lain tanpa login. | `AGENTS §9`: otorisasi dipaksakan di trusted layer pada titik aksi. | Token pembayaran diperlakukan sebagai *capability* terbatas (satu pembayaran, kedaluwarsa, tidak bisa mengubah nominal), sesuai `P§4.3` yang merupakan persyaratan eksplisit proyek (`AGENTS §2` urutan 2). Mitigasi: lihat PAY-05, PAY-19–PAY-23. |
| **K-13** | `P§5.1`: status `failed` final dan tidak bisa berubah. | `P§3.3`/`P§5.2`: setelah gagal, pembeli boleh membuat pembayaran baru. | Tidak bertentangan bila ditulis tepat: pembayaran `failed` tetap final; **pembayaran baru** (objek baru) dibuat untuk pesanan yang sama (PAY-31). |
| **K-14** | `P§11` butir 4 dan `P§5.2`: status pesanan setelah gagal "menunggu_pembayaran_ulang atau dibatalkan, sesuai PRD". | — (terbuka) | Dipilih: pesanan tetap `menunggu_pembayaran` (tanpa status baru), stok tetap dicadangkan sampai batas bayar pesanan (U-12). |

## 7. Keputusan Usulan untuk Hal yang Masih Terbuka

Semua butir berikut **belum diputuskan** pada kedua file sumber. Nilai di bawah adalah **usulan yang bisa dikoreksi**, bukan keputusan.

| No | Hal terbuka (asal) | Usulan | Alternatif |
| --- | --- | --- | --- |
| **U-01** | Sisa stack selain Laravel dan Python (`D§10` butir 1, `D§12b`) | **SQLite** (diputuskan pemilik proyek 2026-11-02, menggantikan usulan PostgreSQL agar simpel); frontend server-rendered Blade + Tailwind CSS + Alpine.js; Chart.js; servis Python berbasis FastAPI; pengujian PHPUnit/Pest dan pytest. **Tidak memakai starter kit.** | PostgreSQL (usulan awal, diganti SQLite 2026-11-02); Inertia + React (tidak dipilih). |
| **U-02** | Default biodata random vs deterministik dan seed (`D§10` butir 3, `D§7.1`) | Default **deterministik**, seed default `20261101`; mode random tersedia. Isi dan format script generator **tidak ditetapkan** sampai script diterima (U-02b). | Default random. |
| **U-02b** | Isi dan format script biodata (`D§10` butir 2) | Hanya kontrak antarmuka (`BiodataGenerator`) dan kolom minimum `name`, `email`; implementasi sementara untuk pengujian adalah *test double* bernomor urut, bukan isi generator final. | — |
| **U-03** | Batas per request simulator (`D§10` butir 4, `D§7.3`, `D§7.5`) | Akun: 100 (ditetapkan). Transaksi: 500. Event standalone (Aksi 4): 2.000. Akun terpilih (Aksi 2): maks. 100. Panjang periode: maks. 366 hari, tidak boleh di masa depan. Nilai berupa konfigurasi; batas akun tidak boleh dikonfigurasi melebihi 100. | Angka lain setelah benchmark. |
| **U-04** | Aturan beli/batal dan status pesanan (`D§10` butir 5, `P§11` butir 4) | Status pesanan: `menunggu_pembayaran`, `dibayar`, `diproses`, `selesai`, `dibatalkan`, `kedaluwarsa`. Stok: dicadangkan saat checkout, dikonfirmasi (berkurang permanen) saat `paid`, dilepas saat `dibatalkan`/`kedaluwarsa`. Pembeli hanya dapat membatalkan pesanan `menunggu_pembayaran`. Checkout memakai seluruh isi keranjang. | Checkout sebagian; pembatalan pesanan terbayar dengan refund simulasi. |
| **U-05** | Pemetaan hak akses (`D§10` butir 6) | Lihat §8. | — |
| **U-06** | Dataset contoh (`D§10` butir 7) | 6 kategori, 40 produk aktif (minimal 5 per kategori), 50 akun simulasi. | Angka lain. |
| **U-07** | Data simulator vs normal pada analitik (`D§10` butir 8) | Default **Semua**; selector Semua / Hanya normal / Hanya simulasi; filter aktif selalu terlihat. | Default Hanya normal. |
| **U-08** | Parameter period-first (`D§10` butir 9, `D§7.3`) | Distribusi waktu default **acak**; distribusi user default **acak**; **pratinjau wajib**; eksekusi **all-or-nothing** dalam satu transaksi DB (rollback otomatis bila gagal). | Distribusi merata/berbobot sebagai default; eksekusi parsial dengan rollback manual. |
| **U-09** | Durasi kedaluwarsa (`P§11` butir 1) | 30 menit sejak pesanan dibuat, berlaku sebagai **batas bayar pesanan**; semua pembayaran untuk pesanan itu kedaluwarsa pada saat yang sama. Dapat dikonfigurasi (`PAYMENT_EXPIRY_MINUTES`). | Jendela baru 30 menit per pembayaran ulang. |
| **U-10** | Format VA dummy dan kode referensi QR (`P§11` butir 2, `P§2`) | VA 16 digit: `999` + kode bank 2 digit (BRI `01`, BCA `02`, BNI `03`) + 11 digit acak unik. Awalan `999` sengaja bukan awalan VA riil. Kode referensi QR: `SIMQR-` + 10 karakter acak. | Format lain. |
| **U-11** | Akses halaman simulasi (`P§11` butir 3, `P§4.3`) | Token cukup untuk membuka halaman dan menekan aksi simulasi (tanpa login); data pribadi pembeli tidak ditampilkan. | Wajib login pemilik pesanan. |
| **U-12** | Status pesanan dan stok setelah gagal (`P§11` butir 4) | Pesanan tetap `menunggu_pembayaran`; stok tetap dicadangkan sampai batas bayar; pembeli boleh membuat pembayaran baru (metode boleh diganti) atau membatalkan. | Pesanan langsung `dibatalkan`. |
| **U-13** | Library QR, ukuran, format (`P§11` butir 5) | QR dirender server-side sebagai **SVG** (tanpa bergantung CDN), koreksi kesalahan level M, tampil minimal 240 px; library pure-PHP (mis. `bacon/bacon-qr-code`) — versi diverifikasi saat instalasi. | PNG via GD/Imagick. |
| **U-14** | Refund simulasi (`P§11` butir 6) | **Ditunda**; tidak dibangun. | Dibangun sebagai fitur terpisah. |
| **U-15** | Provider riil target (`P§11` butir 7) | Tidak ditentukan; adapter dibuat generik. | — |
| **U-16** | Akses katalog (selaras K-11) | Katalog hanya untuk Pembeli login. | Katalog publik + event view berbasis sesi untuk pengunjung. |
| **U-17** | Aturan event view/cart (`D§6.3`, `D§6.4`) | `view` dicatat saat halaman detail produk dibuka, dengan deduplikasi 30 menit per (akun, produk); `add_to_cart` dicatat setiap aksi "Tambah ke keranjang"; mengubah jumlah atau menghapus item **tidak** mencatat event. | Tanpa deduplikasi. |
| **U-18** | Transport callback simulasi (`P§4.2` catatan implementasi) | Sub-request internal melalui kernel HTTP aplikasi (routing, middleware, dan controller yang sama dengan request nyata; tanpa jaringan). Bisa dialihkan ke loopback HTTP lewat konfigurasi. | Hanya log. |
| **U-19** | Pembagian kalkulasi Laravel vs Python (`D§12b`) | Laravel menyiapkan data fakta (agregat harian per produk), Python menghitung seluruh FR-01 s.d. FR-06 dan mengembalikan hasil; Laravel menampilkan serta menyediakan penelusuran ke sumber. Kode Python "apa adanya" belum dilampirkan, sehingga kontrak pada blueprint §11 disesuaikan dengan kode yang sebenarnya. | FR-01 dihitung SQL di Laravel. |
| **U-20** | Zona waktu | Simpan UTC; batas hari/minggu/bulan dan tampilan memakai WITA. Waktu transaksi analitik = `paid_at`. | — |
| **U-21** | Pengecualian simulasi (`D§7.2`) | Lihat SIM-15. | Mengikuti aturan Sistem Asli penuh (akan menggagalkan backdate). |
| **U-22** | Pembuatan akun Admin Toko dan Developer (K-07) | Provisioning lewat perintah artisan/seeder dengan kredensial dari input aman; tanpa halaman registrasi publik. | Admin dapat membuat Admin lain di panel. |
| **U-23** | Cakupan reset (`D§7.5`) | Reset per run dan reset seluruh data simulasi; konfirmasi dengan mengetik frasa; akun simulasi dihapus hanya bila tidak memiliki data `origin = normal`. | — |
| **U-24** | Semantik event `cancel` (`D§4.2`) | Dicatat per item untuk pembatalan pembeli (alasan `buyer`) dan kedaluwarsa (alasan `expired`); pembayaran `failed` tidak mencatat `cancel`. | — |
| **U-25** | Ambang klasifikasi tren dan jumlah data minimum (FR-02) | **Perubahan 2026-11-05 (K1, K2, K3):** minimum **8 minggu penuh** (bucket mingguan Senin–Minggu WITA; minggu parsial di tepi tidak diklasifikasi); perubahan relatif ≥ +10% = naik, ≤ −10% = turun, selain itu **"Tidak ada tren jelas"** (bukan "stabil"). Klasifikasi memakai Mann–Kendall + Sen's slope, dengan koreksi Benjamini–Hochberg atas SEMUA produk aktif pada filter yang sama (q < 0,05). Kurang dari 8 minggu → "Data belum cukup" (bukan ditebak). Nilai lama "minimal 3 bucket" tidak berlaku lagi — 3–4 bucket tidak mungkin signifikan secara statistik. | — |
| **U-26** | Ambang sorotan minat tinggi/pembelian rendah (FR-05) | **Perubahan 2026-11-05 (K6):** produk "Perlu ditinjau" bila `view` ≥ 20 pada periode, rasio `purchase`/`view` ≤ 5%, DAN batas atas interval Wilson 95% ≤ 10%. Pembanding (ANL-56): rasio titik ≥ 10% dan `purchase` ≥ 10, maks 3, urut `purchase` menurun. Ambang ini default dan dikalibrasi ulang setelah ada data riil. | — |
| **U-27** | Jumlah item daftar peringkat (FR-01) | Top/Bottom 5. | — |
| **U-28** | Kredensial akun simulasi | Satu kata sandi bersama dari environment (`SIM_ACCOUNT_PASSWORD`), disimpan hash per akun; tidak pernah ditampilkan atau dicatat. | Kata sandi acak per akun yang ditampilkan sekali. |
| **U-29** | Kebijakan kata sandi | Minimal 8 karakter. | — |

## 8. Matriks Akses (RBAC) — Usulan U-05

`✓` = boleh; `—` = ditolak (HTTP 403 atau 404 sesuai kebijakan "tidak membocorkan keberadaan sumber daya"); `T` = hanya dengan token pembayaran; `S` = hanya sistem (signature).

| Kemampuan | Pengunjung | Pembeli | Admin Toko | Developer |
| --- | --- | --- | --- | --- |
| Daftar dan masuk sebagai pembeli | ✓ | ✓ (sudah masuk: dialihkan) | — | — |
| Masuk Admin Toko / Developer (formulir terpadu) | ✓ (formulir terpadu, aktor dipilih di atas formulir) | ✓ (formulir terpadu, kredensial pembeli ditolak bila aktor bukan pembeli) | ✓ | ✓ |
| Katalog, detail, pencarian | — | ✓ | — | — |
| Keranjang, checkout, batal, riwayat pesanan sendiri | — | ✓ (milik sendiri) | — | — |
| Pembayaran: pilih metode, instruksi, riwayat dan jejak milik sendiri | — | ✓ (milik sendiri) | — | — |
| Halaman simulasi `/pay/sim/<token>` dan aksinya | T | T | T | T |
| Webhook pembayaran | S | S | S | S |
| Kelola kategori, produk, stok | — | — | ✓ | — |
| Daftar/detail pesanan, ubah status pemenuhan | — | — | ✓ | — |
| Daftar/detail pembayaran dan jejak (read-only) | — | — | ✓ | — |
| Kelola akun pembeli (lihat, aktif/nonaktif) | — | — | ✓ | — |
| Analitik FR-01 s.d. FR-06 | — | — | ✓ | — |
| Panel developer, aksi simulator, audit, reset | — | — | — | ✓ |
| Lihat akun simulasi yang ia buat | — | — | — | ✓ |

## 9. Aturan Domain yang Mengikat Fitur

### 9.1 Status pembayaran (`P§5.1`)

`pending → paid`, `pending → failed`, `pending → expired`, `pending → cancelled`. Tidak ada transisi lain. Status `paid`, `failed`, `expired`, `cancelled` bersifat final.

### 9.2 Status pesanan (U-04) dan pemetaan (`P§5.2`)

| Pemicu | Pembayaran | Pesanan | Event |
| --- | --- | --- | --- |
| Checkout | (belum ada) | `menunggu_pembayaran` | `booking` per item |
| Pembeli memilih metode | `pending` | `menunggu_pembayaran` | — |
| Callback `paid` | `paid` | `dibayar` | `purchase` per item |
| Callback `failed` | `failed` | tetap `menunggu_pembayaran` (U-12) | tidak ada `purchase` |
| Batas bayar terlewati | `expired` (bila ada yang pending) | `kedaluwarsa` | `cancel` (alasan `expired`) |
| Pembeli membatalkan | `cancelled` (bila ada yang pending) | `dibatalkan` | `cancel` (alasan `buyer`) |
| Admin memproses | — | `dibayar → diproses` | — |
| Admin menyelesaikan | — | `diproses → selesai` | — |

Transisi lain pada pesanan ditolak. `dibatalkan`, `kedaluwarsa`, `selesai` final.

### 9.3 Stok (U-04, `P§5.3`)

Setiap produk memiliki `stok_fisik` dan `stok_dicadangkan`; `stok_tersedia = stok_fisik − stok_dicadangkan`.

- Checkout: `stok_dicadangkan += qty` (hanya bila `stok_tersedia ≥ qty`).
- `paid`: `stok_dicadangkan −= qty` dan `stok_fisik −= qty` (sekali saja).
- `dibatalkan`/`kedaluwarsa`: `stok_dicadangkan −= qty` (sekali saja).
- `failed`: tidak ada perubahan stok.
- Callback ganda tidak boleh menggandakan efek stok (idempotency berdasarkan `payment_id`).

### 9.4 Event (U-17, U-24)

| Event | Dicatat ketika | Granularitas |
| --- | --- | --- |
| `view` | Detail produk dibuka oleh Pembeli | per (akun, produk), dedup 30 menit |
| `add_to_cart` | Aksi "Tambah ke keranjang" | per aksi |
| `booking` | Pesanan dibuat | per item pesanan |
| `purchase` | Pembayaran pesanan `paid` | per item pesanan |
| `cancel` | Pesanan `dibatalkan` atau `kedaluwarsa` | per item pesanan, alasan `buyer`/`expired` |

## 10. Spesifikasi Fitur

Format tiap baris: **ID | Fitur | Aktor | Sumber | Kriteria terima | Uji**. Seluruh validasi dilakukan di server (trusted layer); validasi sisi klien hanya untuk kenyamanan (`AGENTS §9`). Pesan galat tidak membocorkan detail internal (`AGENTS §10`). Setiap tampilan memiliki keadaan loading, kosong, galat, dan sukses yang sesuai (`AGENTS §7`).

### 10.1 ACC — Akun, Autentikasi, dan Hak Akses (tahap M1)

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| ACC-01 | Register pembeli | Pengunjung | D§6.1, D§4.2, D§11-B | Input: nama (2–100 karakter), email (format valid, maks. 190, unik tanpa membedakan huruf besar/kecil), kata sandi (≥ 8 karakter, U-29), konfirmasi kata sandi sama. Email berdomain simulasi tercadang (`SIM_ACCOUNT_EMAIL_DOMAIN`) ditolak. Sukses: akun role `pembeli`, `origin=normal`, status aktif, kata sandi tersimpan sebagai hash modern (tidak pernah plaintext), pengguna langsung masuk dan diarahkan ke katalog. Kolom profil `username`, `phone`, `gender`, `birthdate` pada `users` bersifat nullable dan diisi oleh simulator (M4), bukan oleh formulir registrasi. Galat tampil per field, input selain kata sandi dipertahankan, submit ganda tidak membuat dua akun. | BB+WB |
| ACC-02 | Login pembeli | Pembeli | D§6.1, D§4.2 | **Keputusan 2026-11-05: login disatukan** — formulir terpadu di `/masuk` dengan sakelar aktor (pembeli | admin_toko | developer); aktor=pembeli di sini. Kredensial benar + akun aktif → sesi pembeli dibuat dan ID sesi diregenerasi. Kredensial salah → pesan generik yang sama untuk "email tidak ada" dan "kata sandi salah". Kredensial Admin Toko atau Developer bila aktor=pembeli ditolak dengan pesan generik yang sama. | BB+WB |
| ACC-03 | Login Admin Toko | Admin Toko | D§6.1, D§4.1, D§3 | **Keputusan 2026-11-05: login disatukan** — formulir terpadu di `/masuk` dengan aktor=admin_toko (URL lama `/admin/masuk` hanya mengalihkan ke `/masuk?aktor=admin_toko`, tidak ada lagi POST login terpisah); hanya menerima akun role `admin_toko`. Kredensial pembeli ditolak dengan pesan generik. Sukses → dialihkan ke dashboard analitik. | BB+WB |
| ACC-04 | Login Developer | Developer | D§6.1, D§4.3, D§3, D§7.5 | **Keputusan 2026-11-05: login disatukan** — formulir terpadu di `/masuk` dengan aktor=developer (URL lama `/developer/masuk` hanya mengalihkan ke `/masuk?aktor=developer`, tidak ada lagi POST login terpisah); tetap memakai tabel dan guard autentikasi **terpisah** dari akun Admin/Pembeli, dan developers kini punya role `dev`. Kredensial Admin/Pembeli bila aktor=developer ditolak; kredensial Developer bila aktor bukan developer ditolak. Sesi Developer tidak otomatis menjadi sesi Admin/Pembeli dan sebaliknya. | BB+WB |
| ACC-05 | Provisioning akun Admin Toko dan Developer | Operator sistem | D§6.1, D§4 (K-07, U-22) | Dibuat lewat perintah artisan/seeder; kata sandi diminta secara interaktif atau dari environment, disimpan sebagai hash, tidak dicetak ke log. Tidak ada halaman registrasi publik untuk dua peran ini. Email unik per tabel. Menjalankan ulang tidak menimpa akun yang sudah ada tanpa konfirmasi. | WB |
| ACC-06 | Logout | Semua login | D§6.1 | Logout (metode POST dengan proteksi CSRF) membatalkan sesi di server dan menghapus cookie; tombol Kembali tidak menampilkan halaman terproteksi dari cache. Logout satu guard tidak memutus guard lain. | BB+WB |
| ACC-07 | Akun nonaktif ditolak | Pembeli | D§6.1 | Login oleh akun nonaktif ditolak dengan pesan jelas. Sesi yang sedang aktif milik akun yang dinonaktifkan menjadi tidak valid pada request berikutnya. Pemeriksaan status dilakukan pada setiap request terotentikasi, bukan hanya saat login. | BB+WB |
| ACC-08 | RBAC deny-by-default | Semua | D§6.1, D§4, D§3 | Setiap rute terproteksi dikaitkan ke guard dan peran. Rute tanpa deklarasi akses ditolak (diuji dengan daftar rute otomatis). Pengguna belum login → dialihkan ke formulir masuk terpadu `/masuk` dengan aktor pra-dipilih (`?aktor=admin_toko` / `?aktor=developer`, default pembeli); pengguna salah peran → 403/404 sesuai §8. Cek dilakukan di server, bukan hanya menyembunyikan menu. | WB+BB |
| ACC-09 | Isolasi data milik pembeli | Pembeli | D§6.1, D§4.2 | Semua baca/ubah keranjang, pesanan, pembayaran, jejak dibatasi oleh `buyer_id` dari sesi terverifikasi **dan** ID sumber daya. Mengakses ID milik pembeli lain mengembalikan respons yang sama seperti ID tidak ada. ID pemilik tidak pernah diterima dari form/query. | BB+WB |
| ACC-10 | Isolasi panel developer | Admin Toko, Pembeli, Developer | D§4.3, D§7.5, D§3 | Semua rute `/developer/*` hanya untuk guard Developer dengan role `dev` (**Keputusan 2026-11-05: login disatukan** — developers kini punya role `dev`, jadi rute developer mendeklarasikan `role:dev` seperti rute terproteksi lain, tanpa pengecualian audit ACC-08); Admin/Pembeli ditolak. Sebaliknya, sesi Developer tidak dapat membuka `/admin/*`, analitik, maupun alur belanja pembeli. | BB+WB |
| ACC-11 | Daftar akun pembeli | Admin Toko | D§6.1, D§4.1 | Tabel berpaginasi (default 20/halaman): nama, email, status, asal data (badge SIMULASI bila `developer_simulation`), tanggal daftar. Filter: status, asal data; pencarian nama/email. Keadaan kosong dan galat tersedia. Tidak menampilkan hash kata sandi. | BB |
| ACC-12 | Detail akun pembeli | Admin Toko | D§6.1, D§4.1 | Menampilkan profil, status, asal data, jumlah pesanan, dan tautan ke pesanan akun tersebut. Akun admin dan developer tidak muncul. | BB |
| ACC-13 | Aktifkan/nonaktifkan akun pembeli | Admin Toko | D§6.1, D§4.1 | Aksi dengan dialog konfirmasi; mengubah status pembeli saja (tidak menyentuh peran atau data lain). Alasan opsional (≤ 255) disimpan bersama pelaku dan waktu. Menonaktifkan memutus sesi aktif (ACC-07). Admin tidak dapat menonaktifkan akun admin/developer lewat fitur ini. | BB+WB |
| ACC-14 | Daftar akun simulasi milik Developer | Developer | D§6.1, D§4.3 | Tabel berpaginasi akun yang dibuat Developer: nama, email, run asal, status. Dapat difilter per run. Read-only; tidak menampilkan kata sandi. Tidak menampilkan akun normal. | BB |
| ACC-15 | Akun simulasi dapat login sebagai pembeli biasa | Pembeli (akun simulasi) | D§7.1, D§3 | Akun hasil simulasi bisa masuk lewat formulir terpadu `/masuk` dengan aktor=pembeli (ACC-02) dan beraktivitas normal (keranjang, checkout). Aktivitas yang dilakukannya lewat UI normal berstatus `origin=normal` (K-04). | BB |

### 10.2 CAT dan PRD — Kategori dan Produk oleh Admin Toko (tahap M2)

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| CAT-01 | Tambah kategori | Admin Toko | D§6.2, D§4.1, D§11-A2 | Nama 2–60 karakter, unik tanpa membedakan huruf besar/kecil (setelah trim); slug dibuat otomatis dan unik. Sukses → kategori aktif muncul di daftar. Duplikat/kosong ditolak dengan pesan per field. | BB+WB |
| CAT-02 | Ubah kategori | Admin Toko | D§6.2, D§4.1 | Nama dapat diubah dengan aturan CAT-01. Perubahan nama tidak mengubah ID dan relasi produk; analitik FR-06 memakai ID kategori sehingga riwayat tetap tersambung. | BB+WB |
| CAT-03 | Hapus kategori kosong | Admin Toko | D§6.2 (U-04 cakupan "manajemen") | Hanya kategori tanpa produk yang dapat dihapus; kategori berproduk ditolak dengan pesan jelas. Dialog konfirmasi wajib. | BB+WB |
| CAT-04 | Daftar kategori | Admin Toko | D§6.2, D§11-A2 | Menampilkan nama dan jumlah produk per kategori; keadaan kosong menyediakan aksi "Tambah kategori". | BB |
| PRD-01 | Tambah produk | Admin Toko | D§6.2, D§4.1, D§11-A3 | Input: nama (2–150, unik tanpa membedakan huruf besar/kecil), kategori (wajib, harus ada), deskripsi (opsional ≤ 2.000), harga (lihat PRD-02), stok awal (integer 0–1.000.000), status aktif (default aktif). Sukses → produk tercatat, `stok_dicadangkan=0`, entri riwayat stok `penyesuaian` dibuat bila stok awal > 0. Galat per field; input dipertahankan. | BB+WB |
| PRD-02 | Validasi harga | Admin Toko | D§6.2, D§4.1 | Harga bilangan bulat rupiah (tanpa desimal), 1 s.d. 100.000.000; format tampil `Rp 1.250.000`. Nol, negatif, desimal, teks ditolak. | WB+BB |
| PRD-03 | Ubah produk | Admin Toko | D§6.2, D§4.1 | Nama, deskripsi, kategori, harga dapat diubah dengan aturan yang sama dengan PRD-01/PRD-02. Perubahan harga **tidak** mengubah pesanan yang sudah dibuat (harga item adalah snapshot, ORD-04). Kategori tujuan harus ada. | BB+WB |
| PRD-04 | Aktifkan/nonaktifkan produk | Admin Toko | D§6.2, D§4.1, D§11-A4 | Produk nonaktif tidak tampil di katalog/pencarian dan tidak dapat ditambahkan ke keranjang (SHP-04, CRT-05). Pesanan yang sudah ada dan cadangan stoknya tidak terpengaruh. Produk tidak dihapus permanen. | BB+WB |
| PRD-05 | Atur stok | Admin Toko | D§6.2, D§4.1, D§11-A3 | Admin menetapkan `stok_fisik` baru (integer 0–1.000.000) dan alasan opsional. Nilai tidak boleh lebih kecil dari `stok_dicadangkan`. Perubahan ditulis atomik beserta satu entri riwayat stok (nilai lama, baru, pelaku, waktu). | BB+WB |
| PRD-06 | Daftar produk admin | Admin Toko | D§6.2, D§4.1, D§9 | Tabel berpaginasi: nama, kategori, harga, stok fisik, stok dicadangkan, stok tersedia, status. Filter kategori dan status; pencarian nama. Stok 0 ditandai "Habis" dengan teks (bukan hanya warna). Hanya menampilkan stok; tidak ada rekomendasi/indikator kebutuhan stok (D§9). | BB |
| PRD-07 | Riwayat stok produk | Admin Toko | D§6.4 (konsistensi inventori), P§5.3 | Tampilan baca-saja urutan waktu: jenis gerakan (`penyesuaian`, `cadangkan`, `konfirmasi`, `lepas`), jumlah, referensi pesanan, pelaku, asal data. Total stok pada daftar produk selalu dapat direkonstruksi dari riwayat ini. | BB+WB |

### 10.3 SHP — Katalog Pembeli (tahap M2)

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| SHP-01 | Daftar produk | Pembeli | D§6.2, D§4.2 | Grid produk aktif, berpaginasi (default 12/halaman), menampilkan nama, harga, kategori, ketersediaan. Dapat dipersempit per kategori. Keadaan kosong dan galat tersedia. | BB |
| SHP-02 | Detail produk | Pembeli | D§6.2, D§4.2 | Menampilkan nama, harga, kategori, deskripsi, stok tersedia (atau "Habis"), kontrol jumlah dan tombol tambah ke keranjang. Tombol nonaktif bila stok tersedia 0. Membuka halaman ini memicu EVT-02. | BB |
| SHP-03 | Pencarian produk | Pembeli | D§6.2, D§4.2 | Kata kunci 2–100 karakter dicocokkan terhadap nama (dan deskripsi) tanpa membedakan huruf besar/kecil; karakter wildcard pada kata kunci diperlakukan harfiah; hanya produk aktif. Hasil berpaginasi; tidak ada hasil → keadaan "tidak ditemukan" dengan aksi bersihkan pencarian. Kata kunci terpantul di UI dengan encoding aman (anti-XSS). Pencarian itu sendiri tidak mencatat event `view`. | BB+WB |
| SHP-04 | Produk nonaktif tidak dapat diakses | Pembeli | D§6.2, D§4.1 | URL detail produk nonaktif atau tidak ada mengembalikan 404 yang sama; tidak muncul di daftar maupun pencarian; tidak mencatat event. | BB+WB |

### 10.4 CRT, STK, ORD, EVT — Alur Pembeli, Stok, Pesanan, dan Pencatatan (tahap M3)

#### CRT — Keranjang

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| CRT-01 | Tambah produk ke keranjang | Pembeli | D§6.3, D§4.2, D§11-B3 | Input: produk aktif, jumlah integer 1–99 dan ≤ stok tersedia. Produk yang sudah ada di keranjang digabung (jumlah bertambah, hasil tetap ≤ 99 dan ≤ stok tersedia). Sukses → item tersimpan, event `add_to_cart` dicatat (EVT-03). Gagal (stok kurang, produk nonaktif, jumlah tidak valid) → pesan jelas, tanpa event. | BB+WB |
| CRT-02 | Ubah jumlah item | Pembeli | D§6.3 | Jumlah baru integer 1–99 dan ≤ stok tersedia; di luar batas ditolak dengan pesan dan jumlah lama dipertahankan. Tidak mencatat event (U-17). | BB+WB |
| CRT-03 | Hapus item dari keranjang | Pembeli | D§6.3 | Item dihapus dari keranjang milik sendiri; dialog konfirmasi tidak diperlukan tetapi tersedia aksi urungkan singkat atau notifikasi toast. Tidak mencatat event. | BB |
| CRT-04 | Lihat keranjang dan total | Pembeli | D§6.3, D§11-B3 | Menampilkan item, harga satuan saat ini, jumlah, subtotal item, total keranjang (dihitung server), dan jumlah item pada header. Keranjang kosong menampilkan keadaan kosong dengan aksi ke katalog. | BB |
| CRT-05 | Validasi ketersediaan keranjang | Pembeli | D§6.3, D§6.2 | Item yang produknya nonaktif atau stok tersedianya kurang dari jumlah ditandai (teks + ikon) dan memblokir checkout hingga diperbaiki atau dihapus. | BB+WB |
| CRT-06 | Persistensi keranjang per akun | Pembeli | D§6.3, D§4.2 | Keranjang tersimpan di server per akun dan bertahan lintas sesi/perangkat; tidak dapat dibaca atau diubah akun lain (ACC-09). | BB+WB |

#### STK — Aturan Stok

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| STK-01 | Model stok (fisik, dicadangkan, tersedia) | Sistem | D§6.2, D§6.3, P§5.3 | `stok_tersedia = stok_fisik − stok_dicadangkan` selalu ≥ 0; kedua nilai tidak pernah negatif (juga dijaga constraint basis data). | WB |
| STK-02 | Pencadangan stok saat checkout | Sistem | D§6.3, P§5.3 (U-04) | Dalam satu transaksi dengan pembuatan pesanan: `stok_dicadangkan += qty` per item hanya bila `stok_tersedia ≥ qty`; bila ada satu item gagal, seluruh checkout dibatalkan tanpa perubahan data. | WB+BB |
| STK-03 | Konfirmasi stok saat pembayaran `paid` | Sistem | P§3.2, P§5.3 | Sekali per pesanan: `stok_dicadangkan −= qty` dan `stok_fisik −= qty`. Callback `paid` kedua tidak mengubah stok lagi. | WB |
| STK-04 | Pelepasan stok saat batal/kedaluwarsa | Sistem | P§3.3, P§5.3 | Sekali per pesanan: `stok_dicadangkan −= qty`. Tidak ada pelepasan ganda; pesanan yang sudah `paid` tidak melepas stok. Pembayaran `failed` tidak melepas stok (U-12). | WB |
| STK-05 | Riwayat gerakan stok | Sistem | D§6.4, P§5.3 | Setiap perubahan stok (`penyesuaian`, `cadangkan`, `konfirmasi`, `lepas`) menulis satu entri tak-ubah (append-only) pada transaksi yang sama dengan perubahannya. | WB |
| STK-06 | Anti-oversell pada konkurensi | Sistem | D§6.3, P§5.3 | Dua checkout bersamaan untuk stok terakhir: tepat satu berhasil, yang lain gagal dengan pesan stok tidak cukup. Dijamin lewat penguncian baris/update bersyarat atomik. | WB |

#### ORD — Checkout, Pesanan, Pembatalan, dan Manajemen Pesanan

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| ORD-01 | Ringkasan checkout | Pembeli | D§6.3, P§3.1 | Halaman menampilkan item, harga, total (dihitung server), dan pilihan metode (PAY-02) sebelum pesanan dibuat. Keranjang kosong atau item bermasalah (CRT-05) mencegah lanjut. | BB |
| ORD-02 | Buat pesanan dari keranjang | Pembeli | D§6.3, D§11-B4, P§3.1 | Satu aksi atomik: validasi keranjang → cadangkan stok (STK-02) → buat pesanan `menunggu_pembayaran` dengan item (harga snapshot) → catat event `booking` per item → kosongkan keranjang → buat pembayaran (PAY-03). Gagal di tahap mana pun → tidak ada perubahan data. Tombol dikunci saat diproses dan kunci idempotensi mencegah pesanan ganda. | BB+WB |
| ORD-03 | Nomor pesanan | Sistem | D§6.4, P§4.1 | Format `IRA-YYMMDD-XXXXXX` (6 karakter acak dari alfabet tanpa karakter membingungkan, unik). Tidak berurutan sehingga tidak dapat ditebak. | WB |
| ORD-04 | Total di server dan snapshot harga | Sistem | D§6.4, P§2, P§3.1 | `total = Σ(harga_snapshot × jumlah)` dihitung server; nominal dari klien/URL diabaikan. Harga produk yang berubah kemudian tidak memengaruhi pesanan lama. | WB |
| ORD-05 | Batas bayar pesanan | Sistem | P§3.3, P§11 butir 1 (U-09) | `payment_deadline_at = waktu pesanan dibuat + PAYMENT_EXPIRY_MINUTES` (default 30). Pembayaran baru untuk pesanan yang sama tidak memperpanjang batas ini. Setelah batas terlewati pesanan tidak dapat dibayar. | WB+BB |
| ORD-06 | State machine pesanan | Sistem | D§6.3, P§5.2 | Hanya transisi pada §9.2 yang diterima; yang lain ditolak dan tidak mengubah data. Setiap transisi menulis riwayat (ORD-14). Diuji menyeluruh sebagai tabel transisi. | WB |
| ORD-07 | Pembatalan pesanan oleh pembeli | Pembeli | D§6.3, D§4.2, P§3.3 | Hanya untuk pesanan milik sendiri berstatus `menunggu_pembayaran`; dialog konfirmasi. Efek atomik: pesanan `dibatalkan`, pembayaran `pending` (bila ada) → `cancelled`, stok dilepas (STK-04), event `cancel` (alasan `buyer`). Pesanan tidak dihapus. Pesanan terbayar/final tidak dapat dibatalkan (pesan jelas). Token pembayaran lama menjadi tidak dapat dipakai. | BB+WB |
| ORD-08 | Riwayat pesanan pembeli | Pembeli | D§6.3, D§4.2, D§11-B5 | Daftar pesanan milik sendiri, terbaru lebih dulu, berpaginasi, dengan nomor, tanggal (WITA), total, status; filter status. Keadaan kosong tersedia. | BB |
| ORD-09 | Detail pesanan pembeli | Pembeli | D§6.3, P§3.2 | Item, total, linimasa status, ringkasan pembayaran, tautan ke detail pembayaran dan jejaknya (PAY-50), tombol bayar/batal sesuai status. | BB |
| ORD-10 | Daftar pesanan admin | Admin Toko | D§6.3, D§4.1 | Tabel berpaginasi semua pesanan; filter status, rentang tanggal, asal data; pencarian nomor pesanan; badge SIMULASI bila `developer_simulation`. | BB |
| ORD-11 | Detail pesanan admin | Admin Toko | D§6.3, D§4.1 | Item, pembeli, total, pembayaran terkait dan statusnya, riwayat status, asal data; read-only kecuali ORD-12. | BB |
| ORD-12 | Ubah status pemenuhan oleh admin | Admin Toko | D§6.3, D§4.1 (K-03) | Hanya `dibayar → diproses` dan `diproses → selesai`; tombol hanya menampilkan transisi valid; transisi lain (mis. menandai terbayar, membatalkan pesanan terbayar) ditolak di server. Pelaku dan waktu tercatat. | BB+WB |
| ORD-13 | Melihat pembatalan | Admin Toko | D§4.1, D§6.3 | Filter/tampilan pesanan `dibatalkan` dan `kedaluwarsa` dengan alasan (`buyer`/`expired`), waktu, dan pemicu. Admin tidak memiliki aksi pembatalan. | BB |
| ORD-14 | Riwayat status pesanan | Sistem | D§6.3, D§6.4, P§4.2 | Setiap transisi menulis baris tak-ubah: status lama → baru, pemicu (`pembeli`, `callback_simulasi`, `job_kedaluwarsa`, `admin`, `panel_developer`), pelaku, waktu, `origin`. | WB |

#### EVT — Pencatatan Event dan Transaksi (sumber analitik)

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| EVT-01 | Pencatatan event | Sistem | D§6.4, D§2 | Setiap event menyimpan: jenis, produk, akun, ID sesi (opsional), waktu kejadian (UTC), ID pesanan/item bila ada, `origin`, `simulation_run_id` bila ada. Event tidak diubah atau dihapus oleh aplikasi (kecuali reset simulasi, SIM-38/39). | WB |
| EVT-02 | Event `view` | Sistem | D§6.3, D§4.2 (U-17) | Dicatat saat detail produk aktif dibuka Pembeli; tidak dicatat pada daftar, pencarian, atau oleh Admin/Developer. Pembukaan berulang oleh akun dan produk yang sama dalam 30 menit hanya dihitung sekali. | WB+BB |
| EVT-03 | Event `add_to_cart` | Sistem | D§6.3, D§4.2 | Dicatat pada setiap aksi tambah yang berhasil (CRT-01); tidak dicatat saat gagal, ubah jumlah, atau hapus. | WB |
| EVT-04 | Event `booking` | Sistem | D§6.3, P§3.1 (K-01) | Satu per item pesanan, dicatat dalam transaksi yang sama dengan pembuatan pesanan; waktu = waktu pesanan dibuat. | WB |
| EVT-05 | Event `purchase` | Sistem | P§1, P§3.2, D§6.3 (K-01) | Satu per item pesanan **hanya** ketika pembayaran menjadi `paid`; unik per item (callback ganda tidak menggandakan); tidak pernah dicatat untuk `failed`, `expired`, `cancelled`. | WB |
| EVT-06 | Event `cancel` | Sistem | D§4.2, D§6.3, P§3.3 (U-24) | Satu per item saat pesanan `dibatalkan` (alasan `buyer`) atau `kedaluwarsa` (alasan `expired`); tidak dicatat untuk `failed`. | WB |
| EVT-07 | Pencatatan transaksi | Sistem | D§6.4 | Pesanan menyimpan: item (produk, jumlah, harga snapshot), nilai total, status, waktu dibuat dan waktu bayar, asal data. Jumlah pesanan terbayar = jumlah transaksi terhitung (§5). | WB |
| EVT-08 | Sumber data tunggal | Sistem | D§6.4, D§2 butir 5 | Dashboard, laporan, inventori, dan daftar transaksi membaca tabel yang sama (tanpa salinan angka). Total penjualan analitik sama dengan jumlah total pesanan terbayar pada daftar pesanan dengan filter yang sama. | WB+BB |
| EVT-09 | Penanda asal data | Sistem | D§2 butir 2, D§7.5 | Akun, pesanan, item, event, pembayaran, dan riwayat status membawa `origin` (`normal` atau `developer_simulation`) dan `simulation_run_id` bila dari panel developer. Nilai tidak dapat diubah lewat antarmuka. | WB |

### 10.5 PAY — Payment Gateway Simulator (tahap M3, dipakai Pembeli lewat checkout)

Semua tampilan modul ini menyandang label **SIMULASI** (PAY-54). Tidak ada koneksi riil, tidak ada logo/branding bank atau e-wallet (PAY-61).

#### Metode, objek pembayaran, QR, dan VA

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| PAY-01 | Katalog metode berbasis konfigurasi | Sistem | P§7 butir 4, P§2 | Enam metode (`qris`, `dana`, `gopay`, `va_bri`, `va_bca`, `va_bni`) didefinisikan **sekali** pada konfigurasi/data: kode, label ("QRIS (Simulasi)", "Dana (Simulasi)", "GoPay (Simulasi)", "VA BRI (Simulasi)", …), jenis tampilan (QR atau VA+QR), kode bank VA, status aktif. Menambah/menonaktifkan metode cukup mengubah konfigurasi, tanpa mengubah kode alur. | WB |
| PAY-02 | Pemilihan metode pada checkout | Pembeli | P§3.1, P§9 butir 1 | Tepat enam pilihan tampil (hanya yang aktif); wajib memilih satu sebelum lanjut. Kode metode di luar katalog ditolak di server. Setiap pilihan berlabel SIMULASI dan memakai ikon generik (bukan logo penyedia). | BB+WB |
| PAY-03 | Pembuatan objek pembayaran | Sistem | P§3.1 butir 4, P§9 butir 2 | Membuat satu pembayaran `pending` untuk pesanan `menunggu_pembayaran`: `amount` = total pesanan dari server, `currency=IDR`, metode terpilih, `provider=simulator`, `is_simulated=true`, kode referensi, token, `expires_at`. Hanya boleh bila pesanan berstatus `menunggu_pembayaran` dan batas bayar belum terlewati. | WB+BB |
| PAY-04 | Durasi dan waktu kedaluwarsa | Sistem | P§3.1, P§11 butir 1 (U-09) | `expires_at` = `payment_deadline_at` pesanan (ORD-05). Durasi dibaca dari `PAYMENT_EXPIRY_MINUTES` (default 30); nilai di luar 1–1.440 menit membuat aplikasi gagal start dengan pesan jelas. | WB |
| PAY-05 | Token pembayaran | Sistem | P§2, P§4.3 | Token acak dari sumber kriptografis (≥ 32 byte entropi, dikodekan URL-safe), unik, terikat ke satu pembayaran, tidak memuat data sensitif, tidak dapat diturunkan dari nomor pesanan, tidak bisa dipakai untuk pembayaran lain, dan berakhir bersama pembayaran. | WB |
| PAY-06 | URL halaman simulasi dan isi QR | Sistem | P§2, P§7 butir 7 | URL `https://<domain>/pay/sim/<token>` disusun dari `APP_URL`; QR meng-encode **persis URL itu** (bukan payload QRIS EMVCo). Memindai QR dengan kamera/Google Lens membuka halaman simulasi. | WB+BB |
| PAY-07 | Tampilan QR untuk QRIS, Dana, GoPay | Pembeli | P§2, P§3.1 butir 5, P§9 butir 3 | QR besar pada halaman instruksi, dirender sebagai SVG (U-13), disertai pita "SIMULASI — bukan QR pembayaran riil" dan teks alternatif. Tiga metode ini hanya berbeda label/gaya generik; isi QR tetap URL simulasi. | BB |
| PAY-08 | Nomor VA dummy | Sistem | P§2, P§11 butir 2, P§9 butir 4 (U-10) | 16 digit: `999` + kode bank (BRI `01`, BCA `02`, BNI `03`) + 11 digit acak, unik di seluruh pembayaran. Awalan `999` membuatnya jelas dummy; UI memberi label "Nomor VA SIMULASI — bukan nomor rekening". | WB |
| PAY-09 | Tampilan VA, QR pendamping, dan salin | Pembeli | P§3.1 butir 5, P§9 butir 4 | Untuk VA BRI/BCA/BNI: nomor VA besar dengan tombol salin (umpan balik "Tersalin"), plus QR kecil berisi URL simulasi. Nomor diformat dikelompokkan untuk keterbacaan; nilai tersalin tanpa spasi. | BB |
| PAY-10 | Kode referensi QR | Sistem | P§2, P§6, P§11 butir 2 (U-10) | Untuk QRIS/Dana/GoPay: `SIMQR-` + 10 karakter acak, unik; disimpan sebagai `reference_code` dan ditampilkan pada halaman simulasi. | WB |
| PAY-11 | Halaman instruksi pembayaran | Pembeli | P§3.1 butir 5, P§9 butir 5 | Menampilkan: nama toko, nomor pesanan, total, metode, QR atau VA (PAY-07/09), hitung mundur (PAY-12), tombol alternatif (PAY-14), dan label SIMULASI. Hanya pemilik pesanan yang dapat membukanya (ACC-09). | BB |
| PAY-12 | Hitung mundur kedaluwarsa | Pembeli | P§3.1 butir 5, P§4.1 | Hitung mundur dari `expires_at` yang dikirim server (dikoreksi selisih jam klien). Saat nol, tampilan beralih ke keadaan kedaluwarsa setelah konfirmasi status dari server; klien tidak menentukan status. Hitung mundur diumumkan ke teknologi bantu secara hemat (tidak tiap detik) dan menghormati reduced-motion. | BB+WB |
| PAY-13 | Pembaruan status otomatis | Pembeli | P§3.2 butir 6, P§3.1 | Halaman instruksi memeriksa status pembayaran milik sendiri secara berkala (default tiap 3 detik, hanya saat tab terlihat). Begitu status final, pembeli dialihkan ke halaman hasil yang sesuai (PAY-45/46/47) — termasuk saat pembayaran dilakukan dari HP lain. | BB |
| PAY-14 | Tombol alternatif dan peringatan URL | Pembeli | P§3.1 butir 5, P§2 | Tombol "Buka Halaman Simulasi Pembayaran" membuka URL simulasi di tab baru. Bila host `APP_URL` adalah loopback (`localhost`/`127.x`), halaman menampilkan peringatan bahwa QR tidak dapat dibuka dari perangkat lain dan menyarankan tombol alternatif. | BB+WB |

#### Halaman simulasi dan aksinya

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| PAY-15 | Halaman simulasi pembayaran | Pemegang token | P§3.2, P§4.1, P§9 butir 6 | `GET /pay/sim/<token>` menampilkan: judul dan label SIMULASI PEMBAYARAN besar, nomor pesanan, ringkasan produk, total, metode berlabel simulasi, nomor referensi/VA, waktu dibuat dan kedaluwarsa, hitung mundur yang sama dengan PAY-12, tiga tombol aksi, serta panel HTTP (PAY-37..PAY-42). Rancang mobile-first karena dibuka dari HP. | BB |
| PAY-16 | Aksi "Bayar Sekarang (Simulasi)" | Pemegang token | P§3.2 butir 4–5, P§4.1 (K-09) | Hanya bila pembayaran `pending` dan belum kedaluwarsa. Memicu callback `paid` lewat jalur webhook yang sama (PAY-36). Hasil: pembayaran `paid`, pesanan `dibayar`, event `purchase`, stok dikonfirmasi; pembeli di halaman instruksi dialihkan (PAY-13) dan halaman simulasi menampilkan pesan berhasil. Tombol dikunci saat diproses (anti klik ganda). | BB+WB |
| PAY-17 | Aksi "Simulasikan Gagal" | Pemegang token | P§3.3, P§4.1 (K-09) | Hanya bila `pending` dan belum kedaluwarsa. Memicu callback `failed` (PAY-30); tidak ada event `purchase`. | BB+WB |
| PAY-18 | Aksi "Kembali ke Pesanan" | Pemegang token | P§4.1 | Tidak mengubah status apa pun. Untuk pemilik yang sedang login → halaman detail pesanan; untuk perangkat lain tanpa sesi → halaman masuk pembeli. | BB |
| PAY-19 | Token tidak dikenal | Pengunjung | P§4.3, P§3.3 | Token tidak ada/ salah format → 404 generik tanpa membocorkan apakah token pernah ada; percobaan tercatat pada log keamanan dan dibatasi lajunya. | BB+WB |
| PAY-20 | Token kedaluwarsa | Pemegang token | P§3.3, P§4.1 | Pembayaran yang melewati `expires_at` ditampilkan sebagai kedaluwarsa dengan pesan jelas; tombol Bayar/Gagal tidak tersedia; upaya aksi ditolak server dan dicatat pada jejak. | BB+WB |
| PAY-21 | Token sudah final atau dipakai ulang | Pemegang token | P§3.3 (akhir) | Untuk `paid`, `failed`, `expired`, `cancelled`: halaman menampilkan status final; setiap upaya aksi ditolak dengan pesan jelas, status tidak berubah, dan upaya dicatat pada jejak pembayaran. | BB+WB |
| PAY-22 | Akses non-pemilik | Pemegang token | P§4.3 (U-11, K-12) | Token saja cukup membuka halaman dan aksi. Halaman hanya menampilkan data yang diperlukan untuk pembayaran tersebut (nomor pesanan, nama produk, jumlah, total, metode, status); tidak menampilkan nama, email, atau data pribadi pembeli. | BB+WB |
| PAY-23 | Nominal tidak berasal dari URL | Sistem | P§2, P§4.2 | Nominal selalu dibaca dari pesanan di server. Parameter `amount` atau sejenisnya pada URL/body aksi diabaikan; payload callback yang memuat nominal berbeda ditolak. Panel HTTP tidak menyediakan bidang yang dapat diedit. | WB+BB |

#### Webhook, state machine, dan dampaknya

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| PAY-24 | Endpoint webhook berversi | Sistem | P§7 butir 6, P§4.2 (K-08) | `POST /api/v1/webhooks/payments/{provider}`; provider dikenal hanya `simulator` pada tahap ini, provider lain → 404. Body JSON `payment_id`, `order_id`, `status`, `paid_at`, `reference`. Endpoint dikecualikan dari proteksi CSRF tetapi dilindungi verifikasi signature (PAY-25). | WB+BB |
| PAY-25 | Verifikasi signature simulasi | Sistem | P§4.2, P§7 butir 6 | Header `X-Simulator-Signature` diverifikasi (HMAC-SHA256 atas body mentah dengan kunci dari environment, perbandingan waktu-konstan). Signature hilang/salah → 401, status tidak berubah, percobaan tercatat. UI memberi catatan eksplisit: "signature ini dummy, bukan signature gateway riil". Kunci tidak pernah ditampilkan. | WB |
| PAY-26 | Validasi payload callback | Sistem | P§4.2, P§1 butir 3 | `status` ∈ {`paid`, `failed`, `expired`}; `payment_id` ada; `order_id` dan `reference` cocok dengan pembayaran; field wajib lengkap; `paid_at` wajib dan valid bila `paid`. Payload tidak valid → 422 tanpa perubahan data. | WB |
| PAY-27 | Idempotency callback | Sistem | P§5.3, P§9 butir 8 | Callback dengan hasil sama untuk `payment_id` yang sudah final → 200 "sudah diproses" tanpa efek ganda (stok, event, riwayat). Callback dengan hasil berbeda untuk pembayaran final → 409, status tetap, tercatat pada jejak. Diuji dengan callback ganda serentak. | WB |
| PAY-28 | State machine pembayaran | Sistem | P§5.1 | Hanya `pending → paid/failed/expired/cancelled`; status final tidak dapat diubah. Seluruh perubahan status melewati satu layanan yang sama (callback, job, pembatalan, generator developer). Diuji sebagai tabel transisi. | WB |
| PAY-29 | Dampak `paid` | Sistem | P§3.2 butir 5, P§5.2 | Dalam satu transaksi basis data: pembayaran `paid` (+`paid_at`), pesanan `dibayar`, event `purchase` per item (EVT-05), stok dikonfirmasi (STK-03), riwayat status dan jejak ditulis. Gagal di tengah → seluruhnya dibatalkan. | WB+BB |
| PAY-30 | Dampak `failed` | Sistem | P§3.3, P§5.2 (U-12) | Pembayaran `failed`; pesanan tetap `menunggu_pembayaran`; tidak ada `purchase`; stok tetap dicadangkan; halaman hasil gagal menawarkan bayar ulang atau batalkan (PAY-46). | WB+BB |
| PAY-31 | Pembayaran ulang setelah gagal | Pembeli | P§3.3, P§5.2 (K-13, U-12) | Selama pesanan `menunggu_pembayaran` dan batas bayar belum terlewati, pembeli dapat membuat pembayaran **baru** (metode boleh berbeda) dengan token/referensi baru. Pembayaran lama tetap `failed`. Hanya satu pembayaran `pending` per pesanan (PAY-63). | BB+WB |
| PAY-32 | Dampak `expired` | Sistem | P§3.3, P§5.2 | Pembayaran `pending` → `expired`, pesanan → `kedaluwarsa`, stok dilepas (STK-04), event `cancel` (alasan `expired`), tanpa `purchase`; riwayat dan jejak ditulis. | WB+BB |
| PAY-33 | Dampak `cancelled` | Sistem | P§3.3, P§5.2 | Dipicu ORD-07: pembayaran `pending` → `cancelled`; token tidak dapat dipakai lagi (PAY-21). | WB+BB |
| PAY-34 | Job kedaluwarsa terjadwal | Sistem | P§3.3, P§9 butir 7 | Penjadwal menjalankan job tiap menit: pesanan `menunggu_pembayaran` dengan `payment_deadline_at ≤ sekarang` dikedaluwarsakan melalui layanan PAY-28/PAY-32 (pemicu `job_kedaluwarsa`). Idempoten, aman dijalankan bersamaan (penguncian baris), memproses dalam batch terbatas. Pesanan `menunggu_pembayaran` tanpa pembayaran `pending` (setelah `failed`) juga dikedaluwarsakan. | WB |
| PAY-35 | Kedaluwarsa saat diakses | Sistem | P§3.3, P§4.1 | Bila halaman instruksi/simulasi/aksi dibuka setelah batas bayar tetapi sebelum job berjalan, sistem memproses kedaluwarsa lebih dulu (layanan yang sama) lalu merespons; kebenaran status tidak bergantung pada jeda penjadwal. | WB+BB |
| PAY-36 | Transport callback simulasi | Sistem | P§4.2 (catatan), P§3.2 butir 5 (U-18) | Aksi Bayar/Gagal dan job menghasilkan callback yang benar-benar diproses endpoint PAY-24 melalui request internal (routing, middleware, verifikasi, controller yang sama). Request dan response yang ditampilkan pada panel adalah rekaman kejadian nyata ini, bukan teks karangan. | WB |

#### Panel Simulated HTTP Request & Response dan jejak

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| PAY-37 | Panel: request pembuatan pembayaran | Pemegang token, Pembeli, Admin | P§4.2 butir 1, P§9 butir 9 | Menampilkan method dan path, body JSON (`order_id`, `amount`, `currency=IDR`, `payment_method`, `return_url`/`callback_url` simulasi, `reference`; tanpa secret) beserta response (status code, `payment_id`, `payment_token`, `qr_url` atau `va_number`, `expires_at`). | BB+WB |
| PAY-38 | Panel: callback simulasi | Pemegang token, Pembeli, Admin | P§4.2 butir 2 | Menampilkan method/path, header (`X-Simulator-Signature`, `Content-Type: application/json`) dengan catatan "dummy", body (`payment_id`, `order_id`, `status`, `paid_at`, `reference`), response sistem (status code) dan status pesanan setelah callback diproses. | BB+WB |
| PAY-39 | Timeline transisi status | Pemegang token, Pembeli, Admin | P§4.2 butir 3, P§6 | Daftar kronologis: waktu (WITA), status lama → baru, pemicu (`tombol_pembeli`, `job_kedaluwarsa`, `pembeli_batal`, `panel_developer`), dan ID request terkait. | BB |
| PAY-40 | Salin JSON | Pemegang token, Pembeli, Admin | P§4.2 butir 4 | Tombol salin untuk request dan response masing-masing; konfirmasi "Tersalin"; teks tersalin identik dengan yang tampil. | BB |
| PAY-41 | Salin sebagai cURL | Pemegang token, Pembeli, Admin | P§4.2 butir 4 | Menghasilkan perintah cURL untuk callback simulasi dengan URL absolut, header dan body yang tampil, memakai token/signature simulasi (dummy), tanpa secret lain. Menjalankannya sesuai aturan PAY-25/PAY-27. | BB+WB |
| PAY-42 | Panel read-only | Semua pemakai panel | P§4.2 butir 4, P§4.1 | Panel hanya menampilkan; tidak ada kontrol yang mengubah nominal atau status. Isi JSON ditampilkan dengan encoding aman (anti-XSS). | BB+WB |
| PAY-43 | Penyimpanan jejak tak-ubah | Sistem | P§6 | Setiap transisi status menyimpan baris jejak dengan snapshot request/response (bila ada lalu lintas) dan ID request; baris tidak diubah setelah ditulis. Jejak dapat dibuka kembali dari halaman pesanan dan halaman admin. Pembayaran buatan panel developer memiliki timeline transisi tanpa snapshot HTTP, dengan catatan "Dibuat oleh panel developer — tanpa lalu lintas HTTP" (K-05). | WB |
| PAY-44 | Redaksi data sensitif pada jejak | Sistem | P§7 butir 5, P§10 | Jejak dan panel tidak pernah memuat secret, kunci signature, header otorisasi, cookie, atau kredensial apa pun. Diuji dengan pemindaian terhadap data jejak yang dihasilkan. | WB |

#### Halaman hasil, struk, riwayat, dan akses admin

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| PAY-45 | Halaman hasil berhasil | Pembeli | P§3.2 butir 6, P§9 butir 10 | Menampilkan ringkasan pesanan, status terbaru, ringkasan pembayaran simulasi, tautan ke jejak HTTP (PAY-50) dan struk (PAY-48), label SIMULASI. | BB |
| PAY-46 | Halaman hasil gagal | Pembeli | P§3.3, P§9 butir 10 | Menjelaskan pembayaran gagal dan status pesanan, menawarkan "Pilih metode lain / bayar ulang" (PAY-31) dan "Batalkan pesanan" (ORD-07) selama masih diizinkan; tidak ada pesan yang menyiratkan uang riil. | BB |
| PAY-47 | Halaman hasil kedaluwarsa | Pembeli | P§3.3, P§9 butir 10 | Menjelaskan pembayaran kedaluwarsa dan pesanan `kedaluwarsa`, tanpa aksi bayar; menyediakan tautan ke riwayat pesanan dan katalog. | BB |
| PAY-48 | Struk pembayaran simulasi | Pembeli | P§9 butir 13 (label pada struk), P§3.2 butir 6 | Untuk pembayaran `paid`: halaman struk dapat dicetak (nomor pesanan, item, total, metode, waktu bayar) dengan label "STRUK SIMULASI — bukan bukti pembayaran sah" yang tercetak. Bentuk struk adalah usulan; `P` hanya mewajibkan labelnya. | BB |
| PAY-49 | Riwayat pembayaran pembeli | Pembeli | P§8, P§9 butir 11 | Daftar pembayaran milik sendiri: pesanan, metode, nominal, status, waktu; berpaginasi; filter status; keadaan kosong. | BB |
| PAY-50 | Detail pembayaran pembeli dan jejak | Pembeli | P§8, P§6 | Detail pembayaran milik sendiri dengan panel HTTP dan timeline (PAY-37..PAY-42). Pembayaran milik orang lain → respons seperti tidak ada (ACC-09). | BB+WB |
| PAY-51 | Daftar pembayaran admin | Admin Toko | P§8, P§9 butir 11 | Tabel read-only berpaginasi: pesanan, pembeli, metode, nominal, status, waktu, asal data; filter status, metode, tanggal. | BB |
| PAY-52 | Detail pembayaran admin dan jejak | Admin Toko | P§8, P§6 | Detail pembayaran dengan panel HTTP dan timeline read-only. | BB |
| PAY-53 | Admin tidak dapat mengubah pembayaran | Admin Toko | P§8 (K-03) | Tidak ada rute/kontrol untuk mengubah status pembayaran oleh Admin atau Developer; permintaan manual ke rute semacam itu → 404/405. | WB+BB |
| PAY-54 | Label SIMULASI di semua tampilan pembayaran | Sistem | P§2, P§9 butir 13, P§3.1 butir 5 | Label jelas dan tidak dapat dilewatkan pada: pilihan metode, halaman instruksi, QR (pita pada gambar), halaman simulasi, halaman hasil, struk, riwayat/detail pembayaran, daftar admin, dan panel jejak. Label berupa teks + ikon (tidak mengandalkan warna saja). Diuji dengan daftar tampilan. | BB |

#### Arsitektur ruang gateway riil (persiapan, bukan integrasi)

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| PAY-55 | Interface adapter pembayaran | Sistem | P§7 butir 1, P§1 butir 4 | Checkout, pesanan, dan analitik hanya memanggil satu interface dengan `createPayment(order, method)`, `getPaymentStatus(payment_id)`, `handleCallback(payload, signature)`, `cancelPayment(payment_id)`. Tidak ada kode alur yang merujuk kelas simulator secara langsung (diuji dengan analisis statis/aturan arsitektur). | WB |
| PAY-56 | SimulatorPaymentAdapter | Sistem | P§7 butir 2 | Satu-satunya implementasi saat ini; tidak memanggil API eksternal; membuat QR/VA dummy, halaman simulasi, dan callback simulasi. | WB |
| PAY-57 | Switch `PAYMENT_DRIVER` | Operator | P§7 butir 5 | Nilai `PAYMENT_DRIVER=simulator` memilih adapter aktif. Nilai kosong atau tidak dikenal → aplikasi gagal start dengan pesan jelas (fail fast). Rute `/pay/sim/*` dan transport simulasi hanya aktif bila driver `simulator`. | WB |
| PAY-58 | Abstraksi QR | Sistem | P§7 butir 7 | Adapter mengembalikan objek instruksi `QrPayload` (jenis + nilai); pembuat gambar QR terpisah dari adapter. Halaman instruksi tidak mengetahui apakah nilai itu URL simulasi atau payload provider. | WB |
| PAY-59 | Registri provider dan verifikator signature | Sistem | P§7 butir 6 | Webhook memilih verifikator signature berdasarkan `{provider}` dari registri; simulator memakai verifikator dummy (PAY-25). Menambah provider = menambah entri registri + adapter + verifikator, tanpa mengubah checkout/pesanan/analitik. | WB |
| PAY-60 | Kredensial hanya lewat environment | Operator | P§7 butir 5, P§10 | Kunci/secret (mis. kunci signature simulasi) hanya dibaca dari environment; tidak ada pada kode, repositori, UI, jejak, maupun log. Berkas contoh environment berisi placeholder. | WB |
| PAY-61 | Tanpa branding dan tanpa klaim gateway riil | Sistem | P§7 butir 8, P§10 | Tidak ada logo/branding/warna merek bank atau e-wallet; teks UI dan dokumen tidak menyatakan pembayaran diproses oleh pihak tersebut atau bahwa gateway riil terhubung. | BB |
| PAY-62 | Data minimal pembayaran | Sistem | P§6 | Tersimpan: `payment_id`, `order_id`, `buyer_id`, `payment_method`, `provider=simulator`, `amount`, `currency=IDR`, `status`, `reference_code`, `qr_url`/`payment_token`, `expires_at`, `paid_at`, `is_simulated=true`, jejak. | WB |
| PAY-63 | Satu pembayaran `pending` per pesanan | Sistem | P§3.1 butir 4, P§3.3 | Klik ganda atau dua tab tidak membuat dua pembayaran `pending` untuk satu pesanan; dijamin oleh constraint basis data dan penguncian. | WB |

### 10.6 SIM — Panel Developer (Sistem Simulator data) (tahap M4, penyempurnaan M7)

Panel ini **bukan** payment simulator (§4.2). Developer tidak mengubah pembayaran individual (`P§8`); pembayaran yang menyertai transaksi buatannya dibuat otomatis (SIM-30).

#### Dasar panel, akun massal

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| SIM-01 | Panel developer dan penanda SIMULATOR | Developer | D§4.3, D§3, D§7 | Panel terpisah dari tampilan Admin/Pembeli, dengan penanda permanen "SIMULATOR — data eksperimental" (teks + ikon) pada semua halamannya. Menu: Beranda, Buat Akun, Transaksi per User, Transaksi per Periode, Funnel, Akun Simulasi, Jejak & Audit, Reset. | BB |
| SIM-02 | Buat akun pembeli simulasi massal | Developer | D§7.1, D§4.3, D§1 butir 6 | Input: jumlah akun (integer ≥ 1), mode biodata, seed (bila deterministik). Sukses → N akun pembeli simulasi dibuat dalam satu transaksi (semua atau tidak sama sekali) dan hasilnya (jumlah, rentang, run) ditampilkan. Satu aksi menghasilkan satu run (SIM-35). | BB+WB |
| SIM-03 | Batas 100 akun per request | Developer | D§7.1, D§1 butir 6, D§7.5 | 1–100 diterima; 0, negatif, non-integer, kosong, dan ≥ 101 ditolak dengan pesan bahwa jumlah lebih besar harus dipecah menjadi beberapa request; tidak ada akun yang dibuat. Batas dijaga di server dan tidak dapat dikonfigurasi melebihi 100. | BB+WB |
| SIM-04 | Biodata mode random | Developer | D§7.1 butir "Random" | Biodata dihasilkan acak lewat kontrak generator (SIM-06); dua eksekusi dapat menghasilkan biodata berbeda. Email dijamin unik; bila ada tabrakan dengan akun yang ada, dibangkitkan ulang sampai unik atau permintaan ditolak utuh. | WB+BB |
| SIM-05 | Biodata mode deterministik | Developer | D§7.1 butir "Deterministik", D§10 butir 3 (U-02) | Seed integer menghasilkan **isi biodata yang identik** pada eksekusi ulang dengan seed, offset, dan jumlah yang sama (ID dan waktu pembuatan dikecualikan dari perbandingan). Opsi `offset` mulai-dari-indeks mencegah tabrakan antar-run. Bila hasil akan bertabrakan email dengan akun yang ada → seluruh permintaan ditolak dengan pesan yang menyarankan seed/offset lain atau reset. Default mode dan seed: U-02. | WB+BB |
| SIM-06 | Kontrak generator biodata | Sistem | D§7.1, D§1 butir 8, D§10 butir 2 (U-02b) | Generator diakses lewat antarmuka tunggal yang menerima jumlah, offset, dan seed (opsional) dan mengembalikan daftar biodata (minimum `name`, `email`). Isi script final **tidak ditetapkan** di PRD ini; script dari pemilik proyek dipasang di balik antarmuka yang sama tanpa mengubah fitur lain. | WB |
| SIM-07 | Penanda dan registrasi akun simulasi | Sistem | D§7.1, D§2 butir 2 | Setiap akun hasil simulasi: role `pembeli`, `origin=developer_simulation`, `simulation_run_id`, email pada domain tercadang (`SIM_ACCOUNT_EMAIL_DOMAIN`, TLD `.test`), status aktif. Dari sisi Sistem Asli ia akun pembeli biasa (ACC-15). Register publik menolak domain tercadang (ACC-01). | WB |
| SIM-08 | Kredensial akun simulasi | Sistem | D§7.1 (akun dapat login), D§2 (U-28) | Kata sandi diambil dari `SIM_ACCOUNT_PASSWORD` dan disimpan sebagai hash per akun; tidak pernah ditampilkan, dicatat, maupun disimpan pada audit. Bila variabel tidak diisi, pembuatan akun ditolak dengan pesan konfigurasi. | WB |

#### Aksi 2 — user-first

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| SIM-09 | Pilih akun simulasi | Developer | D§7.2 | Memilih 1–100 akun simulasi dari daftar bercari. Akun normal tidak dapat dipilih (ditolak di server walau ID dikirim manual). Pilihan kosong ditolak. | BB+WB |
| SIM-10 | Pilih periode transaksi | Developer | D§7.2, D§1 butir 7 | Tanggal mulai dan akhir (inklusif, WITA), termasuk masa lampau. Aturan umum periode pada SIM-28. | BB+WB |
| SIM-11 | Tentukan produk, jumlah, dan banyak transaksi | Developer | D§7.2 | Minimal: satu atau lebih produk **aktif**, jumlah item per transaksi (min–max, default 1–3, tanpa produk ganda dalam satu pesanan), rentang jumlah per item (min–max, integer 1–99), dan jumlah transaksi per akun (integer ≥ 1) dengan total seluruh akun ≤ batas transaksi per request (SIM-17). Input tidak valid ditolak sebelum pratinjau. | BB+WB |
| SIM-12 | Tentukan pola aktivitas penyerta | Developer | D§7.2, D§7.3 butir 6, D§7.4 | Pilih profil funnel per transaksi: rasio view→add_to_cart (default usulan 40%) dan add_to_cart→booking (default usulan 60%); rasio booking→purchase ditentukan oleh campuran status akhir (SIM-23). Dengan default, jumlah menyusut di tiap tahap (D§8). Nilai di luar 1–100% ditolak. Satu "transaksi" pada panel = satu pesanan (satu `booking`). | BB+WB |
| SIM-13 | Eksekusi pembuatan transaksi backdate | Developer | D§7.2, D§1 butir 7 | Menghasilkan pesanan, item, event funnel, dan pembayaran simulasi untuk akun terpilih dengan stempel waktu di dalam periode (bukan waktu hari ini). Semua baris bertanda `developer_simulation` dan `simulation_run_id`. | BB+WB |
| SIM-14 | Aturan Sistem Asli tetap berlaku | Sistem | D§7.2, D§7.5 | Produk harus ada dan aktif, pesanan memakai state machine yang sama (ORD-06, PAY-28), pembayaran mengikuti §9.1, status akhir konsisten dengan pemetaan §9.2. Pelanggaran → request ditolak utuh. | WB |
| SIM-15 | Pengecualian simulasi yang dinyatakan eksplisit | Sistem | D§7.2 kalimat terakhir (K-10, U-21) | Hanya tiga pengecualian: (1) stok katalog **tidak dikurangi** dan tidak dicadangkan oleh transaksi simulator (tidak ada entri gerakan stok); (2) harga item memakai harga produk saat eksekusi; (3) tanggal pembuatan produk tidak divalidasi terhadap tanggal periode. Pengecualian dicatat pada audit run dan ditampilkan di pratinjau. | WB |

#### Aksi 3 — period-first

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| SIM-16 | Pilih periode atau rentang periode | Developer | D§7.3 butir 2, D§1 butir 9 | Pilihan cepat: satu tanggal, satu minggu, satu bulan, rentang kustom; semuanya mengisi tanggal mulai–akhir yang dapat diedit. Mendukung masa lampau. Aturan umum pada SIM-28. | BB+WB |
| SIM-17 | Jumlah transaksi dan batasnya | Developer | D§7.3 butir 3, D§10 butir 4/9 (U-03) | Integer 1–`SIM_MAX_TRANSACTIONS_PER_REQUEST` (default 500). 0, negatif, non-integer, dan di atas batas ditolak dengan pesan untuk memecah request. Batas 100 untuk akun **tidak** otomatis berlaku di sini. | BB+WB |
| SIM-18 | Cakupan user | Developer | D§7.3 butir 4 | Empat pilihan: satu user tertentu; beberapa user terpilih; semua akun simulasi; sejumlah user yang dipilih acak oleh sistem (input k, seed opsional). Hanya akun simulasi yang dapat dicakup. | BB+WB |
| SIM-19 | Validasi cakupan user | Developer | D§7.3 butir 4, D§7.5 | Tidak ada akun simulasi → ditolak dengan petunjuk membuat akun lebih dulu; "beberapa user" tanpa pilihan → ditolak; k acak > jumlah akun tersedia → ditolak; akun nonaktif dilewati dan pelewatan itu diberitahukan di pratinjau. | WB+BB |
| SIM-20 | Distribusi waktu transaksi | Developer | D§7.3 butir 5, D§10 butir 9 (U-08) | Pilihan: acak (default), merata. Stempel waktu tersebar pada seluruh hari dalam periode (bukan menumpuk pada satu waktu). Opsi eksplisit "semua pada satu titik waktu" (tanggal+jam) diizinkan hanya bila dipilih sengaja. Profil naik/turun: SIM-34. | WB+BB |
| SIM-21 | Distribusi antar-user | Developer | D§7.3, D§10 butir 9 | Pilihan: acak (default), merata, berbobot (sebagian user dominan). Dengan "merata", selisih jumlah transaksi antar-user ≤ 1. | WB |
| SIM-22 | Cakupan dan bobot produk | Developer | D§7.2, D§7.4, D§8 | Produk dipilih (default semua produk aktif, atau subset) dengan bobot merata/acak/berbobot; transaksi hanya memuat produk terpilih. Dibutuhkan agar produk naik, turun, dan terlaris/terendah dapat dibentuk (D§8). | BB+WB |
| SIM-23 | Campuran status akhir transaksi | Developer | D§7.3 butir 6, P§5.4 | Rasio status akhir yang jumlahnya 100% (default usulan: `selesai` 80%, `dibatalkan` 10%, `kedaluwarsa` 10%). `selesai` → pembayaran `paid` (SIM-30); `dibatalkan` → pembayaran `cancelled`; `kedaluwarsa` → pembayaran `expired`. Jumlah tidak 100% ditolak. | WB+BB |
| SIM-24 | Event funnel pada setiap transaksi | Developer | D§7.3 butir 6, D§7.4 | Setiap transaksi disertai event `view`, `add_to_cart`, `booking` (dan `purchase` untuk yang `selesai`, `cancel` untuk yang batal/kedaluwarsa) sehingga FR-01 s.d. FR-05 terbaca. Jumlah event tiap tahap tidak pernah lebih besar dari tahap sebelumnya pada data yang dihasilkan. | WB |

#### Pratinjau, eksekusi, dan integritas

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| SIM-25 | Pratinjau sebelum eksekusi | Developer | D§7.3 (terbuka), D§10 butir 9 (U-08) | Aksi 2, 3, dan 4 wajib melewati pratinjau tanpa menulis data: jumlah transaksi, akun, perkiraan jumlah baris/event, periode, produk, status akhir, seed, dan pengecualian simulasi (SIM-15). Pratinjau tidak mengubah data (diuji dengan perbandingan hitungan baris). | BB+WB |
| SIM-26 | Konfirmasi dan anti-submit ganda | Developer | D§7.3 (terbuka), D§7.5 | Eksekusi hanya setelah konfirmasi eksplisit pada pratinjau; kunci idempotensi membuat submit ganda menghasilkan satu run saja. Parameter yang berubah setelah pratinjau mewajibkan pratinjau ulang. | BB+WB |
| SIM-27 | Atomisitas dan rollback | Developer | D§7.3 (terbuka), D§7.5 (U-08) | Seluruh isi satu request dieksekusi dalam satu transaksi basis data: gagal di tengah → tidak ada data tersisa, run berstatus `gagal` dengan pesan galat, dan audit tetap tercatat. | WB |
| SIM-28 | Aturan periode simulasi | Sistem | D§7.2, D§7.3, D§1 butir 7 (U-03) | Tanggal mulai ≤ tanggal akhir; tanggal akhir tidak di masa depan (WITA); panjang periode ≤ 366 hari (`SIM_MAX_PERIOD_DAYS`); format tanggal valid. Pelanggaran → ditolak dengan pesan spesifik. | WB+BB |
| SIM-29 | Stempel waktu dan urutan kronologis | Sistem | D§7.2, D§7.3 butir 5 | Semua stempel waktu (event, pesanan, `paid_at`, riwayat) berada dalam [mulai 00:00:00, akhir 23:59:59.999] WITA. Untuk setiap transaksi: `view ≤ add_to_cart ≤ booking ≤ paid_at`, dan `paid_at ≤ batas bayar pesanan`. | WB |
| SIM-30 | Pembayaran simulasi backdate untuk transaksi selesai | Sistem | P§5.4, P§5.1 (K-05) | Setiap transaksi `selesai` memiliki pembayaran `paid` dengan `paid_at` di periode, ditulis lewat layanan state machine yang sama dengan pemicu `panel_developer`; transaksi `dibatalkan`/`kedaluwarsa` memiliki pembayaran `cancelled`/`expired`. Tidak ada booking tanpa konsekuensi pembayaran yang konsisten (FR-03/FR-04 tidak terlihat rusak). | WB+BB |

#### Aksi 4 — event funnel, variasi pola

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| SIM-31 | Buat funnel lengkap untuk akun dan produk terpilih | Developer | D§7.4, D§4.3 | Input: akun simulasi, produk, periode, dan jumlah per tahap `view` ≥ `add_to_cart` ≥ `booking` ≥ `purchase` ≥ 0 (urutan menyusut divalidasi). Hasil: event `view` dan `add_to_cart` sebanyak jumlahnya; setiap `booking` adalah pesanan nyata, dari situ `purchase` adalah yang berpembayaran `paid` dan sisanya `dibatalkan`/`kedaluwarsa` (D§7.4 menuntut pembatalan sebagai variasi). Batas total event per request (U-03, default 2.000). | BB+WB |
| SIM-32 | Preset pola minat tanpa pembelian | Developer | D§7.4, FR-05, D§8 | Preset yang menghasilkan produk dengan `view` dan `add_to_cart` tinggi tetapi `purchase` mendekati nol, berdampingan dengan produk berpembelian tinggi, sehingga sorotan FR-05 dapat diuji. | BB |
| SIM-33 | Preset pola funnel lengkap | Developer | D§7.4, FR-03, D§8 | Preset dengan jumlah mengecil di tiap tahap (banyak dilihat, sebagian dikeranjang, sebagian dibooking, sebagian dibeli). | BB |
| SIM-34 | Profil tren waktu naik/turun | Developer | D§8 (FR-02), D§6.5 butir 7 | Pada Aksi 3, distribusi waktu dapat dipilih menaik atau menurun sehingga ada produk dengan tren naik dan turun; hasilnya terbaca oleh klasifikasi FR-02. Dibangun pada penyempurnaan simulator (tahap M7). | BB+WB |

#### Jejak, audit, reset, perlindungan data

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| SIM-35 | Catatan run simulasi | Sistem | D§7.5, D§4.3 | Setiap aksi menghasilkan satu catatan run: ID, Developer pelaku, jenis aksi, parameter (termasuk seed), periode, status (`berhasil`/`gagal`), jumlah akun/pesanan/event/pembayaran yang dibuat, waktu mulai/selesai. | WB |
| SIM-36 | Audit aksi simulator | Sistem | D§7.5 | Log append-only: siapa, aksi apa, berapa banyak, periode apa, hasil, waktu; termasuk aksi reset. Tidak dapat diubah atau dihapus lewat UI, juga tidak oleh reset. | WB |
| SIM-37 | Lihat jejak simulasi | Developer | D§4.3, D§7.5 | Daftar run (filter jenis, status, tanggal) dan detail run (parameter, jumlah baris, tautan ke akun/pesanan yang dihasilkan). Menampilkan hanya run buatan sistem simulator. | BB |
| SIM-38 | Reset per run | Developer | D§4.3, D§7.5 (U-23) | Menghapus pesanan, item, event, pembayaran, riwayat, dan jejak yang membawa `simulation_run_id` tersebut; bila run itu membuat akun, akun dihapus hanya bila tidak memiliki data `origin=normal`, jika tidak akun dinonaktifkan dan diberitahukan. Konfirmasi dengan mengetik frasa. Atomik. Metrik analitik berubah seketika sesudahnya. | BB+WB |
| SIM-39 | Reset seluruh data simulasi | Developer | D§4.3, D§7.5 (U-23) | Seperti SIM-38 untuk semua baris `origin=developer_simulation`. Jumlah baris `origin=normal` (akun, pesanan, event, pembayaran, stok) **identik** sebelum dan sesudah reset (diuji). Audit tetap ada. | BB+WB |
| SIM-40 | Perlindungan data normal | Sistem | D§7.5, D§2 butir 3 | Semua kueri tulis/hapus panel dibatasi `origin=developer_simulation`. Memasukkan ID pesanan/akun normal secara manual pada aksi apa pun → ditolak. Sistem Asli tetap utuh dan dapat dipakai tanpa panel (D§2 butir 3). | WB+BB |
| SIM-41 | Larangan mengubah akun admin/pembeli normal | Sistem | D§7.5 | Panel tidak memiliki aksi untuk mengubah peran, hak akses, atau data akun Admin/Pembeli normal, dan tidak memiliki akses tulis ke tabel akun selain baris simulasi buatannya. | WB+BB |
| SIM-42 | Dataset contoh | Operator | D§10 butir 7 (U-06) | Seeder membuat 6 kategori dan 40 produk aktif (minimal 5 per kategori, harga dan stok realistis) sebagai data katalog. 50 akun simulasi dibuat lewat aksi SIM-02, bukan seeder tersendiri. Seeder idempoten (aman diulang). | WB |

### 10.7 ANL — Analitik FR-01 sampai FR-06 (tahap M5 s.d. M7)

Seluruh fitur ANL hanya untuk **Admin Toko** (ANL-08). Perhitungan dilakukan servis Python; Laravel menyiapkan data fakta dan menampilkan hasil (U-19). Penjualan dan `purchase` hanya dari pembayaran `paid` (ANL-07).

#### Komponen lintas fitur (`D§5`: bukan fitur tersendiri, tetapi dapat diuji)

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| ANL-01 | Filter periode bersama | Admin Toko | D§5 (komponen lintas), FR-01..FR-06 | Pilihan cepat (Hari ini, 7 hari, 30 hari, Bulan ini, Bulan lalu) dan rentang kustom (mulai ≤ akhir, format valid). Batas hari/minggu/bulan memakai WITA (U-20). Pilihan tersimpan di URL sehingga dapat dibagikan dan bertahan saat refresh. Periode tidak valid → pesan galat dan tampilan sebelumnya dipertahankan. Dipakai identik oleh FR-01 s.d. FR-06. | BB+WB |
| ANL-02 | Filter asal data | Admin Toko | D§10 butir 8 (U-07), D§2 butir 2 | Selector Semua (default) / Hanya normal / Hanya simulasi memengaruhi seluruh angka dan grafik; pilihan aktif selalu tampil di setiap layar analitik dan tersimpan di URL. | BB+WB |
| ANL-03 | Komponen grafik bersama | Admin Toko | D§5 (komponen lintas) | Satu set komponen grafik untuk semua FR: sumbu berlabel dan satuan (Rp, unit, %), tooltip, legenda, keadaan kosong, keadaan galat, tampilan tabel alternatif untuk aksesibilitas; warna dari token dan tidak menjadi satu-satunya pembawa makna. | BB |
| ANL-04 | Pembaruan cepat | Admin Toko | D§5 (komponen lintas) | Setelah transaksi atau event baru tercatat, pemuatan/penyegaran berikutnya pada layar analitik sudah memuat angka baru tanpa proses manual, batch, atau tombol hitung ulang. Target waktu muat: ≤ 3 detik pada rentang 12 bulan dengan dataset contoh (target usulan; diverifikasi pada pengujian). | BB+WB |
| ANL-05 | Penelusuran ke sumber | Admin Toko | D§2 butir 5, D§6.4 | Setiap angka agregat (kartu, batang grafik, baris tabel) menyediakan tautan "Lihat sumber" ke daftar pesanan atau event yang menyusunnya dengan filter yang sama (periode, asal data, produk/kategori). Jumlah baris sumber cocok dengan angka yang ditelusuri. | BB+WB |
| ANL-06 | Kontrak servis Python | Sistem | D§12b | Laravel mengirim data fakta via HTTP ke servis Python internal dan menerima hasil terstruktur; timeout dan kunci internal dari environment; bentuk respons divalidasi sebelum dipakai; servis tidak dapat dijangkau → layar menampilkan keadaan "Perhitungan sedang tidak tersedia" dengan tombol coba lagi (bukan angka nol). Detail kontrak: blueprint §11. | WB+BB |
| ANL-07 | Definisi penjualan dan `purchase` | Sistem | P§1 butir 3, D§6.4 | Penjualan, jumlah transaksi, kuantitas terjual, dan event `purchase` hanya dihitung dari pesanan dengan pembayaran `paid`; pesanan `menunggu_pembayaran`, `dibatalkan`, `kedaluwarsa` tidak dihitung. Pesanan dengan pembayaran `failed` yang kemudian dibayar ulang dihitung sekali. Waktu transaksi = `paid_at` (U-20). **Perubahan 2026-11-05 (K9):** "transaksi berhasil" = jumlah PESANAN paid, sedangkan "pembelian (event)" = jumlah ITEM paid (satu event per item) — keduanya ditampilkan bila berbeda, jangan disamakan. | WB |
| ANL-08 | Hak akses analitik | Sistem | D§6.1, D§4, D§3 (K-06) | Hanya Admin Toko yang dapat membuka layar dan endpoint analitik (termasuk endpoint data grafik). Pembeli, Developer, dan pengunjung ditolak. | BB+WB |

#### FR-01 — Dashboard Analitik Penjualan (P0)

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| ANL-11 | Ringkasan penjualan dan jumlah transaksi | Admin Toko | FR-01, D§5 | Kartu: total penjualan (Rp, format `Rp 1.250.000`) dan jumlah transaksi terhitung pada periode terfilter. Nilai sama dengan penjumlahan pesanan terbayar pada ANL-05. **Perubahan 2026-11-05 (K8):** kartu juga menampilkan perubahan vs periode sebelumnya memakai definisi FR-04 (ANL-43) beserta tanda signifikansinya — ini perbandingan deskriptif, bukan klaim tren. **Tambahan 2026-11-05 (temuan AI B):** jumlah transaksi dihitung dari blok `order_totals` (pesanan paid unik per hari, blueprint §11.3), BUKAN dijumlahkan dari `orders_paid` per produk — pesanan multi-produk akan terhitung ganda bila dijumlahkan. Bila `order_totals` tidak ada, jumlah transaksi = "Belum dapat dihitung" + peringatan. | BB+WB |
| ANL-12 | Produk terlaris | Admin Toko | FR-01, D§8 (FR-01) | Daftar Top 5 (U-27) berdasarkan kuantitas terjual pada periode; seri diurutkan menurut nilai penjualan lalu nama; menampilkan kuantitas dan nilai. Kurang dari 5 produk terjual → tampil sebanyak yang ada. | BB+WB |
| ANL-13 | Produk paling sedikit terjual | Admin Toko | FR-01, D§8 (FR-01) | Daftar 5 produk aktif dengan kuantitas terjual terendah, **termasuk yang 0** (ditulis "0 terjual"); seri diurutkan menurut nama agar deterministik. Produk nonaktif tanpa penjualan tidak ikut. | BB+WB |
| ANL-14 | Grafik tren penjualan | Admin Toko | FR-01, D§5 | Grafik garis/area penjualan (Rp) dengan granularitas harian, mingguan, atau bulanan (default menyesuaikan panjang periode); bucket tanpa transaksi bernilai 0, bukan hilang. | BB+WB |
| ANL-15 | Keadaan kosong dan galat dashboard | Admin Toko | FR-01, D§6.3 | Tanpa data pada periode → keadaan kosong yang menjelaskan penyebab dan menawarkan ubah periode atau (bagi yang punya akses) petunjuk bahwa data diisi lewat Sistem Asli/Developer; galat servis → ANL-06. | BB |

#### FR-02 — Analisis Tren (P0)

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| ANL-21 | Deret waktu penjualan per produk | Admin Toko | FR-02, D§8 (FR-02) | Memilih 1–5 produk dan metrik (kuantitas terjual — default — atau nilai Rp; "permintaan" diartikan kuantitas terjual); grafik garis per produk pada granularitas yang dipilih dengan bucket kosong = 0. | BB+WB |
| ANL-22 | Klasifikasi tren naik/turun/"Tidak ada tren jelas" | Admin Toko | FR-02 | Setiap produk diklasifikasikan **naik**, **turun**, atau **"Tidak ada tren jelas"** oleh servis Python berdasarkan deret waktunya (Mann–Kendall + Sen's slope, koreksi Benjamini–Hochberg atas semua produk aktif pada filter yang sama); data kurang dari jumlah bucket minimum (U-25: 8 minggu) → "data belum cukup" (bukan ditebak). Ambang dan minimum mengikuti U-25. Deret naik monoton → naik; menurun monoton → turun; datar → "Tidak ada tren jelas" (diuji dengan data sintetis). Label lama "stabil" tidak dipakai lagi — tidak adanya bukti tren bukan berarti stabil. | WB+BB |
| ANL-23 | Daftar produk naik dan turun | Admin Toko | FR-02, D§5 | Dua daftar peringkat produk berdasarkan besar perubahan (persen dan absolut) antara awal dan akhir periode; produk dengan basis nol menampilkan **"Belum dapat dihitung"** untuk persen, bukan tak hingga dan bukan "baru"/"—" (Perubahan 2026-11-05, K7). | BB+WB |
| ANL-24 | Transparansi parameter tren | Admin Toko | FR-02, D§2 butir 5 | Layar menampilkan granularitas, jumlah bucket, ambang, serta statistik klasifikasi (p, q terkoreksi BH, beta Sen, perubahan relatif r) yang dipakai, sehingga hasil dapat ditelusur dan dijelaskan. | BB |

#### FR-03 — Analisis Funnel Pelanggan (P0)

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| ANL-31 | Hitungan empat tahap funnel | Admin Toko | FR-03, D§5 | Jumlah event `view`, `add_to_cart`, `booking`, `purchase` pada periode terfilter (jumlah event, bukan pengguna unik). Angka dapat ditelusur (ANL-05). | BB+WB |
| ANL-32 | Rasio konversi antar tahap | Admin Toko | FR-03 | Persentase tiap tahap terhadap tahap sebelumnya dan terhadap `view`; pembagi nol → "—" (tidak error). | BB+WB |
| ANL-33 | Grafik funnel | Admin Toko | FR-03, D§5 | Grafik batang/corong empat tahap dengan label jumlah dan persen; tabel alternatif tersedia. | BB |
| ANL-34 | Funnel per produk | Admin Toko | FR-03, D§8 (FR-03) | Tabel funnel per produk (dapat diurutkan) dan pilihan satu produk untuk grafiknya. | BB+WB |
| ANL-35 | Anotasi booking tidak berlanjut dan anomali | Admin Toko | FR-03, D§7.4, D§8 | Menampilkan jumlah `booking` yang tidak menjadi `purchase`, dipilah dibatalkan, kedaluwarsa, dan masih menunggu. Bila tahap lebih besar dari tahap sebelumnya (mis. karena batas periode), ditandai sebagai catatan data, tidak disembunyikan. | BB+WB |

#### FR-04 — Perbandingan Periode (P1)

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| ANL-41 | Pilih dua periode pembanding | Admin Toko | FR-04, D§8 (FR-04) | Periode A dan B dipilih sebagai hari, minggu, bulan, atau rentang kustom (mis. bulan ini vs bulan lalu). | BB+WB |
| ANL-42 | Validasi periode pembanding | Admin Toko | FR-04 | Masing-masing mulai ≤ akhir dan valid; panjang periode berbeda atau tumpang tindih tidak ditolak tetapi diberi peringatan eksplisit; kedua periode kosong → keadaan kosong. **Perubahan 2026-11-05:** bila panjang periode berbeda, perbandingan juga dinormalisasi per hari (laju A/dA vs B/dB) sebelum disimpulkan — persen mentah saja tidak cukup. | WB+BB |
| ANL-43 | Metrik perbandingan | Admin Toko | FR-04 | Untuk A dan B: total penjualan, jumlah transaksi, kuantitas terjual; selisih absolut dan persen A→B; basis nol → persen **"Belum dapat dihitung"** (bukan "—", Perubahan 2026-11-05, K7). Untuk unit, transaksi, dan pembelian per produk, tampilkan juga tanda signifikansi uji binomial eksak dua sisi (p < 0,05 → "Berbeda dari variasi acak", p ≥ 0,05 → "Belum dapat dibedakan dari variasi acak"); total penjualan (Rp) hanya deskriptif di versi ini. | BB+WB |
| ANL-44 | Grafik perbandingan | Admin Toko | FR-04, D§5 | Grafik berdampingan (batang/garis) kedua periode dengan sumbu dan legenda jelas; tabel alternatif. | BB |
| ANL-45 | Perbandingan per produk | Admin Toko | FR-04 | Daftar produk dengan perubahan terbesar naik dan turun antara A dan B. | BB+WB |
| ANL-46 | Pintasan periode sebelumnya | Admin Toko | FR-04 | Satu aksi mengisi B dengan periode berpanjang sama tepat sebelum A (mis. minggu lalu untuk minggu ini). | BB+WB |

#### FR-05 — Analisis Aktivitas dan Minat Produk (P1)

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| ANL-51 | Jumlah produk yang dilihat | Admin Toko | FR-05 | Menampilkan jumlah produk berbeda yang dilihat dan total event `view` pada periode, dengan penelusuran (ANL-05). | BB+WB |
| ANL-52 | Riwayat aktivitas pelanggan | Admin Toko | FR-05, D§4.2 | Tabel berpaginasi event (waktu WITA, pelanggan, jenis event, produk); filter pelanggan, jenis, periode, asal data; terbaru lebih dulu. Tidak menampilkan data kredensial. | BB |
| ANL-53 | Sorotan minat tinggi, pembelian rendah ("Perlu ditinjau") | Admin Toko | FR-05, D§8 (FR-05) | Daftar produk "Perlu ditinjau" dengan `view` ≥ 20 dan rasio `purchase`/`view` ≤ 5% (U-26) DAN batas atas interval Wilson 95% ≤ 10%; diurutkan menurut selisih `add_to_cart` − `purchase` terbesar, seri → nama menaik; ambang dan interval tampil di layar. Frasa lama "(atau `add_to_cart` tinggi)" dihapus karena tidak terdefinisi (Perubahan 2026-11-05, K4/K6). Produk yang tidak memenuhi ambang tidak disorot. | WB+BB |
| ANL-54 | Ringkasan minat per produk | Admin Toko | FR-05, FR-03 | Per produk: view, add_to_cart, booking, purchase, rasio konversi; dapat diurutkan dan difilter kategori. Memakai data event yang sama dengan FR-03. | BB+WB |
| ANL-55 | Penelusuran dari sorotan ke riwayat | Admin Toko | FR-05, D§2 butir 5 | Dari baris sorotan produk, satu klik membuka riwayat aktivitas (ANL-52) yang sudah terfilter pada produk itu. | BB |
| ANL-56 | Pembanding minat (baru, 2026-11-05, K5) | Admin Toko | FR-05, D§5 | Daftar pembanding berdampingan dengan sorotan ANL-53: produk dengan rasio titik `purchase`/`view` ≥ 10% dan `purchase` ≥ 10, maks 3, urut `purchase` menurun. Pembanding memakai rasio titik dan jumlah minimum (bukan batas bawah interval), supaya produk yang jelas tinggi pembeliannya tidak gugur karena intervalnya. | WB+BB |

#### FR-06 — Analisis Kategori/Karakteristik Produk (P2)

| ID | Fitur | Aktor | Sumber | Kriteria terima | Uji |
| --- | --- | --- | --- | --- | --- |
| ANL-61 | Performa per kategori | Admin Toko | FR-06, D§8 (FR-06) | Per kategori: penjualan (Rp), kuantitas, jumlah transaksi, dan porsi terhadap total pada periode; Σ porsi = 100% (±pembulatan). | BB+WB |
| ANL-62 | Tren penjualan per kategori | Admin Toko | FR-06 | Grafik garis per kategori sepanjang periode pada granularitas terpilih. | BB+WB |
| ANL-63 | Perbandingan kategori antar periode | Admin Toko | FR-06, FR-04 | Matriks kategori × dua periode dengan selisih; memakai pemilih periode ANL-41. | BB+WB |
| ANL-64 | Karakteristik produk: kelompok harga | Admin Toko | FR-06 ("karakteristik produk"; atribut yang ada hanya kategori dan harga) | Produk dikelompokkan ke rentang harga (batas ditampilkan; usulan 3 kelompok) dan performa tiap kelompok ditampilkan. Karakteristik lain tidak tersedia sampai atribut produk ditambah (di luar scope). | BB+WB |
| ANL-65 | Kategori tanpa transaksi | Admin Toko | FR-06, D§8 (FR-06) | Kategori tanpa penjualan pada periode tampil dengan nilai 0 (tidak hilang); bila hanya satu kategori yang memiliki penjualan, tampil catatan bahwa perbandingan antar-kategori belum bermakna. | BB |

#### Insight otomatis — templat (baru, 2026-11-05, K11)

Insight BUKAN generasi bahasa: kalimat ringkasan di setiap layar adalah templat deterministik yang diisi angka hasil fitur lain, sehingga bisa diuji dan ditelusur ke sumbernya (ANL-05).

| ID | Pemicu | Templat |
| --- | --- | --- |
| INS-01 | Penjualan harian tertinggi dalam periode | "Penjualan tertinggi pada {tgl}: Rp {nilai}, tertinggi dari {n} hari pada periode ini." |
| INS-02 | Penurunan antar tahap funnel terbesar | "Penurunan terbesar: {tahap_a} ke {tahap_b}, {selisih} event ({persen}%)." |
| INS-03 | Produk berlabel "Perlu ditinjau" | "{produk}: {V} kali dilihat, {C} masuk keranjang, {P} pembelian ({r_b}%; interval 95%: {L}–{U}%)." |
| INS-04 | Perubahan produk/total dengan p ≥ 0,05 | "Perubahan {objek} ({A} dibanding {B}) belum dapat dibedakan dari variasi acak (p = {p})." |
| INS-05 | Tren FR-02 berlabel Naik/Turun | "{produk} menunjukkan kecenderungan {naik/turun} dengan bukti {kuat/cukup} selama {n} minggu (perubahan sekitar {r}%)." |
| INS-06 | Ringkasan asal data | "{n_normal} transaksi normal + {n_sim} transaksi simulasi = {total} transaksi berhasil." |

Guardrail: setiap kalimat diturunkan dari angka yang tampil di layar dan punya tautan "Lihat sumber"; kata sebab ("karena", "disebabkan", "akibat", "penyebab") dilarang; rekomendasi tindakan dilarang (hanya daftar pemeriksaan tetap, mis. "Periksa harga, ketersediaan, dan catatan aktivitas"); setiap kalimat menyebut periode dan filter asal data; bila data belum cukup, kalimat diganti "belum cukup data untuk disimpulkan". Modul insight terpisah dari statistik inti, supaya bisa dimatikan tanpa merusak FR-01–FR-06. Diimplementasikan setelah output FR-01–FR-06 tersedia (M6/M7, bukan milestone tunggal).

## 11. Persyaratan Non-Fungsional (kendala dari AGENTS.md dan DESIGN.md)

Butir berikut **bukan fitur produk**; ini kendala mutu yang berlaku pada semua fitur. Sumbernya `AGENTS.md`/`DESIGN.md`, sehingga tidak diberi ID fitur. Tiap kendala diperiksa pada tahap verifikasi (§13).

| ID | Kendala | Rujukan | Pemeriksaan |
| --- | --- | --- | --- |
| NFR-01 | Semua validasi, otorisasi, dan aturan bisnis dijalankan di server; identitas pelaku dan cakupan (akun) hanya dari sesi terverifikasi, tidak dari form, body, header, atau parameter URL. Mutasi yang tidak mengenai baris dianggap gagal, bukan sukses. | AGENTS §8, §9, §10 | Uji otorisasi per rute; uji IDOR; uji "0 baris terpengaruh". |
| NFR-02 | Perlindungan serangan web standar: kueri berparameter (tanpa SQL dari input mentah), encoding keluaran kontekstual (XSS), CSRF untuk request berubah-state yang memakai cookie, pengalihan hanya ke tujuan internal, header keamanan restriktif. Teks dari data (nama, deskripsi, JSON jejak) diperlakukan sebagai data, bukan HTML. | AGENTS §9 | Uji payload XSS/SQLi pada input; pemindaian header. |
| NFR-03 | Sesi memakai mekanisme bawaan framework: cookie HTTP-only, Secure (pada HTTPS), SameSite; umur terbatas; ID diregenerasi saat login; dapat dicabut; perubahan status akun memutus sesi. Guard Developer terpisah dari guard lain. | AGENTS §9 | Uji sesi, uji logout, uji akun nonaktif. |
| NFR-04 | Pembatasan laju pada login (tiga form), token pembayaran (`/pay/sim/*`), webhook, dan aksi simulator, dijalankan sebelum pekerjaan mahal (mis. hashing). Kode sekali pakai tidak dapat diputar ulang. | AGENTS §9 | Uji lonjakan percobaan. |
| NFR-05 | Tidak ada secret/kredensial/token/kunci pada repositori, log, UI, jejak, atau respons; berkas contoh environment berisi placeholder; data pribadi minimum, tidak masuk URL/log/pesan galat. Jika secret terbuka, dilaporkan dan dianggap bocor. | AGENTS §9, §14 | Pemindaian repositori dan log uji; PAY-44. |
| NFR-06 | Operasi multi-langkah yang harus sukses/gagal bersama (checkout, `paid`, kedaluwarsa, pembatalan, eksekusi simulator, reset) atomik; penghitung, stok, dan deduplikasi bebas balapan. Pekerjaan latar tahan ulang (idempoten) dan aman terhadap pekerja ganda. | AGENTS §8 | Uji konkurensi; uji ulang job. |
| NFR-07 | Galat ditangani eksplisit pada lapisan yang bisa bertindak; pesan ke pengguna jelas dan tidak membocorkan internal; detail teknis di log dengan ID korelasi; tidak ada pelaporan sukses untuk hal yang tidak terjadi; dependensi opsional gagal tidak mematahkan alur inti (analitik gagal ≠ toko gagal). | AGENTS §10 | Uji kegagalan servis Python, basis data, dan penjadwal. |
| NFR-08 | Semua daftar dan kueri berat dibatasi (paginasi), tanpa pola N+1, dengan indeks untuk pola kueri baru (periode, `origin`, produk, status). Pekerjaan berat di luar jalur request bila melebihi anggaran waktu. | AGENTS §12 | Uji volume dengan dataset contoh dan 500 transaksi/request. |
| NFR-09 | Perubahan skema lewat migrasi terurut dan dapat ditinjau; skema, kode akses data, validator, dan tipe tetap konsisten; migrasi diterapkan dan diverifikasi sebelum kode yang bergantung padanya. | AGENTS §8 | Uji migrasi naik dari kosong. |
| NFR-10 | Mengikuti stack dan pola yang ada; tidak menambah mekanisme kedua untuk hal yang sudah ada (routing, state, formulir, gaya, konfigurasi, log); dependensi baru hanya bila perlu dan dijelaskan; tidak mengedit berkas hasil generate. | AGENTS §4, §5, §6, §14 | Tinjauan diff; aturan arsitektur otomatis. |
| NFR-11 | Setiap perubahan perilaku disertai uji otomatis (regresi gagal tanpa perbaikan); uji tidak dilemahkan; uji deterministik (waktu dan acak dikendalikan). Hasil verifikasi dilaporkan apa adanya, termasuk yang belum diverifikasi. | AGENTS §11, §17 | Pipeline uji; laporan. |
| NFR-12 | Dokumentasi (README, daftar environment, PRD, blueprint) diperbarui dalam perubahan yang sama dan hanya menggambarkan yang ada dan terverifikasi; PRD/blueprint tidak menyatakan gateway riil terhubung. Temuan sementara dicatat di dokumen status, bukan di aturan. | AGENTS §13 | Tinjauan dokumen vs kode. |
| NFR-13 | Antarmuka mengikuti `DESIGN.md`: register **Product**; token sebagai satu-satunya sumber nilai visual; satu keluarga ikon, satu resep radius/bayangan; kualitas visual dianggap bagian persyaratan. Catatan Desain ada pada blueprint §13. | DESIGN §1, §3, §4, §7 | Tinjauan visual tiga lebar. |
| NFR-14 | Responsif satu sistem tata letak: seluruh layar diperiksa pada ≈ 390, 768, 1280 px; tabel menjadi kartu di ponsel; target sentuh ≥ 44×44 px. Halaman simulasi pembayaran dirancang **mobile-first** karena dibuka dari HP hasil pindai QR. | DESIGN §8, AGENTS §7 | Pemeriksaan visual dan keyboard pada tiga lebar. |
| NFR-15 | Aksesibilitas dasar: kontras 4,5:1 (teks) dan 3:1 (batas/ikon bermakna), fokus keyboard terlihat, label terkait field, galat terkait field, warna bukan satu-satunya pembawa makna, hormati reduced-motion, tabel alternatif untuk grafik. | DESIGN §10, AGENTS §7 | Audit aksesibilitas + uji keyboard. |
| NFR-16 | Setiap alur menyediakan keadaan loading, kosong, galat, sukses, dan nonaktif; mencegah submit ganda; input dipertahankan saat galat; aksi destruktif memakai pola konfirmasi yang sama. Layar sistem 403/404/419/429/500 dan toast **ditambahkan** (bukan fitur produk) karena dibutuhkan keadaan di atas. | DESIGN §2, §9; AGENTS §7 | Daftar keadaan per layar. |
| NFR-17 | Bahasa UI Indonesia; format lokal: `Rp 1.250.000`, `12 Okt 2026`, zona WITA, nomor telepon `+62 …`; label tindakan spesifik ("Simpan perubahan", "Batalkan pesanan") dan konsisten sepanjang alur. | DESIGN §11 | Tinjauan salinan. |
| NFR-18 | Konten contoh realistis dan sesuai skema (nama produk alat tulis, harga rupiah, tanggal), tanpa lorem ipsum; tanpa klaim fiktif (logo pelanggan, testimoni). | DESIGN §11 | Tinjauan dataset. |
| NFR-19 | Waktu disimpan UTC dan ditampilkan/dibucket dalam WITA; pengujian mengendalikan jam. | AGENTS §11 (determinisme), U-20 | Uji batas hari 23:59/00:00 WITA. |
| NFR-20 | Skrip setup/jalan/uji/build portabel lintas OS yang umum dan didokumentasikan; jalur relatif pada dokumen dan skrip. | AGENTS §13, §14 | Uji pada instalasi bersih. |

## 12. Urutan Pembangunan dan Pemetaan Fitur (`D§6.5`)

| Tahap | Isi | Fitur |
| --- | --- | --- |
| **M1** | Fondasi akun, peran, hak akses | ACC-01 s.d. ACC-15 |
| **M2** | Katalog dan stok sampai web siap menerima pembeli | CAT-01 s.d. CAT-04, PRD-01 s.d. PRD-07, SHP-01 s.d. SHP-04 |
| **M3a** | Alur pembeli normal, pesanan, stok, pencatatan event/transaksi | CRT-01 s.d. CRT-06, STK-01 s.d. STK-06, ORD-01 s.d. ORD-14, EVT-01 s.d. EVT-09 |
| **M3b** | Payment simulator: inti alur dan arsitektur adapter | PAY-01 s.d. PAY-36, PAY-55 s.d. PAY-63 |
| **M3c** | Payment simulator: panel HTTP, jejak, halaman hasil, struk, riwayat, akses admin, label | PAY-37 s.d. PAY-54 |
| **M4** | Panel developer versi dasar | SIM-01 s.d. SIM-31, SIM-35 s.d. SIM-42 |
| **M5** | Analitik P0 diuji dengan data simulasi | ANL-01 s.d. ANL-08, ANL-11 s.d. ANL-15, ANL-21 s.d. ANL-24, ANL-31 s.d. ANL-35 |
| **M6** | Analitik P1 | ANL-41 s.d. ANL-46, ANL-51 s.d. ANL-56 |
| **M7** | Analitik P2 dan penyempurnaan simulator | ANL-61 s.d. ANL-65, SIM-32, SIM-33, SIM-34 |

Setiap tahap hanya dianggap selesai bila fitur-fiturnya lulus kriteria terima dan verifikasi §13. Tahap analitik tidak dimulai di atas data kosong (`D§6.5`).

## 13. Strategi Verifikasi (Fase 3 dan Fase 4)

### 13.1 Whitebox (logika dan aturan)

| Target | Fokus | Fitur |
| --- | --- | --- |
| Tabel transisi status pesanan dan pembayaran | Semua pasangan (status, aksi); hanya transisi sah yang berhasil | ORD-06, PAY-28 |
| Layanan stok | Reservasi, konfirmasi, pelepasan, idempotensi, tak-negatif, konkurensi | STK-01..06 |
| Penanganan callback | Signature, validasi payload, idempotensi, konflik status, atomisitas | PAY-24..27, PAY-29 |
| Pembuat token/VA/referensi | Keunikan, entropi, format, tidak dapat ditebak | PAY-05, PAY-08, PAY-10 |
| Kedaluwarsa | Job, on-access, batas waktu tepat (`=`, `±1 detik`), pesanan tanpa pembayaran aktif | PAY-34, PAY-35, ORD-05 |
| Pencatatan event | Aturan dedup `view`, `purchase` hanya saat `paid` | EVT-02..06 |
| PRNG berbenih dan distribusi | Determinisme seed, sebaran waktu/user/produk, invarian kronologis | SIM-05, SIM-20..24, SIM-29 |
| Generator simulasi | Rasio status berjumlah 100%, funnel menyusut, pembayaran konsisten, atomik | SIM-12, SIM-23, SIM-27, SIM-30 |
| Perlindungan data normal | Reset hanya menyentuh `developer_simulation` | SIM-38..41 |
| Algoritma Python | Uji golden-data terhadap kode "apa adanya" | ANL-22, ANL-31..35, ANL-43, ANL-53, ANL-61 |
| Penyusun data fakta | Bucket WITA, definisi penjualan, filter asal data | ANL-01, ANL-02, ANL-07 |

### 13.2 Blackbox (alur dari sisi pengguna), mengikuti `D§11`

| Alur | Skenario inti |
| --- | --- |
| A — Admin menyiapkan toko | Login admin → buat kategori → buat produk + stok → aktifkan → produk muncul di katalog pembeli. |
| B — Pembeli berbelanja | Daftar → cari → buka detail (event view) → tambah ke keranjang → checkout → pilih salah satu dari enam metode → pindai QR atau buka tombol alternatif → Bayar (simulasi) → halaman berhasil → riwayat pesanan. Variasi: Gagalkan → bayar ulang; biarkan kedaluwarsa; batalkan; token dipakai ulang; ganti nominal di URL. |
| C — Admin membaca analitik | Login admin → dashboard → filter periode → tren → funnel → perbandingan → aktivitas/minat → kategori; setiap angka ditelusuri ke sumber. |
| D — Developer mengisi data | Login developer → buat 100 akun (dan coba 101) → transaksi user-first backdate → transaksi period-first (pratinjau → konfirmasi) → baca analitik sebagai Admin → audit → reset → ulangi dengan pola berbeda. |
| E — Keamanan peran | Pembeli membuka `/admin/*` dan `/developer/*`; Admin membuka `/developer/*`; Developer membuka `/admin/*`; pembeli membuka pesanan/pembayaran pembeli lain. |

### 13.3 Hal yang tidak dibuktikan oleh pengujian tahap ini

Pengujian memakai simulator dan data eksperimental; tidak membuktikan perilaku terhadap gateway riil, infrastruktur produksi, atau pemindaian QR oleh aplikasi bank/e-wallet (QR memang bukan payload resmi). Pemindaian dengan kamera/Google Lens diuji manual pada perangkat nyata dan hasilnya dilaporkan apa adanya.

## 14. Matriks Penelusuran Balik (sumber → fitur)

Setiap bagian sumber yang relevan dipetakan ke fitur; tidak ada fitur tanpa sumber (diperiksa pada §15).

### 14.1 `Ira-discussion.md`

| Sumber | Fitur |
| --- | --- |
| D§1 butir 1 (sistem dibangun) | ORD-02, EVT-08, ANL-11 |
| D§1 butir 2 (pendekatan simulator) | SIM-01, SIM-02, SIM-13, ANL-02 |
| D§1 butir 3 (pemisahan wajib) | ACC-04, ACC-10, EVT-09, SIM-01, SIM-40 |
| D§1 butir 4 (tiga aktor) | ACC-08, §4 |
| D§1 butir 5 (FR-01..06 dan pendukung) | seluruh ANL; seluruh ACC..EVT |
| D§1 butir 6 (maks. 100 akun) | SIM-02, SIM-03 |
| D§1 butir 7 (backdate) | SIM-10, SIM-13, SIM-16, SIM-28, SIM-29 |
| D§1 butir 8 (biodata) | SIM-04, SIM-05, SIM-06 |
| D§1 butir 9 (period-first) | SIM-16 s.d. SIM-24 |
| D§2 butir 1–2 (simulasi alat; data dikenali) | EVT-09, SIM-07, SIM-36, SIM-37, ANL-02 |
| D§2 butir 3 (tidak menggantikan alur normal) | SIM-40 |
| D§2 butir 4 (tanpa pembayaran riil) | PAY-55..PAY-61, §6 K-02 |
| D§2 butir 5 (tertelusur) | ANL-05, ANL-24, ANL-55, EVT-08 |
| D§3 (pemisahan Asli vs Simulator) | ACC-03, ACC-04, ACC-10, SIM-01, SIM-40, SIM-41 |
| D§4.1 (Admin) | ACC-03, ACC-11..13, CAT-01..03, PRD-01..06, ORD-10..13, PAY-51..53, ANL-08 |
| D§4.2 (Pembeli) | ACC-01, ACC-02, SHP-01..04, CRT-01, EVT-02, ORD-07..09 |
| D§4.3 (Developer) | ACC-04, ACC-14, SIM-01, SIM-02, SIM-31, SIM-35, SIM-37..39 |
| D§5 FR-01 | ANL-11..ANL-15 |
| D§5 FR-02 | ANL-21..ANL-24 |
| D§5 FR-03 | ANL-31..ANL-35 |
| D§5 FR-04 | ANL-41..ANL-46 |
| D§5 FR-05 | ANL-51..ANL-56 |
| D§5 FR-06 | ANL-61..ANL-65 |
| D§5 komponen lintas | ANL-01, ANL-02, ANL-03, ANL-04 |
| D§6.1 (akun dan hak akses) | ACC-01..ACC-15 |
| D§6.2 (katalog) | CAT-01..04, PRD-01..07, SHP-01..04, STK-01 |
| D§6.3 (alur pembeli normal) | CRT-01..06, STK-02..06, ORD-01..14, EVT-02..06 |
| D§6.4 (event dan transaksi) | EVT-01..09, ORD-03, ORD-14, PRD-07, STK-05 |
| D§6.5 (urutan pembangunan) | §12 |
| D§7.1 (akun massal) | SIM-02..08, ACC-14, ACC-15 |
| D§7.2 (user-first) | SIM-09..15 |
| D§7.3 (period-first) | SIM-16..24, SIM-25, SIM-26, SIM-27 |
| D§7.4 (aksi turunan funnel) | SIM-12, SIM-24, SIM-31..33 |
| D§7.5 (batasan dan keamanan) | ACC-10, SIM-03, SIM-17, SIM-36, SIM-38..41 |
| D§8 (data minimal per FR) | SIM-22, SIM-32..34, ANL-12, ANL-13, ANL-21, ANL-31, ANL-34, ANL-41, ANL-53, ANL-61, ANL-65 |
| D§9 (bukan scope) | §3.2, PRD-06, PAY-61 |
| D§10 butir 1 (stack) | U-01, blueprint §2 |
| D§10 butir 2–3 (biodata) | SIM-05, SIM-06, U-02, U-02b |
| D§10 butir 4, 9 (batas/parameter) | SIM-17, SIM-20..SIM-28, U-03, U-08 |
| D§10 butir 5 (aturan beli/batal) | ORD-02, ORD-05..07, STK-02..04, U-04 |
| D§10 butir 6 (hak akses) | §8, ACC-08 |
| D§10 butir 7 (dataset) | SIM-42, U-06 |
| D§10 butir 8 (data simulasi vs normal) | ANL-02, U-07 |
| D§11 Alur A | CAT-01, CAT-04, PRD-01, PRD-04, PRD-05 |
| D§11 Alur B | ACC-01, ACC-02, SHP-02, CRT-01, ORD-02, ORD-07, ORD-08 |
| D§11 Alur C | ANL-01 s.d. ANL-08, ANL-11 s.d. ANL-15, ANL-21 s.d. ANL-24, ANL-31 s.d. ANL-35, ANL-41 s.d. ANL-46, ANL-51 s.d. ANL-56, ANL-61 s.d. ANL-65, INS-01 s.d. INS-06 |
| D§11 Alur D | SIM-02, SIM-13, SIM-16, SIM-36..39 |
| D§12 (arahan penyusun PRD) | §2.2, struktur fitur pada §10, §13 |
| D§12b (Laravel dan Python) | ANL-06, U-01, U-19 |

### 14.2 `Ira-payment-gateway-simulator.md`

| Sumber | Fitur |
| --- | --- |
| P§1 butir 1–2 (pengalaman dan demo) | PAY-11..PAY-16, PAY-37..PAY-42 |
| P§1 butir 3 (purchase hanya `paid`) | EVT-05, ANL-07, PAY-29 |
| P§1 butir 4 (adapter) | PAY-55..PAY-59 |
| P§2 (enam metode, QR, token, label) | PAY-01, PAY-02, PAY-05..PAY-10, PAY-14, PAY-54 |
| P§3.1 (keranjang → instruksi) | ORD-01, ORD-02, PAY-03, PAY-04, PAY-11, PAY-12, PAY-14 |
| P§3.2 (QR → selesai) | PAY-13, PAY-15, PAY-16, PAY-29, PAY-45, PAY-48 |
| P§3.3 (gagal/kedaluwarsa/batal/token final) | PAY-17, PAY-20, PAY-21, PAY-30..PAY-35, ORD-07, STK-04, PAY-46, PAY-47 |
| P§4.1 (halaman simulasi pembeli) | PAY-15..PAY-18 |
| P§4.2 (panel HTTP) | PAY-24, PAY-25, PAY-26, PAY-36..PAY-42 |
| P§4.3 (akses halaman) | PAY-19, PAY-22, PAY-05 |
| P§5.1 (state machine pembayaran) | PAY-28 |
| P§5.2 (pemetaan ke pesanan) | ORD-06, PAY-29..PAY-33, §9.2 |
| P§5.3 (stok dan idempotency) | STK-01..STK-06, PAY-27 |
| P§5.4 (hubungan dengan panel developer) | SIM-30, EVT-09, §4.2 |
| P§6 (data) | PAY-43, PAY-62, PAY-63 |
| P§7 (arsitektur gateway riil) | PAY-01, PAY-24, PAY-55..PAY-61 |
| P§8 (peran dan akses) | PAY-49..PAY-53, §8 |
| P§9 butir 1–13 (pecahan fitur) | butir 1 PAY-02; 2 PAY-03/04; 3 PAY-06/07/10; 4 PAY-08/09; 5 PAY-11..14; 6 PAY-15..23; 7 PAY-34/35; 8 PAY-24..27/29..33; 9 PAY-37..44; 10 PAY-45..47; 11 PAY-49..52; 12 PAY-55..60; 13 PAY-54, PAY-48 |
| P§10 (bukan scope) | §3.2, PAY-44, PAY-60, PAY-61 |
| P§11 butir 1–7 (terbuka) | U-09, U-10, U-11, U-12, U-13, U-14, U-15 |

## 15. Pemeriksaan Dokumen, Status, dan Langkah Berikutnya

### 15.1 Jumlah fitur per modul

| Modul | Jumlah | Modul | Jumlah |
| --- | --- | --- | --- |
| ACC (akun dan akses) | 15 | PAY (payment simulator) | 63 |
| CAT (kategori) | 4 | SIM (panel developer) | 42 |
| PRD (produk dan stok admin) | 7 | ANL (analitik FR-01..06 dan lintas) | 38 |
| SHP (katalog pembeli) | 4 | CRT (keranjang) | 6 |
| STK (aturan stok) | 6 | ORD (pesanan) | 14 |
| EVT (event dan transaksi) | 9 | **Total** | **208** |

### 15.2 Pemeriksaan yang sudah dijalankan pada dokumen ini

Pemeriksaan dilakukan dengan skrip terhadap berkas `PRD.md` ini (bukan terhadap kode, karena kode belum ada):

- Semua 208 baris fitur memiliki enam kolom, ID unik, kolom Sumber berisi rujukan `D§…`/`P§…`/`FR-0n`, dan kolom Uji berisi `BB`/`WB`.
- Setiap fitur muncul pada matriks penelusuran (§14) dan pada pemetaan tahap (§12).
- Semua rujukan `U-nn` dan `K-nn` pada isi dokumen memiliki definisi pada §6 atau §7.
- Semua ID fitur yang dirujuk pada kriteria terima ada.

Yang **belum** diverifikasi: kecocokan semantik tiap kriteria terima terhadap niat pemilik proyek (butuh tinjauan manusia), dan kelayakan angka usulan (mis. 500 transaksi/request, target ≤ 3 detik) yang baru dapat diukur setelah implementasi.

### 15.3 Yang perlu dikoreksi atau diputuskan pemilik proyek

Prioritas tinjauan (paling memengaruhi rancangan bila diubah):

1. **K-01, K-03, K-05** — kapan `purchase` dicatat, batas wewenang Admin atas status pesanan, dan cara data developer membuat pembayaran backdate.
2. **U-04, U-12** — aturan beli/batal, status pesanan, dan perilaku stok setelah pembayaran gagal.
3. **U-09** — batas bayar per pesanan (bukan per pembayaran).
4. **U-16 / K-11** — katalog hanya untuk pembeli login.
5. **U-19** — pembagian kalkulasi Laravel dan Python; **kode Python yang dimaksud pada `D§12b` belum dilampirkan**, sehingga kontrak pada blueprint dan ambang U-25/U-26 harus disesuaikan setelah kode diterima.
6. **U-02b** — script generator biodata menyusul dari pemilik proyek.
7. **U-03, U-08, U-21** — batas simulator, pratinjau/rollback, dan pengecualian simulasi.
8. **K-07 / U-22** — penyediaan akun Admin dan Developer tanpa registrasi publik.

Perubahan pada butir-butir di atas harus diikuti pembaruan fitur terkait dan `blueprint.md`.
