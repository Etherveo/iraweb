# Generator Biodata Pembeli Simulasi (M4)

Generator biodata untuk panel Developer (SIM-02/05/06): menghasilkan daftar
biodata pembeli simulasi lengkap (nama, email, username, telepon, gender,
tanggal lahir, alamat) dari dataset di `data/`.

## File

| File | Keterangan |
| --- | --- |
| `generate_biodata.py` | CLI wrapper + modul importable (file ini yang dipakai panel). |
| `name_generator.py` | Generator nama (Markov orde-2 + KNN + Naive Bayes + ranking SAW), dilatih HANYA pada nama asli. Salinan dari `about-script/generator/name-generator.py` (nama diubah `-` -> `_` supaya bisa di-import); isi tidak diubah. |
| `data/*.json` | Dataset (salinan dari `about-script/generator/`): `daftar_nama_indonesia.json` (165 nama), `daftar_jalan.json` (101 jalan), `daftar_provinsi.json` (38 provinsi), `daftar_kota_kabupaten.json` (514 kota/kabupaten), `daftar_wilayah_jawa.json`, `daftar_wilayah_kalimantan.json`, `daftar_wilayah_sulawesi.json` (kecamatan/kelurahan/kode pos, 17 provinsi). |

## Dependensi

Stdlib only. Python >= 3.10. Tidak ada akses jaringan.

## Cara menjalankan (CLI)

```bash
# 5 biodata, deterministik dengan seed 42
python3 generate_biodata.py --count 5 --seed 42

# 10 biodata mulai dari indeks ke-5 (offset), seed yang sama
python3 generate_biodata.py --count 10 --seed 42 --offset 5

# 3 biodata acak (seed dibangkitkan dan dilaporkan di JSON)
python3 generate_biodata.py --count 3 --mode random

# Dataset di tempat lain
python3 generate_biodata.py --count 5 --seed 42 --data-dir /jalan/ke/data
```

Keluaran: SATU objek JSON ke stdout:

```json
{"count": 5, "items": [...], "offset": 0, "seed": 42}
```

Tiap item:

```json
{
  "address": {
    "address_type": "Rumah",
    "city": "Kota Gorontalo",
    "district": "Kota Selatan",
    "postal_code": "96115",
    "province": "Gorontalo",
    "recipient_name": "Siti Aminah",
    "recipient_phone": "081234567890",
    "street_address": "Jl. Ahmad Yani No. 12",
    "village": "Limba B"
  },
  "birthdate": "1990-05-14",
  "email": "sitiaminah@sim.ira-stationery.test",
  "gender": "perempuan",
  "name": "Siti Aminah",
  "phone": "081234567890",
  "username": "sitiaminah01"
}
```

Galat (mis. `--count 0`, dataset hilang) -> pesan ke stderr + kode keluar
non-0; stdout tetap kosong.

## Cara memakai sebagai modul

```python
from generate_biodata import generate_biodata

hasil = generate_biodata(count=5, offset=0, seed=42)
# hasil == {"seed": 42, "count": 5, "offset": 0, "items": [...]}
```

## Determinisme (SIM-05)

Seed + offset + count yang sama selalu menghasilkan biodata yang identik.
Aturannya:

- Pool nama (per gender) dan pool nama wilayah dibangun sekali per run dari
  `random` global yang di-seed sekali — pool hanya fungsi dari seed + data
  latih, bukan dari count/offset.
- Tiap item memakai SATU instance `random.Random(seed)` dengan urutan field
  tetap (gender -> nama -> telepon -> tanggal lahir -> alamat).
- Semantik offset: dibangkitkan `offset + count` entri dalam satu aliran,
  dikembalikan irisan `[offset:offset+count]`.
- Tanggal lahir memakai rentang TETAP 1966-01-01 s.d. 2009-12-31 (umur 17–60
  untuk run 2026), bukan umur relatif terhadap tanggal eksekusi — supaya run
  yang diulang di tanggal berbeda tetap identik.
- Nama hasil generasi difilter kelayakannya (total huruf >= 6, tiap kata
  >= 3 huruf — batas diambil dari data latih itu sendiri) dan diurutkan
  menurut kewajaran (plausibilitas Markov) menurun, bukan peringkat SAW:
  peringkat SAW memberi bobot besar pada "pendek + unik" sehingga puncak
  pool justru fragmen 3 huruf ("ruq", "far"). Filter + urutan ini adalah
  fungsi deterministik dari pool, jadi determinisme run tidak berubah.
- Nama hasil generasi TIDAK PERNAH dipakai sebagai data latih (hanya nama
  asli dari `daftar_nama_indonesia.json` yang melatih Markov/KNN/Naive Bayes).

## Email, username, telepon

- Email wajib berdomain `sim.ira-stationery.test` (SIM-07, domain tercadang).
  JANGAN pakai `@contoh.test` — domain itu hanya untuk script mass-data
  mandiri (P6). Email dijamin unik di dalam batch: tabrakan slug -> akhiran
  2 digit berurutan (`sutrisno@...`, `sutrisno01@...`, ...).
- Username = slug nama + 2 digit berurutan per slug (`sutrisno01`,
  `sutrisno02`, ...), unik di dalam batch.
- Telepon = `08` + 8–11 digit (digit pertama setelah `08` adalah 1–9).

## Alamat

Satu alamat per pembeli (penerima = nama + telepon pembeli, jenis `Rumah`).
Provinsi dipilih dari 38 provinsi; kota/kabupaten dari
`daftar_kota_kabupaten.json`; jalan dari `daftar_jalan.json` + `No. 1–200`
(+ huruf A–C kadang-kadang).

- Bila provinsi tercakup file wilayah (Jawa/Kalimantan/Sulawesi, 17
  provinsi), kecamatan/kelurahan/kode pos diambil dari file.
- Bila tidak (21 provinsi lain), kecamatan/kelurahan dibangkitkan via
  Markov orde-2 (basis 220 nama wilayah unik) dan kode pos dari awalan
  kota/provinsi yang dikenal + digit acak. Bagian yang dibangkitkan dicatat
  di stderr sebagai ringkasan hitungan (bukan di database — tidak ada
  kolomnya), contoh:
  `[Alamat] 2 dari 5 alamat memakai kecamatan/kelurahan/kode pos hasil generasi (Markov) — bukan data resmi.`

## Seed & mode (aturan U-02)

- Default panel: deterministik, seed `20261101`. Nilai itu dipakai panel/PHP
  sebagai default form dan SELALU diteruskan eksplisit sebagai `--seed`
  saat memanggil wrapper ini.
- CLI ini: bila `--seed` diberikan, run deterministik dengan seed itu. Bila
  `--seed` TIDAK diberikan, seed dibangkitkan acak dari entropi sistem dan
  dilaporkan di JSON (mode random). Untuk run deterministik dari CLI, selalu
  sertakan `--seed`.

## Pengujian manual

```bash
cd iraweb/python/biodata
python3 generate_biodata.py --count 5 --seed 42 > /tmp/a.json
python3 generate_biodata.py --count 5 --seed 42 > /tmp/b.json
cmp /tmp/a.json /tmp/b.json   # harus identik (determinisme)

python3 generate_biodata.py --count 10 --seed 42 --offset 5
# item-itemnya == item ke-6..10 dari --count 10 --seed 42 tanpa offset

python3 -c "from generate_biodata import generate_biodata; print(generate_biodata(2, seed=1)['items'][0]['name'])"
```
