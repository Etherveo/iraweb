# Prompt — Panel teknis pembayaran jadi tersembunyi (UI polish, untuk Claude)

> Prompt ini dipakai NANTI saat meminta Claude (via Antigravity) memoles UI.
> Jangan kerjakan tugas di bawah ini sebelum Ren memintanya secara eksplisit.
> Konteks: repo `iraweb`, Laravel + Blade + Tailwind + Alpine, milestone M3c sudah
> merge. Panel teknis pembayaran (PAY-37..42) saat ini tampil TERBUKA di halaman
> detail pembayaran pembeli, halaman simulasi, dan detail pembayaran admin.

## Konteks

- Panel teknis = partial `resources/views/payments/_panel.blade.php` (dipakai tiga
  halaman): request `create_payment` (method/path, header, body JSON, response),
  callback webhook (header `X-Simulator-Signature` dummy, body, response, status
  pesanan setelah callback), tombol salin JSON, salin sebagai cURL, dan timeline
  transisi status. Data panel berasal dari `payment_http_traces` dan
  `payment_status_transitions` yang sudah tersimpan — bukan templat.
- Tiga halaman yang menampilkan panel:
  - `resources/views/payments/sim-show.blade.php` (halaman simulasi, publik via token)
  - `resources/views/payments/show.blade.php` (detail pembayaran pembeli)
  - `resources/views/admin/pembayaran-detail.blade.php` (detail pembayaran admin)
- Logika salin ada di `resources/js/app.js` (helper `[data-copy-target]`, dengan
  fallback textarea+execCommand agar tetap jalan dari HP via http LAN).
- Token visual: `docs/UI.md` (lavender `#6655F5`, Manrope+Inter, radius 20–24,
  neumorphic). Alpine sudah tersedia. Jangan tambah dependensi baru.

## Masalah

Panel teknis itu benar dan berguna — tapi penontonnya bukan pembeli awam, melainkan
dosen/penguji saat demo, developer, dan pembeli-sebagai-tester. Pembeli biasa tidak
butuh (dan tidak seharusnya) melihat JSON mentah, header, signature, dan perintah
cURL. Panel harus TETAP ADA dan TETAP BERFUNGSI, tapi tidak terlihat oleh orang
yang tidak mencarinya.

## Tugas

Sembunyikan panel teknis di balik kombinasi rahasia + popup dua tahap:

1. **Keadaan normal:** TIDAK ADA perubahan yang terlihat. Tidak ada tombol baru,
   tidak ada petunjuk, tidak ada teks "detail teknis" di mana pun. Halaman persis
   seperti sekarang, hanya saja panel teknisnya tidak tampil.
2. **Kombinasi rahasia — ketuk/klik 5 kali pada lencana "⚠ SIMULASI"** yang ada di
   halaman tersebut, dalam waktu 3 detik. Harus jalan dengan sentuhan (HP) maupun
   mouse. Kombinasi ini TIDAK BOLEH tertulis di UI mana pun — hanya ada di prompt
   ini dan di dokumentasi. (Tambahkan hook diam-diam, mis. atribut data, pada
   elemen lencana di ketiga halaman di atas; logikanya di `app.js`.)
3. **Setelah kombinasi berhasil:** muncul popup kecil di tengah layar yang isinya
   hanya tombol **"Lihat detail teknis"** dan tombol tutup (×). Tidak ada yang lain.
4. **Saat tombol "Lihat detail teknis" ditekan:** isi popup yang sama BERGANTI
   menjadi panel teknis lengkap (isi partial `_panel.blade.php` yang sama persis:
   request/response pembuatan pembayaran, callback, salin JSON, salin cURL,
   timeline). Popup bisa di-scroll, tombol salin tetap berfungsi, tombol tutup
   dan tombol Esc menutup popup, klik backdrop juga menutup.
5. **Satu sesi browser:** sekali kombinasi berhasil, tombol "Lihat detail teknis"
   tetap tersedia di ketiga halaman panel sampai sesi browser berakhir
   (mis. flag di `sessionStorage`). Tutup tab/browser = kembali tersembunyi.
   Pengguna normal yang tidak tahu kombinasinya tidak akan pernah melihatnya.

## Batasan keras (jangan dilanggar)

- JANGAN ubah backend apa pun: controller, route, model, service, migrasi,
  `PaymentTracePanel`, logika salin, atau isi/data panel. Hanya Blade + JS + CSS.
- JANGAN ubah tes yang ada dan JANGAN ubah perilaku yang dites: panel harus TETAP
  ADA di DOM (tersembunyi, bukan dihapus) supaya tes Pest yang ada
  (PAY-37..42, PAY-50, PAY-52, PAY-54 — total 153 tes) tetap hijau TANPA diubah.
  Kalau satu tes gagal karena perubahanmu, perbaiki implementasimu, bukan tesnya.
- JANGAN ubah label SIMULASI (PAY-54) dan JANGAN hapus satu pun label/teks
  SIMULASI yang sudah ada. Lencana "⚠ SIMULASI" tetap tampil seperti biasa —
  yang berubah hanya: ia jadi pemicu rahasia.
- JANGAN tampilkan secret/kunci apa pun. Signature yang tampil tetap HMAC hasil
  hitungan server seperti sekarang, bukan secret-nya.
- Mobile-first: popup harus nyaman dipakai dari HP (lebar penuh dengan margin,
  bisa di-scroll, tombol cukup besar). Salin dari HP via http LAN harus tetap
  jalan (fallback yang sudah ada jangan dirusak).
- Aksesibilitas minimal: Esc menutup, fokus masuk ke popup saat dibuka, tombol
  tutup berlabel jelas, popup berlabel `aria`.

## Verifikasi (lakukan semua sebelum selesai)

- `npm run build` sukses.
- `vendor/bin/pest` tetap 153 tes hijau, tanpa mengubah satu pun file tes.
- Cek manual (laptop + HP via IP LAN): tanpa kombinasi, halaman sama persis
  seperti sebelumnya dan panel tidak terlihat; ketuk lencana 5 kali → popup
  kecil berisi "Lihat detail teknis" muncul; tekan tombolnya → panel lengkap
  tampil di dalam popup; tombol salin JSON dan cURL berfungsi; Esc/backdrop/×
  menutup popup; buka tab baru → panel kembali tersembunyi; tutup tab lalu buka
  lagi → kembali tersembunyi.
- Laporkan: file yang diubah, cara kerja kombinasinya, dan apa pun yang tidak
  sempat diverifikasi.
