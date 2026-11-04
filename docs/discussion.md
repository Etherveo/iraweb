# Discussion — Ira Stationery: Sistem E-commerce dengan Pendekatan Simulator

> **Status:** Keputusan final hasil diskusi, 1 November 2026.
> **Tujuan dokumen ini:** Menjadi satu-satunya acuan awal bagi penyusunan PRD.md dan blueprint.md.
> **Sumber kebutuhan:** Laporan Fase 1 Analisis Kebutuhan (Implementasi dan Pengujian Perangkat Lunak) untuk Ira Stationery, yang didasarkan pada kuesioner 3 responden (2 Bagian IT, 1 Manajer/Supervisor).
> **Catatan penting:** Dokumen ini tidak membahas pendekatan pengembangan lain. Yang berlaku hanyalah apa yang tertulis di sini.

## 1. Ringkasan Keputusan Final

| No | Keputusan | Isi |
| --- | --- | --- |
| 1 | Sistem yang dibangun | Sistem e-commerce Ira Stationery yang terintegrasi dengan fitur Trend Analytics. |
| 2 | Pendekatan data | Simulator. Data yang dipakai untuk menguji dan menjalankan fitur analisis adalah data eksperimental yang dibuat lewat panel developer, bukan data riil toko dan bukan transaksi pembayaran riil. |
| 3 | Pemisahan yang wajib dijaga | Ada dua sisi yang jelas terpisah: (a) Sistem Asli dan (b) Sistem Simulator (panel developer). Keduanya memakai basis data yang sama agar analitik bisa membaca hasil simulasi, tetapi peran, alur, dan jejaknya harus bisa dibedakan. |
| 4 | Aktor | Tiga aktor: Admin Toko, Pembeli, dan Developer (simulator). Tidak ada aktor lain pada tahap ini. |
| 5 | Fitur tujuan akhir | Fitur analisis FR-01 sampai FR-06 (lihat Bagian 5). Fitur-fitur ini tidak bisa berdiri sendiri; fitur pendukung pada Bagian 6 harus dibangun terlebih dahulu. |
| 6 | Batas simulasi massal | Satu request pembuatan akun massal maksimal 100 akun. |
| 7 | Periode simulasi | Simulator boleh membuat transaksi dan aktivitas pada periode yang sudah lewat (backdate), karena riwayat lampau tidak mungkin dibuat dengan pemakaian normal hari ini. |
| 8 | Biodata simulasi | Biodata akun simulasi dibuat secara random atau deterministik. Script generator biodata akan diberikan terpisah oleh pemilik proyek; dokumen ini tidak menetapkan isi script tersebut. |
| 9 | Aksi simulator period-first | Selain aksi user-first (pilih user, lalu buatkan transaksi pada suatu periode), ada aksi period-first: login Developer → pilih periode/rentang periode → perbanyak transaksi di periode tersebut, oleh satu user atau banyak user. Lihat Bagian 7.3. |

## 2. Prinsip Simulasi

1. **Simulasi adalah alat, bukan klaim data riil.** Semua akun, aktivitas, dan transaksi yang dibuat panel developer adalah data eksperimental untuk keperluan tugas dan pengujian.
2. **Data simulasi harus bisa dikenali.** Setiap data yang berasal dari simulator wajib bisa dibedakan dari data pemakaian normal (misalnya lewat penanda sumber/asalan data), supaya bisa difilter, diaudit, dan dihapus/reset tanpa merusak data pemakaian normal.
3. **Simulasi tidak menggantikan alur normal.** Sistem Asli harus tetap bisa dipakai secara normal dari awal sampai akhir tanpa panel developer. Panel developer hanya mempercepat dan memperbanyak data, bukan menjadi satu-satunya cara mengisi sistem.
4. **Tidak ada pembayaran riil.** Pembelian pada Sistem Asli dan pada simulator adalah proses pesanan internal (beli/batal) untuk keperluan simulasi dan analitik, bukan integrasi payment gateway riil.
5. **Hasil analisis harus bisa ditelusur.** Angka pada dashboard, tren, funnel, dan perbandingan harus bisa ditelusur kembali ke transaksi dan event aktivitas yang menjadi sumbernya.

