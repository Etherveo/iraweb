{{--
    Pemilih akun simulasi (SIM-09, D8). Pencarian diketik menyaring
    daftar di sisi klien — sengaja tanpa paginasi server: paginasi
    akan membuang centang di halaman lain, sementara daftar ini
    terbatas (maks 100 akun per request, umumnya puluhan).
--}}
<div class="developer-field" data-sim-account-picker>
    <label class="developer-label" for="sim-account-search">Akun simulasi</label>
    <input class="input" type="search" id="sim-account-search" data-sim-account-filter placeholder="Cari nama atau email…" autocomplete="off">
    <p class="developer-hint"><span data-sim-account-count>{{ $accounts->count() }}</span> akun simulasi tersedia. Pilih minimal 1, maksimal 100. Akun nonaktif dilewati saat eksekusi (SIM-19) dan dilaporkan di pratinjau.</p>
    @if ($accounts->isEmpty())
        <p class="developer-info-box">Belum ada akun simulasi — <a class="developer-cell-link" href="{{ route('developer.akun.create') }}">buat akun dulu</a>.</p>
    @else
        <div class="developer-account-list" data-sim-account-list>
            @foreach ($accounts as $account)
                <label class="developer-check-label" data-sim-account-row data-search="{{ mb_strtolower($account->name.' '.$account->email) }}">
                    <input type="checkbox" name="user_ids[]" value="{{ $account->id }}" {{ in_array($account->id, (array) old('user_ids', [])) ? 'checked' : '' }}>
                    <span>
                        <strong>{{ $account->name }}</strong><br>
                        <small>{{ $account->email }}</small>
                    </span>
                    @unless ($account->is_active)
                        <span class="badge-simulasi">Nonaktif</span>
                    @endunless
                </label>
            @endforeach
            <p class="developer-hint" data-sim-account-empty hidden>Tidak ada akun yang cocok dengan pencarian.</p>
        </div>
    @endif
    @error('user_ids')
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
