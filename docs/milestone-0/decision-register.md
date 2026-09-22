# Decision Register Milestone 0

## Status contract

- `provisional`: keputusan internal untuk menjaga desain konsisten, belum mendapat sign-off eksternal.
- `approved`: disetujui owner bisnis/legal yang berwenang dan memiliki referensi evidence.
- `verified`: perilaku provider telah dibuktikan lewat sandbox/production readiness test.
- `blocked`: tidak boleh diasumsikan atau digunakan untuk mengaktifkan production.

Tidak ada nama approver atau bukti persetujuan yang diisi secara fiktif. Kolom evidence harus berisi referensi tiket, surat, email keputusan, atau hasil uji yang aman—bukan credential, signature, raw callback, payment URL, atau PII. Template input private tersedia pada [`evidence-manifest.example.json`](evidence-manifest.example.json); file aktual wajib berada di `storage/app/private/milestone-0/evidence.json` dan tidak di-commit.

## Keputusan dan sign-off

| ID | Area | Keputusan internal | Status | Owner sign-off | Review date | Evidence | Release blocker |
| --- | --- | --- | --- | --- | --- | --- | --- |
| DEC-001 | Merchant account | MVP memakai satu merchant account platform untuk seluruh transaksi Tenant | provisional | Business + Duitku | Belum tersedia | Belum tersedia | Ya |
| DEC-002 | Merchant-of-record | Platform bertindak sebagai merchant-of-record; payment tetap ditautkan ke satu Tenant/order | provisional | Business + Legal + Duitku | Belum tersedia | Belum tersedia | Ya |
| DEC-003 | Gateway fee | Fee Duitku aktual menjadi beban Tenant; Customer tidak dikenai surcharge | provisional | Business + Finance | Belum tersedia | Belum tersedia | Ya |
| DEC-004 | Platform fee | Platform service fee bernilai nol pada MVP | provisional | Business + Finance | Belum tersedia | Scope contract | Tidak untuk sandbox; ya untuk production sign-off |
| DEC-005 | Tenant payout | Hak Tenant dibayar manual di luar gateway melalui immutable batch | provisional | Business + Finance + Legal | Belum tersedia | Belum tersedia | Ya |
| DEC-006 | Driver payout | Komisi fixed per leg dibayar manual oleh Tenant; refund tidak membalik komisi completed | provisional | Business + Finance | Belum tersedia | Scope contract | Ya untuk operational sign-off |
| DEC-007 | Refund | Hanya full refund manual, diajukan Tenant maksimal 3x24 jam setelah completion dan diproses Super User | provisional | Business + Legal + Finance | Belum tersedia | Belum tersedia | Ya |
| DEC-008 | Payment timing | Fixed dibayar sebelum pickup; per-kg dibayar setelah berat final dikunci | provisional | Product + Operations | Belum tersedia | Domain contract | Tidak, kecuali DEC-001/002 berubah |
| DEC-009 | Privacy window | Tenant melihat contact hingga 3x24 jam setelah completion; Driver hanya setelah accept sampai task selesai | provisional | Product + Legal/Privacy | Belum tersedia | Belum tersedia | Ya |
| DEC-010 | Proof retention | Foto proof dihapus 90 hari setelah order selesai dan seluruh refund terkait selesai | provisional | Legal/Privacy + Operations | Belum tersedia | Belum tersedia | Ya |
| DEC-011 | Financial/PII retention | Record transaksi minimum dipertahankan; periode final finansial dan PII belum ditentukan | blocked | Legal + Finance | Belum tersedia | Belum tersedia | Ya |
| DEC-012 | Per-kg precision | Berat ditagih per 100 gram dan harga per kg wajib kelipatan Rp10 | approved internally | Product + Engineering | 2026-09-16 | `domain-contract.md` | Tidak |
| DEC-013 | Provider data minimization | Kirim hanya field wajib/yang dibutuhkan channel; alamat pickup/delivery tidak dikirim ke Duitku | provisional | Security + Duitku | Belum tersedia | Dokumentasi provider, perlu sandbox | Ya untuk channel-specific fields |
| DEC-014 | Payment source of truth | Hanya callback/inquiry tervalidasi yang dapat mengubah payment; redirect/JS callback tidak dipercaya | approved internally | Engineering + Security | 2026-09-16 | Dokumentasi resmi Duitku | Tidak |

## Release blockers

| ID | Blocker | Bukti penutupan minimum | Owner |
| --- | --- | --- | --- |
| RB-001 | Model multi-tenant pada satu merchant account belum disetujui | Persetujuan tertulis Duitku dan review legal model merchant-of-record | Business/Legal |
| RB-002 | Pajak, dispute, refund manual, dan payout belum disahkan | SOP dan sign-off Business/Finance/Legal | Business/Finance/Legal |
| RB-003 | Retention finansial dan PII belum final | Retention schedule, lawful basis, deletion/anonymization rules | Legal/Privacy |
| RB-004 | Channel dan fee production belum diketahui | Daftar channel merchant aktif, limit nominal, MDR/fixed fee, settlement behavior | Finance/Duitku |
| RB-005 | Sandbox live belum dijalankan | Evidence create, payment, callback, duplicate, expiry, inquiry, fee, dan timeout | Engineering |
| RB-006 | Callback HTTPS publik belum tersedia | Provider callback valid diterima server-to-server dan mendapat HTTP 200 | Engineering/Operations |

## Aturan perubahan keputusan

1. Perubahan keputusan harus memperbarui dokumen scope/domain/flow yang terdampak.
2. Perubahan DEC-001 atau DEC-002 memicu review ulang payment, onboarding Tenant, settlement, payout, refund, dan laporan.
3. Bukti yang mengandung data sensitif disimpan di sistem akses-terbatas di luar repository; dokumen ini hanya menyimpan referensi aman.
4. Status tidak boleh dinaikkan ke `approved` atau `verified` hanya berdasarkan asumsi, mock, atau automated test dengan HTTP fake.