## 3. Pemisahan: Sistem Asli vs Sistem Simulator

| Aspek | Sistem Asli | Sistem Simulator (Panel Developer) |
| --- | --- | --- |
| Pengguna | Admin Toko dan Pembeli | Developer |
| Tujuan | Menjalankan toko secara normal: kelola produk, beli, dan lihat analitik | Menghasilkan data eksperimental dalam jumlah besar dan pada periode yang diinginkan |
| Cara data masuk | Satu per satu lewat alur normal (daftar, cari, keranjang, beli/batal) | Massal dan backdate lewat aksi simulator (lihat Bagian 7) |
| Kecepatan | Real-time sesuai pemakaian | Sekali request bisa menghasilkan banyak akun, aktivitas, dan transaksi |
| Jejak data | Data pemakaian normal | Data bertanda simulator, bisa diaudit dan di-reset |
| Akses | Login Admin Toko dan login/register Pembeli | Login Developer yang terpisah; tidak boleh dicampur dengan akun Admin Toko atau Pembeli |

Pemisahan ini penting karena dua alasan praktis:

- Membuat akun satu per satu dan mengulang aktivitas yang sama berkali-kali tidak efisien.
- Riwayat transaksi untuk periode yang sudah lewat tidak mungkin dibuat lewat pemakaian normal pada hari ini, padahal fitur tren dan perbandingan periode justru membutuhkan riwayat tersebut.

## 4. Aktor

### 4.1 Admin Toko (Admin Web)

Mengelola toko dan membaca hasil analisis.

- Login sebagai admin.
- Mengelola katalog produk: tambah, ubah, nonaktifkan produk, atur kategori dan stok.
- Mengelola pesanan yang masuk: melihat pesanan, mengubah status pesanan, dan melihat pembatalan.
- Melihat seluruh fitur Trend Analytics (FR-01 sampai FR-06).
- Tidak membuat data simulasi massal. Itu tugas Developer.

### 4.2 Pembeli

Pengguna normal yang berbelanja lewat web.

- Register dan login sebagai pembeli.
- Mencari dan melihat produk (aktivitas ini tercatat sebagai event view).
- Memasukkan produk ke keranjang (event add-to-cart).
- Melakukan pembelian atau pembatalan (event booking/purchase/cancel dan transaksi).
- Melihat riwayat pesanannya sendiri.
- Tidak bisa mengakses panel developer dan tidak bisa melihat analitik toko.

### 4.3 Developer (Simulator)

Satu-satunya aktor yang boleh menginjeksi data eksperimental secara massal.

- Login sebagai developer lewat panel yang terpisah.
- Membuat banyak akun pembeli simulasi dalam satu request (maksimal 100 akun per request).
- Memilih akun simulasi yang sudah ada, lalu membuatkan transaksi dan aktivitas untuk akun tersebut pada suatu periode, termasuk periode lampau.
- Menghasilkan event funnel secara lengkap (view, add-to-cart, booking, purchase) supaya funnel bisa dianalisis, bukan hanya transaksi akhirnya saja.
- Melihat jejak simulasi yang ia buat dan me-reset/menghapus data simulasi bila diperlukan.
- Tidak berperan sebagai admin toko dan tidak berperan sebagai pembeli normal dalam alur yang sama.

## 5. Daftar Fitur Baru (Fitur Tujuan Akhir)

Ini adalah fitur analisis dari laporan Fase 1. Urutan prioritasnya mengikat: P0 dibangun paling awal, lalu P1, lalu P2.

### P0 — Wajib

- **FR-01 Dashboard Analitik Penjualan.** Ringkasan penjualan, jumlah transaksi, produk terlaris, produk paling sedikit terjual, dan tren penjualan, dalam bentuk ringkasan statistik dan grafik, dilengkapi filter periode.
- **FR-02 Analisis Tren.** Perubahan penjualan/permintaan per produk dari waktu ke waktu, serta identifikasi produk yang trennya naik atau turun. Dasar terkuat: kebutuhan ini dinilai 5,00/5 oleh seluruh responden dan analisis tren dipilih 3/3 responden.
- **FR-03 Analisis Funnel Pelanggan.** Perbandingan tahapan dilihat → keranjang → booking → pembelian, dalam statistik dan grafik. Prasyarat datanya: event product view, add-to-cart, booking, dan purchase harus tercatat.

