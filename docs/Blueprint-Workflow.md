# Blueprint — Ira Stationery: Workflow Sistem

> **Status:** Draf workflow untuk Figma Make, 2 November 2026.
> **Pasangan file ini:** Kirim file ini bersama `UI.md` ke Figma Make (`PRD.md` opsional, hanya untuk ruang lingkup).
> **Bukan file ini:** Ini BUKAN blueprint backend. Dokumen ini sengaja **tidak menetapkan** bahasa pemrograman, framework, basis data, layanan kalkulasi, atau cara penyimpanan data. Semua hal teknis itu diputuskan di dokumen teknis terpisah dan tidak boleh ditebak dari dokumen ini.
> **Sumber fitur:** `PRD.md` (apa yang harus ada dan kriteria terimanya). Dokumen ini menjelaskan **urutan dan alurnya**: siapa melakukan apa, dalam urutan apa, apa yang berubah, dan layar apa yang muncul di tiap langkah.
> **Bahasa dan format:** Antarmuka berbahasa Indonesia. Mata uang Rupiah (`Rp 1.250.000`), tanggal (`12 Okt 2026`), zona waktu WITA (Asia/Makassar).

## 1. Sistem dalam Satu Pandangan

Ira Stationery adalah toko alat tulis dan perlengkapan sekolah di Gorontalo. Sistem ini adalah toko online-nya yang terintegrasi dengan analitik penjualan, dengan pendekatan simulator.

Ada dua sisi yang harus selalu bisa dibedakan pengguna:

1. **Sistem Asli** — toko yang dipakai secara normal. Admin mengelola toko, pembeli berbelanja, admin membaca analitik.
2. **Sistem Simulator** — alat untuk menghasilkan data eksperimental. Ada dua simulator yang berbeda dan tidak boleh tertukar:
   - **Panel Developer:** membuat banyak akun dan transaksi eksperimental, termasuk untuk periode yang sudah lewat.
   - **Halaman Simulasi Pembayaran:** halaman yang dibuka lewat QR pembayaran, tempat pembeli mensimulasikan bayar/gagal untuk satu pembayaran.

Semua pembayaran dan semua data eksperimental bertanda **SIMULASI**. Tidak ada uang riil yang berpindah dan tidak ada koneksi ke bank atau e-wallet yang sebenarnya.

Alur besarnya, dalam satu kalimat per tahap:

```
Admin menyiapkan katalog → Pembeli melihat, mengeranjangkan, dan membeli →
Pembayaran disimulasikan → Pesanan berubah status → Aktivitas dan transaksi
tercatat → Admin membaca analitik dari catatan itu → Developer mengisi data
eksperimental bila riwayat masih kurang → Admin membaca analitik lagi.
```

## 2. Aktor dan Pemisahan

Hanya ada tiga aktor. Tidak ada aktor lain pada tahap ini.

| Aktor | Masuk lewat | Bisa melakukan | Tidak bisa melakukan |
| --- | --- | --- | --- |
| **Admin Toko** | Login admin | Mengelola kategori dan produk beserta stok, mengelola pesanan dan pembayaran (lihat saja untuk pembayaran), mengelola akun pembeli, membaca seluruh analitik | Membuka panel developer, membuat data simulasi massal, berbelanja sebagai pembeli lewat akun admin |
| **Pembeli** | Register dan login pembeli | Melihat katalog, mencari produk, mengisi keranjang, membeli, membatalkan pesanan, melihat riwayat pesanannya sendiri, mensimulasikan pembayaran untuk pesanannya sendiri | Melihat analitik toko, membuka panel developer, melihat pesanan atau pembayaran pembeli lain |
| **Developer** | Login developer yang terpisah | Membuka panel simulator, membuat banyak akun simulasi, membuat transaksi eksperimental (user-first dan period-first), melihat jejak simulasi, me-reset data simulasi saja | Mengelola toko sebagai admin, berbelanja sebagai pembeli normal, mengubah data pemakaian normal |

Aturan pemisahan yang wajib terlihat di antarmuka:

- Panel developer selalu punya penanda permanen, misalnya bilah atas bertuliskan “SIMULATOR — data eksperimental”, warna sidebar yang berbeda dari admin, dan judul halaman yang diawali kata “Simulator”.
- Admin tidak punya penanda simulasi, kecuali badge SIMULASI pada baris data yang memang berasal dari simulator.
- Halaman simulasi pembayaran selalu punya label besar “SIMULASI PEMBAYARAN” di atas lipatan, karena halaman ini sering dibuka dari HP hasil pindai QR.
- Satu orang tidak boleh merangkap peran dalam satu sesi: login admin, login pembeli, dan login developer adalah tiga pintu masuk yang terpisah.

## 3. Objek Utama yang Dilihat Pengguna

Ini adalah objek konseptual untuk keperluan alur dan layar, bukan skema penyimpanan. Setiap objek di bawah ini harus bisa dilihat atau dicari dari layar yang relevan, bukan hanya tersembunyi di belakang layar.

### 3.1 Produk dan kategori

- **Kategori:** nama kategori (misalnya alat tulis, perlengkapan sekolah, kebutuhan sehari-hari), dan status aktif.
- **Produk:** nama, kategori, harga, stok yang tersedia, deskripsi singkat, dan status aktif/nonaktif.
- **Aturan tampil:** produk nonaktif tidak muncul di katalog pembeli. Produk dengan stok habis tetap bisa dilihat, tetapi tombol belinya dalam keadaan tidak aktif dan ada keterangan stok habis.
- **Gambar produk:** pada tahap ini tidak ada foto produk asli. Gunakan placeholder ikon + gradasi warna per kategori. Jangan pakai gambar acak dari internet yang bisa rusak.

### 3.2 Keranjang

