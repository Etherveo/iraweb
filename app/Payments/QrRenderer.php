<?php

namespace App\Payments;

// M3b PAY-58, blueprint §8.3. Renders a QR payload as HTML. The
// instruction page only calls render(QrPayload); it never knows what
// is inside the payload or how it is drawn.
//
// QR digambar di sisi klien (script QR Ren, package npm `qrcode`):
// halaman instruksi membawa URL sim di [data-qr-url] dan
// resources/js/app.js menggambarnya ke <img class="qr-image">.
// Implementasi server di sini (TextQrRenderer) hanya fallback bila
// JS mati/gagal: URL sebagai teks berlabel jelas bukan QR. Render
// dipakai di dua tempat:
//  - halaman instruksi (resources/views/payments/instruction-show.blade.php):
//    QR besar untuk QRIS/Dana/GoPay, QR kecil pendamping untuk VA.
//  - (nanti, M3c) panel jejak boleh menampilkan ulang QR dari
//    payment yang sama lewat QrPayload yang sama.
interface QrRenderer
{
    // Returns the HTML that displays the payload. Must never throw
    // when a QR implementation is missing: fall back to labelled text.
    public function render(QrPayload $payload): string;
}