### P1 — Penting

- **FR-04 Perbandingan Periode.** Membandingkan performa penjualan berdasarkan hari, minggu, bulan, atau periode kustom yang dipilih. Dinilai 4,67 dan dipilih 2/3 responden.
- **FR-05 Analisis Aktivitas dan Minat Produk.** Jumlah produk yang dilihat, riwayat aktivitas pelanggan, serta sorotan produk yang sering dilihat atau dimasukkan ke keranjang tetapi pembeliannya rendah. Memakai data event yang sama dengan FR-03.

### P2 — Pengembangan Lanjutan

- **FR-06 Analisis Kategori/Karakteristik Produk.** Hubungan performa penjualan dengan kategori, periode, atau karakteristik produk. Dukungan paling lemah (nilai 4,00; tren per kategori hanya dipilih 1/3 responden), sehingga dibangun terakhir.

### Komponen lintas fitur (bukan fitur tersendiri)

- Filter periode, dipakai bersama oleh FR-01 sampai FR-06.
- Visualisasi grafik, dipakai bersama oleh FR-01 sampai FR-06.
- Pembaruan cepat: metrik analitik diperbarui segera setelah transaksi atau event baru tercatat, tanpa pengolahan manual tambahan.

## 6. Fitur Pendukung yang Harus Dibangun Terlebih Dahulu

Fitur analisis pada Bagian 5 tidak bisa dipakai kalau data sumbernya belum ada. Karena itu, sebelum atau bersamaan dengan analitik, fitur-fitur pendukung berikut harus dibangun, dalam urutan ketergantungan ini:

### 6.1 Fondasi: akun dan hak akses

- **Register dan login** untuk Pembeli, Admin Toko, dan Developer (login developer terpisah dari login admin/pembeli).
- **Role-based access control (RBAC).** Setiap peran hanya bisa mengakses fitur dan datanya sendiri. Pemetaan akses spesifik per fitur ditetapkan pada tahap perancangan (PRD/blueprint), dengan acuan awal: Pemilik/manajer dan admin mengakses analitik, pembeli hanya mengakses belanjanya sendiri, developer hanya mengakses panel simulator.
- **Manajemen akun dasar** oleh Admin Toko (melihat dan mengelola akun pembeli) dan oleh Developer (melihat akun simulasi yang ia buat).

Alasan: tanpa ini, tidak ada pembeli yang bisa beraktivitas, tidak ada admin yang bisa mengelola toko, dan tidak ada pemisahan yang aman antara pemakaian normal dan simulasi.

### 6.2 Katalog siap menerima pembeli

- **Manajemen produk oleh Admin Toko:** tambah, ubah, aktif/nonaktifkan produk, harga, dan stok.
- **Manajemen kategori produk.**
- **Halaman katalog untuk Pembeli:** daftar produk, detail produk, dan pencarian produk.

Alasan: alur admin harus rampung sampai web siap menerima pembeli sebelum ada transaksi apa pun. Tanpa katalog dan stok, tidak ada produk yang bisa dilihat, dimasukkan keranjang, atau dibeli.

### 6.3 Alur pembeli normal (penghasil data utama)

- **Melihat produk** (mencatat event view).
- **Keranjang belanja:** tambah, ubah jumlah, dan hapus produk (mencatat event add-to-cart).
- **Pembelian:** checkout internal dari keranjang menjadi pesanan/transaksi (mencatat event booking dan purchase, serta mengurangi stok sesuai aturan yang ditetapkan pada PRD).
- **Pembatalan pesanan** oleh pembeli sesuai aturan yang ditetapkan pada PRD (mencatat pembatalan, bukan menghapus transaksi begitu saja).
- **Riwayat pesanan pembeli.**
- **Manajemen pesanan oleh Admin Toko:** melihat daftar pesanan, detail pesanan, dan mengubah status pesanan.