- **Isi keranjang:** daftar produk, jumlah per produk, harga satuan, dan subtotal.
- **Aksi:** tambah jumlah, kurangi jumlah, hapus item, kosongkan keranjang.
- **Aturan:** jumlah tidak boleh melebihi stok yang tersedia, dan tidak boleh nol atau negatif. Bila stok berubah saat produk masih di keranjang, pembeli diberi tahu sebelum checkout.

### 3.3 Pesanan

- **Isi pesanan:** nomor pesanan, pembeli, daftar produk beserta jumlah dan harga saat dibeli, total, status pesanan, status pembayaran, dan linimasa status.
- **Linimasa status** harus selalu terlihat pada detail pesanan, supaya pembeli dan admin bisa menelusur apa yang sudah terjadi, bukan hanya status terakhirnya.

### 3.4 Pembayaran

- **Isi pembayaran:** nomor pesanan, metode pembayaran, total, kode referensi, status pembayaran, waktu dibuat, dan waktu kedaluwarsa.
- **Metode yang wajib ada (enam):** QRIS, Dana, GoPay, Virtual Account BRI, Virtual Account BCA, Virtual Account BNI.
- **Untuk QRIS, Dana, dan GoPay:** pembeli melihat QR besar.
- **Untuk Virtual Account BRI, BCA, dan BNI:** pembeli melihat nomor Virtual Account besar yang bisa disalin, plus QR kecil untuk membuka halaman simulasi pembayaran.
- **QR pada sistem ini bukan QR pembayaran riil.** QR mengarah ke halaman simulasi pembayaran untuk pembayaran itu, sehingga bisa dipindai dengan kamera HP atau Google Lens dan dibuka di browser. Jangan pernah menampilkan QR ini seolah-olah QR resmi bank atau e-wallet, dan jangan pakai logo atau warna merek bank/e-wallet. Gunakan ikon generik (kode QR, dompet, bank) dan nama metode dalam bentuk teks.

### 3.5 Event aktivitas (sumber analitik)

Setiap aktivitas pembeli berikut harus tercatat sebagai satu event, dengan produk, pelaku, dan waktu kejadiannya:

1. `view` — produk dilihat (halaman detail dibuka).
2. `add-to-cart` — produk dimasukkan ke keranjang.
3. `booking` — pesanan dibuat (checkout selesai, pembayaran masih menunggu).
4. `purchase` — pembayaran berhasil (hanya bila status pembayaran menjadi berhasil).
5. `cancel` — pesanan dibatalkan atau kedaluwarsa.

Tanpa event ini, analitik tidak punya bahan. Karena itu, setiap layar dan aksi pada Bagian 4 harus jelas event apa yang dihasilkannya.

### 3.6 Simulasi (jejak developer)

- **Akun simulasi:** akun pembeli yang dibuat lewat panel developer, bertanda simulator.
- **Sesi simulasi (run):** satu kali eksekusi simulator, berisi: siapa developernya, aksi apa yang dijalankan, berapa banyak yang dibuat, untuk periode apa, kapan dijalankan, dan hasilnya (berhasil, gagal sebagian, atau gagal total dengan tidak ada data yang setengah jadi).
- **Jejak simulasi:** daftar semua aksi simulator yang bisa dibuka kembali oleh developer, termasuk pratinjau sebelum eksekusi dan hasil sesudahnya.
- **Reset simulasi:** menghapus data simulasi saja, tanpa mengubah data pemakaian normal. Reset wajib lewat konfirmasi eksplisit (misalnya mengetik frasa konfirmasi), bukan satu klik tanpa pengaman.

## 4. Alur Utama (Workflow)

Setiap alur di bawah ini ditulis sebagai langkah pengguna. Bila satu langkah punya cabang (berhasil/gagal/kedaluwarsa), cabangnya ditulis, bukan disembunyikan.

### W1 — Admin menyiapkan toko sampai siap menerima pembeli

1. Admin login.
2. Admin membuat kategori produk (nama, status aktif).
3. Admin membuat produk: nama, kategori, harga, stok awal, deskripsi singkat, status aktif.
4. Admin memeriksa katalog dari sisi pembeli: produk aktif muncul, produk nonaktif tidak muncul, stok tampil benar.
5. **Titik siap:** toko dinyatakan siap menerima pembeli hanya bila minimal ada satu kategori aktif dan satu produk aktif dengan stok tersedia.
6. Setelah ada pesanan, admin mengelola pesanan dari daftar pesanan (lihat W6) dan membaca analitik (lihat W7).

**Keadaan yang harus ada:** daftar kosong, formulir dengan galat per field, tombol loading, konfirmasi hapus/nonaktifkan, dan pesan sukses setelah simpan.

### W2 — Pembeli mencari dan melihat produk

1. Pembeli membuka katalog (boleh tanpa login untuk melihat, tetapi checkout wajib login — lihat catatan pada Bagian 9).
2. Pembeli mencari dengan kata kunci, memfilter berdasarkan kategori, dan mengurutkan hasil bila tersedia.
3. Pembeli membuka detail produk. Langkah ini mencatat event `view`.
4. Pembeli melihat harga, stok, deskripsi, dan tombol tambah ke keranjang.
5. Bila stok habis, tombol tambah ke keranjang tidak aktif dan ada keterangan stok habis. Bila produk tidak ditemukan, tampil keadaan tidak ditemukan dengan satu aksi kembali yang jelas, bukan halaman kosong.

**Keadaan yang harus ada:** loading (skeleton), kosong, tidak ditemukan, galat, dan terisi.

### W3 — Pembeli mengisi keranjang dan checkout

