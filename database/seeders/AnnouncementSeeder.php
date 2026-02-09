<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    /**
     * Seed announcements for core system features.
     */
    public function run(): void
    {
        $admin = User::where('role', UserRole::SuperAdmin->value)->first();

        if (! $admin) {
            return;
        }

        $announcements = [
            [
                'title' => 'Point of Sale (POS) Tersedia',
                'content' => 'Sistem kasir sudah aktif! Gunakan menu POS untuk memproses pesanan pelanggan dengan cepat. Mendukung varian produk, modifier, catatan item, dan pembayaran tunai. Akses melalui sidebar Operasional > POS / Kasir.',
                'type' => 'update',
                'published_at' => now()->subDays(14),
            ],
            [
                'title' => 'QR Code Self-Order untuk Pelanggan',
                'content' => 'Pelanggan kini bisa memesan langsung dari meja melalui QR code. Buat meja di menu Meja, cetak QR code-nya, dan letakkan di meja. Pesanan akan masuk otomatis ke sistem setelah dikonfirmasi oleh kasir.',
                'type' => 'update',
                'published_at' => now()->subDays(12),
            ],
            [
                'title' => 'Kitchen Display System (KDS) Aktif',
                'content' => 'Layar dapur real-time kini tersedia untuk memantau pesanan. Dapur dapat melihat pesanan masuk, mengubah status ke Diproses dan Siap. Akses melalui sidebar Dapur > Kitchen Display.',
                'type' => 'update',
                'published_at' => now()->subDays(10),
            ],
            [
                'title' => 'Manajemen Inventaris & Resep',
                'content' => 'Kelola bahan baku dan resep menu dari satu tempat. Tambahkan ingredient, buat resep untuk setiap menu item, dan pantau pergerakan stok. Sistem akan memberikan peringatan saat stok menipis.',
                'type' => 'update',
                'published_at' => now()->subDays(8),
            ],
            [
                'title' => 'Laporan Penjualan Harian & Mingguan',
                'content' => 'Fitur laporan sudah tersedia di menu Laporan. Lihat ringkasan penjualan harian, mingguan, dan bulanan. Termasuk data menu terlaris dan breakdown metode pembayaran.',
                'type' => 'update',
                'published_at' => now()->subDays(6),
            ],
            [
                'title' => 'Manajemen Menu & Modifier',
                'content' => 'Atur menu dengan kategori, varian ukuran (S/M/L), dan modifier (Extra Shot, Less Sugar, dll). Setiap kategori bisa memiliki gambar default yang otomatis digunakan menu item tanpa gambar.',
                'type' => 'info',
                'published_at' => now()->subDays(4),
            ],
            [
                'title' => 'Manajemen Staff & Peran',
                'content' => 'Sistem mendukung 7 peran pengguna: Owner, Manager, Kasir, Waiter, Kitchen, Customer, dan Super Admin. Setiap peran memiliki akses yang disesuaikan untuk keamanan operasional cafe Anda.',
                'type' => 'info',
                'published_at' => now()->subDays(2),
            ],
            [
                'title' => 'Selamat Datang di HsCaffeSystem!',
                'content' => 'Terima kasih telah menggunakan platform kami. Jelajahi semua fitur yang tersedia melalui menu sidebar. Jika ada pertanyaan atau kendala, hubungi admin melalui WhatsApp.',
                'type' => 'success',
                'published_at' => now(),
            ],
        ];

        foreach ($announcements as $data) {
            Announcement::create([
                ...$data,
                'is_active' => true,
                'created_by' => $admin->id,
            ]);
        }
    }
}
