// QR pembayaran simulasi (M3b, script QR Ren — package npm `qrcode`).
// Halaman instruksi membawa URL sim di [data-qr-url]; script ini
// menggambar QR-nya (PNG data URL, scale 8 seperti script bot WA Ren)
// ke <img class="qr-image"> di dalam wadah yang sama. Bila JS mati atau
// gagal, fallback teks server (TextQrRenderer) tetap tampil.
import { toDataURL } from 'qrcode';

// Salin dari panel jejak HTTP (M3c PAY-40/41). Tombol membawa
// [data-copy-target="<id elemen <pre>"] dan [data-copy-label="<label
// awal>"]; teks yang disalin adalah isi persis elemen target
// (textContent), sama dengan yang tampil di layar. navigator.clipboard
// butuh konteks aman (https/localhost), jadi di host LAN (http)
// dipakai fallback textarea + execCommand agar salin tetap jalan
// dari HP. Tanpa bundle baru tombol tidak melakukan apa-apa:
// jalankan `npm run build` setelah pull.
//
// Perbaikan fallback HP (M3c, 2026-11-05): versi lama hanya memanggil
// ta.select() — di browser HP (terutama iOS Safari) itu saja tidak
// cukup: textarea harus difokuskan DAN seleksinya dibuat eksplisit
// dengan setSelectionRange, kalau tidak execCommand('copy') gagal
// diam-diam atau menyalin kosong. readOnly mencegah keyboard muncul
// saat focus di iOS. Inilah kenapa "Salin cURL" gagal dari HP
// sementara "Salin JSON" (yang kebetulan lolos seleksi) jalan.
function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    return new Promise((resolve, reject) => {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.readOnly = true;
        ta.style.position = 'fixed';
        ta.style.top = '0';
        ta.style.left = '0';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        ta.setSelectionRange(0, ta.value.length);
        try {
            const ok = document.execCommand('copy');
            document.body.removeChild(ta);
            if (ok) resolve();
            else reject(new Error('copy failed'));
        } catch (e) {
            document.body.removeChild(ta);
            reject(e);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-copy-target]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.getAttribute('data-copy-target'));
            if (!target) return;
            const label = btn.getAttribute('data-copy-label') || btn.textContent;
            // textContent, bukan innerText: untuk isi <pre> keduanya
            // sama, tapi textContent tidak tergantung layout/visibilitas
            // (innerText mengembalikan "" bila elemennya tersembunyi —
            // relevan saat panel nanti masuk ke popup tersembunyi).
            copyText(target.textContent).then(() => {
                btn.textContent = 'Tersalin';
                setTimeout(() => { btn.textContent = label; }, 2000);
            }).catch(() => {
                btn.textContent = 'Gagal menyalin';
                setTimeout(() => { btn.textContent = label; }, 2000);
            });
        });
    });

    document.querySelectorAll('[data-qr-url]').forEach((slot) => {
        const url = slot.getAttribute('data-qr-url');
        const img = slot.querySelector('img.qr-image');
        if (!url || !img) return;

        toDataURL(url.slice(0, 2048), { scale: 8 })
            .then((dataUrl) => {
                img.src = dataUrl;
                img.hidden = false;
                const fallback = slot.querySelector('.qr-fallback');
                if (fallback) fallback.hidden = true;
            })
            .catch(() => {
                // Biarkan fallback teks tampil; halaman tetap berfungsi
                // lewat tautan "Buka Halaman Simulasi Pembayaran".
            });
    });
});
