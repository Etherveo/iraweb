# Payment Gateway Simulator — Ira Stationery

> **Status:** Spesifikasi pendamping, 1 November 2026.
> **Baca bersama:** File ini WAJIB dibaca bersama `Ira-discussion.md` (dokumen discussion e-commerce + simulator). File ini tidak menggantikan discussion tersebut; ia hanya merinci satu bagian: simulasi pembayaran.
> **Prinsip utama:** Yang dibangun pada tahap ini adalah **SIMULATOR payment gateway, bukan integrasi payment gateway riil**. Tidak ada uang riil yang berpindah, tidak ada koneksi ke QRIS, Dana, GoPay, BRI, BCA, atau BNI yang sebenarnya.
> **Kenapa ada file terpisah:** Supaya penyusun PRD/blueprint bisa mengerjakan modul pembayaran sebagai satu kesatuan yang jelas, tanpa mencampuradukkannya dengan panel developer (simulator data) pada discussion.

## 1. Tujuan

1. Pembeli bisa merasakan alur pembayaran yang lengkap dan nyata dari sisi pengalaman: pilih metode → dapat instruksi bayar → bayar (simulasi) → pesanan berubah status.
2. Pembayaran bisa didemokan dengan keren dan bisa dijelaskan secara teknis: QR yang dipindai membuka halaman web simulasi, dan halaman itu menampilkan simulasi HTTP request/response serta jejak perubahan statusnya.
3. Analitik (FR-01 sampai FR-05 pada discussion) mendapatkan data pembayaran yang konsisten: hanya pesanan yang status pembayarannya `paid` yang dihitung sebagai pembelian (purchase).
4. Arsitektur disiapkan sejak awal supaya suatu hari bisa diganti/diintegrasikan dengan payment gateway riil **tanpa mengubah alur checkout, pesanan, dan analitik**.

## 2. Metode Pembayaran yang Wajib Ada (Minimal)

| Metode | Jenis tampilan utama | Yang dilihat pembeli |
| --- | --- | --- |
| QRIS | QR | QR yang dipindai membuka halaman simulasi pembayaran untuk pembayaran tersebut. |
| E-wallet — Dana | QR | QR bergaya simulasi Dana yang dipindai membuka halaman simulasi pembayaran untuk pembayaran tersebut. |
| E-wallet — GoPay | QR | QR bergaya simulasi GoPay yang dipindai membuka halaman simulasi pembayaran untuk pembayaran tersebut. |
| Virtual Account — BRI | Nomor VA + QR | Nomor VA simulasi BRI, plus QR yang dipindai membuka halaman simulasi pembayaran untuk pembayaran tersebut. |
| Virtual Account — BCA | Nomor VA + QR | Nomor VA simulasi BCA, plus QR yang dipindai membuka halaman simulasi pembayaran untuk pembayaran tersebut. |
| Virtual Account — BNI | Nomor VA + QR | Nomor VA simulasi BNI, plus QR yang dipindai membuka halaman simulasi pembayaran untuk pembayaran tersebut. |

Catatan penting tentang QR:

- **QR pada simulator ini BUKAN payload QRIS EMVCo dan BUKAN QR resmi Dana/GoPay/bank.**
- QR meng-encode sebuah URL halaman simulasi, misalnya `https://<domain-aplikasi>/pay/sim/<payment-token>`.
- Karena isinya URL biasa, QR bisa dipindai dengan kamera HP, Google Lens, atau pemindai QR apa pun, lalu membuka halaman web simulasi pembayaran di browser.
- Token pada URL bersifat: terikat pada satu pembayaran saja, tidak bisa dipakai untuk pembayaran lain, tidak bisa dipakai mengubah nominal (nominal selalu divalidasi dari pesanan di server, bukan dari URL), dan kedaluwarsa bersama pembayaran.
- Setiap halaman pembayaran, QR, dan struk wajib menampilkan label **SIMULASI** yang jelas.

Format nomor VA simulasi, awalan per bank, dan panjang digitnya ditetapkan pada PRD/blueprint sebagai format dummy yang konsisten (misalnya awalan simulasi per bank + nomor referensi pesanan), dan harus terlihat jelas sebagai dummy, bukan nomor VA riil.

## 3. Alur End-to-End Pembeli

### 3.1 Dari keranjang sampai instruksi bayar

