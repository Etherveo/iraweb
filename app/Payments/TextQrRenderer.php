<?php

namespace App\Payments;

use Illuminate\Support\Facades\Log;

// M3b PAY-07/PAY-09 (fallback bila JS mati/gagal). QR utama digambar
// di sisi klien oleh resources/js/app.js (package npm `qrcode`,
// toDataURL, scale 8 — script QR Ren) dari [data-qr-url] di halaman
// instruksi. Fallback ini menampilkan URL pembayaran sebagai teks
// dengan label yang jelas: ini BUKAN kode QR dan tidak dapat dipindai.
// Halaman tetap berfungsi (tautan "Buka Halaman Simulasi Pembayaran"
// tetap bisa diklik) dan tidak pernah fatal.
class TextQrRenderer implements QrRenderer
{
    public function render(QrPayload $payload): string
    {
        Log::warning('QrRenderer: fallback teks (QR digambar JS di app.js; tampil bila JS mati/gagal).');

        $url = e($payload->value);

        return '<div class="qr-fallback" role="img" aria-label="Tautan pembayaran simulasi (bukan kode QR)">'
            .'<p><strong>QR belum tersedia — menunggu script QR.</strong></p>'
            .'<p>Ini bukan kode QR dan tidak dapat dipindai. Buka tautan pembayaran (simulasi) di bawah ini:</p>'
            .'<p><code>'.$url.'</code></p>'
            .'</div>';
    }
}
