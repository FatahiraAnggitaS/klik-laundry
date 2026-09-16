<?php

namespace App\Services\Dashboard;

use App\DTOs\Dashboard\DashboardPreviewData;
use App\Enums\UserRole;

final class GetDashboardPreviewService
{
    public function handle(UserRole $role): DashboardPreviewData
    {
        $content = match ($role) {
            UserRole::Customer => $this->customerContent(),
            UserRole::TenantOwner => $this->tenantContent(),
            UserRole::Driver => $this->driverContent(),
            UserRole::SuperUser => $this->superUserContent(),
        };

        return new DashboardPreviewData(
            activeRole: $role,
            roles: $this->roles(),
            navigation: $content['navigation'],
            hero: $content['hero'],
            metrics: $content['metrics'],
            focus: $content['focus'],
            workItems: $content['workItems'],
            milestones: $this->milestones(),
        );
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    private function roles(): array
    {
        return [
            ['value' => UserRole::Customer->value, 'label' => 'Customer', 'description' => 'Pesan dan lacak laundry'],
            ['value' => UserRole::TenantOwner->value, 'label' => 'Tenant owner', 'description' => 'Kelola operasional outlet'],
            ['value' => UserRole::Driver->value, 'label' => 'Driver', 'description' => 'Tangani pickup dan delivery'],
            ['value' => UserRole::SuperUser->value, 'label' => 'Super User', 'description' => 'Pantau kesehatan platform'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerContent(): array
    {
        return [
            'navigation' => [
                ['label' => 'Ringkasan', 'icon' => 'layout-dashboard'],
                ['label' => 'Pesanan saya', 'icon' => 'shopping-bag'],
                ['label' => 'Cari outlet', 'icon' => 'store'],
                ['label' => 'Alamat', 'icon' => 'map-pin'],
                ['label' => 'Bantuan', 'icon' => 'circle-help'],
            ],
            'hero' => [
                'eyebrow' => 'Selasa, 16 September',
                'title' => 'Laundry rapi, waktu tetap milikmu.',
                'description' => 'Pantau pickup, pembayaran, dan progres cucian dalam satu tempat yang tenang.',
                'primaryAction' => 'Buat pesanan',
                'secondaryAction' => 'Cari outlet terdekat',
            ],
            'metrics' => [
                ['label' => 'Pesanan aktif', 'value' => '2', 'change' => '1 sedang diproses', 'tone' => 'blue', 'icon' => 'shopping-bag'],
                ['label' => 'Siap diantar', 'value' => '1', 'change' => 'Pilih slot delivery', 'tone' => 'green', 'icon' => 'package-check'],
                ['label' => 'Pengeluaran bulan ini', 'value' => 'Rp286 rb', 'change' => '3 pesanan selesai', 'tone' => 'amber', 'icon' => 'wallet-cards'],
                ['label' => 'Outlet tersimpan', 'value' => '4', 'change' => 'Dalam radius layanan', 'tone' => 'violet', 'icon' => 'store'],
            ],
            'focus' => [
                'label' => 'Berikutnya untukmu',
                'title' => 'Pilih slot delivery',
                'description' => 'Pesanan KL-240916-014 dari Bening Laundry sudah selesai dan siap diantar.',
                'meta' => 'Batas respons: hari ini, 18.00 WIB',
                'progress' => 78,
                'action' => 'Pilih jadwal',
                'icon' => 'clock',
            ],
            'workItems' => [
                ['id' => 'KL-240916-014', 'title' => 'Cuci komplit · 4,2 kg', 'subtitle' => 'Bening Laundry · Tebet', 'status' => 'Siap diantar', 'statusTone' => 'green', 'meta' => 'Rp78.000'],
                ['id' => 'KL-240915-008', 'title' => 'Paket express · 2 unit', 'subtitle' => 'Klik Clean · Pancoran', 'status' => 'Diproses', 'statusTone' => 'blue', 'meta' => 'Rp64.000'],
                ['id' => 'KL-240911-027', 'title' => 'Cuci kering · 3,5 kg', 'subtitle' => 'Sora Laundry · Kemang', 'status' => 'Selesai', 'statusTone' => 'neutral', 'meta' => 'Rp56.000'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantContent(): array
    {
        return [
            'navigation' => [
                ['label' => 'Ringkasan', 'icon' => 'layout-dashboard'],
                ['label' => 'Pesanan', 'icon' => 'shopping-bag'],
                ['label' => 'Outlet & paket', 'icon' => 'store'],
                ['label' => 'Driver', 'icon' => 'route'],
                ['label' => 'Keuangan', 'icon' => 'wallet-cards'],
            ],
            'hero' => [
                'eyebrow' => 'Operasional tenant · 3 outlet',
                'title' => 'Hari sibuk, tetap terkendali.',
                'description' => 'Prioritaskan order yang butuh tindakan dan jaga ritme seluruh outlet dari satu dashboard.',
                'primaryAction' => 'Tambah order manual',
                'secondaryAction' => 'Lihat semua pesanan',
            ],
            'metrics' => [
                ['label' => 'Pesanan aktif', 'value' => '24', 'change' => '+8 sejak pagi', 'tone' => 'blue', 'icon' => 'shopping-bag'],
                ['label' => 'SLA hari ini', 'value' => '94%', 'change' => '2 order perlu perhatian', 'tone' => 'green', 'icon' => 'shield-check'],
                ['label' => 'Omzet hari ini', 'value' => 'Rp2,48 jt', 'change' => '+12,4% dari kemarin', 'tone' => 'amber', 'icon' => 'wallet-cards'],
                ['label' => 'Driver tersedia', 'value' => '6/8', 'change' => '2 sedang bertugas', 'tone' => 'violet', 'icon' => 'route'],
            ],
            'focus' => [
                'label' => 'Perlu tindakan',
                'title' => 'Konfirmasi berat final',
                'description' => 'Tiga pesanan per-kg sudah tiba di outlet dan menunggu penimbangan sebelum invoice dibuat.',
                'meta' => 'Tertua menunggu 18 menit',
                'progress' => 58,
                'action' => 'Buka antrean timbang',
                'icon' => 'scale',
            ],
            'workItems' => [
                ['id' => 'KL-240916-021', 'title' => 'Nadia Putri · Cuci komplit', 'subtitle' => 'Outlet Tebet · Pickup selesai 10.42', 'status' => 'Menunggu berat', 'statusTone' => 'amber', 'meta' => 'Est. 5 kg'],
                ['id' => 'KL-240916-019', 'title' => 'Dimas Arga · Express', 'subtitle' => 'Outlet Pancoran · SLA 2 jam', 'status' => 'Diproses', 'statusTone' => 'blue', 'meta' => 'Rp96.000'],
                ['id' => 'KL-240916-014', 'title' => 'Rani Pratiwi · Cuci komplit', 'subtitle' => 'Outlet Tebet · Slot 14.00–16.00', 'status' => 'Siap diantar', 'statusTone' => 'green', 'meta' => 'Rp78.000'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function driverContent(): array
    {
        return [
            'navigation' => [
                ['label' => 'Ringkasan', 'icon' => 'layout-dashboard'],
                ['label' => 'Penawaran tugas', 'icon' => 'bell'],
                ['label' => 'Tugas saya', 'icon' => 'route'],
                ['label' => 'Komisi', 'icon' => 'wallet-cards'],
                ['label' => 'Bantuan', 'icon' => 'circle-help'],
            ],
            'hero' => [
                'eyebrow' => 'Status tersedia · Area Jakarta Selatan',
                'title' => 'Rute jelas, tugas selesai tepat waktu.',
                'description' => 'Semua pickup dan delivery hari ini tersusun berdasarkan prioritas waktumu.',
                'primaryAction' => 'Lihat penawaran baru',
                'secondaryAction' => 'Atur ketersediaan',
            ],
            'metrics' => [
                ['label' => 'Tugas hari ini', 'value' => '5', 'change' => '3 sudah selesai', 'tone' => 'blue', 'icon' => 'route'],
                ['label' => 'Tugas aktif', 'value' => '1', 'change' => 'Pickup sebelum 11.30', 'tone' => 'green', 'icon' => 'clock'],
                ['label' => 'Komisi minggu ini', 'value' => 'Rp185 rb', 'change' => 'Rp75 rb belum dibayar', 'tone' => 'amber', 'icon' => 'wallet-cards'],
                ['label' => 'Tepat waktu', 'value' => '96%', 'change' => '24 tugas terakhir', 'tone' => 'violet', 'icon' => 'shield-check'],
            ],
            'focus' => [
                'label' => 'Tugas aktif',
                'title' => 'Pickup di Tebet Barat',
                'description' => 'Ambil laundry dan antar ke Bening Laundry. Detail kontak terlihat selama tugas aktif.',
                'meta' => 'Slot 09.30–11.30 · sekitar 1,8 km',
                'progress' => 42,
                'action' => 'Buka detail tugas',
                'icon' => 'route',
            ],
            'workItems' => [
                ['id' => 'PU-0916-032', 'title' => 'Pickup · Tebet Barat', 'subtitle' => 'Ke Bening Laundry · 1,8 km', 'status' => 'Dalam perjalanan', 'statusTone' => 'blue', 'meta' => 'Rp18.000'],
                ['id' => 'DL-0916-018', 'title' => 'Delivery · Pancoran', 'subtitle' => 'Dari Klik Clean · slot 13.00–15.00', 'status' => 'Berikutnya', 'statusTone' => 'amber', 'meta' => 'Rp20.000'],
                ['id' => 'PU-0916-024', 'title' => 'Pickup · Mampang', 'subtitle' => 'Ke Sora Laundry · selesai 08.46', 'status' => 'Selesai', 'statusTone' => 'neutral', 'meta' => 'Rp17.000'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function superUserContent(): array
    {
        return [
            'navigation' => [
                ['label' => 'Platform overview', 'icon' => 'layout-dashboard'],
                ['label' => 'Tenant', 'icon' => 'store'],
                ['label' => 'Rekonsiliasi', 'icon' => 'wallet-cards'],
                ['label' => 'Security audit', 'icon' => 'shield-check'],
                ['label' => 'Konfigurasi', 'icon' => 'settings'],
            ],
            'hero' => [
                'eyebrow' => 'Platform health · seluruh sistem normal',
                'title' => 'Sinyal penting, tanpa kebisingan.',
                'description' => 'Pantau tenant, payment, dan operasional lintas platform dengan PII tetap terlindungi.',
                'primaryAction' => 'Tinjau tenant pending',
                'secondaryAction' => 'Buka rekonsiliasi',
            ],
            'metrics' => [
                ['label' => 'Tenant aktif', 'value' => '10', 'change' => '2 menunggu review', 'tone' => 'blue', 'icon' => 'store'],
                ['label' => 'Order 24 jam', 'value' => '387', 'change' => '96,8% selesai normal', 'tone' => 'green', 'icon' => 'shopping-bag'],
                ['label' => 'Perlu rekonsiliasi', 'value' => '3', 'change' => 'Tidak ada mismatch kritis', 'tone' => 'amber', 'icon' => 'wallet-cards'],
                ['label' => 'Availability', 'value' => '99,7%', 'change' => '30 hari berjalan', 'tone' => 'violet', 'icon' => 'shield-check'],
            ],
            'focus' => [
                'label' => 'Risk queue',
                'title' => 'Tiga payment perlu inquiry',
                'description' => 'Callback belum diterima melewati waktu normal. Lakukan inquiry tanpa mengubah status secara manual.',
                'meta' => 'Tertua sejak 09.12 WIB',
                'progress' => 28,
                'action' => 'Buka rekonsiliasi',
                'icon' => 'shield-alert',
            ],
            'workItems' => [
                ['id' => 'PAY-6TQ19N', 'title' => 'Bening Laundry · Rp78.000', 'subtitle' => 'QRIS · callback belum diterima', 'status' => 'Inquiry', 'statusTone' => 'amber', 'meta' => '18 menit'],
                ['id' => 'TEN-0028', 'title' => 'Langit Bersih Laundry', 'subtitle' => 'Onboarding · dokumen telah dilengkapi', 'status' => 'Review tenant', 'statusTone' => 'blue', 'meta' => 'Hari ini'],
                ['id' => 'SEC-0142', 'title' => 'PII reveal · order KL-240915-041', 'subtitle' => 'Support case · alasan tercatat', 'status' => 'Teraudit', 'statusTone' => 'neutral', 'meta' => '08.54 WIB'],
            ],
        ];
    }

    /**
     * @return list<array{title: string, description: string, status: string}>
     */
    private function milestones(): array
    {
        return [
            ['title' => 'Risk validation', 'description' => 'Domain contract siap; sign-off dan sandbox live masih blocker', 'status' => 'blocked'],
            ['title' => 'Foundation engineering', 'description' => 'SQLite reference slice dan CI tersedia; hosted run masih pending', 'status' => 'current'],
            ['title' => 'Identity & core operations', 'description' => 'Authentication, tenancy, order, dispatch, dan payment', 'status' => 'later'],
        ];
    }
}