1. Pembeli login, mengisi keranjang, lalu checkout.
2. Sistem membuat pesanan dengan status awal `menunggu_pembayaran` dan mencatat event `booking`.
3. Pembeli memilih salah satu metode pada Bagian 2.
4. Sistem membuat satu objek pembayaran (payment) dengan status `pending`, nominal sama persis dengan total pesanan, metode yang dipilih, kode referensi (nomor VA untuk VA, token QR untuk QR/e-wallet), dan waktu kedaluwarsa.
5. Sistem menampilkan halaman instruksi pembayaran:
   - Nama toko, nomor pesanan, total yang harus dibayar, dan metode yang dipilih.
   - Untuk QRIS/Dana/GoPay: gambar QR besar.
   - Untuk VA BRI/BCA/BNI: nomor VA besar yang bisa disalin, plus QR kecil untuk membuka halaman simulasi.
   - Hitung mundur waktu kedaluwarsa.
   - Tombol "Buka Halaman Simulasi Pembayaran" sebagai jalan alternatif bila pembeli tidak memindai QR (misalnya sedang membuka web dari laptop).
   - Label SIMULASI yang tidak bisa dilewatkan.

### 3.2 Dari QR sampai pembayaran selesai (simulasi)

1. Pembeli memindai QR dengan kamera/ Google Lens / pemindai QR.
2. Browser membuka halaman simulasi pembayaran untuk pembayaran tersebut (`/pay/sim/<payment-token>`).
3. Halaman simulasi menampilkan:
   - Label SIMULASI PEMBAYARAN.
   - Ringkasan pesanan: nomor pesanan, daftar produk ringkas, total, metode, dan nomor referensi/VA.
   - Sisa waktu pembayaran (countdown yang sama dengan halaman instruksi).
   - Tombol aksi simulasi (lihat Bagian 4).
4. Pembeli menekan **Bayar (Simulasi Berhasil)**.
5. Sistem, lewat jalur yang sama seperti callback/webhook pada gateway riil (lihat Bagian 5), mengubah status pembayaran menjadi `paid` dan status pesanan menjadi `dibayar/diproses`, mencatat event `purchase`, dan mengonfirmasi stok sesuai aturan pada PRD.
6. Pembeli diarahkan ke halaman pesanan berhasil, dengan ringkasan pembayaran simulasi dan tautan ke jejak HTTP-nya (Bagian 5).

### 3.3 Bila pembayaran gagal atau kedaluwarsa

- **Tombol Gagalkan (Simulasi Gagal):** status pembayaran menjadi `failed`, pesanan kembali ke status yang memungkinkan pembeli membuat pembayaran baru (metode boleh diganti) atau membatalkan pesanan, sesuai aturan PRD. Event `purchase` tidak tercatat.
- **Kedaluwarsa:** bila waktu habis dan belum dibayar, job/scheduler mengubah status pembayaran menjadi `expired` dan pesanan menjadi `kedaluwarsa/dibatalkan`. Stok yang sempat dicadangkan dilepas sesuai aturan PRD.
- **Pembatalan oleh pembeli:** pembeli bisa membatalkan pesanan yang masih menunggu pembayaran; status pembayaran menjadi `cancelled`, dan tidak ada lagi aksi bayar yang bisa dilakukan pada token tersebut.
- Setelah `paid`, `failed` yang sudah final, `expired`, atau `cancelled`, token QR/URL yang sama tidak bisa dipakai lagi untuk mengubah hasil. Upaya memakai ulang token harus ditolak dengan pesan yang jelas dan dicatat pada jejak pembayaran.

## 4. Halaman Simulasi Pembayaran (Interface Web)

Halaman ini adalah pusat "keren"-nya simulator. Spesifikasi minimalnya:

### 4.1 Bagian pembeli

- Judul dan label SIMULASI yang besar dan jelas.
- Identitas pembayaran: nomor pesanan, metode (dengan penanda simulasi, misalnya "QRIS (Simulasi)", "Dana (Simulasi)"), total, nomor referensi/VA bila ada, dan waktu dibuat/kedaluwarsa.
- Countdown waktu tersisa, yang berubah menjadi status kedaluwarsa bila waktu habis.
- Tombol aksi:
  - `Bayar Sekarang (Simulasi)` — mensimulasikan pembayaran berhasil.
  - `Simulasikan Gagal` — mensimulasikan pembayaran gagal.
  - `Kembali ke Pesanan` — kembali ke halaman pesanan pembeli tanpa mengubah status.
- Pesan hasil yang jelas setelah aksi: berhasil, gagal, atau kedaluwarsa, beserta status pesanan terbaru.

### 4.2 Panel "Simulated HTTP Request & Response" (wajib ada, biar bisa didemokan)

