// QR pembayaran simulasi (M3b, script QR Ren — package npm `qrcode`).
// Halaman instruksi membawa URL sim di [data-qr-url]; script ini
// menggambar QR-nya (PNG data URL, scale 8 seperti script bot WA Ren)
// ke <img class="qr-image"> di dalam wadah yang sama. Bila JS mati atau
// gagal, fallback teks server (TextQrRenderer) tetap tampil.
import { toDataURL } from 'qrcode';

document.addEventListener('DOMContentLoaded', () => {
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