Alasan: inilah Input-Process-Output inti Sistem Asli. Transaksi dan event dari alur inilah yang kemudian dibaca oleh FR-01 sampai FR-06. Tanpa alur ini, analitik hanya menampilkan halaman kosong.

### 6.4 Pencatatan event dan transaksi sebagai sumber analitik

- **Pencatatan event aktivitas** dengan minimal: jenis event (view, add-to-cart, booking, purchase), produk, akun/sesi terkait, dan waktu kejadian.
- **Pencatatan transaksi** dengan minimal: pesanan, produk dan jumlah, nilai transaksi, status pesanan, dan waktu transaksi.
- **Konsistensi data:** angka pada dashboard, laporan, inventori, dan transaksi harus berasal dari sumber data yang sama, bukan dari salinan yang bisa berbeda.

Alasan: FR-03 dan FR-05 sepenuhnya bergantung pada event, sedangkan FR-01, FR-02, FR-04, dan FR-06 bergantung pada transaksi. Kalau pencatatan ini tidak dirancang sejak awal, analitik harus ditempel belakangan dan hasilnya tidak bisa ditelusur.

### 6.5 Urutan pembangunan yang disarankan

1. Fondasi akun, peran, dan hak akses (6.1).
2. Katalog dan stok sampai web siap menerima pembeli (6.2).
3. Alur pembeli normal dan manajemen pesanan admin, beserta pencatatan event dan transaksi (6.3 dan 6.4).
4. Panel developer simulator versi dasar: pembuatan akun massal dan pembuatan transaksi/aktivitas (Bagian 7), supaya data untuk menguji analitik tersedia.
5. Fitur analisis P0 (FR-01, FR-02, FR-03), diuji dengan data simulasi.
6. Fitur analisis P1 (FR-04, FR-05).
7. Fitur analisis P2 (FR-06), lalu penyempurnaan simulator (misalnya variasi pola funnel dan periode).

Urutan ini memastikan setiap fitur analisis, saat dibangun, langsung punya data untuk diuji, bukan dibangun di atas data kosong.

## 7. Panel Developer: Spesifikasi Simulator

Panel developer adalah bagian dari Sistem Simulator. Spesifikasi berikut mengikat untuk PRD dan blueprint.

### 7.1 Aksi 1 — Create banyak akun

- Developer login ke panel developer, memilih aksi create user, dan menginput jumlah akun yang ingin dibuat.
- **Maksimal 100 akun per request.** Jumlah di atas itu harus dipecah menjadi beberapa request.
- Sistem membuat akun pembeli simulasi sesuai jumlah yang diinput.
- Biodata akun dipilih secara random atau deterministik:
  - Random: biodata digenerate (script generator akan diberikan terpisah oleh pemilik proyek).
  - Deterministik: biodata dihasilkan dari seed yang sama sehingga hasilnya bisa diulang persis untuk pengujian.
  - Pilihan antara random dan deterministik, serta format biodata, ditetapkan pada PRD/blueprint setelah script generator diterima; dokumen ini tidak mengarang isi script tersebut.
- Akun hasil simulasi wajib bertanda simulator dan terdaftar sebagai akun pembeli biasa dari sisi Sistem Asli (bisa login, bisa beraktivitas), supaya aktivitasnya terbaca wajar oleh analitik.

### 7.2 Aksi 2 — Buatkan transaksi pada suatu periode untuk user yang dipilih

- Developer memilih satu atau beberapa akun simulasi yang sudah ada.
- Developer memilih periode transaksi (tanggal mulai dan tanggal akhir), termasuk periode yang sudah lewat dari hari ini.
- Developer menentukan, minimal: produk yang dibeli, jumlah, dan pola aktivitas yang menyertainya (lihat 7.3).
- Sistem membuat transaksi dan event dengan stempel waktu di dalam periode yang dipilih (backdate), bukan dengan waktu hari ini.
- Transaksi yang dibuat lewat aksi ini tetap mengikuti aturan dasar Sistem Asli (produk harus ada, stok dan status pesanan harus konsisten) kecuali aturan yang secara eksplisit dinyatakan sebagai pengecualian simulasi pada PRD.

### 7.3 Aksi 3 — Perbanyak transaksi pada periode yang dipilih (period-first)

