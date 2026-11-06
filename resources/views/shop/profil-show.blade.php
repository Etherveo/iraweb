@extends('layouts.app')

@section('content')
    <div class="admin-page">
        <header class="admin-head">
            <div>
                <p class="admin-eyebrow">Toko Ira &bull; Akun Pembeli</p>
                <h1 class="admin-title">Profil Saya</h1>
                <p class="admin-subtitle">Kelola informasi pribadi dan alamat pengiriman Anda.</p>
            </div>
            <div class="admin-head-actions">
                <a class="btn-primary" href="{{ route('profil.edit') }}">Ubah Profil &amp; Alamat</a>
            </div>
        </header>

        <div class="buyer-split-layout">
            <div class="buyer-main-stack">
                <section class="card admin-panel" aria-labelledby="profil-data-heading">
                    <h2 class="admin-section-title" id="profil-data-heading">Data Profil</h2>
                    <dl class="admin-kv-list">
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Nama lengkap</dt>
                            <dd class="admin-kv-row-val"><strong>{{ $user->name }}</strong></dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Email</dt>
                            <dd class="admin-kv-row-val">{{ $user->email }}</dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Username</dt>
                            <dd class="admin-kv-row-val">{{ $user->username ?? 'Belum diisi' }}</dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Nomor telepon</dt>
                            <dd class="admin-kv-row-val">{{ $user->phone ?? 'Belum diisi' }}</dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Jenis kelamin</dt>
                            <dd class="admin-kv-row-val">{{ $user->gender ? ucfirst($user->gender) : 'Belum diisi' }}</dd>
                        </div>
                        <div class="admin-kv-row">
                            <dt class="admin-kv-row-label">Tanggal lahir</dt>
                            <dd class="admin-kv-row-val">{{ $user->birthdate ? $user->birthdate->format('d M Y') : 'Belum diisi' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="card admin-panel" aria-labelledby="profil-alamat-heading">
                    <div class="buyer-payment-head">
                        <h2 class="admin-section-title" id="profil-alamat-heading">Alamat Pengiriman</h2>
                        @if ($address)
                            <span class="status-chip status-chip-info">
                                <span>{{ $address->address_type }}</span>
                            </span>
                        @endif
                    </div>

                    @if ($address)
                        <dl class="admin-kv-list">
                            <div class="admin-kv-row">
                                <dt class="admin-kv-row-label">Nama penerima</dt>
                                <dd class="admin-kv-row-val"><strong>{{ $address->recipient_name }}</strong></dd>
                            </div>
                            <div class="admin-kv-row">
                                <dt class="admin-kv-row-label">Telepon penerima</dt>
                                <dd class="admin-kv-row-val">{{ $address->recipient_phone ?? 'Belum diisi' }}</dd>
                            </div>
                            <div class="admin-kv-row">
                                <dt class="admin-kv-row-label">Alamat jalan</dt>
                                <dd class="admin-kv-row-val">{{ $address->street_address }}</dd>
                            </div>
                            <div class="admin-kv-row">
                                <dt class="admin-kv-row-label">Kelurahan / Desa</dt>
                                <dd class="admin-kv-row-val">{{ $address->village }}</dd>
                            </div>
                            <div class="admin-kv-row">
                                <dt class="admin-kv-row-label">Kecamatan</dt>
                                <dd class="admin-kv-row-val">{{ $address->district }}</dd>
                            </div>
                            <div class="admin-kv-row">
                                <dt class="admin-kv-row-label">Kota / Kabupaten</dt>
                                <dd class="admin-kv-row-val">{{ $address->city }}</dd>
                            </div>
                            <div class="admin-kv-row">
                                <dt class="admin-kv-row-label">Provinsi</dt>
                                <dd class="admin-kv-row-val">{{ $address->province }}</dd>
                            </div>
                            <div class="admin-kv-row">
                                <dt class="admin-kv-row-label">Kode pos</dt>
                                <dd class="admin-kv-row-val"><code class="admin-code">{{ $address->postal_code }}</code></dd>
                            </div>
                        </dl>
                    @else
                        <div class="admin-empty">
                            <p class="admin-empty-title">Belum ada alamat tersimpan.</p>
                            <p class="admin-hint">Tambahkan alamat utama Anda untuk melengkapi profil pembeli.</p>
                            <div class="admin-form-actions">
                                <a class="btn-secondary" href="{{ route('profil.edit') }}#form-alamat">Tambah Alamat</a>
                            </div>
                        </div>
                    @endif
                </section>
            </div>

            <aside class="card admin-panel" aria-label="Pintasan akun">
                <h2 class="admin-section-title">Pintasan Akun</h2>
                <p class="admin-hint">Akses cepat ke aktivitas belanja Anda di Ira Stationery.</p>
                <div class="admin-form-actions" style="flex-direction: column; align-items: stretch;">
                    <a class="btn-secondary" href="{{ route('pesanan.index') }}">Riwayat Pesanan</a>
                    <a class="btn-secondary" href="{{ route('pembayaran.index') }}">Riwayat Pembayaran</a>
                    <a class="btn-secondary" href="{{ route('keranjang.index') }}">Lihat Keranjang</a>
                </div>
            </aside>
        </div>
    </div>
@endsection
