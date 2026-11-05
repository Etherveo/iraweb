<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

// M2 catalog seed data.
// DATA CONTOH FIKTIF — bukan data penjualan Ira Stationery yang asli,
// bukan data toko yang sebenarnya. Nama, harga, dan stok di bawah hanya
// contoh untuk demo/pengujian, dan harus diganti admin lewat halaman
// Produk sebelum dipakai untuk laporan apa pun.
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Pulpen & Pensil' => [
                ['Pulpen Gel Hitam 0,5 mm', 5000, 120],
                ['Pulpen Gel Biru 0,5 mm', 5000, 85],
                ['Pensil 2B', 3000, 200],
                ['Pensil Mekanik 0,5 mm', 15000, 40],
                ['Spidol Whiteboard Hitam', 12000, 60],
            ],
            'Kertas & Buku' => [
                ['Buku Tulis 38 Lembar', 6000, 150],
                ['Buku Gambar A4', 10000, 90],
                ['Kertas HVS A4 1 Rim', 55000, 30],
                ['Kertas Origami 1 Pak', 8000, 70],
                ['Map Plastik Bening', 4000, 110],
            ],
            'Perlengkapan Kantor' => [
                ['Stapler Kecil', 25000, 25],
                ['Isi Stapler No. 10', 7000, 0], // contoh stok habis (PRD-06/SHP-01)
                ['Gunting Kantor', 18000, 45],
                ['Lem Stick', 9000, 80],
                ['Selotip Bening', 6000, 100],
            ],
            'Seni & Kreativitas' => [
                ['Pensil Warna 12 Warna', 35000, 35],
                ['Crayon 24 Warna', 45000, 20],
                ['Cat Air 12 Warna', 55000, 15],
                ['Kuas Lukis Set 5', 30000, 25],
            ],
            'Aksesoris Sekolah' => [
                ['Penggaris 30 cm', 5000, 130],
                ['Penghapus Putih', 2000, 160],
                ['Rautan Pensil', 3000, 90],
                ['Tas Pensil Kain', 20000, 50],
                // Produk contoh nonaktif: tidak tampil di katalog pembeli (SHP-04).
                ['Kotak Pensil Kayu Edisi Lama', 40000, 10, false],
            ],
        ];

        foreach ($data as $categoryName => $products) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName],
            );

            foreach ($products as $row) {
                [$name, $price, $stock] = $row;
                $active = $row[3] ?? true;

                $product = Product::firstOrCreate(
                    ['name' => $name],
                    [
                        'category_id' => $category->id,
                        'description' => 'Produk contoh fiktif untuk demo katalog.',
                        'price_idr' => $price,
                        'stock_on_hand' => $stock,
                        'stock_reserved' => 0,
                        'is_active' => $active,
                    ],
                );

                // Mirror PRD-01: initial stock > 0 gets one adjustment row.
                if ($stock > 0 && ! StockMovement::where('product_id', $product->id)->exists()) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'penyesuaian',
                        'delta_on_hand' => $stock,
                        'delta_reserved' => 0,
                        'order_id' => null,
                        'actor_type' => 'admin_toko',
                        'actor_id' => null,
                        'note' => 'Stok awal seeder M2 (data contoh fiktif): 0 → '.$stock.'.',
                        'origin' => 'normal',
                        'simulation_run_id' => null,
                        'created_at' => now(),
                    ]);
                }
            }
        }
    }
}
