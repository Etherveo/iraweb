{{-- Tombol retry/cancel untuk satu run (keputusan Ren 2026-11-11).
     Dipakai di daftar Jejak & Audit dan di detail run: "Coba lagi" +
     "Batalkan" untuk run 'Berjalan'; "Coba lagi" saja untuk 'Gagal';
     tidak ada tombol untuk 'Berhasil'/'Direset'. data-confirm-once
     (app.js) mencegah klik ganda — kunci idempotensi baru di server
     tetap menjadi pengaman terakhir. --}}
@if ($run->status === 'running' || $run->status === 'gagal')
    <div class="developer-form-actions">
        <form method="POST" action="{{ route('developer.run.retry', $run) }}" data-confirm-once>
            @csrf
            <button class="btn-secondary" type="submit">Coba lagi</button>
        </form>
        @if ($run->status === 'running')
            <form method="POST" action="{{ route('developer.run.cancel', $run) }}" data-confirm-once>
                @csrf
                <button class="btn-danger" type="submit">Batalkan</button>
            </form>
        @endif
    </div>
@endif
