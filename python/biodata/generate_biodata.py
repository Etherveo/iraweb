#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
generate_biodata.py — Generator biodata pembeli simulasi (M4, SIM-02/05/06).

Modul importable + CLI. Dipakai panel Developer (SIM-02) lewat antarmuka
tunggal: jumlah, offset, seed -> daftar biodata lengkap (nama, email,
username, telepon, gender, tanggal lahir, alamat).

    from generate_biodata import generate_biodata
    hasil = generate_biodata(count=5, offset=0, seed=42)

atau lewat CLI:

    python3 generate_biodata.py --count 5 --seed 42
    python3 generate_biodata.py --count 5 --mode random

Keluaran CLI: SATU objek JSON ke stdout:
    {"seed": int, "count": int, "offset": int, "items": [...]}

Determinisme (SIM-05): seed yang sama + offset + count yang sama selalu
menghasilkan biodata yang identik (ID dan waktu pembuatan dikecualikan —
modul ini tidak membuat ID/waktu). Aturannya:
  - SATU aliran RNG berbibit untuk seluruh run: pool nama + pool wilayah
    dibangun dari `random` global yang di-seed sekali (urutan tetap:
    laki-laki, perempuan, wilayah); tiap item memakai SATU instance
    `random.Random(seed)` dengan urutan field tetap
    (gender -> nama -> telepon -> tanggal lahir -> alamat).
  - Semantik offset: dibangkitkan `offset + count` entri dalam satu aliran,
    lalu dikembalikan irisan `[offset : offset+count]`. Jadi item ke-6..10
    dari run `--count 10 --seed 42` sama dengan run
    `--count 10 --seed 42 --offset 5`.
  - Tanggal lahir memakai rentang TETAP 1966-01-01 s.d. 2009-12-31 (bukan
    umur relatif terhadap tanggal eksekusi), supaya run yang diulang di
    tanggal berbeda tetap identik.

Nama: dihasilkan modul `name_generator` (Markov orde-2 + KNN + Naive Bayes
+ ranking SAW, dilatih HANYA pada nama asli dari
`data/daftar_nama_indonesia.json`). Hasil generasi TIDAK PERNAH dipakai
sebagai data latih. Dua penyesuaian kualitas di wrapper ini (tanpa mengubah
name_generator.py): (1) peringkat SAW TIDAK dipakai sebagai urutan — SAW
memberi bobot besar pada "pendek + unik" sehingga puncak pool justru
fragmen 3 huruf ("ruq", "rim", "far"); urutan pemakaian adalah kewajaran
(plausibilitas Markov) menurun; (2) filter kelayakan: total huruf >= 6
DAN tiap kata >= 3 huruf — kedua batas diambil dari data latih itu sendiri
(nama satu kata terpendek 6 huruf, kata terpendek 3 huruf). Cetakan
`name_generator` ke stdout (mis. "[Naive Bayes] dilatih dari ...") diredam
di dalam generate_biodata() supaya stdout CLI tetap SATU objek JSON murni.

Email: wajib berdomain `sim.ira-stationery.test` (SIM-07, domain tercadang
dari `config('ira.sim_account_email_domain')`). JANGAN pakai `@contoh.test`
— domain itu hanya untuk script mass-data mandiri (P6). Email dijamin unik
di dalam batch (tabrakan slug -> akhiran 2 digit berurutan).

Alamat: 1 per pembeli. Provinsi dipilih dari 38 provinsi; bila provinsi
tercakup file wilayah (Jawa/Kalimantan/Sulawesi, 17 provinsi), kecamatan /
kelurahan / kode pos diambil dari file; bila tidak, kecamatan/kelurahan
dibangkitkan via Markov orde-2 (basis 220 nama wilayah) dan kode pos dari
awalan provinsi + digit acak. Bagian yang dibangkitkan dicatat di stderr
(ringkasan hitungan), BUKAN di database (tidak ada kolomnya).