1. Pembeli menekan tambah ke keranjang dari katalog atau detail produk. Langkah ini mencatat event `add-to-cart`.
2. Pembeli membuka keranjang: daftar item, jumlah, harga satuan, subtotal, dan total.
3. Pembeli mengubah jumlah atau menghapus item. Subtotal dan total diperbarui langsung.
4. Pembeli menekan checkout. Sistem memeriksa: pembeli sudah login, keranjang tidak kosong, dan stok tiap item masih cukup.
5. Bila ada item yang stoknya tidak cukup, checkout berhenti pada item itu dengan pesan yang menyebut nama produknya, bukan pesan umum.
6. Bila lolos, sistem membuat pesanan dengan status `menunggu pembayaran` dan mencatat event `booking`. Pembeli diarahkan ke pemilihan metode pembayaran (W4).

**Keadaan yang harus ada:** keranjang kosong, item bermasalah, submit ganda dicegah (tombol menjadi loading dan tidak bisa ditekan dua kali), dan input jumlah dipertahankan bila terjadi galat.

### W4 — Pembeli memilih metode dan menerima instruksi pembayaran

1. Pembeli melihat ringkasan pesanan dan enam kartu metode: QRIS, Dana, GoPay, Virtual Account BRI, Virtual Account BCA, Virtual Account BNI. Setiap kartu berlabel SIMULASI.
2. Pembeli memilih satu metode. Tombol lanjut tidak aktif sebelum metode dipilih.
3. Sistem membuat satu pembayaran untuk pesanan itu dengan status `menunggu` (pending), total sama persis dengan total pesanan, dan waktu kedaluwarsa.
4. Pembeli diarahkan ke halaman instruksi pembayaran yang menampilkan:
   - Nomor pesanan, total, metode yang dipilih, dan waktu kedaluwarsa dengan hitung mundur.
   - Untuk QRIS/Dana/GoPay: QR besar.
   - Untuk Virtual Account: nomor Virtual Account besar yang bisa disalin, plus QR kecil.
   - Tombol “Buka Halaman Simulasi Pembayaran” sebagai jalan alternatif bila pembeli tidak memindai QR (misalnya sedang membuka web dari laptop).
   - Label SIMULASI yang tidak bisa dilewatkan.
5. Selama pembayaran masih menunggu, pembeli bisa kembali ke halaman instruksi ini dari riwayat pesanan.

**Keadaan yang harus ada:** belum memilih metode, stok tidak cukup saat checkout, pembayaran sudah kedaluwarsa, dan peringatan bila halaman dibuka dari perangkat yang tidak bisa memindai QR-nya sendiri.

### W5 — Pembayaran disimulasikan lewat QR (alur paling penting untuk didemokan)

1. Pembeli memindai QR dari halaman instruksi dengan kamera HP, Google Lens, atau pemindai QR apa pun.
2. Browser membuka halaman simulasi pembayaran untuk pembayaran itu. Halaman ini dirancang **mobile-first**: satu kolom, label besar di atas, aksi mudah dijangkau ibu jari.
3. Halaman simulasi menampilkan, dalam urutan ini:
   - Label “SIMULASI PEMBAYARAN” besar.
   - Ringkasan pesanan: nomor pesanan, daftar produk ringkas, total, metode, dan kode referensi/nomor Virtual Account bila ada.
   - Hitung mundur sisa waktu pembayaran.
   - Tiga aksi: **Bayar Sekarang (Simulasi)**, **Simulasikan Gagal**, dan **Kembali ke Pesanan**.
   - Panel “Simulated HTTP Request & Response” di bawah aksi (lihat W5b).
4. **Cabang A — Bayar berhasil:** pembeli menekan Bayar Sekarang (Simulasi). Status pembayaran menjadi `berhasil`, status pesanan menjadi `dibayar/diproses`, event `purchase` tercatat, dan pembeli diarahkan ke halaman hasil berhasil dengan ringkasan dan tautan ke riwayat pesanan.
5. **Cabang B — Gagal:** pembeli menekan Simulasikan Gagal. Status pembayaran menjadi `gagal`, pesanan kembali ke keadaan yang memungkinkan bayar ulang (metode boleh diganti) atau dibatalkan, sesuai aturan pada PRD. Event `purchase` tidak tercatat.
6. **Cabang C — Kedaluwarsa:** bila waktu habis dan belum dibayar, status pembayaran menjadi `kedaluwarsa` dan pesanan menjadi `kedaluwarsa/dibatalkan`. Pembeli yang membuka halaman simulasi setelah kedaluwarsa hanya melihat pesan status, bukan lagi tombol bayar.
7. **Cabang D — Token dipakai ulang:** bila QR atau tautan halaman simulasi dibuka lagi setelah pembayaran sudah final (berhasil/gagal/kedaluwarsa/dibatalkan), sistem menolak perubahan dan menampilkan pesan status final yang jelas, bukan tombol aksi yang menyesatkan.
8. **Cabang E — Pembatalan oleh pembeli:** pembeli bisa membatalkan pesanan yang masih menunggu pembayaran dari riwayat pesanan. Status pembayaran menjadi `dibatalkan`, dan tautan/QR-nya tidak bisa lagi dipakai untuk membayar.

#### W5b — Panel Simulated HTTP Request & Response

Panel ini wajib ada pada halaman simulasi pembayaran, karena inilah bagian yang membuat simulasi ini bisa dijelaskan secara teknis saat demo:

1. **Tab Pembuatan Pembayaran:** apa yang dikirim saat checkout membuat pembayaran (pesanan, total, metode, dan referensi), dan apa jawabannya (status, kode referensi, dan waktu kedaluwarsa). Tanpa nilai rahasia apa pun.
2. **Tab Callback:** apa yang dikirim simulator kembali ke sistem saat tombol Bayar/Gagal ditekan (status baru, waktu, dan referensi), beserta jawabannya (status pesanan setelah diproses). Tampilkan keterangan bahwa tanda tangan (signature) pada simulasi ini adalah dummy, bukan tanda tangan gateway riil.
3. **Tab Timeline:** daftar kronologis perubahan status: waktu, status lama → status baru, dan pemicunya (tombol pembeli, kedaluwarsa, atau pembatalan).
4. **Fitur demo:** tombol salin JSON untuk tiap tab, dan tombol salin sebagai perintah cURL untuk callback simulasi.
5. **Aturan tampil:** panel bersifat hanya-baca bagi pembeli. Isinya dirender sebagai teks, bukan sebagai kode yang bisa dieksekusi dari halaman. Di HP, blok kode menggulir horizontal di dalam wadahnya, bukan membuat seluruh halaman menggulir ke samping.