Ini berbeda dengan Aksi 2. Pada Aksi 2, titik mulanya adalah user: pilih user dulu, lalu buatkan transaksi pada suatu periode. Pada aksi ini, titik mulanya adalah periode.

Alurnya:

1. Developer login ke panel developer.
2. Developer memilih periode atau rentang periode (misalnya satu tanggal, satu minggu, satu bulan, atau rentang kustom mulai–akhir), termasuk periode yang sudah lewat dari hari ini.
3. Developer menentukan jumlah transaksi yang ingin diperbanyak pada periode tersebut.
4. Developer menentukan cakupan user-nya, salah satu dari:
   - satu user simulasi tertentu;
   - beberapa user simulasi yang dipilih;
   - semua akun simulasi yang ada;
   - user simulasi yang dipilih secara random oleh sistem.
5. Sistem menghasilkan transaksi-transaksi tersebut dengan stempel waktu yang tersebar di dalam periode yang dipilih (bukan semuanya menumpuk pada satu waktu yang sama, kecuali itu yang secara eksplisit diminta).
6. Setiap transaksi yang dihasilkan tetap disertai event funnel yang relevan (view, add-to-cart, booking, purchase) dan variasi status yang wajar (misalnya selesai dan batal), supaya hasilnya bisa dibaca oleh FR-01 sampai FR-05, terutama FR-04 perbandingan periode.

Yang sengaja belum ditetapkan pada dokumen ini, dan harus ditetapkan pada PRD/blueprint:

- Batas jumlah transaksi per request untuk aksi ini (angka 100 yang sudah ditetapkan hanya berlaku untuk pembuatan akun, bukan otomatis berlaku di sini).
- Cara distribusi transaksi antar-user dan antar-hari di dalam periode (merata, random, atau berbobot) dan nilai default-nya.
- Apakah Developer harus melihat pratinjau/konfirmasi (jumlah, periode, user, produk) sebelum eksekusi, dan bagaimana pembatalan/rollback bila eksekusi sudah berjalan sebagian.

Aksi ini adalah jawaban langsung untuk kebutuhan FR-04: tanpa kemampuan memperbanyak transaksi pada periode tertentu, akan sulit menghasilkan dua periode yang layak dibandingkan.

### 7.4 Aksi turunan yang dibutuhkan agar funnel bisa dianalisis

Satu transaksi saja tidak cukup untuk FR-03 dan FR-05. Simulator juga harus bisa menghasilkan, untuk akun dan produk yang dipilih:

- Event view (produk dilihat).
- Event add-to-cart (produk masuk keranjang tetapi belum tentu dibeli).
- Event booking (produk dipesan).
- Event purchase (pembelian selesai).
- Pembatalan (pesanan dibatalkan), sebagai variasi status yang wajar.

Dengan begitu, simulator bisa membentuk pola funnel yang lengkap (banyak dilihat, sebagian masuk keranjang, sebagian dibooking, sebagian dibeli) maupun pola minat tanpa pembelian (sering dilihat/dikeranjangkan, jarang dibeli) yang menjadi sorotan FR-05.

### 7.5 Batasan dan keamanan simulator

- Panel developer hanya bisa diakses oleh peran Developer, bukan oleh Admin Toko atau Pembeli.
- Batas 100 akun per request berlaku untuk pembuatan akun massal. Batas serupa untuk pembuatan transaksi/aktivitas massal ditetapkan pada PRD/blueprint (dokumen ini tidak menetapkan angka yang belum diputuskan).
- Setiap aksi simulator tercatat (siapa, aksi apa, berapa banyak, untuk periode apa) supaya bisa diaudit.
- Data simulasi bisa di-reset/dihapus secara terpisah, tanpa menghapus data pemakaian normal.
- Simulator tidak boleh mengubah peran, hak akses, atau data akun admin/pembeli normal di luar akun simulasi yang ia buat.

## 8. Data Minimal yang Harus Bisa Dihasilkan Simulator

Supaya tiap fitur analisis bisa diuji, simulator harus bisa menghasilkan data minimal berikut:

