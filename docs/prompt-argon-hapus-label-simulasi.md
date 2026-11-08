# Prompt — Hapus label SIMULASI dari UI pembeli & admin (Argon)

## Tugas
Hapus SEMUA label simulasi dari UI pembeli dan admin, supaya dari UI saja mustahil membedakan data simulasi dan data normal — UI harus terlihat seperti toko biasa. Pembedanya tetap ada, tapi hanya di metadata (kolom `origin`, `is_simulated`, `simulation_run_id` di database) dan di panel developer. Pindahkan trigger panel teknis tersembunyi dari badge SIMULASI ke teks "Status pesanan". Perbarui PRD/blueprint + dokumen panel dengan catatan keputusan. Perbarui tes yang meng-assert label yang dihapus.

## Kenapa
Keputusan Ren 2026-11-08: label simulasi membuat UI terlihat "kotor" saat dilihat dosen. Sejak awal label itu cukup hadir sebagai metadata; field itulah yang dipakai untuk mengetahui mana simulasi dan mana bukan, sesuai keperluan — sementara di sisi UI: 0 label simulasi. Kalau dosen ingin tahu sesuatu itu simulasi, ia melihat metadata (field di DB, script terminal, atau UI khusus di sisi developer). Admin/pembeli melihat semuanya normal.

## Prasyarat — JANGAN mulai sebelum ini
- Tugas ini jalan SETELAH polish panel developer (commit `944dc2e`, branch `feature/polish-panel-simulator`) merge ke `feature/m4-simulator`. Mulai hanya kalau Ren bilang polish sudah merge.
- Branch kerja BARU `feature/hapus-label-simulasi` dari `feature/m4-simulator` terbaru (bukan dari `main`, bukan dari branch polish).

## Yang diubah

### 1. Label SIMULASI di UI pembeli — hapus semuanya
Cari dengan grep `simulasi` (case-insensitive) di `resources/views/shop/`, `resources/views/payments/`, `resources/views/admin/`, `resources/views/layouts/` (bagian pembeli/admin saja), `resources/views/auth/`, dan `config/payment.php` — saat ini ±109 kemunculan di 18 file. Setiap kemunculan yang terlihat di UI harus hilang atau jadi netral. Yang sudah diketahui:
- `config/payment.php`: 6 label metode `QRIS (Simulasi)`, `Dana (Simulasi)`, `GoPay (Simulasi)`, `VA BRI/BCA/BNI (Simulasi)` → nama polos (`QRIS`, `Dana`, `GoPay`, `VA BRI`, `VA BCA`, `VA BNI`). Komentar di baris 4 yang menjelaskan label juga disesuaikan.
- `payments/show.blade.php` + `admin/pembayaran-detail.blade.php`: judul `Detail Pembayaran — SIMULASI` → `Detail Pembayaran`; subjudul `Ringkasan pembayaran simulasi dan status pesanan terkait.` → `Ringkasan pembayaran dan status pesanan terkait.`; banner `.admin-sim-banner` (⚠ SIMULASI — bukan pembayaran riil) DIHAPUS dari kedua halaman.
- `payments/index.blade.php`, `instruction-show.blade.php`, `sim-show.blade.php`: banner `.admin-sim-banner` dihapus; teks `Nomor VA SIMULASI — bukan nomor rekening.` → netral (mis. `Nomor VA — bukan nomor rekening.` — jangan tambah peringatan baru).
- `payments/hasil-show.blade.php`: judul `⚠ Pembayaran Berhasil — SIMULASI` → `Pembayaran Berhasil`; semua kata SIMULASI di halaman ini dihapus.
- `payments/struk-show.blade.php`: banner + disclaimer `STRUK SIMULASI — bukan bukti pembayaran sah.` dihapus — struk dibaca sebagai struk biasa. Jangan tambah peringatan baru.
- `shop/checkout-show.blade.php`, `shop/pesanan-detail.blade.php`, `shop/pesanan-index.blade.php`: hapus label SIMULASI (nama metode jadi polos, badge/lencana simulasi dihapus).
- `admin/pembayaran-index.blade.php`, `admin/pesanan-index.blade.php`, `admin/pesanan-detail.blade.php`, `admin/akun-pembeli-index.blade.php`, `admin/akun-pembeli-detail.blade.php`, `admin/produk-riwayat.blade.php`: hapus lencana SIMULASI (mis. `@if ($x->origin === 'developer_simulation')` yang me-render badge) dan kontrol filter asal data (`origin`) — pembeda asal data tetap ada di DB, tidak lagi di UI. Laporkan tiap perubahan di laporan akhir.
- `resources/views/layouts/app.blade.php`: hapus label SIMULASI HANYA di bagian yang terlihat pembeli/admin. Bagian shell developer (sidebar developer + SimBanner permanen) JANGAN disentuh.
- Partial panel teknis (`payments/_panel.blade.php`, `payments/_trace.blade.php`, `payments/_countdown.blade.php`): isinya TETAP — panel itu tersembunyi dari DOM dan hanya muncul lewat trigger khusus, jadi bukan "UI yang terlihat". Jangan ubah perilakunya.

