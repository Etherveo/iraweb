#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
generator.py - Generator nama orang (Markov chain + KNN + Naive Bayes + AHP/SAW/WP)

Program ini membangkitkan nama baru dari daftar nama asli, lalu menyaring dan
meranking hasilnya.

Tahap 1 - GENERASI : Markov chain tingkat karakter (n-gram, orde = --order).
Tahap 2 - FILTER   : KNN (jarak Levenshtein) membuang kandidat yang terlalu mirip
                     dengan nama latih; Multinomial Naive Bayes (n-gram karakter
                     1-3, Laplace smoothing) menyimpan kandidat berkelas --target.
Tahap 3 - RANKING  : empat kriteria (panjang, kemudahan diucapkan, keunikan,
                     kewajaran) dibobot dengan AHP, lalu diurutkan dengan SAW dan WP.

REVISI - mencegah model collapse
    Hasil generasi TIDAK PERNAH dipakai sebagai data latih, baik oleh Markov chain,
    KNN, maupun Naive Bayes. Data latih selalu hanya nama asli ("sumber": "asli"):
      - tanpa --update : dari --json (default daftar_nama.json)
      - dengan --update: dari trained_name_list.json (hanya entri "asli"), ditambah
                         nama baru dari --tambah. Bila berkas itu tidak ada, program
                         berhenti dengan pesan; daftar_nama.json tidak dipakai diam-diam.
    Guard: bila ada entri non-asli yang lolos ke data latih, program berhenti dengan error.

REVISI - trained_name_list.json (array objek, ditulis setiap run)
    Nama asli      : {"nama", "sumber": "asli", "kelompok", "gender", "generasi": 0}
    Hasil generasi : {"nama", "sumber": "generated", "generasi": N, "skor_saw",
                      "skor_wp", "lolos_filter"}   (hanya bila --save-generated)

    Field          Dipakai oleh
    nama           Markov (transisi), KNN (jarak Levenshtein), ranking (rata-rata proporsi
                   vokal dan kewajaran), deduplikasi, holdout
    sumber         penyaring + guard: hanya "asli" yang boleh menjadi data latih
    kelompok       Naive Bayes bila --label kelompok ("referensi_worldbuilding" tidak
                   melatih Naive Bayes)
    gender         Naive Bayes bila --label gender
    generasi       PENELUSURAN SAJA (asli = 0, hasil generasi = nomor run); tidak masuk
                   kalkulasi
    skor_saw, skor_wp, lolos_filter
                   PENELUSURAN SAJA (hanya entri generated); tidak masuk kalkulasi
    Catatan: KNN hanya memakai "nama"; Naive Bayes memakai "nama" + "kelompok"/"gender".
    Field penelusuran tetap ada karena skemanya diminta eksplisit; tidak ada field lain.

    Aturan simpan: tanpa --save-generated, file hanya berisi nama asli (entri generated
    lama tidak dibawa). Dengan --save-generated, entri generated lama dipertahankan dan
    kandidat run ini ditambahkan dengan generasi = (generasi terbesar di file) + 1.
    File berisi seluruh nama asli (termasuk data uji); pemisahan holdout dilakukan ulang
    secara deterministik pada setiap run.

REVISI - metrik diversity (--metrik, default metrik.json)
    hitung_diversity() dihitung untuk data asli (yang melatih), kandidat lolos filter,
    dan top 20 SAW, dicetak, lalu ditambahkan sebagai run baru (run_001, run_002, ...)
    ke metrik.json tanpa menimpa run lama.

REVISI - pengujian holdout (--holdout PERSEN)
    Sebagian nama asli dipisah sebagai data uji (urutan hash dengan seed tetap, jadi
    anggota data uji stabil antar run dan tidak ikut --seed). Data uji tidak melatih
    Markov, KNN, maupun Naive Bayes; daftarnya disimpan ke holdout.json. Program
    mencetak rata-rata jarak Levenshtein tiap kandidat lolos filter ke data uji
    terdekatnya.

--demo tidak membaca/menulis trained_name_list.json, holdout.json, maupun metrik.json.

Kebutuhan: Python 3.10 ke atas, hanya pustaka standar (tanpa numpy/scikit-learn).

Contoh pemakaian:
    python generator.py --json daftar_nama.json --label gender --target perempuan
    python generator.py --update --label gender --target perempuan --save-generated
    python generator.py --update --tambah nama_baru.txt --label gender --target perempuan
    python generator.py --json daftar_nama.json --holdout 20 --label none
    python generator.py --json daftar_nama.json --label kelompok --target jepang
    python generator.py --demo