### W6 — Pembeli dan admin mengelola pesanan

**Dari sisi pembeli:**

1. Pembeli membuka riwayat pesanan: daftar pesanan dengan nomor, tanggal, total, status pesanan, dan status pembayaran.
2. Pembeli membuka detail satu pesanan: daftar produk, total, linimasa status, dan aksi yang masih tersedia (bayar ulang bila masih menunggu dan belum kedaluwarsa, batalkan bila masih bisa dibatalkan, lihat struk simulasi bila sudah berhasil).
3. Pembeli yang sudah berhasil membayar bisa membuka struk simulasi: dokumen ringkas yang bisa dicetak, dengan label SIMULASI yang ikut tercetak.

**Dari sisi admin:**

1. Admin membuka daftar pesanan dengan filter status dan tanggal.
2. Admin membuka detail pesanan: produk, total, linimasa status, dan detail pembayaran beserta jejaknya (hanya-baca).
3. Admin mengubah status pesanan sesuai wewenang yang ditetapkan pada PRD (misalnya dari dibayar menjadi diproses/selesai). Admin tidak mengubah status pembayaran secara manual lewat layar ini; status pembayaran hanya berubah lewat aksi simulasi pembeli, kedaluwarsa, atau pembatalan yang sah.
4. Admin membuka daftar pembayaran dengan filter metode dan status, dan membuka detail satu pembayaran beserta jejak HTTP-nya (hanya-baca).

**Keadaan yang harus ada:** daftar kosong, filter tanpa hasil, galat, dan dialog konfirmasi untuk pembatalan atau perubahan status yang tidak bisa dibatalkan.

### W7 — Admin membaca analitik

Analitik dibaca dari catatan yang sama dengan yang dipakai toko: transaksi yang pembayarannya berhasil, dan event aktivitas. Angka pada analitik harus bisa ditelusur kembali ke transaksi dan event sumbernya (misalnya lewat tautan “Lihat sumber” pada tiap grafik dan tabel).

Urutan baca yang disarankan, sesuai prioritas PRD:

1. **Dashboard (FR-01):** ringkasan penjualan, jumlah transaksi, produk terlaris, produk paling sedikit terjual, dan tren penjualan. Filter periode selalu terlihat di atas. Keadaan kosong (“belum ada data pada periode ini”) dan keadaan galat layanan harus dirancang, bukan dibiarkan kosong begitu saja.
2. **Tren (FR-02):** perubahan penjualan/permintaan per produk dari waktu ke waktu. Soroti produk yang trennya naik dan produk yang trennya turun, bukan sekadar menampilkan grafik mentah tanpa kesimpulan.
3. **Funnel (FR-03):** perbandingan dilihat → keranjang → booking → pembelian, lengkap dengan jumlah dan persentase konversi antar-tahap. Funnel yang sehat menyusut di tiap tahap; bila satu tahap kosong, tampilkan alasannya (misalnya belum ada event pada periode itu), bukan angka nol tanpa konteks.
4. **Perbandingan periode (FR-04):** bandingkan dua periode (hari, minggu, bulan, atau rentang kustom). Tampilkan periode saat ini, periode pembanding, selisih, dan persentase perubahannya untuk tiap produk dan total.
5. **Aktivitas dan minat produk (FR-05):** jumlah produk dilihat, riwayat aktivitas, dan sorotan produk yang sering dilihat atau dimasukkan keranjang tetapi jarang dibeli, berdampingan dengan produk yang pembeliannya tinggi.
6. **Kategori/karakteristik (FR-06, dibangun terakhir):** performa penjualan berdasarkan kategori, periode, atau karakteristik produk.

**Filter yang selalu ada pada semua layar analitik:** periode (rentang tanggal), dan asal data (data normal, data simulasi, atau keduanya — sesuai keputusan pada PRD). Mengganti filter harus memperbarui grafik dan tabel secara bersamaan, bukan hanya salah satunya.

**Keadaan yang harus ada pada tiap layar analitik:** loading, kosong, “data belum cukup untuk analisis ini”, galat, dan versi tabel sebagai alternatif grafik (untuk aksesibilitas dan untuk memeriksa angka tanpa menebak dari grafik).

### W8 — Developer membuat banyak akun simulasi

1. Developer login ke panel developer (pintu masuk terpisah dari admin dan pembeli).
2. Developer membuka layar “Buat Akun Simulasi”.
3. Developer menginput jumlah akun yang ingin dibuat. **Maksimal 100 akun per request.** Jumlah di atas itu harus dipecah menjadi beberapa request, dan sistem harus menolak angka di atas batas dengan pesan yang jelas, bukan diam-diam memotongnya.
4. Developer memilih cara biodata dibuat: random atau deterministik (hasil yang sama bila diulang dengan benih yang sama). Format dan script biodata ditentukan di luar dokumen ini; layar ini hanya meminta jumlah dan cara, bukan mengarang biodata di tempat.
5. Developer menekan buat dan melihat pratinjau atau ringkasan hasil: berapa akun berhasil dibuat, berapa gagal (bila ada), dan daftar akun simulasi yang baru dibuat.
6. Akun simulasi yang dibuat langsung bisa dipakai sebagai akun pembeli biasa dari sisi Sistem Asli (bisa login dan beraktivitas), tetapi tetap bertanda simulator pada jejak developer dan pada badge data simulasi di admin.