Seed & mode (aturan U-02): default panel adalah deterministik dengan seed
20261101 — nilai itu dipakai panel/PHP sebagai default form dan selalu
diteruskan eksplisit sebagai `--seed`. CLI ini: bila `--seed` diberikan,
run deterministik dengan seed itu; bila TIDAK diberikan, seed dibangkitkan
acak dari entropi sistem dan dilaporkan di JSON (mode random). Untuk run
deterministik dari CLI, selalu sertakan `--seed`.

Stdlib only, Python >= 3.10. Tidak ada akses jaringan/file di luar data/.
"""

from __future__ import annotations

import argparse
import contextlib
import io
import json
import os
import random
import re
import sys
import unicodedata
from datetime import date, timedelta
from pathlib import Path

# --- name_generator.py tinggal di direktori yang sama; pastikan bisa di-import
# --- baik saat dijalankan sebagai script maupun saat modul ini di-import dari
# --- tempat lain. (Import biasa mendaftarkan modul ke sys.modules sebelum
# --- eksekusi, jadi dekorator @dataclass di name_generator.py aman.)
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import name_generator  # noqa: E402

SIM_DOMAIN = "sim.ira-stationery.test"
DEFAULT_SEED = 20261101  # U-02: default panel; PHP meneruskannya eksplisit.
BIRTH_START = date(1966, 1, 1)
BIRTH_END = date(2009, 12, 31)
GENDERS = ("laki-laki", "perempuan")
NAME_POOL_CANDIDATES = 300
WILAYAH_POOL_CANDIDATES = 300

# Awalan kode pos 3 digit per kota (pengetahuan umum, dari isi_wilayah_kosong.py).
# Dipakai hanya untuk alamat yang kecamatan/kelurahannya dibangkitkan.
AWALAN_KOTA = {
    "Kota Makassar": "901", "Kota Parepare": "911", "Kota Palopo": "919",
    "Kota Kendari": "931", "Kota Bau-Bau": "937",
    "Kabupaten Mamuju": "915", "Kabupaten Majene": "914",
    "Kabupaten Polewali Mandar": "913",
    "Kota Manado": "951", "Kota Bitung": "955",
    "Kota Palu": "941", "Kabupaten Banggai": "947",
    "Kota Gorontalo": "961", "Kabupaten Gorontalo": "962",
    "Kota Pontianak": "781", "Kota Tarakan": "771",
}

# Awalan kode pos 2 digit per provinsi (pendekatan umum; data simulasi, BUKAN
# data resmi). Dipakai hanya bila kota tidak ada di AWALAN_KOTA — yaitu untuk
# 21 provinsi di luar Jawa/Kalimantan/Sulawesi yang kecamatan/kelurahannya
# dibangkitkan via Markov.
PREFIX_PROVINSI = {
    "Aceh": "23", "Sumatera Utara": "20", "Sumatera Barat": "25",
    "Riau": "28", "Kepulauan Riau": "29", "Jambi": "36",
    "Sumatera Selatan": "30", "Kepulauan Bangka Belitung": "33",
    "Bengkulu": "38", "Lampung": "34",
    "Bali": "80", "Nusa Tenggara Barat": "83", "Nusa Tenggara Timur": "85",
    "Maluku": "97", "Maluku Utara": "97",
    "Papua": "98", "Papua Barat": "98", "Papua Selatan": "98",
    "Papua Tengah": "98", "Papua Pegunungan": "98", "Papua Barat Daya": "98",
}


class GeneratorError(Exception):
    """Galat yang membuat seluruh permintaan ditolak (bukan dipakai sebagian)."""


def _slug(nama: str) -> str:
    """Slug huruf kecil tanpa spasi/simbol: 'Siti Aminah' -> 'sitiaminah'."""
    teks = unicodedata.normalize("NFKD", nama).encode("ascii", "ignore").decode("ascii")
    return re.sub(r"[^a-z0-9]", "", teks.lower())


def _baca_json(jalur: Path):
    with open(jalur, encoding="utf-8") as f:
        return json.load(f)


class _Konteks:
    """Dataset + pool yang dibangun sekali per run (deterministik thd seed)."""

    def __init__(self, data_dir: Path):
        self.data_dir = data_dir
        self.provinces: list[str] = []
        self.kota_kab: dict[str, list[str]] = {}
        self.streets: list[str] = []
        # wilayah[provinsi] = [{nama, kecamatan: [{nama, kelurahan: [{nama, kode_pos}]}]}]
        self.wilayah: dict[str, list[dict]] = {}
        self.name_pools: dict[str, list[str]] = {}
        self.wilayah_pool: list[str] = []
        self._wilayah_ptr = 0
        self.generated_address_count = 0
        self._muat()

    def _muat(self) -> None:
        d = self.data_dir
        nama_file = d / "daftar_nama_indonesia.json"
        self.entries, _ringkasan = name_generator.muat_dataset(str(nama_file))
        if not self.entries:
            raise GeneratorError(f"Tidak ada nama latih di {nama_file}")

        self.provinces = _baca_json(d / "daftar_provinsi.json")["provinsi"]
        if not self.provinces:
            raise GeneratorError("daftar_provinsi.json kosong")
        kota_kab = _baca_json(d / "daftar_kota_kabupaten.json")["provinsi"]
        self.kota_kab = {p["nama"]: list(p["kota_kabupaten"]) for p in kota_kab}
        for prov in self.provinces:
            if prov not in self.kota_kab or not self.kota_kab[prov]:
                raise GeneratorError(f"Provinsi {prov!r} tidak punya kota/kabupaten di data")
        self.streets = _baca_json(d / "daftar_jalan.json")["jalan"]
        if not self.streets:
            raise GeneratorError("daftar_jalan.json kosong")

        # Indeks wilayah: hanya kota yang punya kecamatan -> kelurahan -> kode_pos.
        for nama_file_w in ("daftar_wilayah_jawa.json",
                            "daftar_wilayah_kalimantan.json",
                            "daftar_wilayah_sulawesi.json"):
            berkas = _baca_json(d / nama_file_w)
            for prov in berkas["provinsi"]:
                kota_bersih = []
                for kota in prov["kota_kabupaten"]:
                    kec_bersih = []
                    for kec in kota.get("kecamatan", []):
                        kel_bersih = [k for k in kec.get("kelurahan", [])
                                      if k.get("kode_pos")]
                        if kel_bersih:
                            kec_bersih.append({"nama": kec["nama"],
                                               "kelurahan": kel_bersih})
                    if kec_bersih:
                        kota_bersih.append({"nama": kota["nama"],
                                            "kecamatan": kec_bersih})
                if kota_bersih:
                    self.wilayah.setdefault(prov["nama"], []).extend(kota_bersih)

    def build_pools(self) -> None:
        """Bangun pool nama per gender + pool nama wilayah. Urutan TETAP
        (laki-laki, perempuan, wilayah) dan memakai `random` global yang
        sudah di-seed — pool hanya fungsi dari seed + data latih."""
        for gender in GENDERS:
            self.name_pools[gender] = _build_name_pool(self.entries, gender)
        self.wilayah_pool = _build_wilayah_pool(self)

    def next_wilayah_name(self) -> str:
        """Ambil satu nama wilayah hasil generasi (berputar bila pool habis)."""
        if not self.wilayah_pool:
            raise GeneratorError("Pool nama wilayah kosong.")
        nama = self.wilayah_pool[self._wilayah_ptr % len(self.wilayah_pool)]
        self._wilayah_ptr += 1
        return nama


def _build_name_pool(entries, gender: str,
                     candidates: int = NAME_POOL_CANDIDATES) -> list[str]:
    """Pool nama hasil generasi untuk satu gender, terurut kewajaran menurun.

    Pipeline name_generator: Markov orde-2 -> KNN (jarak >= 2) -> Naive Bayes
    (kelas harus == gender) -> ranking AHP/SAW/WP. Dilatih HANYA pada nama
    asli bergender itu. Bila hasil generasi kosong (sangat tidak mungkin),
    jatuh kembali ke nama latih asli bergender itu dan catat ke stderr.

    Peringkat SAW TIDAK dipakai sebagai urutan pemakaian: SAW memberi bobot
    besar pada kriteria "pendek + unik", sehingga puncak pool justru fragmen
    3 huruf ("ruq", "rim", "bar", "far") — MIN_PANJANG=3 di name_generator
    membiarkan fragmen itu lolos. Sebagai gantinya, pool difilter
    (total huruf >= 6 DAN tiap kata >= 3 huruf — kedua batas diambil dari
    data latih itu sendiri: nama satu kata terpendek 6 huruf, kata
    terpendek 3 huruf) lalu diurutkan menurut kewajaran (plausibilitas
    Markov) menurun, dengan peringkat SAW sebagai pemecah seri.
    """
    latih = [e.nama for e in entries if e.gender == gender]
    if not latih:
        raise GeneratorError(f"Tidak ada nama latih bergender {gender!r}")
    model = name_generator.RantaiMarkov(2)
    model.latih(latih)
    kandidat, _percobaan = name_generator.tahap_generasi(
        model, candidates, set(latih))
    keunikan = name_generator.tahap_filter_knn(kandidat, latih, ambang=2, k=5)
    lolos = list(keunikan)
    if lolos:
        nb = name_generator.siapkan_naive_bayes(entries, "gender", gender)
        lolos = [n for n in lolos if nb.prediksi(n) == gender]
    if lolos:
        baris, _bobot, _info = name_generator.tahap_ranking(
            lolos, keunikan, model,
            name_generator.rata_proporsi_vokal(latih))
        layak = [r for r in baris
                 if r["panjang"] >= 6
                 and all(len(kata) >= 3 for kata in r["nama"].split())]
        layak.sort(key=lambda r: (-r["kewajaran"], r["peringkat_saw"]))
        if layak:
            return [r["nama"] for r in layak]
    print(f"[Peringatan] Pool nama {gender} hasil generasi kosong; "
          f"memakai nama latih asli.", file=sys.stderr)
    return list(latih)


def _build_wilayah_pool(ctx: _Konteks,
                        candidates: int = WILAYAH_POOL_CANDIDATES) -> list[str]:
    """Pool nama kecamatan/kelurahan hasil generasi (Markov orde-2, basis
    220 nama wilayah unik dari 3 file wilayah; sama seperti
    isi_wilayah_kosong.py). Dipakai hanya untuk 21 provinsi tanpa data
    kecamatan/kelurahan."""
    latih: list[str] = []
    for kota_list in ctx.wilayah.values():
        for kota in kota_list:
            for kec in kota["kecamatan"]:
                latih.append(kec["nama"].lower())
                for kel in kec["kelurahan"]:
                    latih.append(kel["nama"].lower())
    latih_unik = sorted(set(latih))
    if not latih_unik:
        raise GeneratorError("Tidak ada nama wilayah latih.")
    model = name_generator.RantaiMarkov(2)
    model.latih(latih_unik)
    kandidat, _percobaan = name_generator.tahap_generasi(
        model, candidates, set(latih_unik))
    lolos = name_generator.tahap_filter_knn(kandidat, latih_unik, ambang=2, k=5)
    pool = [n.title() for n in lolos]
    if not pool:
        raise GeneratorError("Pool nama wilayah hasil generasi kosong.")
    return pool


def _generated_postal(rng: random.Random, city: str, province: str) -> str:
    """Kode pos hasil generasi: awalan kota/provinsi yang dikenal + digit acak
    berbibit. BUKAN data resmi; hanya untuk data simulasi."""
    if city in AWALAN_KOTA:
        return AWALAN_KOTA[city] + f"{rng.randint(10, 99):02d}"
    prefix = PREFIX_PROVINSI.get(province, "90")
    return prefix + f"{rng.randint(100, 999):03d}"


def _generate_address(rng: random.Random, ctx: _Konteks,
                      name: str, phone: str) -> tuple[dict, bool]:
    """Satu alamat. Mengembalikan (alamat, memakai_bagian_generated)."""
    province = rng.choice(ctx.provinces)
    kota_list = ctx.wilayah.get(province)
    if kota_list:
        kota = rng.choice(kota_list)
        city = kota["nama"]
        kec = rng.choice(kota["kecamatan"])
        kel = rng.choice(kec["kelurahan"])
        district, village, postal = kec["nama"], kel["nama"], kel["kode_pos"]
        generated = False
    else:
        city = rng.choice(ctx.kota_kab[province])
        district = ctx.next_wilayah_name()
        village = ctx.next_wilayah_name()
        postal = _generated_postal(rng, city, province)
        generated = True
    street = rng.choice(ctx.streets)
    number = rng.randint(1, 200)
    letter = rng.choice(["", "", "", "A", "B", "C"])
    return {
        "recipient_name": name,
        "recipient_phone": phone,
        "province": province,
        "city": city,
        "district": district,
        "village": village,
        "postal_code": postal,
        "street_address": f"{street} No. {number}{letter}",
        "address_type": "Rumah",
    }, generated


def _generate_items(rng: random.Random, ctx: _Konteks,
                    total: int) -> tuple[list[dict], int]:
    """Bangkitkan `total` biodata dalam SATU aliran RNG. Urutan field tetap
    per item: gender -> nama -> telepon -> tanggal lahir -> alamat."""
    items: list[dict] = []
    used_slugs: dict[str, int] = {}
    name_ptr = {g: 0 for g in GENDERS}
    generated_addr = 0
    span_days = (BIRTH_END - BIRTH_START).days
    for _ in range(total):
        gender = "laki-laki" if rng.random() < 0.5 else "perempuan"
        pool = ctx.name_pools[gender]
        name = pool[name_ptr[gender] % len(pool)]
        name_ptr[gender] += 1
        slug = _slug(name)
        occ = used_slugs.get(slug, 0) + 1
        used_slugs[slug] = occ
        email = (f"{slug}@{SIM_DOMAIN}" if occ == 1
                 else f"{slug}{occ - 1:02d}@{SIM_DOMAIN}")
        username = f"{slug}{occ:02d}"
        n_digits = rng.randint(8, 11)
        phone = "08" + str(rng.randint(1, 9)) + "".join(
            str(rng.randint(0, 9)) for _ in range(n_digits - 1))
        birthdate = BIRTH_START + timedelta(days=rng.randint(0, span_days))
        address, generated = _generate_address(rng, ctx, name, phone)
        if generated:
            generated_addr += 1
        items.append({
            "name": name,
            "email": email,
            "username": username,
            "phone": phone,
            "gender": gender,
            "birthdate": birthdate.isoformat(),
            "address": address,
        })
    return items, generated_addr


def generate_biodata(count: int, offset: int = 0, seed: int | None = None,
                     mode: str = "deterministic",
                     data_dir: str | Path | None = None) -> dict:
    """Antarmuka SIM-06 (modul importable).

    count: jumlah biodata (1..n; panel membatasi 1..100 sebelum memanggil).
    offset: mulai-dari-indeks (SIM-05); dibangkitkan offset+count entri,
        dikembalikan irisan [offset:offset+count].
    seed: bila diberikan -> deterministik dengan seed itu. Bila None ->
        seed dibangkitkan acak dari entropi sistem dan dilaporkan di hasil
        (mode random). Nilai default panel (U-02, 20261101) dipakai panel/PHP
        sebagai default form dan diteruskan eksplisit sebagai seed.
    mode: "deterministic" (default) atau "random"; hanya dokumentasi niat
        pemanggil bila seed diberikan (deterministik) — tanpa seed, run
        selalu acak dan seed yang dipakai dilaporkan.
    """
    if count < 1:
        raise GeneratorError("--count minimal 1")
    if offset < 0:
        raise GeneratorError("--offset minimal 0")
    if mode not in ("random", "deterministic"):
        raise GeneratorError("--mode harus 'random' atau 'deterministic'")
    if seed is not None:
        effective_seed = seed
    else:
        effective_seed = random.SystemRandom().randint(0, 2**31 - 1)

    data_dir = (Path(data_dir) if data_dir is not None
                else Path(__file__).resolve().parent / "data")
    # name_generator mencetak ke stdout (mis. "[Naive Bayes] dilatih dari
    # ...") — redam selama pemuatan dataset + pembangunan pool supaya stdout
    # CLI tetap SATU objek JSON murni. (Semua pesan wrapper sendiri sudah
    # ke stderr, jadi tidak terpengaruh.)
    with contextlib.redirect_stdout(io.StringIO()):
        ctx = _Konteks(data_dir)

        # Pool dibangun dari `random` global yang di-seed sekali, urutan tetap —
        # pool hanya fungsi dari (seed, data latih), bukan dari count/offset.
        random.seed(effective_seed)
        ctx.build_pools()

    rng = random.Random(effective_seed)
    items, generated_addr = _generate_items(rng, ctx, offset + count)
    if generated_addr:
        print(f"[Alamat] {generated_addr} dari {count} alamat memakai "
              f"kecamatan/kelurahan/kode pos hasil generasi (Markov) — "
              f"bukan data resmi.", file=sys.stderr)
    return {
        "seed": effective_seed,
        "count": count,
        "offset": offset,
        "items": items[offset:offset + count],
    }


def buat_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(
        prog="generate_biodata.py",
        description="Generator biodata pembeli simulasi (M4, SIM-02/05/06). "
                    "Mencetak SATU objek JSON ke stdout.",
        epilog="Tanpa --seed, seed dibangkitkan acak dan dilaporkan di JSON. "
               "Untuk run deterministik, selalu sertakan --seed "
               "(default panel U-02: 20261101).",
    )
    p.add_argument("--count", type=int, required=True,
                   help="jumlah biodata (minimal 1; panel membatasi 1..100)")
    p.add_argument("--offset", type=int, default=0,
                   help="mulai-dari-indeks (default: 0)")
    p.add_argument("--seed", type=int, default=None,
                   help="seed deterministik; bila tidak ada -> seed acak")
    p.add_argument("--mode", choices=["random", "deterministic"],
                   default="deterministic",
                   help="niat pemanggil (default: deterministic)")
    p.add_argument("--data-dir", default=None,
                   help="direktori dataset (default: data/ di sebelah file)")
    return p


def main(argv: list[str] | None = None) -> int:
    """Titik masuk CLI: JSON ke stdout, galat ke stderr + kode non-0."""
    if sys.version_info < (3, 10):
        print("[GALAT] Butuh Python 3.10 atau lebih baru.", file=sys.stderr)
        return 1
    args = buat_parser().parse_args(argv)
    try:
        hasil = generate_biodata(count=args.count, offset=args.offset,
                                 seed=args.seed, mode=args.mode,
                                 data_dir=args.data_dir)
    except GeneratorError as e:
        print(f"[GALAT] {e}", file=sys.stderr)
        return 1
    except OSError as e:
        print(f"[GALAT] Gagal membaca berkas data: {e}", file=sys.stderr)
        return 1
    except Exception as e:  # jaring pengaman: jangan bocorkan traceback mentah
        print(f"[GALAT] Kesalahan tak terduga ({type(e).__name__}): {e}",
              file=sys.stderr)
        return 1
    print(json.dumps(hasil, ensure_ascii=False, sort_keys=True))
    return 0


if __name__ == "__main__":
    sys.exit(main())
