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

// Eye toggle kata sandi (UI polish Tahap 1, halaman /masuk). Tombol membawa
// [data-password-toggle="<id input>"] dan tersembunyi lewat atribut hidden
// sampai script ini berjalan, jadi tanpa JS formulir tetap normal. Tidak
// menyentuh nilai atau pengiriman field: hanya mengganti type password/text.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
        const input = document.getElementById(btn.getAttribute('data-password-toggle'));
        if (!input) return;
        const iconShow = btn.querySelector('[data-icon-show]');
        const iconHide = btn.querySelector('[data-icon-hide]');
        btn.hidden = false;
        btn.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            btn.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            btn.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            if (iconShow) iconShow.hidden = reveal;
            if (iconHide) iconHide.hidden = !reveal;
        });
    });
});

// Popup tamu di detail produk (UI polish Tahap 2). Tombol dengan
// [data-guest-popup-open="<id popup>"] membuka popup; [data-guest-popup-close]
// (tombol ×, backdrop) dan Esc menutupnya. Fokus pindah ke panel saat dibuka,
// kembali ke tombol pemicu saat ditutup, dan Tab dijebak di dalam panel.
// Tanpa JS, popup tetap tersembunyi (atribut hidden) dan tombol tidak
// melakukan apa-apa; POST /keranjang/tambah tetap dikunci auth di server.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-guest-popup-open]').forEach((opener) => {
        const popup = document.getElementById(opener.getAttribute('data-guest-popup-open'));
        if (!popup) return;
        const panel = popup.querySelector('[role="dialog"]');
        const focusables = () => panel.querySelectorAll('a[href], button:not([disabled])');

        const close = () => {
            popup.hidden = true;
            document.removeEventListener('keydown', onKey);
            opener.focus();
        };
        const onKey = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
                return;
            }
            if (e.key === 'Tab') {
                const items = focusables();
                if (items.length === 0) return;
                const first = items[0];
                const last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        };

        opener.addEventListener('click', () => {
            popup.hidden = false;
            document.addEventListener('keydown', onKey);
            panel.focus();
        });
        popup.querySelectorAll('[data-guest-popup-close]').forEach((el) => el.addEventListener('click', close));
    });

    // Stepper jumlah: tombol ± hanya muncul bila JS aktif; input angka tetap
    // sumber nilai yang dikirim, dibatasi min/max milik input itu sendiri.
    document.querySelectorAll('[data-stepper]').forEach((box) => {
        const input = box.querySelector('input[type="number"]');
        const minus = box.querySelector('[data-stepper-minus]');
        const plus = box.querySelector('[data-stepper-plus]');
        if (!input || !minus || !plus) return;
        const step = (delta) => {
            const min = Number(input.min) || 1;
            const max = Number(input.max) || 99;
            const next = Math.min(max, Math.max(min, (Number(input.value) || min) + delta));
            input.value = String(next);
        };
        minus.hidden = false;
        plus.hidden = false;
        minus.addEventListener('click', () => step(-1));
        plus.addEventListener('click', () => step(1));
    });
});