**Keadaan yang harus ada:** jumlah melebihi batas (101 dan seterusnya ditolak), jumlah tidak valid (nol, negatif, bukan angka), gagal sebagian, dan daftar akun kosong.

### W9 — Developer membuat transaksi untuk user yang dipilih (user-first)

1. Developer memilih satu atau beberapa akun simulasi yang sudah ada.
2. Developer memilih periode transaksi (tanggal mulai dan tanggal akhir), **termasuk periode yang sudah lewat dari hari ini**.
3. Developer menentukan produk, jumlah, dan pola aktivitas yang menyertainya (lihat W9b).
4. Developer melihat **pratinjau** sebelum eksekusi: ringkasan apa yang akan dibuat (jumlah transaksi, jumlah event, rentang tanggal, dan akun yang terlibat).
5. Developer menekan konfirmasi. Sistem membuat transaksi dan event dengan stempel waktu di dalam periode yang dipilih, bukan dengan waktu hari ini.
6. Developer melihat hasil eksekusi: berhasil, gagal, atau gagal sebagian dengan penjelasan. Bila gagal di tengah jalan, tidak boleh ada data setengah jadi yang tertinggal tanpa keterangan.
7. Developer kembali ke Sistem Asli sebagai admin dan membaca analitik dari data tersebut untuk memastikan angkanya berubah sesuai harapan.

#### W9b — Pola aktivitas yang harus bisa dibuat bersama transaksi

Satu transaksi saja tidak cukup untuk menguji funnel. Simulator harus bisa menghasilkan, untuk akun dan produk yang dipilih:

- Event `view` (produk dilihat).
- Event `add-to-cart` (produk masuk keranjang, belum tentu dibeli).
- Event `booking` (pesanan dibuat).
- Event `purchase` (pembayaran berhasil).
- Pembatalan (pesanan dibatalkan) sebagai variasi status yang wajar.

Dengan begitu, developer bisa membentuk pola funnel yang lengkap maupun pola minat tanpa pembelian (sering dilihat/dikeranjangkan, jarang dibeli).

### W10 — Developer memperbanyak transaksi pada periode yang dipilih (period-first)

Ini berbeda dengan W9. Pada W9, titik mulanya adalah user. Pada alur ini, titik mulanya adalah periode.

1. Developer login ke panel developer.
2. Developer memilih periode atau rentang periode (satu tanggal, satu minggu, satu bulan, atau rentang kustom mulai–akhir), termasuk periode yang sudah lewat.
3. Developer menentukan jumlah transaksi yang ingin diperbanyak pada periode tersebut.
4. Developer menentukan cakupan user-nya, salah satu dari:
   - Satu user simulasi tertentu.
   - Beberapa user simulasi yang dipilih.
   - Semua akun simulasi yang ada.
   - User simulasi yang dipilih secara acak oleh sistem.
5. Developer melihat pratinjau (jumlah, periode, user, dan produk yang terlibat) dan menekan konfirmasi.
6. Sistem menghasilkan transaksi-transaksi itu dengan stempel waktu yang **tersebar di dalam periode yang dipilih**, bukan semuanya menumpuk pada satu waktu yang sama, kecuali itu yang secara eksplisit diminta.
7. Setiap transaksi disertai event funnel yang relevan dan variasi status yang wajar (selesai dan batal), supaya hasilnya bisa dibaca oleh dashboard, tren, funnel, dan terutama perbandingan periode (FR-04).

Alur ini adalah jawaban langsung untuk kebutuhan perbandingan periode: tanpa kemampuan memperbanyak transaksi pada periode tertentu, akan sulit menghasilkan dua periode yang layak dibandingkan.

### W11 — Developer mengaudit dan me-reset data simulasi

1. Developer membuka layar Jejak/Audit: daftar semua aksi simulator (kapan, aksi apa, berapa banyak, untuk periode apa, dan hasilnya).
2. Developer membuka detail satu sesi simulasi untuk melihat pratinjau dan hasil yang pernah dibuatnya.
3. Bila perlu mengulang dengan pola berbeda, developer menekan reset data simulasi.
4. Sistem meminta konfirmasi eksplisit (misalnya mengetik frasa konfirmasi) sebelum reset dijalankan.
5. Setelah reset, hanya data bertanda simulator yang hilang. Data pemakaian normal harus tetap utuh, dan sistem harus menampilkan ringkasan sebelum/sesudah (jumlah baris data normal tidak berubah) supaya reset bisa diverifikasi.
6. Developer bisa mengulang simulasi dengan pola berbeda setelah reset.

## 5. Mesin Status (Ringkasan untuk Layar)

### 5.1 Status pembayaran

```
menunggu → berhasil
menunggu → gagal
menunggu → kedaluwarsa
menunggu → dibatalkan
```

Status akhir (berhasil, gagal, kedaluwarsa, dibatalkan) tidak bisa diubah lagi. Upaya mengubahnya harus ditolak dengan pesan yang jelas dan dicatat pada jejak pembayaran.

### 5.2 Status pesanan (pemetaan yang dipakai layar)

| Status pembayaran | Status pesanan yang tampil | Event analitik |
| --- | --- | --- |
| Menunggu | Menunggu pembayaran | `booking` sudah tercatat saat pesanan dibuat |
| Berhasil | Dibayar/diproses | `purchase` tercatat |
| Gagal | Menunggu pembayaran ulang atau dibatalkan, sesuai aturan PRD | Tidak ada `purchase` |
| Kedaluwarsa | Kedaluwarsa/dibatalkan | Tidak ada `purchase` |
| Dibatalkan | Dibatalkan | Tidak ada `purchase` |