| Fitur | Data minimal yang harus tersedia |
| --- | --- |
| FR-01 Dashboard | Transaksi dari beberapa produk dalam satu periode, termasuk produk terlaris dan produk paling sedikit terjual. |
| FR-02 Tren | Transaksi per produk yang tersebar pada beberapa titik waktu, sehingga ada produk yang naik dan ada yang turun. |
| FR-03 Funnel | Event view, add-to-cart, booking, dan purchase untuk produk yang sama, dengan jumlah yang mengecil di tiap tahap. |
| FR-04 Perbandingan periode | Transaksi pada minimal dua periode yang bisa dibandingkan (misalnya bulan ini vs bulan lalu, atau dua rentang kustom). |
| FR-05 Aktivitas dan minat | Produk yang sering dilihat/dimasukkan keranjang tetapi jarang dibeli, berdampingan dengan produk yang pembeliannya tinggi. |
| FR-06 Kategori | Transaksi dari lebih dari satu kategori produk, supaya performa antar-kategori bisa dibandingkan. |

## 9. Yang Eksplisit Bukan Scope pada Tahap Ini

Berdasarkan laporan Fase 1, hal-hal berikut tidak dibangun sebagai bagian dari sistem ini, kecuali ada persetujuan perubahan scope dari dosen:

- Integrasi payment gateway riil (QRIS, e-wallet, transfer/virtual account, kartu) dan otomatisasi status pembayaran riil.
- Integrasi data riil toko Ira Stationery.
- Indikator/rekomendasi kebutuhan stok yang cerdas (bukan sekadar menampilkan stok).
- Analisis produk yang sering dibeli bersamaan.

Menyebutkan daftar ini penting supaya PRD dan blueprint tidak mengembang kembali ke fitur yang sudah dikeluarkan pada Fase 1.

## 10. Yang Masih Terbuka untuk PRD dan Blueprint

Dokumen ini sengaja tidak memutuskan hal-hal berikut; PRD dan blueprint harus memutuskannya secara eksplisit:

1. Stack teknologi dan struktur proyek (belum diputuskan dalam diskusi ini).
2. Isi dan format script generator biodata random (akan diberikan oleh pemilik proyek).
3. Pilihan default antara biodata random vs deterministik, dan seed yang dipakai bila deterministik.
4. Batas jumlah transaksi/aktivitas per request simulator (angka 100 baru ditetapkan untuk pembuatan akun).
5. Aturan detail pembelian dan pembatalan (misalnya kapan stok berkurang/kembali, status pesanan apa saja yang ada).
6. Pemetaan hak akses spesifik per fitur untuk tiap peran.
7. Jumlah produk, kategori, dan akun simulasi default yang dipakai sebagai dataset contoh.
8. Apakah data simulator tampil bersama data normal pada analitik secara default, atau bisa difilter/dipisahkan pada tampilan.
9. Parameter aksi period-first (Bagian 7.3): batas transaksi per request, distribusi antar-user dan antar-hari di dalam periode, serta perlu tidaknya pratinjau/konfirmasi sebelum eksekusi.

## 11. Alur Normal Sistem Asli (Ringkasan untuk PRD)

### Alur A — Admin menyiapkan toko

1. Login Admin Toko.
2. Buat dan kelola kategori produk.
3. Buat dan kelola katalog produk beserta stoknya.
4. Pastikan katalog aktif dan siap dilihat pembeli.
5. Setelah ada pesanan, kelola pesanan dan lihat analitik (FR-01 sampai FR-06).

### Alur B — Pembeli berbelanja

1. Register/login Pembeli.
2. Cari dan lihat produk (event view tercatat).
3. Masukkan produk ke keranjang (event add-to-cart tercatat).
4. Lakukan pembelian dari keranjang, atau batalkan pesanan (event booking/purchase/cancel dan transaksi tercatat).
5. Lihat riwayat pesanannya sendiri.

### Alur C — Admin membaca analitik

1. Login Admin Toko.
2. Buka dashboard (FR-01) dan filter periode.
3. Buka analisis tren (FR-02), funnel (FR-03), perbandingan periode (FR-04), aktivitas dan minat produk (FR-05), dan kategori (FR-06).
4. Gunakan hasil tersebut sebagai bahan keputusan, tanpa pengolahan manual tambahan.