### 2. Trigger panel teknis tersembunyi — PINDAH (bukan dihapus)
Trigger lama adalah ketuk 5x pada `.admin-sim-banner` — banner itu dihapus di langkah 1, jadi trigger harus pindah:
- Target baru: teks **`Status pesanan`** (`<dt class="admin-kv-row-label">Status pesanan</dt>`) di dalam section **Ringkasan pembayaran** (`<h2 class="admin-section-title">Ringkasan pembayaran</h2>`), pada DUA halaman: `admin/pembayaran-detail.blade.php` (~baris 68) dan `payments/show.blade.php` (~baris 63).
- Caranya: beri elemen teks itu atribut trigger yang sama seperti sebelumnya (`data-trace-trigger="admin-trace-popup"` / `"buyer-trace-popup"`), dan JS yang sudah ada (ketuk 5x dalam 3 detik → popup → panel yang sama) dipakai ulang tanpa mengubah perilakunya: tanpa tombol buka-ulang yang persisten, tanpa flag sessionStorage.
- `payments/sim-show.blade.php` TIDAK punya teks "Status pesanan" — pindahkan trigger-nya ke teks yang setara dan tidak mencolok di halaman itu (mis. teks judul/ringkasan), tanpa label SIMULASI, dan sebutkan pilihanmu di laporan akhir.
- Perilaku popup + panel TIDAK berubah; yang berubah hanya elemen yang diketuk.

### 3. Panel developer — JANGAN disentuh
`resources/views/developer/*`, shell developer + SimBanner permanen di layout, dan aksen oranye SIMULASI di panel developer TETAP — itu "UI khusus" tempat label simulasi boleh ada.

## Yang TIDAK boleh disentuh
- JANGAN ubah controller, FormRequest, route, service, model, migrasi, config (selain label metode di `config/payment.php` di atas), atau logika apa pun. Ini tugas tampilan + dokumen + tes.
- JANGAN ubah mekanisme reset: konfirmasi ketik-frasa `HAPUS RUN` dan `HAPUS SEMUA SIMULASI` tetap seperti itu.
- JANGAN ubah halaman pratinjau jadi menulis data — pratinjau tetap tanpa tulis.
- JANGAN ubah teks/angka yang di-assert tes KECUALI yang memang label yang dihapus (lihat bagian Tes) — dan jangan hapus/mengosongkan tes.