### 5.3 Stok dalam bahasa alur (bukan bahasa teknis)

- Saat pesanan dibuat, stok produk dicadangkan (tidak bisa dibeli orang lain untuk jumlah yang sama).
- Saat pembayaran berhasil, cadangan itu dikonfirmasi menjadi pengurangan stok yang permanen.
- Saat pembayaran gagal, kedaluwarsa, atau dibatalkan, cadangan itu dilepas kembali.
- Stok tidak boleh berkurang dua kali untuk satu pembayaran yang sama, dan tidak boleh menjadi negatif. Aturan persisnya (kapan cadangan dibuat dan dilepas) mengikuti PRD; layar hanya perlu menampilkan stok tersedia, stok dicadangkan (bila relevan), dan pesan stok tidak cukup dengan nama produknya.

## 6. Analitik sebagai Workflow (Tanpa Rumus Teknis)

Bagian ini menjelaskan apa yang dihitung, dalam bahasa pengguna, supaya layar analitik tidak sekadar menampilkan grafik tanpa makna.

- **Penjualan pada satu periode:** jumlahkan total pesanan yang pembayarannya berhasil pada periode itu. Pesanan yang masih menunggu, gagal, kedaluwarsa, atau dibatalkan tidak dihitung sebagai penjualan.
- **Produk terlaris:** urutkan produk berdasarkan jumlah terjual (atau total penjualan, sesuai definisi pada PRD) dari yang terbesar. Produk paling sedikit terjual adalah urutan sebaliknya, termasuk produk yang tidak terjual sama sekali pada periode itu bila PRD menetapkannya demikian.
- **Tren naik/turun:** bandingkan penjualan satu produk pada beberapa titik waktu. Bila penjualan pada titik terbaru lebih tinggi dari titik sebelumnya secara konsisten, produk itu ditandai naik; bila lebih rendah, ditandai turun. Bila tidak ada data pembanding, tampilkan “belum cukup data”, bukan kesimpulan yang dibuat-buat.
- **Funnel:** hitung berapa event `view`, berapa `add-to-cart`, berapa `booking`, dan berapa `purchase` untuk produk atau periode yang sama, lalu hitung persentase dari satu tahap ke tahap berikutnya. Funnel yang bisa dianalisis adalah funnel yang menyusut, bukan funnel yang lurus 100%.
- **Perbandingan periode:** ambil dua periode dengan panjang yang bisa dibandingkan, hitung penjualan tiap produk dan totalnya pada masing-masing periode, lalu tampilkan selisih dan persentase perubahannya.
- **Minat tanpa pembelian:** cari produk dengan event `view` dan `add-to-cart` yang tinggi tetapi event `purchase` yang rendah. Produk seperti ini adalah sorotan utama pada layar aktivitas dan minat produk.
- **Per kategori:** kelompokkan penjualan berdasarkan kategori produk, lalu bandingkan antar-kategori pada periode yang sama.

Semua angka di atas harus berasal dari transaksi dan event yang sama dengan yang dipakai toko. Tidak ada angka analitik yang diketik manual ke dalam grafik.

## 7. Peta Layar per Permukaan

Tabel ini adalah inventaris layar untuk Figma Make. Satu baris = satu layar (atau satu kelompok layar yang sangat berdekatan). Kolom “Keadaan” adalah keadaan yang wajib dirancang untuk layar itu, bukan opsional.

### 7.1 Storefront (Pembeli)

| Layar | Tujuan dan elemen kunci | Keadaan yang wajib ada |
| --- | --- | --- |
| Masuk / Daftar pembeli | Formulir login dan register pembeli, tautan lupa kata sandi bila ada pada PRD | Galat per field, tombol loading, akun nonaktif |
| Katalog | Grid kartu produk, sidebar kategori, pencarian | Loading skeleton, kosong, tidak ditemukan, galat |
| Detail produk | Placeholder produk, harga, stok, deskripsi, kontrol jumlah, tombol tambah ke keranjang | Stok habis, galat tambah ke keranjang |
| Keranjang | Tabel/kartu item, ringkasan lengket (subtotal, total) | Kosong, item bermasalah (stok tidak cukup) |
| Checkout + pilih metode | Ringkasan pesanan, enam kartu metode berlabel SIMULASI | Belum memilih metode, submit ganda dicegah, stok tidak cukup |
| Instruksi pembayaran | QR besar atau nomor Virtual Account besar + QR kecil, hitung mundur, tombol buka halaman simulasi | Aktif, kedaluwarsa, peringatan perangkat |
| Riwayat pesanan dan detail | Daftar pesanan, detail dengan linimasa status, aksi bayar ulang/batalkan/lihat struk | Kosong, filter tanpa hasil |
| Hasil berhasil / gagal / kedaluwarsa | Kartu hasil, ringkasan pembayaran, aksi lanjut (kembali ke katalog, lihat pesanan) | Tiga varian hasil |
| Struk simulasi | Dokumen ringkas yang bisa dicetak, label SIMULASI ikut tercetak | — |

### 7.2 Admin Toko

