<?php

namespace App\Services\MilestoneZero;

use App\DTOs\MilestoneZero\WireflowPreviewData;
use App\Enums\UserRole;

final class GetWireflowPreviewService
{
    public function handle(UserRole $role, ?string $requestedStep): ?WireflowPreviewData
    {
        $wireflow = match ($role) {
            UserRole::Customer => $this->customerWireflow(),
            UserRole::TenantOwner => $this->tenantOwnerWireflow(),
            UserRole::Driver => $this->driverWireflow(),
            UserRole::SuperUser => $this->superUserWireflow(),
        };

        $activeStepId = $requestedStep ?? $wireflow['steps'][0]['id'];
        $activeStepIndex = array_search($activeStepId, array_column($wireflow['steps'], 'id'), true);

        if ($activeStepIndex === false) {
            return null;
        }

        return new WireflowPreviewData(
            activeRole: $role,
            roles: $this->roles(),
            wireflow: $wireflow,
            activeStepIndex: $activeStepIndex,
            riskNotices: $this->riskNotices(),
        );
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    private function roles(): array
    {
        return [
            ['value' => UserRole::Customer->value, 'label' => 'Customer', 'description' => 'Pesan, bayar, dan lacak laundry'],
            ['value' => UserRole::TenantOwner->value, 'label' => 'Tenant owner', 'description' => 'Siapkan dan jalankan operasional'],
            ['value' => UserRole::Driver->value, 'label' => 'Driver', 'description' => 'Terima dan selesaikan task'],
            ['value' => UserRole::SuperUser->value, 'label' => 'Super User', 'description' => 'Review risiko dan rekonsiliasi'],
        ];
    }

    /**
     * @return array{title: string, summary: string, outcome: string, steps: list<array<string, mixed>>}
     */
    private function customerWireflow(): array
    {
        return [
            'title' => 'Dari pencarian outlet hingga order selesai',
            'summary' => 'Customer memilih satu outlet dan satu paket, lalu mengikuti payment timing yang berbeda untuk fixed dan per-kg.',
            'outcome' => 'Customer melihat status fulfillment dan payment secara terpisah tanpa pernah menentukan total atau status dari browser.',
            'steps' => [
                $this->step('discover', '01', 'Temukan outlet', 'Customer', 'Cari berdasarkan area atau lokasi yang disetujui.', 'Sistem hanya menampilkan outlet aktif dalam radius dan mengurutkan jarak perkiraan.', 'Browsing', 'Lokasi bersifat opsional untuk browsing; koordinat wajib sebelum order.', [
                    ['label' => 'Izin lokasi diberikan', 'result' => 'Tampilkan jarak garis lurus dan outlet eligible.'],
                    ['label' => 'Izin lokasi ditolak', 'result' => 'Tetap dapat browse berdasarkan kota/area tanpa jarak.'],
                ]),
                $this->step('configure-order', '02', 'Pilih paket dan jadwal', 'Customer', 'Pilih satu paket, alamat pickup/delivery, dan slot maksimal tujuh hari.', 'Backend memvalidasi radius, lead time dua jam, blackout, readiness, dan menyimpan snapshot.', 'Draft tervalidasi', 'Harga, fee, Tenant, Customer, dan status tidak dipercaya dari client.', [
                    ['label' => 'Paket fixed', 'result' => 'Quantity wajib bilangan bulat dan total langsung final.'],
                    ['label' => 'Paket per-kg', 'result' => 'Estimasi berat hanya informasi dan belum menjadi tagihan.'],
                ]),
                $this->step('payment-branch', '03', 'Ikuti cabang pembayaran', 'Customer', 'Fixed memilih channel saat checkout; per-kg menunggu hasil timbang outlet.', 'Fixed membuat invoice sebelum pickup. Per-kg membuat invoice setelah berat final dikunci.', 'Awaiting payment / pickup', 'Return URL hanya halaman informasi; callback server-to-server adalah sumber status.', [
                    ['label' => 'Fixed', 'result' => 'Bayar sebelum pickup task dapat dijalankan.'],
                    ['label' => 'Per-kg', 'result' => 'Pickup dan timbang dahulu, lalu bayar total final.'],
                ]),
                $this->step('track-processing', '04', 'Pantau proses', 'Customer', 'Lihat payment, pickup, processing, dan indikator keterlambatan.', 'Timeline durable menjadi source of truth; realtime hanya mempercepat pembaruan.', 'Processing', 'Payload realtime tidak membawa alamat, telepon, atau error provider.', [
                    ['label' => 'WebSocket gagal', 'result' => 'Polling konservatif memulihkan status terbaru.'],
                    ['label' => 'Target terlewati', 'result' => 'Tampilkan delayed tanpa auto-refund atau transisi tersembunyi.'],
                ]),
                $this->step('delivery-slot', '05', 'Pilih slot delivery', 'Customer', 'Setelah laundry siap, pilih slot delivery yang tersedia.', 'Sistem memvalidasi slot sebelum task delivery dapat ditawarkan.', 'Ready for delivery', 'Alamat lengkap hanya tersedia bagi pihak yang sedang menangani order.', [
                    ['label' => 'Belum memilih 7 hari', 'result' => 'Indicator awaiting_customer aktif tanpa storage fee.'],
                ]),
                $this->step('completed', '06', 'Order selesai', 'Driver dan sistem', 'Driver menyelesaikan delivery dengan actor dan timestamp.', 'Order completed, komisi dibuat tepat sekali, dan akses kontak Driver dicabut.', 'Terminal', 'Tenant hanya melihat PII sampai 3x24 jam setelah completion.', [
                    ['label' => 'Ada keluhan', 'result' => 'Customer menghubungi outlet; Tenant dapat mengajukan full refund dalam 3x24 jam.'],
                ]),
            ],
        ];
    }

    /**
     * @return array{title: string, summary: string, outcome: string, steps: list<array<string, mixed>>}
     */
    private function tenantOwnerWireflow(): array
    {
        return [
            'title' => 'Dari onboarding hingga rekonsiliasi Tenant',
            'summary' => 'Owner menyiapkan Tenant dan outlet sebelum menjalankan order, dispatch, weighing, serta laporan.',
            'outcome' => 'Setiap tindakan operasional tetap tenant-scoped dan keputusan finansial dapat ditelusuri ke record sumber.',
            'steps' => [
                $this->step('tenant-onboarding', '01', 'Selesaikan onboarding', 'Tenant owner', 'Daftar, verifikasi email, menunggu approval, lalu setup TOTP.', 'Tenant belum dapat beroperasi sampai approved dan kontrol keamanan terpenuhi.', 'Pending approval', 'Satu owner aktif per Tenant; credential tidak boleh dibagikan.', [
                    ['label' => 'Rejected', 'result' => 'Tampilkan alasan dan izinkan resubmission.'],
                    ['label' => 'Suspended', 'result' => 'Order baru berhenti, tetapi order berjalan tetap diselesaikan.'],
                ]),
                $this->step('outlet-readiness', '02', 'Penuhi readiness outlet', 'Tenant owner', 'Lengkapi rekening payout, profil, koordinat, radius, jam, slot, fee, dan paket.', 'Outlet hanya aktif jika seluruh readiness condition dihitung Service dan rekening telah diverifikasi.', 'Draft / active', 'Perubahan rekening menahan payout sampai verifikasi ulang.', [
                    ['label' => 'Syarat belum lengkap', 'result' => 'Aktivasi ditolak dengan checklist yang jelas.'],
                ]),
                $this->step('order-board', '03', 'Kelola antrean order', 'Tenant owner', 'Pantau order berdasarkan status dan indikator tindakan.', 'Board memisahkan fulfillment, payment, delayed, dan awaiting_customer.', 'Operational queue', 'Query dan mutation selalu menggunakan trusted Tenant context.', [
                    ['label' => 'Fixed belum paid', 'result' => 'Pickup belum boleh dijalankan.'],
                    ['label' => 'Per-kg', 'result' => 'Pickup dapat dimulai sebelum invoice final.'],
                ]),
                $this->step('dispatch-weighing', '04', 'Dispatch dan timbang', 'Tenant owner', 'Tawarkan satu task ke satu Driver dan konfirmasi berat per-kg.', 'Acceptance atomic; berat dibulatkan naik per 100 gram dan harga dihitung backend.', 'Awaiting weight / assigned', 'Offer hanya menampilkan area; kontak lengkap baru terlihat setelah accept.', [
                    ['label' => 'Offer expired', 'result' => 'Task kembali pending dan dapat ditawarkan ulang.'],
                    ['label' => 'Per-kg confirmed', 'result' => 'Total final dikunci dan invoice dapat dibuat.'],
                ]),
                $this->step('processing-delivery', '05', 'Proses dan antar', 'Tenant owner', 'Mulai processing setelah paid, tandai ready, lalu dispatch delivery.', 'State machine menolak lompat status dan menyelesaikan order saat delivery selesai.', 'Processing / delivery', 'Tenant dan Super User tidak dapat menandai payment paid secara manual.', [
                    ['label' => 'Tidak ada Driver', 'result' => 'Tandai delayed dan reschedule dengan alasan; tidak auto-cancel.'],
                ]),
                $this->step('tenant-finance', '06', 'Laporan dan refund', 'Tenant owner', 'Lihat gross, fee aktual, komisi, adjustment, payout, dan ajukan full refund.', 'Sistem menjaga payment asli tetap paid dan mencatat refund sebagai adjustment negatif.', 'Reconciled / submitted', 'Fee unknown tidak boleh ditampilkan sebagai nol atau nilai final.', [
                    ['label' => 'Refund dalam 3x24 jam', 'result' => 'Kirim request sebesar payment paid, tidak dapat diedit.'],
                    ['label' => 'Payment eligible payout', 'result' => 'Masuk seluruhnya ke batch berdasarkan cutoff, bukan pilihan ID client.'],
                ]),
            ],
        ];
    }

    /**
     * @return array{title: string, summary: string, outcome: string, steps: list<array<string, mixed>>}
     */
    private function driverWireflow(): array
    {
        return [
            'title' => 'Dari undangan hingga komisi earned',
            'summary' => 'Driver hanya menerima task milik Tenant-nya dan memperoleh akses minimum sesuai tahap pekerjaan.',
            'outcome' => 'Satu Driver hanya mempunyai satu task aktif, proof tervalidasi, dan komisi tercatat tepat sekali.',
            'steps' => [
                $this->step('driver-invitation', '01', 'Terima undangan', 'Driver', 'Gunakan invitation sekali pakai untuk membuat credential.', 'Sistem mengikat Driver ke tepat satu Tenant dan menolak token expired/reused.', 'Invited / active', 'Invitation menyimpan hash token, bukan plaintext token.', [
                    ['label' => 'Token invalid', 'result' => 'Akses ditolak tanpa membocorkan keberadaan akun.'],
                ]),
                $this->step('driver-availability', '02', 'Atur availability', 'Driver', 'Pilih available untuk menerima offer baru.', 'Unavailable mencegah offer baru tetapi tidak membatalkan task aktif.', 'Available / unavailable', 'Account status dan availability adalah dua konsep terpisah.', []),
                $this->step('masked-offer', '03', 'Tinjau offer terbatas', 'Driver', 'Lihat outlet, slot, area, tipe task, komisi, dan jarak perkiraan.', 'Nomor telepon serta alamat lengkap Customer tetap dimasking sebelum accept.', 'Offered - 10 menit', 'Satu task hanya memiliki satu offer aktif pada satu waktu.', [
                    ['label' => 'Reject / expiry', 'result' => 'Offer ditutup dan task kembali ke Tenant.'],
                    ['label' => 'Accept', 'result' => 'Conditional update memastikan hanya satu Driver menang.'],
                ]),
                $this->step('active-task', '04', 'Jalankan task', 'Driver', 'Setelah accept, buka detail kontak dan ubah accepted menjadi in_progress.', 'Akses kontak diberikan hanya selama task aktif.', 'Accepted / in progress', 'Satu active task per Driver ditegakkan secara transaction-safe.', [
                    ['label' => 'Task pickup', 'result' => 'Antar laundry ke outlet untuk fixed atau weighing per-kg.'],
                    ['label' => 'Task delivery', 'result' => 'Antar laundry kepada Customer dan selesaikan order.'],
                ]),
                $this->step('task-proof', '05', 'Selesaikan dengan proof', 'Driver', 'Konfirmasi completion; foto dan catatan bersifat opsional.', 'Actor dan timestamp wajib, file privat, akses kontak langsung dicabut.', 'Completed', 'Hanya raster tervalidasi; nama/path dari user tidak dipercaya.', [
                    ['label' => 'Retry completion', 'result' => 'Tidak menggandakan history atau komisi.'],
                ]),
                $this->step('driver-commission', '06', 'Lihat komisi', 'Driver', 'Lihat earned dan paid berdasarkan periode.', 'Komisi fixed per leg disnapshot ketika task diberikan dan earned tepat sekali.', 'Earned / paid', 'Refund order tidak membalik pekerjaan Driver yang sudah selesai.', []),
            ],
        ];
    }

    /**
     * @return array{title: string, summary: string, outcome: string, steps: list<array<string, mixed>>}
     */
    private function superUserWireflow(): array
    {
        return [
            'title' => 'Kontrol platform tanpa generic override',
            'summary' => 'Super User menangani onboarding, rekonsiliasi, payout, refund, dan investigasi melalui action terbatas.',
            'outcome' => 'Akses lintas Tenant tetap beralasan, masked secara default, dan seluruh tindakan sensitif dapat diaudit.',
            'steps' => [
                $this->step('tenant-review', '01', 'Review Tenant', 'Super User', 'Tinjau pendaftaran dan approve/reject dengan alasan.', 'Keputusan mencatat actor dan timestamp tanpa mengaktifkan outlet otomatis.', 'Reviewed', 'Super User tidak memiliki tenant_id dan tidak dapat impersonate.', [
                    ['label' => 'Approve', 'result' => 'Owner melanjutkan TOTP, payout account, dan readiness.'],
                    ['label' => 'Reject', 'result' => 'Tenant menerima alasan dan dapat memperbaiki data.'],
                ]),
                $this->step('payment-controls', '02', 'Kelola payment controls', 'Super User', 'Atur channel allowlist atau payment maintenance mode.', 'Invoice baru dapat dihentikan tanpa memblokir callback dan inquiry attempt lama.', 'Operational control', 'Credential provider tidak pernah dikirim ke frontend atau audit metadata.', [
                    ['label' => 'Maintenance on', 'result' => 'Create invoice ditolak dengan pesan aman.'],
                    ['label' => 'Callback lama', 'result' => 'Tetap diproses dan diverifikasi.'],
                ]),
                $this->step('reconciliation', '03', 'Rekonsiliasi payment', 'Super User', 'Tinjau mismatch dan picu inquiry terkontrol.', 'Inquiry tervalidasi boleh memperbarui status secara monotonic; tidak ada tombol mark paid.', 'Needs inquiry', 'Signature, raw callback, payment URL, dan provider error tidak masuk log.', [
                    ['label' => 'Fee unknown', 'result' => 'Settlement ditahan dan nilai tidak dianggap nol.'],
                    ['label' => 'Duplicate callback', 'result' => 'Acknowledge sukses tanpa side effect kedua.'],
                ]),
                $this->step('refund-payout', '04', 'Review refund dan payout', 'Super User', 'Approve/reject refund, catat transfer manual, dan finalisasi payout eligible.', 'Amount dan batch membership dihitung Service dari record sumber.', 'Audited finance', 'Reason, re-authentication, authorization, dan immutable final record wajib.', [
                    ['label' => 'Payout hold', 'result' => 'Finalization berhenti tanpa menghentikan operasional Tenant.'],
                    ['label' => 'Refund post-payout', 'result' => 'Buat adjustment negatif pada payout berikutnya.'],
                ]),
                $this->step('pii-investigation', '05', 'Reveal PII terbatas', 'Super User', 'Ajukan reveal untuk satu order dengan alasan.', 'Grant memiliki expiry, dicatat append-only, dan tidak dapat diekspor.', 'Time-limited access', 'Tampilan default masked; revealed PII tidak masuk CSV atau log.', [
                    ['label' => 'Re-auth stale', 'result' => 'Minta password/TOTP confirmation sebelum reveal.'],
                ]),
                $this->step('audit-readiness', '06', 'Audit dan readiness', 'Super User', 'Pantau audit, blocker legal/provider, serta health operasional.', 'Production payment tetap nonaktif sampai seluruh blocker Milestone 0 terbukti selesai.', 'Release blocked', 'Satu Super User belum menyediakan four-eyes approval; monitoring adalah compensating control.', []),
            ],
        ];
    }

    /**
     * @param  list<array{label: string, result: string}>  $branches
     * @return array<string, mixed>
     */
    private function step(
        string $id,
        string $number,
        string $title,
        string $actor,
        string $trigger,
        string $systemOutcome,
        string $status,
        string $privacy,
        array $branches,
    ): array {
        return compact('id', 'number', 'title', 'actor', 'trigger', 'systemOutcome', 'status', 'privacy', 'branches');
    }

    /**
     * @return list<array{title: string, description: string, severity: string}>
     */
    private function riskNotices(): array
    {
        return [
            [
                'title' => 'Keputusan bisnis masih provisional',
                'description' => 'Merchant-of-record, fee, payout, refund, privacy, dan retention membutuhkan persetujuan eksternal.',
                'severity' => 'blocker',
            ],
            [
                'title' => 'Sandbox live belum dijalankan',
                'description' => 'Credential dan callback HTTPS publik belum tersedia; hasil provider tidak diasumsikan.',
                'severity' => 'blocker',
            ],
        ];
    }
}