## Dokumen — PRD/blueprint ikut diubah (perintah Ren)
PRD dan blueprint selama ini mewajibkan label itu, jadi keputusan ini harus dicatat sebagai perubahan — bukan diam-diam. Perbarui bagian-bagian ini saja (cari berdasarkan ID persyaratannya), dengan catatan yang sama di tiap bagian:
> **Keputusan 2026-11-08 (Ren):** label SIMULASI dihapus dari UI pembeli dan admin — UI tidak lagi membedakan data simulasi dan data normal. Pembedanya hanya metadata (`origin` / `is_simulated` / `simulation_run_id` di database) dan panel developer. Persyaratan lama di bawah ini diganti dengan keputusan ini.
- PRD SIM-01 (§10.6) + blueprint §10.6 SIM-01: marker permanen "SIMULATOR — data eksperimental" kini HANYA di panel developer, bukan lagi di UI pembeli/admin.
- PRD PAY-54 + blueprint §9: label SIMULASI di semua halaman pembayaran — dihapus dari UI pembeli/admin.
- PRD ACC-11 + blueprint §8: lencana SIMULASI di daftar akun pembeli admin — dihapus.
- PRD §9.1 + blueprint §9 (label metode pembayaran): akhiran "(Simulasi)" dihapus dari label UI.
- PRD ORD-10/11/13 + PAY-51 (filter asal data di daftar admin): kontrol filter asal data dihapus dari UI.
- `docs/prompt-ui-panel-teknis-tersembunyi.md`: perbarui deskripsi trigger (dari badge SIMULASI → teks "Status pesanan" di section Ringkasan pembayaran; sim-show: teks setara yang kamu pilih).
- JANGAN ubah bagian PRD/blueprint lain. Tunjukkan diff dokumen di laporan akhir.

## Tes — perbarui yang meng-assert label yang dihapus
- Cari tes yang meng-assert label SIMULASI / "(Simulasi)" / badge simulasi (mis. PAY-54, ACC-11, ORD-01 enam metode, judul hasil bayar, disclaimer struk, banner detail pembayaran admin, selector trigger lama) — ubah assertion-nya ke UI baru yang netral (mis. judul `Detail Pembayaran` tanpa akhiran, nama metode polos). Tes yang meng-assert panel tersembunyi di DOM dan perilaku trigger harus tetap hijau — perbarui selector-nya ke elemen trigger baru.
- Jangan hapus atau mengosongkan tes. Tunjukkan diff tes di laporan akhir.

## Verifikasi
- `npm run build` harus sukses.
- `./vendor/bin/pest` harus tetap 235 tes hijau — penghapusan label tidak boleh memerahkan tes.
- Cek manual di browser (login sebagai pembeli, lalu sebagai admin): buka halaman katalog, checkout, pembayaran (instruksi, detail, hasil, struk, riwayat), pesanan, dan daftar admin — di UI yang TERLIHAT tidak ada lagi kata SIMULASI/simulasi. (Teks di dalam panel teknis tersembunyi, yang hanya muncul lewat trigger, bukan pelanggaran.)
- Cek manual trigger baru: ketuk teks "Status pesanan" 5x dalam 3 detik di Detail Pembayaran (admin + pembeli) → popup muncul → panel teknis tampil seperti sebelumnya.
- Panel developer tidak berubah (banner + aksen oranye masih ada); frasa reset `HAPUS RUN` / `HAPUS SEMUA SIMULASI` tetap.
- Laporkan hasil build + Pest + daftar halaman yang dicek di laporan akhir.

## Git (kamu yang kerjakan)
- Branch baru `feature/hapus-label-simulasi` dari `feature/m4-simulator` terbaru — HANYA setelah Ren bilang polish sudah merge ke `feature/m4-simulator`.
- Commit + push ke branch, lalu BERHENTI dan tunggu kata "aman" dari Ren.
- Setelah Ren bilang aman: `git checkout feature/m4-simulator` → `git pull` → `git merge feature/hapus-label-simulasi` (harus fast-forward) → `git push origin feature/m4-simulator` → `git push origin --delete feature/hapus-label-simulasi` → `git branch -d feature/hapus-label-simulasi` → laporkan commit `feature/m4-simulator` terakhir. Ren sendiri yang merge `feature/m4-simulator` ke `main` — BUKAN kamu.
- JANGAN merge sebelum Ren bilang aman.