| Layar | Tujuan dan elemen kunci | Keadaan yang wajib ada |
| --- | --- | --- |
| Masuk admin | Formulir login admin (pintu masuk terpisah dari pembeli dan developer) | Galat per field, tombol loading, akun nonaktif |
| Dashboard analitik | Filter periode dan asal data di atas, kartu KPI, grafik tren, daftar terlaris/terendah | Kosong, galat, loading |
| Tren | Kontrol filter di atas, grafik tren per produk, tabel alternatif, tautan “Lihat sumber” | Kosong, data belum cukup, galat |
| Funnel | Grafik funnel empat tahap dengan jumlah dan persen, tabel alternatif | Kosong, data belum cukup, galat |
| Perbandingan periode | Dua pemilih periode, tabel dan grafik selisih dan persen perubahan | Kosong, data belum cukup, galat |
| Aktivitas dan minat produk | Daftar produk dilihat, riwayat aktivitas, sorotan minat tanpa pembelian | Kosong, data belum cukup, galat |
| Kategori (analitik) | Grafik dan tabel performa per kategori | Kosong, data belum cukup, galat |
| Kategori produk (kelola) | Daftar kategori, formulir tambah/ubah | Kosong, validasi, konfirmasi hapus |
| Produk (daftar dan formulir) | Daftar produk, formulir produk dan stok, riwayat stok bila ada | Kosong, validasi, konfirmasi hapus/nonaktifkan |
| Pesanan (daftar dan detail) | Daftar berfilter, detail dengan linimasa dan jejak pembayaran (hanya-baca) | Kosong, filter tanpa hasil |
| Pembayaran (daftar dan detail) | Daftar berfilter metode/status, detail dengan panel HTTP hanya-baca | Kosong, filter tanpa hasil |
| Akun pembeli (daftar dan detail) | Daftar akun, detail akun, aksi nonaktifkan | Kosong, dialog nonaktifkan |
| Layar sistem | 403/404/419/429/500 dengan satu tombol kembali yang jelas | — |

### 7.3 Panel Developer

| Layar | Tujuan dan elemen kunci | Keadaan yang wajib ada |
| --- | --- | --- |
| Masuk developer | Formulir login developer (pintu masuk terpisah, bilah SIMULATOR) | Galat per field, tombol loading, akun nonaktif |
| Beranda simulator | Ringkasan sesi terakhir, jalan pintas ke tiga aksi utama, penanda SIMULATOR permanen | Kosong (belum ada sesi) |
| Buat akun simulasi | Input jumlah (maksimal 100 per request), pilihan random/deterministik, ringkasan hasil | Melebihi batas, jumlah tidak valid, gagal sebagian |
| Transaksi per user (user-first) | Pilih akun, pilih periode, pilih produk dan pola, pratinjau, konfirmasi | Pratinjau, berhasil, gagal (rollback), batas terlampaui |
| Transaksi per periode (period-first) | Pilih periode, input jumlah, pilih cakupan user, pratinjau, konfirmasi | Pratinjau, berhasil, gagal (rollback), batas terlampaui |
| Funnel (simulator) | Pilih pola funnel yang ingin dibuat (misalnya rasio tiap tahap), pratinjau, konfirmasi | Pratinjau, berhasil, gagal |
| Akun simulasi (daftar) | Daftar akun simulasi berfilter, detail akun | Kosong, filter tanpa hasil |
| Jejak dan audit | Daftar sesi simulasi, detail sesi, ringkasan hasil | Kosong, filter tanpa hasil |
| Reset data simulasi | Penjelasan apa yang akan dihapus, konfirmasi ketik frasa, ringkasan sebelum/sesudah | Konfirmasi belum lengkap, reset dibatalkan |

### 7.4 Halaman Simulasi Pembayaran (dibuka lewat QR)

| Layar | Tujuan dan elemen kunci | Keadaan yang wajib ada |
| --- | --- | --- |
| Halaman simulasi (satu kolom, mobile-first) | Label SIMULASI PEMBAYARAN besar → ringkasan pesanan → hitung mundur → tiga aksi → panel HTTP | Menunggu, berhasil, gagal, kedaluwarsa, final/dipakai ulang, tidak ditemukan |
| Panel HTTP (tiga tab) | Tab Pembuatan Pembayaran, tab Callback, tab Timeline; tombol salin JSON dan salin cURL; keterangan signature dummy | Blok kode hanya-baca, menggulir horizontal di HP |

## 8. Navigasi dan Responsif (Konseptual)

- **Storefront (pembeli):** bilah atas pada desktop (Katalog, Keranjang, Pesanan, Pembayaran, Akun). Pada HP, navigasi menjadi tab bawah maksimal lima item atau laci navigasi. Tabel menjadi kartu pada HP.
- **Admin:** sidebar bergrup pada desktop — *Analitik* (Dashboard, Tren, Funnel, Perbandingan, Aktivitas & Minat, Kategori), *Toko* (Produk, Kategori produk), *Transaksi* (Pesanan, Pembayaran), *Akun* (Akun pembeli). Pada tablet, sidebar menjadi rail ikon. Pada HP, empat item teratas menjadi tab bawah plus “Lainnya”, tabel menjadi kartu, dan filter pindah ke lembar bawah (bottom sheet).
- **Developer:** sidebar delapan item pada desktop (sesuai daftar pada Bagian 7.3), rail ikon pada tablet, tab bawah plus “Lainnya” pada HP. Penanda SIMULATOR tetap terlihat pada semua lebar layar.
- **Halaman simulasi pembayaran:** satu kolom penuh pada HP dengan aksi menempel dekat bawah, kolom tengah di dalam kartu pada desktop. Halaman ini dirancang mobile-first karena dibuka dari HP hasil pindai QR.
- **Semua permukaan:** satu sistem tata letak adaptif (bukan dua desain yang kebetulan mirip). Periksa pada tiga lebar: sekitar 390 px, 768 px, dan 1280 px. Target sentuh minimal 44×44 px pada HP dan tablet.

## 9. Aturan dan Batas yang Memengaruhi Alur

Aturan berikut bukan detail teknis; aturan ini mengubah apa yang dilihat dan bisa dilakukan pengguna, sehingga harus terlihat pada alur dan layar.

