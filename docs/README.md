# Dokumentasi Laundry Multi-Tenant

Dokumen ini adalah blueprint untuk MVP aplikasi laundry multi-tenant. Baseline keputusan divalidasi pada **15 September 2026**. Repository memiliki foundation Laravel/Inertia, prototype Milestone 0, vertical slice Identity/Tenant/Super User Milestone 2, Outlet/Catalog/Discovery/Scheduling Milestone 3, serta implementasi lokal Order/Lifecycle Milestone 4 yang menunggu verifikasi hosted CI; versi aktual pada `composer.lock` dan `package-lock.json` tetap menjadi source of truth.

## Daftar dokumen

| File | Isi |
| --- | --- |
| [`01-product-scope.md`](01-product-scope.md) | Tujuan produk, persona, scope MVP, non-goals, dan acceptance criteria |
| [`02-architecture.md`](02-architecture.md) | Arsitektur modern monolith, multi-tenancy, authorization, realtime, dan deployment topology |
| [`03-technology-stack.md`](03-technology-stack.md) | Stack yang direkomendasikan, fungsi, alasan, dan trade-off |
| [`04-domain-and-data-model.md`](04-domain-and-data-model.md) | Bounded context sederhana, state machine, tabel inti, constraint, dan index |
| [`05-core-flows-and-integrations.md`](05-core-flows-and-integrations.md) | Alur order, Duitku, tracking realtime, laporan, dan komisi driver |
| [`06-mvp-roadmap.md`](06-mvp-roadmap.md) | Tahapan implementasi, prioritas, exit criteria, dan backlog pasca-MVP |
| [`07-security-testing-operations.md`](07-security-testing-operations.md) | Security baseline, test strategy, observability, backup, dan readiness checklist |
| [`08-implementation-baseline.md`](08-implementation-baseline.md) | Status implementasi aktual, SQLite/PostgreSQL portability, reference slice, dependency, dan quality gate |
| [`milestone-0/README.md`](milestone-0/README.md) | Decision register, domain contract, wireflow, threat model, dan sandbox spike Milestone 0 |

## Keputusan ringkas

- Bentuk aplikasi: **responsive web application** dengan Laravel modern monolith dan Inertia; belum membuat mobile app atau REST API terpisah.
- Multi-tenancy: **shared database, shared schema**, dengan `tenant_id` pada seluruh data milik tenant.
- Skala sasaran: sedikitnya 10 tenant aktif; desain awal tetap aman untuk pertumbuhan tanpa premature sharding.
- Realtime: Laravel Reverb + Echo pada private channel, dengan notifikasi database sebagai riwayat dan polling sebagai fallback.
- Pembayaran: Duitku POP melalui backend; callback tervalidasi adalah sumber perubahan status pembayaran, bukan browser redirect/JS callback.
- Settlement MVP: satu merchant account platform, pencatatan hak tenant dan komisi driver secara internal; payout dilakukan manual di luar gateway.
- Geolokasi: koordinat outlet dan lokasi customer, lalu perhitungan jarak sederhana; PostGIS dan live GPS driver belum diperlukan.
- Keuangan: laporan kas berbasis pembayaran sukses, fee gateway, dan komisi driver; belum berupa general ledger akuntansi.

## Asumsi yang wajib divalidasi sebelum coding payment

1. Merchant agreement Duitku mengizinkan model marketplace/multi-tenant yang direncanakan.
2. Siapa merchant of record, siapa menanggung MDR/fee, dan kapan dana menjadi hak tenant.
3. Channel QRIS/E-Wallet yang benar-benar aktif pada merchant production; daftar pada dokumentasi bukan jaminan aktivasi akun.
4. Kebijakan refund, dispute, pajak, payout tenant, dan payout komisi driver.
5. Laundry berbasis berat ditagih setelah berat final dikonfirmasi outlet; paket fixed-price boleh langsung ditagih.

Jika bisnis mengharuskan dana masuk langsung ke masing-masing tenant atau split settlement otomatis, desain payment dan onboarding merchant harus ditinjau ulang sebelum implementasi.

## Sumber resmi utama

- [Laravel 13 release notes](https://laravel.com/docs/13.x/releases)
- [Laravel React starter kit](https://laravel.com/starter-kits)
- [Laravel broadcasting](https://laravel.com/docs/13.x/broadcasting)
- [Inertia.js v3 documentation](https://inertiajs.com/docs/v3/getting-started/upgrade-guide)
- [Duitku POP API](https://docs.duitku.com/pop/id/)
- [Duitku API reference](https://docs.duitku.com/api/id/)

Dokumentasi pihak ketiga harus diperiksa ulang saat implementasi karena API, channel pembayaran, fee, dan prosedur aktivasi dapat berubah.