Panel ini menampilkan, untuk pembayaran yang sedang dibuka, simulasi lalu lintas HTTP seolah-olah ada payment gateway eksternal, lengkap dengan:

1. **Request pembuatan pembayaran (checkout → adapter pembayaran).**
   - Method dan path, misalnya `POST /api/payments` (internal) atau endpoint adapter yang setara.
   - Body JSON: `order_id`, `amount`, `currency: IDR`, `payment_method`, `return_url` / `callback_url` simulasi, dan `reference` — tanpa secret apa pun.
   - Response: status code simulasi, `payment_id`, `payment_token`, `qr_url` atau `va_number`, dan `expires_at`.
2. **Callback/webhook simulasi (simulator → sistem).**
   - Method dan path, misalnya `POST /webhooks/payment-simulator`.
   - Header simulasi, misalnya `X-Simulator-Signature: <dummy-signature>` dan `Content-Type: application/json`, dengan catatan eksplisit bahwa signature ini dummy dan bukan signature gateway riil.
   - Body JSON: `payment_id`, `order_id`, `status` (`paid` / `failed` / `expired`), `paid_at`, dan `reference`.
   - Response sistem: status code dan status pesanan setelah callback diproses.
3. **Jejak transisi status (timeline).**
   - Daftar kronologis: waktu, status lama → status baru, pemicu (tombol pembeli, job kedaluwarsa, atau aksi admin/developer bila ada), dan ID request yang terkait.
4. **Fitur penunjang demo.**
   - Tombol salin JSON request/response.
   - Tombol salin sebagai perintah cURL untuk request callback simulasi (dengan token dummy), supaya bisa didemokan dari terminal juga.
   - Panel ini bersifat read-only untuk pembeli; ia menampilkan apa yang terjadi, bukan tempat mengedit nominal atau status secara manual lewat URL.

Catatan implementasi untuk PRD/blueprint: apakah callback simulasi benar-benar dilakukan lewat HTTP internal ke endpoint webhook aplikasi sendiri, atau hanya dicatat sebagai log simulasi, diputuskan pada blueprint — tetapi dari sisi pengguna, tampilannya harus sama: ada request, ada response, dan ada timeline. Jangan menampilkan HTTP palsu yang tidak pernah terjadi dalam bentuk apa pun di dalam sistem.

### 4.3 Akses halaman simulasi

- Halaman dibuka lewat token pembayaran, sehingga bisa dibuka dari HP lain setelah QR dipindai (itulah gunanya QR).
- Bila pembeli yang membuka token bukan pemilik pesanan, PRD harus memutuskan apakah halaman tetap bisa dibuka (demo terbuka, karena ini simulasi) atau harus login sebagai pemilik pesanan. Keputusan itu ditulis eksplisit pada PRD; default yang disarankan untuk tugas ini adalah: token sudah cukup untuk membuka halaman simulasi, tetapi halaman tetap menampilkan label SIMULASI dan tidak menampilkan data pribadi pembeli di luar yang diperlukan untuk pembayaran tersebut.
- Token tidak boleh berisi data sensitif, dan tidak boleh bisa ditebak dari nomor pesanan saja.

## 5. State Machine dan Dampaknya ke Pesanan, Stok, dan Analitik

### 5.1 Status pembayaran

```
pending → paid
pending → failed
pending → expired
pending → cancelled
```

Tidak ada transisi lain. Status final (`paid`, `failed`, `expired`, `cancelled`) tidak bisa diubah lagi.

### 5.2 Pemetaan ke pesanan

| Status pembayaran | Status pesanan (usulan, dikunci pada PRD) | Event analitik |
| --- | --- | --- |
| `pending` | `menunggu_pembayaran` | `booking` sudah tercatat saat pesanan dibuat |
| `paid` | `dibayar/diproses` | `purchase` tercatat |
| `failed` | `menunggu_pembayaran_ulang` atau `dibatalkan`, sesuai aturan PRD | Tidak ada `purchase` |
| `expired` | `kedaluwarsa/dibatalkan` | Tidak ada `purchase` |
| `cancelled` | `dibatalkan` | Tidak ada `purchase` |

### 5.3 Stok

Aturan detail stok (kapan dicadangkan, kapan dikonfirmasi berkurang, kapan dilepas) ditetapkan pada PRD bersama aturan beli/batal pada discussion. Prinsip yang mengikat di sini: stok tidak boleh berkurang permanen untuk pembayaran yang gagal/kedaluwarsa/dibatalkan, dan tidak boleh ganda berkurang bila callback simulasi diterima dua kali (idempotency berdasarkan `payment_id`).