1. **Maksimal 100 akun per request** pada pembuatan akun simulasi. Angka di atas itu ditolak dengan pesan yang jelas.
2. **Pembayaran punya waktu kedaluwarsa** dan hitung mundur yang terlihat. Durasi pastinya mengikuti usulan pada PRD (contoh yang dipakai pada dokumen pendamping adalah 30 menit); dokumen workflow ini tidak mengunci durasi itu sebagai keputusan final.
3. **Token/QR pembayaran hanya untuk satu pembayaran**, tidak bisa dipakai ulang setelah final, dan tidak bisa mengubah nominal. Nominal selalu diambil dari pesanan, bukan dari tautan atau input pengguna.
4. **Satu pembayaran aktif per pesanan** pada satu waktu (aturan persisnya mengikuti PRD). Pembeli yang gagal bayar harus membuat pembayaran baru atau membatalkan, bukan menumpuk pembayaran aktif yang membingungkan.
5. **Profil dan pengaturan pembeli tidak ditambahkan** pada tahap ini. Layar sistem yang ditambahkan hanyalah layar galat (403/404/419/429/500), toast, dialog konfirmasi, dan keadaan loading/kosong/galat — karena keadaan itu dibutuhkan oleh alur, bukan karena itu fitur produk.
6. **Tidak ada logo atau warna merek bank/e-wallet** pada metode pembayaran. Nama metode ditulis sebagai teks dengan ikon generik.
7. **Tidak ada klaim gateway riil.** Setiap tampilan pembayaran, QR, struk, dan jejak harus bisa dibedakan dari pembayaran riil oleh orang yang melihatnya pertama kali.
8. **Bahasa dan format lokal:** semua label, pesan galat, dan aksi memakai bahasa Indonesia yang spesifik (misalnya “Simpan perubahan”, “Batalkan pesanan”, “Bayar Sekarang (Simulasi)”), bukan label umum seperti “Submit” atau “OK” tanpa konteks.
9. **Keadaan standar pada tiap alur:** loading, kosong, tidak ditemukan, galat dengan aksi coba lagi, sukses, dan tidak aktif. Input dipertahankan bila terjadi galat. Submit ganda dicegah. Aksi destruktif (hapus, nonaktifkan, reset simulasi) selalu lewat dialog konfirmasi dengan pola yang sama.

## 10. Data Contoh yang Harus Ada pada Prototipe

Supaya layar Figma Make tidak terlihat kosong atau dibuat-buat, prototipe harus diisi data contoh yang realistis dan sesuai dengan objek pada Bagian 3:

- **Produk:** minimal produk dari lebih dari satu kategori, dengan harga Rupiah yang wajar untuk alat tulis, stok yang bervariasi (ada yang banyak, ada yang sedikit, ada yang habis), dan produk aktif serta nonaktif sebagai contoh.
- **Pesanan:** pesanan pada tiap status (menunggu pembayaran, dibayar/diproses, gagal, kedaluwarsa, dibatalkan) sebagai contoh untuk riwayat dan daftar admin.
- **Pembayaran:** contoh untuk keenam metode, dengan QR dan nomor Virtual Account dummy yang jelas-jelas simulasi.
- **Funnel:** event yang menyusut di tiap tahap (misalnya banyak dilihat, sebagian masuk keranjang, sebagian dibooking, sebagian dibeli), bukan funnel lurus 100%.
- **Tren dan perbandingan:** transaksi yang tersebar pada minimal dua periode, dengan produk yang naik dan produk yang turun, supaya grafik tren dan perbandingan periode punya cerita.
- **Minat tanpa pembelian:** produk yang sering dilihat/dikeranjangkan tetapi jarang dibeli, berdampingan dengan produk yang pembeliannya tinggi.
- **Data simulasi vs normal:** contoh data bertanda simulator dan data normal, supaya badge SIMULASI dan filter asal data bisa dilihat bekerja, bukan hanya ditulis pada spesifikasi.

Semua data contoh di atas adalah data fiktif untuk prototipe dan tugas. Jangan pernah menyajikannya sebagai data penjualan riil Ira Stationery, dan jangan memakai nama pelanggan, logo, atau testimoni fiktif yang diklaim nyata.

## 11. Yang Bukan Bagian dari Workflow Ini

- Integrasi gateway riil, QR resmi bank/e-wallet, perpindahan uang riil, settlement, dan refund ke rekening/e-wallet riil.
- Integrasi data riil toko Ira Stationery.
- Rekomendasi kebutuhan stok yang cerdas dan analisis produk yang sering dibeli bersamaan.
- Profil dan pengaturan pembeli, serta fitur pemasaran (landing page, testimoni, harga berlangganan) yang tidak ada pada PRD.
- Keputusan teknis: bahasa, framework, basis data, layanan kalkulasi, cara penyimpanan, dan cara deployment. Semua itu ada pada dokumen teknis terpisah dan tidak boleh disimpulkan dari dokumen workflow ini.

## 12. Hal Terbuka untuk UI.md dan PRD (Bukan Hal Teknis)

Dokumen workflow ini sengaja tidak memutuskan hal-hal berikut. UI.md dan PRD harus memutuskannya secara eksplisit, dan Figma Make harus menandai asumsi yang dipakainya bila hal ini belum diputuskan saat prototipe dibuat:

1. Durasi kedaluwarsa pembayaran yang final.
2. Format persis nomor Virtual Account dummy per bank dan kode referensi QR.
3. Apakah halaman simulasi boleh dibuka tanpa login, atau wajib login pemilik pesanan.
4. Status pesanan persis setelah pembayaran gagal (bayar ulang atau batal) dan aturan stok persisnya.
5. Apakah data simulator tampil bersama data normal pada analitik secara default, atau bisa difilter/dipisahkan pada tampilan.
6. Gaya visual final (warna merek, tipografi, radius, dan kepadatan) — hal ini ditetapkan pada UI.md yang Ira-sentris. UI.md sudah ada dan menjadi acuan visual utama; Figma Make memakai token dan aturan visual dari UI.md, dan DESIGN.md hanya dipakai bila UI.md diam, bukan mengarang gaya baru per layar.