### Alur D — Developer mengisi data eksperimental

1. Login Developer ke panel simulator.
2. Create banyak akun pembeli (maksimal 100 per request).
3. Pilih akun simulasi, pilih periode (termasuk periode lampau), dan buatkan transaksi beserta event funnel-nya. Atau, dengan arah sebaliknya: pilih periode/rentang periode dulu, lalu perbanyak transaksi di periode tersebut oleh satu atau banyak user (Bagian 7.3).
4. Kembali ke Sistem Asli sebagai Admin Toko dan baca analitik dari data tersebut.
5. Bila perlu, audit dan reset data simulasi, lalu ulangi simulasi dengan pola berbeda.

## 12. Arahan untuk Penyusun PRD (Penting)

Dokumen discussion ini adalah bahan mentah yang disengaja masih berbentuk konsep. Penyusun PRD tidak boleh menyalinnya mentah-mentah menjadi daftar fitur yang pendek.

1. **Pecah setiap konsep menjadi potongan-potongan kecil yang bisa diuji.** Satu konsep pada dokumen ini boleh — dan bila perlu harus — menjadi beberapa fitur pada PRD. Contoh: "perbanyak transaksi pada periode" bukan satu fitur tunggal; ia mengandung setidaknya pemilihan periode, penentuan jumlah, pemilihan cakupan user, distribusi waktu, pembuatan transaksi, pembuatan event funnel, pratinjau/konfirmasi, pencatatan audit, dan penanganan batas/gagal sebagian.
2. **Adaptasi 1-ke-1 hanya bila memang tidak ada persyaratan lain.** Bila suatu konsep ternyata hanya mengandung satu perilaku yang bisa diuji dan tidak punya turunan (validasi, batas, status, jejak, atau variasi), barulah ia ditulis sebagai satu fitur.
3. **Jangan mengecilkan daftar karena takut berhalusinasi.** Kehati-hatian yang benar bukan memangkas fitur, melainkan menandai asal setiap fitur: setiap fitur pada PRD harus bisa ditelusur kembali ke satu konsep pada dokumen ini atau ke satu FR pada laporan Fase 1, lengkap dengan kriteria terimanya. Fitur yang tidak bisa ditelusur seperti itu tidak boleh masuk; fitur yang tertelusur tidak boleh dihilangkan hanya karena dokumen ini menuliskannya secara ringkas.
4. **Rincikan sampai bisa diuji pada Fase 3 dan 4.** Untuk tiap fitur hasil pecahan, PRD harus menulis: siapa aktornya, input apa yang diminta, validasi dan batas apa yang berlaku, apa yang berubah pada data, dan bagaimana hasil itu diverifikasi (whitebox pada logika/aturannya, blackbox pada alurnya dari sisi pengguna).
5. **Hal yang masih terbuka pada Bagian 10 tetap ditulis sebagai keputusan yang harus diambil, bukan diasumsikan diam-diam.** Bila PRD mengambil nilai default untuk hal yang terbuka (misalnya batas transaksi per request), nilai itu harus ditulis eksplisit sebagai usulan yang bisa dikoreksi, bukan disajikan seolah-olah sudah diputuskan pada diskusi ini.

## 13. Keputusan Lanjutan (1 November 2026)

Keputusan ini diambil setelah dokumen ini ditulis, dan dicatat di sini supaya tidak hilang saat dokumen ini dipakai sebagai acuan PRD dan blueprint:

- Stack aplikasi: Laravel (PHP).
- Kalkulasi algoritma tetap memakai Python apa adanya sebagai servis terpisah (bukan refactor ke PHP/Node.js), karena memakai numpy, pandas, dan scikit-learn. Kontraknya: Laravel mengirim data, Python menghitung dan mengembalikan hasil.
- PRD dan blueprint akan dibuat oleh Claude, bukan oleh Rin; dokumen ini tetap menjadi acuan diskusinya.

Dengan keputusan ini, butir 1 pada §10 (stack teknologi) sudah terjawab; butir 2–9 di §10 masih terbuka (butir 9, parameter aksi period-first, ditambahkan belakangan bersama Aksi 3 di §7.3 dan juga belum diputuskan).