### 5.4 Hubungan dengan panel developer (discussion Bagian 7)

Panel developer dan payment simulator adalah dua simulator yang berbeda, tetapi harus saling konsisten:

- Saat panel developer membuat transaksi masa lampau (discussion Bagian 7.2 dan 7.3), transaksi yang berstatus selesai harus disertai objek pembayaran simulasi berstatus `paid` dengan `paid_at` yang juga backdate ke periode tersebut. Kalau tidak, funnel akan punya `booking` tanpa `purchase` dan FR-03/FR-04 akan terlihat rusak padahal hanya datanya yang tidak lengkap.
- Sebaliknya, pembayaran simulasi yang dibuat lewat alur pembeli normal tidak boleh ditandai sebagai data panel developer; ia adalah data pemakaian normal (meskipun pembayarannya sendiri simulasi).

## 6. Data yang Harus Disimpan

Minimal, untuk satu pembayaran:

- `payment_id`, `order_id`, `buyer_id`.
- `payment_method`: `qris`, `dana`, `gopay`, `va_bri`, `va_bca`, `va_bni`.
- `provider`: `simulator` (nilai ini yang nantinya diganti bila ada provider riil).
- `amount`, `currency` (`IDR`).
- `status`: `pending`, `paid`, `failed`, `expired`, `cancelled`.
- `reference_code`: nomor VA untuk VA, atau kode referensi QR untuk QR/e-wallet.
- `qr_url` / `payment_token` dan `expires_at`, `paid_at` bila ada.
- `is_simulated`: selalu `true` pada tahap ini.
- Jejak lengkap: setiap transisi status beserta request/response snapshot-nya (Bagian 4.2), supaya bisa dibuka kembali dari halaman pesanan dan halaman admin.

## 7. Arsitektur: Space untuk Payment Gateway Riil di Masa Depan

Ini adalah bagian yang diminta secara eksplisit: beri ruang pengembangan untuk integrasi gateway riil, tanpa membangun gateway riil itu sekarang.

1. **Adapter pembayaran (interface).** Seluruh alur checkout, pesanan, dan analitik hanya berbicara dengan satu interface pembayaran, misalnya:
   - `createPayment(order, method)` → mengembalikan instruksi bayar (QR/VA/token) dan waktu kedaluwarsa.
   - `getPaymentStatus(payment_id)` → mengembalikan status terbaru.
   - `handleCallback(payload, signature)` → memvalidasi dan mengubah status pembayaran/pesanan.
   - `cancelPayment(payment_id)` → membatalkan pembayaran yang masih pending.
2. **Simulator adalah satu implementasi adapter.** Implementasi saat ini bernama `SimulatorPaymentAdapter`. Ia tidak memanggil API eksternal apa pun; ia membuat QR/VA dummy, halaman simulasi, dan callback simulasi.
3. **Gateway riil adalah implementasi adapter berikutnya.** Bila suatu hari diintegrasikan (misalnya Midtrans, Xendit, atau provider lain), yang ditambahkan adalah implementasi adapter baru + konfigurasi, bukan perubahan pada checkout, pesanan, atau analitik.
4. **Katalog metode berbasis konfigurasi.** Daftar metode (QRIS, Dana, GoPay, VA BRI/BCA/BNI) disimpan sebagai konfigurasi/data, bukan hardcode tersebar di banyak tempat, supaya menambah metode baru tidak mengharuskan mengubah banyak file.
5. **Konfigurasi lewat environment.** Satu switch konfigurasi, misalnya `PAYMENT_DRIVER=simulator`, menentukan adapter yang aktif. Kredensial gateway riil (bila ada nanti) hanya lewat environment/secret manager, tidak pernah ditulis di kode, tidak pernah ditampilkan pada panel HTTP simulator.
6. **Endpoint webhook berversi.** Endpoint callback dibuat dengan path yang bisa dipakai ulang oleh adapter riil nanti (misalnya `/webhooks/payments/{provider}`), dengan lapisan verifikasi signature yang pada simulator memakai signature dummy dan pada provider riil memakai signature asli provider tersebut.
7. **Abstraksi QR.** Pembuatan QR dipisahkan dari adapter: adapter simulator mengembalikan URL halaman simulasi untuk di-QR-kan; adapter riil nanti mengembalikan payload QR dari provider. Halaman instruksi pembayaran tidak perlu tahu perbedaan itu.
8. **Batas yang jelas pada tahap ini.** Space di atas adalah persiapan arsitektur, bukan janji integrasi. PRD/blueprint tahap ini tidak boleh menulis seolah-olah gateway riil sudah terhubung, dan tidak boleh menampilkan logo/branding bank/e-wallet seolah-olah pembayaran diproses oleh mereka.