"""

from __future__ import annotations

import argparse
import csv
import hashlib  # REVISI: urutan hash untuk memilih data uji (holdout) yang stabil
import heapq
import json
import math
import os
import random
import re
import sys
import tempfile
import unicodedata
from collections import Counter, defaultdict
from dataclasses import dataclass
from datetime import datetime  # REVISI: cap waktu pada catatan metrik

# ============================================================================
# KONSTANTA (mudah diubah)
# ============================================================================

TOKEN_AWAL = "^"            # penanda awal nama pada Markov chain
TOKEN_AKHIR = "$"           # penanda akhir nama pada Markov chain
MAKS_PANJANG = 15           # panjang maksimal nama hasil generasi (jumlah huruf)
MIN_PANJANG = 3             # panjang minimal nama hasil generasi (jumlah huruf)
FAKTOR_PERCOBAAN = 100      # batas percobaan generasi = FAKTOR_PERCOBAAN x --count
TOP_N = 20                  # jumlah baris pada tabel peringkat

# Tipe entri yang bukan nama orang -> dibuang dari data latih
TIPE_DIBUANG = frozenset({
    "tempat", "elemen_tempat", "kerajaan_imperium",
    "kota_wilayah_benteng", "entitas_supernatural",
})
KELOMPOK_REFERENSI = "referensi_worldbuilding"   # duplikat: hanya untuk dedup, bukan melatih NB

VOKAL = frozenset("aeiou")
POLA_NAMA = re.compile(r"[a-z]+( [a-z]+)*")      # huruf a-z, boleh dipisah satu spasi

# Matriks perbandingan berpasangan AHP (skala Saaty 1-9).
# Urutan kriteria: panjang, kemudahan diucapkan, keunikan, kewajaran.
MATRIKS_AHP = [
    [1,   1 / 2, 2,     3],
    [2,   1,     3,     4],
    [1 / 2, 1 / 3, 1,   2],
    [1 / 3, 1 / 4, 1 / 2, 1],
]
NAMA_KRITERIA = ["Panjang", "Pengucapan", "Keunikan", "Kewajaran"]
JENIS_KRITERIA = ["cost", "benefit", "benefit", "benefit"]
RI = [0, 0, 0.58, 0.90, 1.12, 1.24, 1.32, 1.41, 1.45, 1.49]   # Random Index n = 1..10

# REVISI: konstanta untuk sumber data, holdout, dan berkas keluaran tambahan
SUMBER_ASLI = "asli"                # satu-satunya sumber yang boleh menjadi data latih
SUMBER_GENERATED = "generated"      # hasil generasi: hanya penelusuran, tidak pernah melatih
JSON_DEFAULT = "daftar_nama.json"
FILE_HOLDOUT = "holdout.json"
FILE_METRIK_DEFAULT = "metrik.json"
SEED_HOLDOUT = 20240601             # tetap (tidak ikut --seed) agar anggota data uji stabil antar run
MIN_DATA_UJI = 5                    # jumlah data uji minimal bila --holdout > 0
MIN_DATA_LATIH = 10                 # jumlah data latih minimal setelah holdout
MAKS_HOLDOUT_PERSEN = 50            # batas atas --holdout

# Pemetaan huruf Latin yang tidak terurai oleh normalisasi Unicode
_PETA_HURUF = str.maketrans({
    "ø": "o", "æ": "ae", "œ": "oe", "ß": "ss", "ð": "d", "þ": "th", "đ": "d", "ł": "l",
})

# Dataset contoh kecil untuk --demo (label gender)
DATA_DEMO = [
    ("budi", "laki-laki"), ("agus", "laki-laki"), ("andi", "laki-laki"),
    ("rizky", "laki-laki"), ("dimas", "laki-laki"), ("fajar", "laki-laki"),
    ("hendra", "laki-laki"), ("bayu", "laki-laki"), ("eko", "laki-laki"),
    ("ilham", "laki-laki"),
    ("siti", "perempuan"), ("sri", "perempuan"), ("dewi", "perempuan"),
    ("putri", "perempuan"), ("ayu", "perempuan"), ("rina", "perempuan"),
    ("wulan", "perempuan"), ("lestari", "perempuan"), ("nadia", "perempuan"),
    ("melati", "perempuan"),
]


class GeneratorError(Exception):
    """Galat yang bisa dijelaskan ke pengguna; dicetak sebagai pesan, bukan traceback."""


@dataclass
class Entri:
    """Satu nama latih beserta label yang dipakai algoritma."""
    nama: str                      # huruf kecil, sudah dinormalisasi
    gender: str | None = None      # dipakai Naive Bayes bila --label gender
    kelompok: str | None = None    # dipakai Naive Bayes bila --label kelompok
    referensi: bool = False        # True = dari referensi_worldbuilding (tidak melatih NB)
    sumber: str = SUMBER_ASLI      # REVISI: "asli" atau "generated"; hanya "asli" boleh jadi data latih


# ============================================================================
# DATA: PEMUATAN DAN NORMALISASI
# ============================================================================

def normalisasi_nama(teks: str) -> str:
    """Ubah nama ke huruf kecil, lepas tanda diakritik (Oisín -> oisin), rapikan spasi."""
    teks = teks.strip().lower().translate(_PETA_HURUF)
    teks = unicodedata.normalize("NFKD", teks)
    teks = "".join(c for c in teks if not unicodedata.combining(c))
    return re.sub(r"\s+", " ", teks)


def _teks_atau_none(nilai) -> str | None:
    """Ubah nilai JSON menjadi teks huruf kecil bersih; None bila kosong atau bukan teks."""
    if isinstance(nilai, str) and nilai.strip():
        return nilai.strip().lower()
    return None


def _baca_json(jalur: str):
    """Baca berkas JSON dengan pesan galat yang jelas."""
    try:
        with open(jalur, encoding="utf-8-sig") as f:
            return json.load(f)
    except FileNotFoundError:
        raise GeneratorError(
            f"Berkas JSON tidak ditemukan: '{jalur}'. Periksa path-nya atau opsi --json."
        ) from None
    except json.JSONDecodeError as e:
        raise GeneratorError(
            f"Format JSON tidak sesuai: '{jalur}' bukan JSON yang valid "
            f"(baris {e.lineno}, kolom {e.colno}: {e.msg})."
        ) from None
    except UnicodeDecodeError:
        raise GeneratorError(f"Berkas '{jalur}' bukan teks UTF-8 yang valid.") from None


def _lengkapi_label(dasar: Entri, tambahan: Entri) -> None:
    """Isi label kosong pada 'dasar' dari entri duplikat 'tambahan' (label yang sudah ada tidak diubah)."""
    if dasar.gender is None and tambahan.gender is not None:
        dasar.gender = tambahan.gender
    if dasar.kelompok is None and tambahan.kelompok is not None and not tambahan.referensi:
        dasar.kelompok = tambahan.kelompok


def jaga_hanya_asli(entri: list[Entri], konteks: str) -> None:
    """
    REVISI: guard proporsi. Hentikan program bila ada entri bersumber selain 'asli'
    di data latih, supaya hasil generasi tidak pernah ikut melatih model.
    """
    for e in entri:
        if e.sumber != SUMBER_ASLI:
            raise GeneratorError(
                f"Guard proporsi gagal di {konteks}: entri '{e.nama}' bersumber '{e.sumber}', "
                f"padahal data latih hanya boleh berisi sumber '{SUMBER_ASLI}'. "
                "Program dihentikan agar hasil generasi tidak masuk data latih."
            )


def muat_dataset(jalur: str) -> tuple[list[Entri], dict]:
    """
    Muat daftar_nama.json dan siapkan data latih nama orang.

    - Buang entri bertipe non-orang (TIPE_DIBUANG).
    - Normalisasi nama (huruf kecil, tanpa diakritik) lalu deduplikasi.
    - Entri referensi_worldbuilding dipakai untuk deduplikasi saja: bila namanya sudah
      ada di kelompok lain, entri referensi dibuang; bila belum ada, dipertahankan
      sebagai nama latih Markov/KNN tetapi ditandai agar tidak melatih Naive Bayes.

    Mengembalikan (daftar Entri unik, ringkasan hitungan).
    """
    data = _baca_json(jalur)
    if not isinstance(data, dict) or not isinstance(data.get("daftar_nama"), list):
        raise GeneratorError(
            "Format JSON tidak sesuai: objek utama harus punya array 'daftar_nama'."
        )

    ringkasan = {
        "total": len(data["daftar_nama"]),
        "dibuang_tipe": Counter(),
        "dibuang_format": [],
        "duplikat_referensi": 0,
        "duplikat_lain": 0,
    }
    biasa: list[Entri] = []
    referensi: list[Entri] = []

    for urutan, e in enumerate(data["daftar_nama"], start=1):
        if not isinstance(e, dict) or not isinstance(e.get("nama"), str) or not e["nama"].strip():
            raise GeneratorError(
                f"Format JSON tidak sesuai: entri ke-{urutan} pada 'daftar_nama' "
                "tidak punya field 'nama' berupa teks."
            )
        tipe = _teks_atau_none(e.get("tipe"))
        if tipe in TIPE_DIBUANG:                      # bukan nama orang
            ringkasan["dibuang_tipe"][tipe] += 1
            continue
        nama = normalisasi_nama(e["nama"])
        if not POLA_NAMA.fullmatch(nama):             # mengandung simbol / bukan huruf Latin
            ringkasan["dibuang_format"].append(e["nama"])
            continue
        kelompok = _teks_atau_none(e.get("kelompok"))
        entri = Entri(
            nama=nama,
            gender=_teks_atau_none(e.get("gender")),
            kelompok=kelompok,
            referensi=(kelompok == KELOMPOK_REFERENSI),
        )
        (referensi if entri.referensi else biasa).append(entri)

    unik: dict[str, Entri] = {}
    for entri in biasa:                               # kelompok asli lebih dulu
        if entri.nama in unik:
            ringkasan["duplikat_lain"] += 1
            _lengkapi_label(unik[entri.nama], entri)
        else:
            unik[entri.nama] = entri
    for entri in referensi:                           # lalu referensi (hanya untuk dedup)
        if entri.nama in unik:
            ringkasan["duplikat_referensi"] += 1
        else:
            unik[entri.nama] = entri

    if not unik:
        raise GeneratorError("Tidak ada nama orang yang tersisa di data latih setelah penyaringan.")
    hasil = list(unik.values())
    jaga_hanya_asli(hasil, "muat_dataset")   # REVISI: guard proporsi, hanya sumber 'asli' boleh lolos
    return hasil, ringkasan


def data_demo() -> list[Entri]:
    """Dataset contoh kecil (20 nama berlabel gender) untuk mode --demo."""
    return [Entri(nama=n, gender=g) for n, g in DATA_DEMO]


# REVISI: hasil pemuatan trained_name_list.json yang sudah divalidasi
@dataclass
class HasilTrained:
    """Isi trained_name_list.json setelah divalidasi dan dipisah menurut sumber."""
    asli: list[Entri]          # nama asli unik; satu-satunya yang boleh melatih model
    generated: list[dict]      # catatan hasil generasi lama; hanya penelusuran, tidak pernah melatih
    duplikat: int              # jumlah entri asli duplikat yang dibuang saat memuat


def _galat_trained(jalur: str, urutan: int, alasan: str) -> GeneratorError:
    """REVISI: pesan galat seragam untuk entri trained_name_list.json yang tidak sesuai."""
    return GeneratorError(
        f"Format '{jalur}' tidak sesuai: entri ke-{urutan} {alasan}. "
        "Berkas tidak diubah; perbaiki atau hapus secara manual."
    )


def _validasi_generated(e: dict, nama: str, urutan: int, jalur: str) -> dict:
    """REVISI: validasi satu catatan generated lama; hanya field skema yang dipertahankan."""
    generasi = e.get("generasi")
    if isinstance(generasi, bool) or not isinstance(generasi, int) or generasi < 1:
        raise _galat_trained(jalur, urutan, "bersumber 'generated' tetapi 'generasi'-nya bukan bilangan bulat >= 1")
    for kunci in ("skor_saw", "skor_wp"):
        nilai = e.get(kunci)
        if nilai is not None and (isinstance(nilai, bool) or not isinstance(nilai, (int, float))):
            raise _galat_trained(jalur, urutan, f"punya '{kunci}' yang bukan angka atau null")
    if not isinstance(e.get("lolos_filter"), bool):
        raise _galat_trained(jalur, urutan, "bersumber 'generated' tetapi 'lolos_filter'-nya bukan true/false")
    return {"nama": nama, "sumber": SUMBER_GENERATED, "generasi": generasi,
            "skor_saw": e.get("skor_saw"), "skor_wp": e.get("skor_wp"), "lolos_filter": e["lolos_filter"]}


def muat_trained(jalur: str) -> HasilTrained | None:
    """
    REVISI: muat trained_name_list.json (array objek) dan pisahkan menurut 'sumber'.

    - sumber 'asli'      -> daftar Entri (duplikat setelah huruf kecil dibuang dan dihitung)
    - sumber 'generated' -> hanya disimpan sebagai catatan; TIDAK menjadi Entri, jadi tidak
                            bisa melatih Markov, KNN, maupun Naive Bayes
    - sumber lain/kosong -> galat jelas

    Mengembalikan None bila berkas belum ada.
    """
    if not os.path.exists(jalur):
        return None
    data = _baca_json(jalur)
    if not isinstance(data, list):
        raise GeneratorError(
            f"Format '{jalur}' tidak sesuai: harus berupa array objek. Berkas dari versi lama "
            "(objek dengan 'daftar_nama', tanpa field 'sumber') tidak didukung; buat ulang dengan "
            "menjalankan program tanpa --update."
        )
    asli: list[Entri] = []
    generated: list[dict] = []
    terlihat: set[str] = set()
    duplikat = 0
    for urutan, e in enumerate(data, start=1):
        if not isinstance(e, dict):
            raise _galat_trained(jalur, urutan, "bukan objek")
        sumber = e.get("sumber")
        if sumber not in (SUMBER_ASLI, SUMBER_GENERATED):
            raise _galat_trained(
                jalur, urutan,
                f"punya 'sumber' tidak valid ({sumber!r}); nilai yang diizinkan: '{SUMBER_ASLI}' atau '{SUMBER_GENERATED}'")
        nama = normalisasi_nama(e["nama"]) if isinstance(e.get("nama"), str) else None
        if nama is None or not POLA_NAMA.fullmatch(nama):
            raise _galat_trained(jalur, urutan, "tidak punya 'nama' yang valid")
        if sumber == SUMBER_GENERATED:
            generated.append(_validasi_generated(e, nama, urutan, jalur))
            continue
        if nama in terlihat:
            duplikat += 1
            continue
        terlihat.add(nama)
        kelompok = _teks_atau_none(e.get("kelompok"))
        asli.append(Entri(nama=nama, gender=_teks_atau_none(e.get("gender")), kelompok=kelompok,
                          referensi=(kelompok == KELOMPOK_REFERENSI), sumber=SUMBER_ASLI))
    jaga_hanya_asli(asli, "muat_trained")
    return HasilTrained(asli, generated, duplikat)


def gabung_data_latih(asli: list[Entri], tambahan: list[Entri]) -> tuple[list[Entri], int]:
    """
    REVISI: gabungkan dua daftar nama ASLI (mis. isi trained_name_list.json + --tambah).
    Nama yang sudah ada (setelah huruf kecil) dibuang sebagai duplikat; entri pertama menang.
    Mengembalikan (gabungan, jumlah duplikat yang dibuang).
    """
    sudah_ada = {e.nama for e in asli}
    hasil = list(asli)
    duplikat = 0
    for e in tambahan:
        if e.nama in sudah_ada:
            duplikat += 1
        else:
            sudah_ada.add(e.nama)
            hasil.append(e)
    jaga_hanya_asli(hasil, "gabung_data_latih")
    return hasil, duplikat


def muat_nama_tambahan(jalur: str) -> tuple[list[Entri], dict]:
    """
    REVISI: baca nama asli baru dari --tambah.
    - berkas .json : struktur sama dengan daftar_nama.json (disaring seperti muat_dataset)
    - lainnya      : teks, satu nama per baris (baris kosong dan berawalan # dilewati);
                     nama dari teks tidak punya label sehingga tidak melatih Naive Bayes
    Semua nama baru bersumber 'asli' dengan generasi 0.
    Mengembalikan (entri, info) dengan info berisi duplikat, dibuang_format, dibuang_tipe.
    """
    if not os.path.isfile(jalur):
        raise GeneratorError(f"Berkas --tambah tidak ditemukan: '{jalur}'.")
    if jalur.lower().endswith(".json"):
        entri, r = muat_dataset(jalur)
        return entri, {"duplikat": r["duplikat_lain"] + r["duplikat_referensi"],
                       "dibuang_format": len(r["dibuang_format"]),
                       "dibuang_tipe": sum(r["dibuang_tipe"].values())}
    try:
        with open(jalur, encoding="utf-8-sig") as f:
            baris = f.read().splitlines()
    except UnicodeDecodeError:
        raise GeneratorError(f"Berkas --tambah '{jalur}' bukan teks UTF-8 yang valid.") from None
    entri: list[Entri] = []
    terlihat: set[str] = set()
    info = {"duplikat": 0, "dibuang_format": 0, "dibuang_tipe": 0}
    for teks in baris:
        teks = teks.strip()
        if not teks or teks.startswith("#"):
            continue
        nama = normalisasi_nama(teks)
        if not POLA_NAMA.fullmatch(nama):
            info["dibuang_format"] += 1
            continue
        if nama in terlihat:
            info["duplikat"] += 1
            continue
        terlihat.add(nama)
        entri.append(Entri(nama=nama))
    if not entri:
        raise GeneratorError(
            f"Berkas --tambah '{jalur}' tidak berisi nama yang valid (satu nama per baris, huruf Latin saja).")
    return entri, info


def muat_data_update(jalur_trained: str, jalur_tambah: str | None) -> tuple[list[Entri], dict, list[dict]]:
    """
    REVISI: data latih untuk --update = entri 'asli' dari trained_name_list.json
    (+ nama baru dari --tambah). Entri 'generated' diabaikan sepenuhnya.
    Berkas tidak ada -> galat; daftar_nama.json tidak dipakai diam-diam.
    Mengembalikan (entri latih, info hitungan, catatan generated lama).
    """
    hasil = muat_trained(jalur_trained)
    if hasil is None:
        raise GeneratorError(
            f"--update membutuhkan '{jalur_trained}', tetapi berkas itu tidak ditemukan. "
            "Jalankan dulu tanpa --update (memakai daftar_nama.json) untuk membuatnya. "
            "Program tidak akan memakai daftar_nama.json secara diam-diam."
        )
    entri = list(hasil.asli)
    info = {"asli_trained": len(hasil.asli), "generated_diabaikan": len(hasil.generated),
            "dari_tambah": 0, "duplikat": hasil.duplikat, "dibuang_format": 0, "dibuang_tipe": 0}
    if jalur_tambah:
        baru, t = muat_nama_tambahan(jalur_tambah)
        entri, dup = gabung_data_latih(entri, baru)
        info["dari_tambah"] = len(baru) - dup
        info["duplikat"] += t["duplikat"] + dup
        info["dibuang_format"] = t["dibuang_format"]
        info["dibuang_tipe"] = t["dibuang_tipe"]
    if not entri:
        raise GeneratorError(f"'{jalur_trained}' tidak berisi nama asli. Buat ulang dengan menjalankan "
                             "program tanpa --update, atau isi lewat --tambah.")
    jaga_hanya_asli(entri, "muat_data_update")
    return entri, info, hasil.generated


def cetak_ringkasan_update(jalur_trained: str, jalur_tambah: str | None, info: dict, entri: list[Entri]) -> None:
    """REVISI: cetak jumlah nama asli yang dipakai dan jumlah duplikat yang dibuang pada mode --update."""
    n_gender = sum(1 for e in entri if ambil_label(e, "gender"))
    n_kelompok = sum(1 for e in entri if ambil_label(e, "kelompok"))
    print(f"[Update] Data latih dari '{jalur_trained}' (hanya entri sumber '{SUMBER_ASLI}')")
    print(f"         nama asli dibaca dari file   : {info['asli_trained']}")
    print(f"         entri generated diabaikan    : {info['generated_diabaikan']}")
    if jalur_tambah:
        print(f"         nama baru dari --tambah      : {info['dari_tambah']} ('{jalur_tambah}')")
        if info["dibuang_format"] or info["dibuang_tipe"]:
            print(f"         dibuang dari --tambah        : {info['dibuang_format']} format tidak valid, "
                  f"{info['dibuang_tipe']} bukan nama orang")
    print(f"         duplikat dibuang             : {info['duplikat']}")
    print(f"         nama asli yang dipakai       : {len(entri)} "
          f"(berlabel gender: {n_gender}, berlabel kelompok: {n_kelompok})")


def pisah_holdout(entri: list[Entri], persen: float) -> tuple[list[Entri], list[str]]:
    """
    REVISI: pisahkan sebagian nama asli sebagai data uji sebelum training.
    Nama diurutkan menurut hash SHA-256 (dengan SEED_HOLDOUT tetap); k nama pertama menjadi
    data uji. Dengan begitu anggota data uji stabil antar run dan tidak bergantung --seed.
    Mengembalikan (entri latih, daftar nama uji terurut). persen <= 0 -> tanpa holdout.
    """
    if persen <= 0:
        return entri, []
    n = len(entri)
    k = round(n * persen / 100)
    if k < MIN_DATA_UJI:
        raise GeneratorError(
            f"Data uji terlalu sedikit: --holdout {persen:g} dari {n} nama asli hanya menghasilkan {k} nama "
            f"(minimal {MIN_DATA_UJI}). Naikkan --holdout atau tambah data asli.")
    if n - k < MIN_DATA_LATIH:
        raise GeneratorError(
            f"Data latih terlalu sedikit setelah holdout: tersisa {n - k} nama (minimal {MIN_DATA_LATIH}). "
            "Turunkan --holdout atau tambah data asli.")
    urut = sorted(entri, key=lambda e: hashlib.sha256(f"{SEED_HOLDOUT}:{e.nama}".encode("utf-8")).hexdigest())
    uji = {e.nama for e in urut[:k]}
    latih = [e for e in entri if e.nama not in uji]
    return latih, sorted(uji)


def simpan_holdout(jalur: str, uji: list[str]) -> None:
    """REVISI: simpan daftar nama data uji ke holdout.json (array nama huruf kecil)."""
    _tulis_atomik(jalur, json.dumps(uji, ensure_ascii=False, indent=2) + "\n")


def ambil_label(entri: Entri, mode: str) -> str | None:
    """Label Naive Bayes untuk sebuah entri; entri referensi tidak pernah dipakai melatih NB."""
    if entri.referensi:
        return None
    return entri.gender if mode == "gender" else entri.kelompok


def cetak_ringkasan_data(jalur: str, ringkasan: dict, entri: list[Entri]) -> None:
    """Cetak ringkasan pemuatan data latih."""
    print(f"[Data] {jalur}: {ringkasan['total']} entri dibaca")
    total_tipe = sum(ringkasan["dibuang_tipe"].values())
    if total_tipe:
        rincian = ", ".join(f"{t}={n}" for t, n in sorted(ringkasan["dibuang_tipe"].items()))
        print(f"       dibuang karena bukan nama orang (tipe): {total_tipe} ({rincian})")
    if ringkasan["dibuang_format"]:
        print(f"       dibuang karena format nama tidak valid: {len(ringkasan['dibuang_format'])}")
    print(f"       duplikat dari referensi_worldbuilding (untuk dedup): {ringkasan['duplikat_referensi']}")
    print(f"       duplikat antar kelompok lain: {ringkasan['duplikat_lain']}")
    n_gender = sum(1 for e in entri if ambil_label(e, "gender"))
    n_kelompok = sum(1 for e in entri if ambil_label(e, "kelompok"))
    print(f"       nama latih unik: {len(entri)} (berlabel gender: {n_gender}, berlabel kelompok: {n_kelompok})")


# ============================================================================
# TAHAP 1 - GENERASI (MARKOV CHAIN TINGKAT KARAKTER)
# ============================================================================

class RantaiMarkov:
    """Markov chain karakter berorde n; state = n karakter terakhir."""

    def __init__(self, orde: int):
        self.orde = orde
        self.transisi: dict[str, dict[str, float]] = {}      # state -> {karakter: probabilitas}
        self._pilihan: dict[str, tuple[list[str], list[float]]] = {}

    def latih(self, daftar_nama: list[str]) -> None:
        """Hitung frekuensi transisi dari setiap nama (diapit token awal/akhir) lalu normalisasi."""
        hitung: dict[str, Counter] = defaultdict(Counter)
        for nama in daftar_nama:
            deret = TOKEN_AWAL * self.orde + nama + TOKEN_AKHIR
            for i in range(self.orde, len(deret)):
                hitung[deret[i - self.orde:i]][deret[i]] += 1
        for state, cacah in hitung.items():
            total = sum(cacah.values())
            self.transisi[state] = {c: n / total for c, n in cacah.items()}
            self._pilihan[state] = (list(self.transisi[state]), list(self.transisi[state].values()))

    def sampel(self, maks_huruf: int) -> str | None:
        """Bangkitkan satu nama; None bila melebihi maks_huruf (spasi tidak dihitung huruf)."""
        state = TOKEN_AWAL * self.orde
        hasil: list[str] = []
        huruf = 0
        while True:
            karakter, bobot = self._pilihan[state]
            c = random.choices(karakter, weights=bobot)[0]
            if c == TOKEN_AKHIR:
                return "".join(hasil)
            hasil.append(c)
            if c != " ":
                huruf += 1
                if huruf > maks_huruf:
                    return None
            state = (state + c)[-self.orde:]

    def kewajaran(self, nama: str) -> float:
        """Rata-rata geometrik probabilitas transisi per karakter: exp(rata-rata log p)."""
        deret = TOKEN_AWAL * self.orde + nama + TOKEN_AKHIR
        total_log, n = 0.0, 0
        for i in range(self.orde, len(deret)):
            p = self.transisi.get(deret[i - self.orde:i], {}).get(deret[i], 0.0)
            total_log += math.log(p if p > 0 else 1e-9)   # pengaman bila transisi tak pernah terlihat
            n += 1
        return math.exp(total_log / n)


def tahap_generasi(model: RantaiMarkov, jumlah: int, nama_latih: set[str]) -> tuple[list[str], int]:
    """
    Tahap 1: bangkitkan hingga `jumlah` kandidat unik yang bukan nama dataset.
    Percobaan dibatasi FAKTOR_PERCOBAAN x jumlah agar tidak loop tanpa henti.
    Mengembalikan (kandidat, jumlah percobaan).
    """
    maks_percobaan = FAKTOR_PERCOBAAN * jumlah
    hasil: list[str] = []
    terlihat: set[str] = set()
    percobaan = 0
    while len(hasil) < jumlah and percobaan < maks_percobaan:
        percobaan += 1
        nama = model.sampel(MAKS_PANJANG)
        if nama is None:                                    # terlalu panjang
            continue
        if len(nama.replace(" ", "")) < MIN_PANJANG:        # terlalu pendek
            continue
        if nama != nama.strip() or "  " in nama:            # spasi di tepi / ganda
            continue
        if nama in nama_latih or nama in terlihat:          # sama dengan dataset / duplikat kandidat
            continue
        terlihat.add(nama)
        hasil.append(nama)
    return hasil, percobaan


# ============================================================================
# TAHAP 2 - FILTER (KNN LEVENSHTEIN + NAIVE BAYES)
# ============================================================================

def jarak_levenshtein(a: str, b: str) -> int:
    """Jarak Levenshtein (sisip, hapus, ganti = 1) dengan dynamic programming dua baris."""
    if a == b:
        return 0
    if len(a) < len(b):
        a, b = b, a
    if not b:
        return len(a)
    sebelumnya = list(range(len(b) + 1))
    for i, ca in enumerate(a, start=1):
        sekarang = [i]
        for j, cb in enumerate(b, start=1):
            sekarang.append(min(
                sebelumnya[j] + 1,                      # hapus
                sekarang[j - 1] + 1,                    # sisip
                sebelumnya[j - 1] + (ca != cb),         # ganti
            ))
        sebelumnya = sekarang
    return sebelumnya[-1]


def tahap_filter_knn(kandidat: list[str], nama_latih: list[str], ambang: int, k: int) -> dict[str, float]:
    """
    Filter KNN: buang kandidat yang jarak terdekatnya ke nama latih < ambang.
    Skor keunikan = rata-rata jarak ke k tetangga terdekat (makin besar makin unik).
    Mengembalikan dict {nama: skor keunikan} untuk kandidat yang lolos (urutan terjaga).
    """
    k_efektif = min(k, len(nama_latih))
    lolos: dict[str, float] = {}
    for kand in kandidat:
        heap: list[int] = []        # max-heap (nilai dinegatifkan) berisi k jarak terkecil
        ditolak = False
        for nama in nama_latih:
            # Selisih panjang adalah batas bawah jarak: lewati bila pasti tidak masuk k terdekat
            if len(heap) == k_efektif and abs(len(kand) - len(nama)) >= -heap[0]:
                continue
            d = jarak_levenshtein(kand, nama)
            if d < ambang:
                ditolak = True
                break
            if len(heap) < k_efektif:
                heapq.heappush(heap, -d)
            elif d < -heap[0]:
                heapq.heapreplace(heap, -d)
        if not ditolak:
            lolos[kand] = sum(-x for x in heap) / len(heap)
    return lolos


class NaiveBayesKarakter:
    """Multinomial Naive Bayes dengan fitur hitungan n-gram karakter (1..3) dan Laplace smoothing."""

    def __init__(self, n_min: int = 1, n_maks: int = 3, alpha: float = 1.0):
        self.n_min, self.n_maks, self.alpha = n_min, n_maks, alpha

    def _fitur(self, nama: str):
        """Hasilkan seluruh n-gram karakter (spasi dihitung sebagai karakter biasa)."""
        for n in range(self.n_min, self.n_maks + 1):
            for i in range(len(nama) - n + 1):
                yield nama[i:i + n]

    def latih(self, daftar_nama: list[str], label: list[str]) -> None:
        """Hitung frekuensi n-gram per kelas dan prior kelas."""
        self.kelas = sorted(set(label))
        jumlah_kelas = Counter(label)
        self.hitung = {k: Counter() for k in self.kelas}
        for nama, y in zip(daftar_nama, label):
            self.hitung[y].update(self._fitur(nama))
        self.kosakata = set()
        for c in self.hitung.values():
            self.kosakata.update(c)
        v = len(self.kosakata)
        self.total = {k: sum(self.hitung[k].values()) for k in self.kelas}
        self.log_prior = {k: math.log(jumlah_kelas[k] / len(label)) for k in self.kelas}
        self.log_penyebut = {k: math.log(self.total[k] + self.alpha * v) for k in self.kelas}

    def prediksi(self, nama: str) -> str:
        """Prediksi kelas dengan log-posterior tertinggi (n-gram di luar kosakata diabaikan)."""
        fitur = Counter(f for f in self._fitur(nama) if f in self.kosakata)
        terbaik, skor_terbaik = None, -math.inf
        for k in self.kelas:
            skor = self.log_prior[k]
            for f, n in fitur.items():
                skor += n * (math.log(self.hitung[k][f] + self.alpha) - self.log_penyebut[k])
            if skor > skor_terbaik:
                terbaik, skor_terbaik = k, skor
        return terbaik


def siapkan_naive_bayes(entri: list[Entri], mode: str, target: str) -> NaiveBayesKarakter:
    """Validasi --target lalu latih Naive Bayes dari entri berlabel (label None dan referensi dilewati)."""
    nama, label = [], []
    for e in entri:
        y = ambil_label(e, mode)
        if y:
            nama.append(e.nama)
            label.append(y)
    kelas = sorted(set(label))
    if not label:
        raise GeneratorError(f"Tidak ada entri berlabel '{mode}' di data latih, Naive Bayes tidak bisa dilatih.")
    if target not in kelas:
        raise GeneratorError(
            f"--target '{target}' tidak ada di data latih untuk --label {mode}. "
            f"Kelas yang tersedia: {', '.join(kelas)}."
        )
    if len(kelas) < 2:
        raise GeneratorError(
            f"Label '{mode}' hanya punya satu kelas ('{kelas[0]}'); Naive Bayes butuh minimal dua kelas."
        )
    model = NaiveBayesKarakter()
    model.latih(nama, label)
    rincian = ", ".join(f"{k}={label.count(k)}" for k in kelas)
    print(f"[Naive Bayes] dilatih dari {len(label)} nama berlabel {mode} ({rincian}), "
          f"{len(model.kosakata)} fitur n-gram")
    return model


# ============================================================================
# TAHAP 3 - RANKING (AHP + SAW + WP)
# ============================================================================

def hitung_bobot_ahp(matriks: list[list[float]]) -> tuple[list[float], dict]:
    """
    Bobot AHP = eigenvector utama matriks perbandingan berpasangan (power iteration),
    dinormalisasi agar berjumlah 1. Menghitung juga lambda_max, CI, dan CR.
    """
    n = len(matriks)
    if n == 0 or any(len(baris) != n for baris in matriks):
        raise GeneratorError("Matriks AHP harus berbentuk persegi.")
    if n > len(RI):
        raise GeneratorError(f"Tabel RI hanya mencakup n sampai {len(RI)}.")

    def kali(v):
        return [sum(matriks[i][j] * v[j] for j in range(n)) for i in range(n)]

    v = [1.0 / n] * n
    for _ in range(10000):
        w = kali(v)
        s = sum(w)
        w = [x / s for x in w]
        selisih = max(abs(a - b) for a, b in zip(w, v))
        v = w
        if selisih < 1e-14:
            break
    av = kali(v)
    lambda_max = sum(av[i] / v[i] for i in range(n)) / n
    ci = (lambda_max - n) / (n - 1) if n > 1 else 0.0
    ri = RI[n - 1]
    cr = ci / ri if ri > 0 else 0.0
    return v, {"lambda_max": lambda_max, "ci": ci, "cr": cr}


def hitung_saw(nilai: list[list[float]], bobot: list[float], jenis: list[str]) -> list[float]:
    """SAW: benefit x/max, cost min/x; skor = jumlah bobot x nilai ternormalisasi."""
    n_kriteria = len(bobot)
    kolom = [[baris[j] for baris in nilai] for j in range(n_kriteria)]
    hasil = []
    for baris in nilai:
        skor = 0.0
        for j in range(n_kriteria):
            r = (min(kolom[j]) / baris[j]) if jenis[j] == "cost" else (baris[j] / max(kolom[j]))
            skor += bobot[j] * r
        hasil.append(skor)
    return hasil


def hitung_wp(nilai: list[list[float]], bobot: list[float], jenis: list[str]) -> list[float]:
    """WP: S_i = produk x^(+w) untuk benefit dan x^(-w) untuk cost; V_i = S_i / jumlah S."""
    if any(x <= 0 for baris in nilai for x in baris):
        raise GeneratorError("Metode WP butuh semua nilai kriteria > 0.")
    s = []
    for baris in nilai:
        log_s = sum((-w if t == "cost" else w) * math.log(x) for x, w, t in zip(baris, bobot, jenis))
        s.append(math.exp(log_s))
    total = sum(s)
    return [x / total for x in s]


def beri_peringkat(skor: list[float], nama: list[str]) -> list[int]:
    """Peringkat 1 = skor terbesar; seri diurutkan menurut nama agar hasil stabil."""
    urut = sorted(range(len(skor)), key=lambda i: (-skor[i], nama[i]))
    peringkat = [0] * len(skor)
    for posisi, i in enumerate(urut, start=1):
        peringkat[i] = posisi
    return peringkat


def rata_proporsi_vokal(nama_latih: list[str]) -> float:
    """Rata-rata proporsi vokal (terhadap jumlah huruf) pada seluruh nama latih."""
    proporsi = []
    for nama in nama_latih:
        huruf = nama.replace(" ", "")
        proporsi.append(sum(1 for c in huruf if c in VOKAL) / len(huruf))
    return sum(proporsi) / len(proporsi)


def tahap_ranking(nama_lolos: list[str], keunikan: dict[str, float], model: RantaiMarkov,
                  rata_vokal: float) -> tuple[list[dict], list[float], dict]:
    """
    Tahap 3: hitung empat kriteria, bobot AHP, lalu skor dan peringkat SAW serta WP.
    Mengembalikan (baris hasil, bobot, info AHP).
    """
    nilai = []
    for nama in nama_lolos:
        huruf = nama.replace(" ", "")
        panjang = len(huruf)
        prop_vokal = sum(1 for c in huruf if c in VOKAL) / panjang
        gugus = len(re.findall(r"[^aeiou ]{3,}", nama))            # gugus >= 3 konsonan berurutan
        pengucapan = max(0.01, 1.0 - abs(prop_vokal - rata_vokal) - 0.2 * gugus)
        nilai.append([float(panjang), pengucapan, keunikan[nama], model.kewajaran(nama)])

    bobot, info = hitung_bobot_ahp(MATRIKS_AHP)
    saw = hitung_saw(nilai, bobot, JENIS_KRITERIA)
    wp = hitung_wp(nilai, bobot, JENIS_KRITERIA)
    rank_saw = beri_peringkat(saw, nama_lolos)
    rank_wp = beri_peringkat(wp, nama_lolos)

    baris = []
    for i, nama in enumerate(nama_lolos):
        baris.append({
            "nama": nama, "panjang": int(nilai[i][0]), "pengucapan": nilai[i][1],
            "keunikan": nilai[i][2], "kewajaran": nilai[i][3],
            "saw": saw[i], "peringkat_saw": rank_saw[i],
            "wp": wp[i], "peringkat_wp": rank_wp[i],
        })
    return baris, bobot, info


# ============================================================================
# REVISI: METRIK DIVERSITY DAN PENGUJIAN HOLDOUT
# ============================================================================

def hitung_diversity(nama_list: list[str]) -> dict:
    """
    REVISI: ukur keragaman sekumpulan nama (huruf, spasi tidak dihitung sebagai huruf).
    - jumlah_bigram_unik: jumlah bigram karakter berbeda di seluruh nama
    - entropi_huruf     : entropi Shannon (bit) distribusi huruf
    - rata_panjang      : rata-rata panjang nama dalam huruf
    - rasio_vokal       : rata-rata proporsi vokal per nama
    - rasio_unik        : jumlah nama unik / jumlah nama total
    Catatan: jumlah_bigram_unik dan entropi bergantung pada ukuran sampel, jadi bandingkan
    antar run pada kelompok yang sama.
    """
    if not nama_list:
        return {"jumlah_bigram_unik": 0, "entropi_huruf": 0.0, "rata_panjang": 0.0,
                "rasio_vokal": 0.0, "rasio_unik": 0.0}
    bigram: set[str] = set()
    cacah: Counter = Counter()
    panjang: list[int] = []
    vokal: list[float] = []
    for nama in nama_list:
        nama = nama.lower()
        bigram.update(nama[i:i + 2] for i in range(len(nama) - 1))
        huruf = nama.replace(" ", "")
        cacah.update(huruf)
        panjang.append(len(huruf))
        vokal.append(sum(1 for c in huruf if c in VOKAL) / len(huruf) if huruf else 0.0)
    total = sum(cacah.values())
    entropi = -sum((n / total) * math.log2(n / total) for n in cacah.values()) if total else 0.0
    return {
        "jumlah_bigram_unik": len(bigram),
        "entropi_huruf": entropi,
        "rata_panjang": sum(panjang) / len(panjang),
        "rasio_vokal": sum(vokal) / len(vokal),
        "rasio_unik": len({n.lower() for n in nama_list}) / len(nama_list),
    }


def cetak_metrik(metrik: dict[str, dict], jumlah: dict[str, int]) -> None:
    """REVISI: cetak metrik diversity tiap kelompok dalam satu tabel."""
    judul = {"data_asli": "Data asli", "lolos_filter": "Lolos filter", "top20_saw": f"Top {TOP_N} SAW"}
    print("\n[Metrik diversity]")
    print(f"{'Kelompok':<14}{'Jml':>5}{'Bigram':>8}{'Entropi':>9}{'RataPjg':>9}{'Vokal':>8}{'Unik':>7}")
    print("-" * 60)
    for kunci, m in metrik.items():
        print(f"{judul.get(kunci, kunci):<14}{jumlah[kunci]:>5}{m['jumlah_bigram_unik']:>8}"
              f"{m['entropi_huruf']:>9.4f}{m['rata_panjang']:>9.3f}{m['rasio_vokal']:>8.4f}{m['rasio_unik']:>7.3f}")


def jarak_rata_ke_holdout(kandidat: list[str], uji: list[str]) -> float:
    """REVISI: rata-rata jarak Levenshtein tiap kandidat ke data uji terdekatnya."""
    total = 0
    for kand in kandidat:
        total += min(jarak_levenshtein(kand, u) for u in uji)
    return total / len(kandidat)


def simpan_metrik(jalur: str, catatan: dict) -> str:
    """
    REVISI: tambahkan satu run ke metrik.json (objek: nama run -> catatan) tanpa menimpa
    run lama, sehingga tren antar run bisa dilihat. Mengembalikan nama run (run_001, ...).
    """
    semua: dict = {}
    if os.path.exists(jalur):
        semua = _baca_json(jalur)
        if not isinstance(semua, dict):
            raise GeneratorError(f"Format '{jalur}' tidak sesuai: harus objek dengan satu entri per run. "
                                 "Berkas tidak diubah.")
    n = len(semua) + 1
    while f"run_{n:03d}" in semua:
        n += 1
    nama_run = f"run_{n:03d}"
    semua[nama_run] = catatan
    _tulis_atomik(jalur, json.dumps(semua, ensure_ascii=False, indent=2) + "\n")
    return nama_run


# ============================================================================
# OUTPUT: TABEL, CSV, DAN trained_name_list.json
# ============================================================================

def cetak_tabel_top(judul: str, baris: list[dict], kunci_skor: str, kunci_rank: str) -> None:
    """Cetak tabel top-N (peringkat, nama, skor)."""
    urut = sorted(baris, key=lambda r: r[kunci_rank])[:TOP_N]
    print(f"\n{judul}")
    print(f"{'Peringkat':>9}  {'Nama':<18}  {'Skor':>10}")
    print("-" * 42)
    for r in urut:
        print(f"{r[kunci_rank]:>9}  {r['nama'].title():<18}  {r[kunci_skor]:>10.6f}")


def _tulis_atomik(jalur: str, teks: str) -> None:
    """Tulis berkas lewat berkas sementara lalu ganti, supaya tidak setengah tertulis."""
    folder = os.path.dirname(os.path.abspath(jalur))
    os.makedirs(folder, exist_ok=True)
    fd, sementara = tempfile.mkstemp(dir=folder, prefix=".tmp_", suffix=".json")
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as f:
            f.write(teks)
        os.replace(sementara, jalur)
    except BaseException:
        if os.path.exists(sementara):
            os.remove(sementara)
        raise


def simpan_csv(jalur: str, baris: list[dict]) -> None:
    """Simpan semua kandidat yang lolos filter ke CSV (urut peringkat SAW)."""
    folder = os.path.dirname(os.path.abspath(jalur))
    os.makedirs(folder, exist_ok=True)
    kolom = ["nama", "panjang", "skor_pengucapan", "skor_keunikan", "skor_kewajaran",
             "skor_saw", "peringkat_saw", "skor_wp", "peringkat_wp"]
    with open(jalur, "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(kolom)
        for r in sorted(baris, key=lambda r: r["peringkat_saw"]):
            w.writerow([r["nama"].title(), r["panjang"],
                        f"{r['pengucapan']:.6f}", f"{r['keunikan']:.6f}", f"{r['kewajaran']:.6f}",
                        f"{r['saw']:.6f}", r["peringkat_saw"],
                        f"{r['wp']:.8f}", r["peringkat_wp"]])


def simpan_trained(jalur: str, entri_asli: list[Entri], generated: list[dict]) -> tuple[int, int]:
    """
    REVISI: tulis trained_name_list.json sebagai array objek (satu objek per baris).
    - nama asli : {"nama", "sumber": "asli", "kelompok", "gender", "generasi": 0}
    - generated : {"nama", "sumber": "generated", "generasi", "skor_saw", "skor_wp", "lolos_filter"}
                  (daftar kosong bila --save-generated tidak aktif)
    Tidak ada field lain. Mengembalikan (jumlah asli, jumlah generated) yang ditulis.
    """
    jaga_hanya_asli(entri_asli, "simpan_trained")
    objek: list[dict] = []
    for e in entri_asli:
        objek.append({"nama": e.nama, "sumber": SUMBER_ASLI, "kelompok": e.kelompok,
                      "gender": e.gender, "generasi": 0})
    for g in generated:
        objek.append({"nama": g["nama"], "sumber": SUMBER_GENERATED, "generasi": g["generasi"],
                      "skor_saw": g["skor_saw"], "skor_wp": g["skor_wp"], "lolos_filter": g["lolos_filter"]})
    isi = "[]" if not objek else "[\n" + ",\n".join("  " + json.dumps(o, ensure_ascii=False) for o in objek) + "\n]"
    _tulis_atomik(jalur, isi + "\n")
    return len(entri_asli), len(generated)


def bangun_generated(kandidat: list[str], baris: list[dict], nama_lolos: list[str], generasi: int) -> list[dict]:
    """
    REVISI: susun catatan hasil generasi run ini (hanya untuk penelusuran).
    Kandidat yang gugur di filter diberi skor_saw/skor_wp null dan lolos_filter false.
    """
    lolos = set(nama_lolos)
    skor = {r["nama"]: (r["saw"], r["wp"]) for r in baris}
    hasil = []
    for nama in kandidat:
        saw, wp = skor.get(nama, (None, None))
        hasil.append({"nama": nama, "sumber": SUMBER_GENERATED, "generasi": generasi,
                      "skor_saw": round(saw, 8) if saw is not None else None,
                      "skor_wp": round(wp, 8) if wp is not None else None,
                      "lolos_filter": nama in lolos})
    return hasil


def _coba_simpan(label: str, fungsi) -> bool:
    """REVISI: jalankan satu langkah penyimpanan; galat jadi peringatan agar langkah lain tetap jalan."""
    try:
        fungsi()
        return True
    except GeneratorError as e:
        print(f"[Output] PERINGATAN: '{label}' tidak diperbarui. {e}", file=sys.stderr)
    except OSError as e:
        print(f"[Output] PERINGATAN: '{label}' gagal ditulis ({e}).", file=sys.stderr)
    return False


def _baca_generated_lama(jalur: str, nama_pool: set[str]) -> list[dict]:
    """
    REVISI: pada run tanpa --update, baca trained_name_list.json lama hanya untuk
    (a) catatan generated lama dan (b) memberi tahu bila ada nama asli yang akan tertimpa.
    Tidak ada isi file ini yang masuk ke data latih.
    """
    try:
        lama = muat_trained(jalur)
    except GeneratorError:
        print(f"[Info] '{jalur}' lama tidak terbaca (format tidak sesuai) dan akan ditimpa berkas baru.")
        return []
    if lama is None:
        return []
    hilang = sum(1 for e in lama.asli if e.nama not in nama_pool)
    if hilang:
        print(f"[Info] '{jalur}' lama berisi {hilang} nama asli yang tidak ada di data ini "
              "(mis. dari --tambah); ikut tertimpa. Pakai --update untuk mempertahankannya.")
    return lama.generated


# ============================================================================
# ARGUMEN CLI DAN ALUR UTAMA
# ============================================================================

def buat_parser() -> argparse.ArgumentParser:
    """Definisi opsi baris perintah."""
    p = argparse.ArgumentParser(
        description="Generator nama orang: Markov chain + KNN + Naive Bayes + ranking AHP/SAW/WP.",
        epilog=__doc__.split("Contoh pemakaian:")[-1].rstrip(),
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    # REVISI: default None agar --json yang diabaikan oleh --update bisa dideteksi
    p.add_argument("--json", default=None,
                   help="berkas data latih asli (default: daftar_nama.json); diabaikan bila --update")
    p.add_argument("--label", choices=["gender", "kelompok", "none"], default="gender",
                   help="sumber label Naive Bayes (default: gender)")
    p.add_argument("--target", help="kelas target; wajib jika --label bukan none")
    p.add_argument("--count", type=int, default=500, help="jumlah kandidat generasi (default: 500)")
    p.add_argument("--order", type=int, default=2, help="orde Markov chain (default: 2)")
    p.add_argument("--threshold", type=int, default=2,
                   help="jarak Levenshtein minimal ke nama latih (default: 2)")
    p.add_argument("--k", type=int, default=5, help="jumlah tetangga KNN untuk skor keunikan (default: 5)")
    p.add_argument("--out", default="hasil.csv", help="berkas CSV keluaran (default: hasil.csv)")
    p.add_argument("--seed", type=int, default=42, help="seed acak (default: 42)")
    p.add_argument("--demo", action="store_true",
                   help="pakai dataset contoh tertanam (tidak menulis trained_name_list.json, holdout.json, metrik.json)")
    # REVISI: --update kini hanya membaca nama ASLI dari trained_name_list.json
    p.add_argument("-update", "--update", action="store_true", dest="update",
                   help="data latih = entri 'asli' di trained_name_list.json (+ --tambah); "
                        "hasil generasi tidak pernah dipakai")
    p.add_argument("--trained", default="trained_name_list.json",
                   help="berkas data asli/penelusuran (default: trained_name_list.json)")
    # REVISI: argumen baru
    p.add_argument("--tambah", metavar="BERKAS",
                   help="(bersama --update) nama asli baru: .txt satu nama per baris, atau .json "
                        "berstruktur seperti daftar_nama.json")
    p.add_argument("--save-generated", action="store_true", dest="save_generated",
                   help="simpan kandidat hasil generasi ke trained_name_list.json (hanya penelusuran)")
    p.add_argument("--metrik", default=FILE_METRIK_DEFAULT,
                   help="berkas metrik diversity antar run (default: metrik.json)")
    p.add_argument("--holdout", type=float, default=0.0, metavar="PERSEN",
                   help="persen nama asli yang ditahan sebagai data uji (default: 0, contoh: 20)")
    return p


def validasi_argumen(p: argparse.ArgumentParser, a: argparse.Namespace) -> None:
    """Periksa nilai opsi; keluar dengan pesan bila tidak valid."""
    if a.count < 1:
        p.error("--count harus >= 1")
    if a.order < 1:
        p.error("--order harus >= 1")
    if a.threshold < 0:
        p.error("--threshold harus >= 0")
    if a.k < 1:
        p.error("--k harus >= 1")
    if a.label != "none" and not a.target and not a.demo:
        p.error("--target wajib diisi jika --label bukan none (contoh: --label gender --target perempuan)")
    # REVISI: validasi argumen baru
    if a.tambah and not a.update:
        p.error("--tambah hanya bisa dipakai bersama --update")
    if not 0 <= a.holdout <= MAKS_HOLDOUT_PERSEN:
        p.error(f"--holdout harus antara 0 dan {MAKS_HOLDOUT_PERSEN} (persen)")


def jalankan(a: argparse.Namespace) -> int:
    """Alur utama: muat nama asli -> (holdout) -> generasi -> filter -> ranking -> metrik -> keluaran."""
    random.seed(a.seed)
    mode = a.label
    target = a.target.strip().lower() if a.target else None
    status = 0                                  # REVISI: 1 bila ada penyimpanan yang gagal
    generated_lama: list[dict] = []             # REVISI: catatan generasi lama; tidak pernah melatih

    # --- Data latih (REVISI: hanya nama asli; hasil generasi tidak pernah masuk) ---
    if a.demo:
        if mode == "kelompok":
            raise GeneratorError("--demo hanya menyediakan label gender. Pakai --label gender atau --label none.")
        entri = data_demo()
        if mode == "gender" and not target:
            target = "perempuan"
        print(f"[Data] Mode --demo: {len(entri)} nama contoh berlabel gender")
        if a.update or a.save_generated:        # REVISI
            print("       Catatan: --update, --tambah, dan --save-generated diabaikan pada --demo")
    elif a.update:                              # REVISI: baca trained_name_list.json (asli saja)
        if a.json:
            print("[Update] Catatan: --json diabaikan karena --update memakai trained_name_list.json")
        entri, info_update, generated_lama = muat_data_update(a.trained, a.tambah)
        cetak_ringkasan_update(a.trained, a.tambah, info_update, entri)
    else:
        jalur_json = a.json or JSON_DEFAULT     # REVISI: default diterapkan di sini
        entri, ringkasan = muat_dataset(jalur_json)
        cetak_ringkasan_data(jalur_json, ringkasan, entri)
        generated_lama = _baca_generated_lama(a.trained, {e.nama for e in entri})   # REVISI

    # --- REVISI: pisahkan data uji (holdout) sebelum training apa pun ---
    pool_asli = list(entri)                     # seluruh nama asli (latih + uji) untuk trained_name_list.json
    entri, uji = pisah_holdout(entri, a.holdout)
    if uji:
        print(f"[Holdout] {len(uji)} dari {len(pool_asli)} nama asli ditahan sebagai data uji "
              f"({a.holdout:g} persen); {len(entri)} nama dipakai melatih")
    jaga_hanya_asli(entri, "jalankan")          # REVISI: guard proporsi sebelum training

    # --- Naive Bayes (dilatih lebih awal agar galat --target muncul sebelum generasi) ---
    nb = None
    if mode != "none":
        nb = siapkan_naive_bayes(entri, mode, target)
    elif target:
        print("[Info] --target diabaikan karena --label none")

    nama_latih = [e.nama for e in entri]
    set_latih = set(nama_latih)

    # --- Tahap 1: generasi ---
    model = RantaiMarkov(a.order)
    model.latih(nama_latih)
    kandidat, percobaan = tahap_generasi(model, a.count, set_latih)
    print(f"\n[Tahap 1] Kandidat awal (Markov orde {a.order}, unik, bukan nama dataset): {len(kandidat)} "
          f"dari {percobaan} percobaan")
    if len(kandidat) < a.count:
        print(f"          Peringatan: hanya {len(kandidat)} dari {a.count} kandidat yang tercapai dalam batas percobaan "
              "(data latih terlalu kecil/orde terlalu tinggi?)")
    if not kandidat:
        raise GeneratorError("Tidak ada kandidat yang berhasil dibangkitkan. "
                             "Coba turunkan --order atau tambah data latih.")

    # --- Tahap 2a: KNN ---
    keunikan = tahap_filter_knn(kandidat, nama_latih, a.threshold, a.k)
    print(f"[Tahap 2] Setelah KNN (jarak terdekat >= {a.threshold}): {len(keunikan)}")
    if not keunikan:
        raise GeneratorError("Tidak ada kandidat yang lolos filter KNN. "
                             "Coba turunkan --threshold atau naikkan --count.")

    # --- Tahap 2b: Naive Bayes ---
    nama_lolos = list(keunikan)
    if nb is not None:
        nama_lolos = [n for n in nama_lolos if nb.prediksi(n) == target]
        print(f"[Tahap 2] Setelah Naive Bayes (kelas '{target}'): {len(nama_lolos)}")
        if not nama_lolos:
            raise GeneratorError(f"Tidak ada kandidat yang diprediksi masuk kelas '{target}'. "
                                 "Coba naikkan --count atau turunkan --threshold.")
    else:
        print("[Tahap 2] Naive Bayes dilewati (--label none)")

    # --- Tahap 3: ranking ---
    baris, bobot, info = tahap_ranking(nama_lolos, keunikan, model, rata_proporsi_vokal(nama_latih))
    print("\n[Tahap 3] Bobot AHP: " + ", ".join(f"{n}={w:.4f}" for n, w in zip(NAMA_KRITERIA, bobot)))
    print(f"          lambda_max={info['lambda_max']:.4f}  CI={info['ci']:.4f}  CR={info['cr']:.4f}")
    if info["cr"] >= 0.1:
        print("          PERINGATAN: CR >= 0,1 - matriks perbandingan tidak konsisten, "
              "sebaiknya revisi MATRIKS_AHP.")

    cetak_tabel_top(f"Top {TOP_N} SAW", baris, "saw", "peringkat_saw")
    cetak_tabel_top(f"Top {TOP_N} WP", baris, "wp", "peringkat_wp")

    # --- REVISI: metrik diversity (data asli, lolos filter, top 20 SAW) dan jarak ke data uji ---
    top20 = [r["nama"] for r in sorted(baris, key=lambda r: r["peringkat_saw"])[:TOP_N]]
    kelompok_nama = {"data_asli": nama_latih, "lolos_filter": nama_lolos, "top20_saw": top20}
    metrik = {k: hitung_diversity(v) for k, v in kelompok_nama.items()}
    jumlah = {k: len(v) for k, v in kelompok_nama.items()}
    cetak_metrik(metrik, jumlah)
    jarak_uji = None
    if uji:
        jarak_uji = jarak_rata_ke_holdout(nama_lolos, uji)
        print(f"\n[Holdout] Jarak Levenshtein rata-rata kandidat lolos filter ke data uji terdekat: "
              f"{jarak_uji:.4f} (kandidat={len(nama_lolos)}, data uji={len(uji)})")

    # --- Keluaran ---
    simpan_csv(a.out, baris)
    print(f"\n[Output] {len(baris)} kandidat lolos filter disimpan ke '{a.out}'")

    generasi = None                             # REVISI
    generated_tersimpan: list[dict] = []        # REVISI
    trained_ok = False                          # REVISI
    if a.demo:
        print("[Output] Mode --demo: trained_name_list.json, holdout.json, dan metrik.json tidak ditulis")
    else:
        # REVISI: trained_name_list.json = nama asli; hasil generasi hanya bila --save-generated
        if a.save_generated:
            generasi = max((g["generasi"] for g in generated_lama), default=0) + 1
            generated_tersimpan = generated_lama + bangun_generated(kandidat, baris, nama_lolos, generasi)
        elif generated_lama:
            print(f"[Output] Catatan: {len(generated_lama)} entri generated lama tidak ikut disimpan "
                  "(tanpa --save-generated)")
        trained_ok = _coba_simpan(a.trained, lambda: simpan_trained(a.trained, pool_asli, generated_tersimpan))
        if trained_ok:
            print(f"[Output] '{a.trained}': {len(pool_asli)} nama asli, {len(generated_tersimpan)} entri generated")
        else:
            status = 1

        # REVISI: holdout.json
        if uji:
            if _coba_simpan(FILE_HOLDOUT, lambda: simpan_holdout(FILE_HOLDOUT, uji)):
                print(f"[Output] {len(uji)} nama data uji disimpan ke '{FILE_HOLDOUT}'")
            else:
                status = 1

        # REVISI: metrik.json (ditambahkan sebagai run baru)
        def _simpan_metrik() -> None:
            catatan = {
                "waktu": datetime.now().isoformat(timespec="seconds"),
                "parameter": {
                    "sumber_data": "trained_name_list.json" if a.update else (a.json or JSON_DEFAULT),
                    "label": mode, "target": target, "count": a.count, "order": a.order,
                    "threshold": a.threshold, "k": a.k, "seed": a.seed,
                    "holdout_persen": a.holdout, "save_generated": a.save_generated, "generasi": generasi,
                },
                "jumlah_nama": jumlah,
            }
            for kunci, m in metrik.items():
                catatan[kunci] = {n: (round(v, 6) if isinstance(v, float) else v) for n, v in m.items()}
            if jarak_uji is not None:
                catatan["holdout"] = {"jumlah_data_uji": len(uji),
                                      "rata_jarak_levenshtein_terdekat": round(jarak_uji, 6)}
            nama_run = simpan_metrik(a.metrik, catatan)
            print(f"[Output] Metrik run ini ditambahkan ke '{a.metrik}' sebagai {nama_run}")

        if not _coba_simpan(a.metrik, _simpan_metrik):
            status = 1

    # --- REVISI: ringkasan akhir ---
    print("\n=== Ringkasan ===")
    print(f"Nama asli dipakai melatih : {len(entri)}" + (f" (data uji ditahan: {len(uji)})" if uji else ""))
    print(f"Kandidat lolos filter     : {len(nama_lolos)}")
    if a.save_generated and trained_ok:
        print(f"Entri generated disimpan  : {len(generated_tersimpan)} "
              f"(baru: {len(kandidat)}, generasi {generasi})")
    return status


def main(argv: list[str] | None = None) -> int:
    """Titik masuk: parsing argumen dan penanganan galat tanpa traceback."""
    if sys.version_info < (3, 10):
        print("[GALAT] Program ini butuh Python 3.10 atau lebih baru.", file=sys.stderr)
        return 1
    parser = buat_parser()
    args = parser.parse_args(argv)
    validasi_argumen(parser, args)
    try:
        return jalankan(args)
    except GeneratorError as e:
        print(f"\n[GALAT] {e}", file=sys.stderr)
        return 1
    except OSError as e:
        print(f"\n[GALAT] Gagal membaca/menulis berkas: {e}", file=sys.stderr)
        return 1
    except KeyboardInterrupt:
        print("\nDibatalkan oleh pengguna.", file=sys.stderr)
        return 130
    except Exception as e:  # jaring pengaman terakhir agar tidak keluar traceback
        print(f"\n[GALAT] Kesalahan tak terduga ({type(e).__name__}): {e}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