## 8. Peran dan Akses

| Aktor | Bisa melakukan |
| --- | --- |
| Pembeli | Memilih metode, melihat QR/VA dan halaman instruksi, membuka halaman simulasi lewat QR atau tombol, menekan Bayar/Gagal simulasi untuk pembayarannya sendiri, melihat riwayat pembayaran dan jejak HTTP pembayarannya sendiri. |
| Admin Toko | Melihat daftar pembayaran, detail pembayaran, dan jejak HTTP-nya (read-only). Admin tidak mengubah status pembayaran secara manual lewat halaman ini; perubahan status hanya lewat aksi simulasi pembeli, job kedaluwarsa, atau aksi yang ditetapkan eksplisit pada PRD. |
| Developer (panel data) | Tidak mengubah pembayaran individual lewat payment simulator. Tugas developer tetap seperti pada discussion: membuat akun dan transaksi massal, yang bila berstatus selesai harus disertai pembayaran simulasi `paid` (Bagian 5.4). |

## 9. Pecahan Fitur untuk PRD (Mengikuti Arahan Discussion Bagian 12)

Satu konsep "payment gateway simulator" harus dipecah pada PRD menjadi setidaknya fitur-fitur berikut, masing-masing dengan kriteria terima sendiri:

1. Pemilihan metode pembayaran pada checkout (6 metode pada Bagian 2).
2. Pembuatan objek pembayaran dan penentuan waktu kedaluwarsa.
3. Pembuatan dan penampilan QR (URL halaman simulasi) untuk QRIS, Dana, dan GoPay.
4. Pembuatan dan penampilan nomor VA dummy untuk BRI, BCA, dan BNI, beserta QR pendampingnya.
5. Halaman instruksi pembayaran dengan countdown.
6. Halaman simulasi pembayaran (`/pay/sim/<token>`) dengan aksi Bayar/Gagal.
7. Job/scheduler kedaluwarsa pembayaran.
8. Callback/webhook simulasi beserta idempotency dan pemetaan status ke pesanan.
9. Panel Simulated HTTP Request & Response beserta timeline status, tombol salin JSON, dan salin sebagai cURL.
10. Halaman pesanan berhasil/gagal/kedaluwarsa setelah aksi simulasi.
11. Riwayat pembayaran pembeli dan daftar pembayaran admin (read-only).
12. Interface adapter pembayaran dan switch konfigurasi `PAYMENT_DRIVER=simulator` sebagai persiapan gateway riil (Bagian 7).
13. Penanda SIMULASI pada semua tampilan pembayaran, QR, struk, dan jejak.

Daftar di atas adalah pecahan minimal. Bila satu butir ternyata mengandung beberapa perilaku yang bisa diuji secara terpisah (misalnya validasi token, penanganan token kedaluwarsa, dan penanganan token dipakai ulang), PRD boleh — dan harus — memecahnya lebih lanjut, selama tiap pecahan tetap tertelusur ke dokumen ini.

## 10. Yang Bukan Scope

- Koneksi API riil ke QRIS, Dana, GoPay, BRI, BCA, atau BNI.
- Payload QRIS EMVCo resmi dan QR resmi e-wallet/bank.
- Perpindahan uang riil, settlement, dan refund ke rekening/e-wallet riil. (Refund simulasi — mengubah status menjadi refund-simulasi tanpa uang berpindah — hanya boleh ditambahkan bila PRD menetapkannya eksplisit sebagai fitur simulasi terpisah.)
- Penyimpanan atau penampilan kredensial, API key, atau signature gateway riil dalam bentuk apa pun.

## 11. Keputusan Terbuka untuk PRD/Blueprint

1. Durasi kedaluwarsa pembayaran (usulan awal: 30 menit, bisa diubah pada PRD).
2. Format persis nomor VA dummy per bank dan format kode referensi QR.
3. Apakah halaman simulasi boleh dibuka tanpa login (default yang disarankan ada pada Bagian 4.3) atau wajib login pemilik pesanan.
4. Status pesanan persis setelah pembayaran gagal (bayar ulang atau batal) dan aturan stok persisnya.
5. Library QR yang dipakai dan ukuran/format gambar QR.
6. Apakah refund simulasi dimasukkan pada tahap ini atau ditunda.
7. Provider gateway riil mana yang menjadi target integrasi di masa depan (tidak perlu diputuskan sekarang, tetapi adapter pada Bagian 7 harus cukup umum untuk menampungnya).
