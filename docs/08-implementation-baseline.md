# Implementation Baseline

> Status aktual per 24 September 2026. Dokumen ini menjelaskan implementasi yang benar-benar tersedia dan tidak menggantikan product, domain, atau architecture contract.

## Status milestone

- Milestone 0: **in progress — external blockers**. Contract M5/M6, threat model, wireflow, private evidence-manifest preflight, dan guarded sandbox callback simulator tersedia; live provider/legal evidence belum dimuat dan live sandbox belum dijalankan.
- Milestone 1: **selesai — CI hijau pada run #2 (commit 9b23f68)**. Persistence slice, boundary enforcement, safe error mapping, static analysis, dan workflow CI PostgreSQL 18/Redis 8 terverifikasi hosted.
- Milestone 2: **selesai — hosted CI hijau pada run #4 (commit `3af7fdb`)**. Identity, Tenant lifecycle, Super User, payout account, security middleware, audit, responsive UI, PostgreSQL 18, Redis 8, dan Gitleaks telah terverifikasi.
- Milestone 3: **selesai — hosted CI hijau pada run #5 (commit `2b9366d`)**. Outlet, katalog, address book, discovery, scheduling preview, lifecycle/readiness, responsive UI, PostgreSQL 18/Redis 8, dan Gitleaks telah terverifikasi.
- Milestone 4: **selesai — hosted CI hijau pada run #7 (commit `af34964`)**. Order fixed/per-kg, snapshot/idempotency, lifecycle, isolation, monitoring, responsive Inertia UI, PostgreSQL 18/Redis 8, dan Gitleaks telah terverifikasi.
- Milestone 5: **selesai — hosted CI hijau pada run #10 (commit `618b7f7`)**. Driver invitation/availability, Tenant-scoped invitation, pickup dispatch, privacy window, transactional cancellation, weight confirmation, private proof, commission snapshot, expiry job, dan responsive Inertia UI tersedia. Delivery completion sengaja tetap diblokir sampai Milestone 7.
- Milestone 6: **in progress — external sandbox blocker; hosted CI run #10 hijau**. Invoice Duitku, active-attempt guard, callback idempotent, expiry, browser return read-only, receipt, Tenant payment list, Super User reconciliation/inquiry, channel control, maintenance mode, dan production fail-closed tersedia. Live sandbox belum dijalankan.
- Milestone 7: **implementation complete — hosted CI run #13 hijau; release blocked**. Processing/readiness, Customer dan coordinated Tenant delivery scheduling, delivery dispatch/completion atomic, durable notification, Reverb/Echo private channel, polling fallback, dan proof access revocation tersedia. Release tetap bergantung pada blocker eksternal M0/M6.
- Milestone 8 dan seterusnya: belum diimplementasikan.

Refund, Driver payout, Tenant payout transaction, physical proof deletion, dan production payment integration belum tersedia. Payout saat ini hanya mencakup onboarding rekening dan payout hold, bukan pemindahan dana. M7 menjadwalkan dan mencabut akses proof setelah 90 hari, tetapi file baru boleh dihapus pada M9 setelah refund guard M8 tersedia.

## Runtime dan dependency aktual

| Area | Versi terpasang/target |
| --- | --- |
| PHP lokal | 8.4.25 |
| Laravel | 13.33.0 |
| Laravel Reverb | 1.12.0 |
| Laravel Fortify | 1.39.0 |
| Inertia Laravel adapter | 3.0.0 |
| Inertia React adapter | 3.0.3 |
| Laravel Echo / Pusher JS | 2.5.0 / 8.6.0 |
| React | 19.0.8 |
| TypeScript | 6.0.3 |
| Tailwind CSS | 4.3.3 |
| Vite | 8.3.0 |
| Node.js project target | 24.21.0 melalui `.nvmrc` |
| Static analysis | Larastan 3.12.1 / PHPStan 2.2.14, level 5 |

Lockfile tetap menjadi source of truth untuk patch version.

## Database dan portability

Local development dan test memakai SQLite terlebih dahulu. `.env.example` aman dan langsung menunjuk `database/database.sqlite`; alternatif PostgreSQL tersedia sebagai komentar tanpa credential. Session, queue, dan cache local memakai database agar tidak memerlukan Redis.

PostgreSQL tetap target staging/production. Workflow GitHub Actions menjalankan migration dan seluruh backend test pada PostgreSQL 18 serta Redis 8. Migration dan Eloquent query harus portable; kelulusan SQLite saja tidak membuktikan locking, concurrency, atau vendor-specific behavior PostgreSQL.

Schema domain pertama adalah `platform_settings` dengan record global awal:

- maximum service radius 20 km;
- payment maintenance disabled;
- version 1;
- optional updating actor untuk milestone authorization berikutnya.

Schema M2 menambahkan `tenants`, identity/lifecycle/Fortify fields pada `users`, `tenant_payout_accounts`, dan `activity_logs`. Migration melakukan backfill ULID user legacy, reversible pada SQLite, serta memakai tipe/constraint portable untuk PostgreSQL. Payout PII disimpan dengan encrypted cast dan current account ditegakkan melalui nullable unique key.

Schema M3 menambahkan `outlets`, operating hours, pickup/delivery slots, blackout, package global Tenant, dan customer address. Snapshot outlet onboarding dibackfill non-destruktif menjadi satu outlet draft; rollback menghapus tabel M3 tanpa menghapus snapshot. Default address memakai nullable unique owner key yang portable. Discovery menghitung Haversine di SQL PostgreSQL dan memakai bounding query plus perhitungan PHP pada SQLite.

Schema M4 menambahkan Order aggregate, satu item, dua address snapshot, append-only status/schedule history, dan incident indicator. Unique Customer/idempotency key, request fingerprint, foreign key `restrict`, serta transaction boundary melindungi duplicate dan perubahan master. Timestamp disimpan UTC; validasi schedule memakai `Asia/Jakarta`.

Schema M5 menambahkan Tenant commission settings, invitation token hash, Driver profile/availability, delivery task/offer/history, earned commission, dan append-only weight confirmation. Nullable unique active keys menjaga satu offer aktif per task, satu task accepted/in-progress per Driver, satu current weight confirmation per Order, serta kompatibilitas SQLite/PostgreSQL. `order_items` menyimpan actual dan billable grams final.

Schema M6 memakai tabel payment attempt dan append-only event yang sudah disiapkan baseline, lalu menambahkan nullable unique `active_order_key` serta timestamp cooldown inquiry. Migration melakukan duplicate check sebelum backfill pending attempt dan reversible pada SQLite/PostgreSQL. Attempt terminal membersihkan active key; failed/expired tidak dapat berubah menjadi paid.

Schema M7 menambahkan durable `notifications` dengan dedupe key, `processing_started_at`, serta expiry/revocation akses proof pada task dan weight confirmation. Migration membackfill processing/proof timestamp secara non-destruktif, reversible, dan tidak menghapus object proof saat akses kedaluwarsa.

## Identity, Tenant, dan Super User

Fortify menangani registration Customer, login/logout, reset/update password, email verification, password confirmation, TOTP, recovery codes, dan challenge. Passkeys dinonaktifkan. `/tenant/register` membuat Tenant pending/inactive dan owner; Driver dibuat hanya dari invitation Tenant 48 jam yang tokennya disimpan sebagai hash; Super User hanya dibuat melalui `php artisan super-user:provision` tanpa password argument/output.

Route terautentikasi memakai account-status + `auth_version`; Tenant owner dan Super User wajib email verified serta TOTP confirmed. Sensitive mutation memerlukan password+TOTP re-auth yang berumur maksimal 15 menit. Lifecycle dan mutation mengikuti `Form Request -> Controller -> Service -> Repository`, dibungkus transaction bersama append-only audit.

Workspace Inertia menyediakan profile, status blocker, onboarding/resubmission, payout readiness/hold, rekening masked, closure request, security settings, dan audit terbaru. Super User memperoleh tenant pagination, explicit review/lifecycle actions, payout hold, user suspension/reactivation, serta antrean rekening pending tanpa plaintext/ciphertext PII.

## Reference vertical slice

Route `GET /foundation/platform-settings` membuktikan alur:

```text
ShowPlatformSettingsRequest
  -> ShowPlatformSettingsController
  -> GetPlatformSettingsService
  -> PlatformSettingRepositoryInterface
  -> EloquentPlatformSettingRepository
  -> PlatformSetting
  -> PlatformSettingsData
  -> Inertia page
```

Binding interface terdaftar eksplisit di `AppServiceProvider`. Service tidak mengetahui Eloquent; Controller dan Form Request tidak mengetahui Model/Repository/DB. Record hilang menghasilkan domain exception yang dipetakan terpusat menjadi HTTP 503 dengan pesan aman untuk Inertia maupun JSON.

Dashboard preview dan wireflow Milestone 0 tetap memakai fixture. Dashboard menyediakan tautan menuju reference slice database.

## Frontend

Struktur frontend tetap memisahkan `pages`, `layouts`, reusable `components/ui`, domain composition, dan shared `types`. Halaman foundation responsive, keyboard-focusable, dan hanya menerima konfigurasi publik yang diperlukan. M3 menambahkan discovery/address/workspace; M4 menambahkan checkout, daftar/filter/detail Order, timeline, lifecycle form, dan printable receipt skeleton; M5 menambahkan acceptance invitation, Driver dashboard, serta Tenant driver/dispatch workspace. M6 menambahkan checkout/status/receipt payment, daftar Tenant read-only, reconciliation Super User, serta control channel/maintenance. M7 menambahkan readiness/delivery action, notification center/unread badge, Echo partial reload, dan polling fallback. Payment URL hanya dikirim kepada Customer pemilik selama relevan; props operasional tidak menerimanya.

## Quality gates

Quality gate lokal:

```powershell
composer test
composer analyse
composer format:check
npm run lint
npm run types:check
npm run build
composer audit --locked
npm audit --audit-level=high
```

Architecture tests memblokir Controller/Form Request dari persistence detail, Service dari Eloquent/query builder, dan Repository dari Service/Gateway/HTTP/Notification. Test juga memastikan contract resolve ke Eloquent implementation.

Workflow `.github/workflows/ci.yml` menjalankan install dari lockfile, manifest validation, migration/test PostgreSQL, Redis smoke, Larastan, Pint, ESLint, TypeScript, production build, dependency audit, dan Gitleaks CLI yang dipin ke image digest. Workflow mendukung manual dispatch, concurrency cancellation, dan timeout; syntax telah diverifikasi lokal dengan actionlint 1.7.12.

Verifikasi lokal M2 per 17 September 2026 mencakup 67 Pest test/755 assertion, migration forward/rollback pada SQLite terisolasi, Larastan tanpa error/baseline, Pint, ESLint, TypeScript, production build, Composer/npm audit, actionlint 1.7.12, secret pattern review, dan `git diff --check`. Gitleaks penuh serta PostgreSQL 18/Redis 8 tetap dibuktikan oleh hosted CI karena binary Gitleaks lokal tidak tersedia.

Verifikasi lokal M3 mencakup 84 Pest test/1.069 assertion, migration forward/rollback dan backfill preservation SQLite, Larastan tanpa error, Pint, ESLint, TypeScript, production build, Composer/npm audit, actionlint 1.7.12, Gitleaks staged scan 8.30.1, query-count fixture 10 Tenant/20 outlet, dan `git diff --check`.

Verifikasi lokal M4 mencakup 93 Pest test/1.184 assertion, migration round-trip SQLite, snapshot/idempotency/isolation/query-count regression, Larastan tanpa error, Pint, ESLint, TypeScript, production build, Composer/npm audit, dan `git diff --check`. Actionlint, dependency audit, Gitleaks full-history, PostgreSQL 18, serta Redis 8 lulus pada hosted CI.

Verifikasi lokal gabungan M5/M6 per 22 September 2026 mencakup 131 Pest test/1.697 assertion, migration round-trip SQLite, lifecycle/privacy/cancellation M5, invoice/callback/inquiry/expiry M6, Larastan tanpa error, Pint, ESLint, TypeScript, production build, Composer/npm audit, Gitleaks 8.30.1, dan `git diff --check`. Live Duitku sandbox berstatus `blocked/not executed` dan HTTP fake tidak dihitung sebagai bukti provider.

Verifikasi lokal M7 per 24 September 2026 mencakup 145 Pest test/1.837 assertion, migration round-trip dan local forward migration SQLite, processing/delivery/notification/privacy/retention regression, Larastan tanpa error, Pint, ESLint, TypeScript, production build, Composer/npm audit, safe secret-pattern review, dan `git diff --check`. Workflow CI tidak berubah sehingga actionlint tidak dijalankan ulang lokal. Reverb deployment smoke tetap menunggu staging.

[Hosted CI run #13](https://github.com/FatahiraAnggitaS/klik-laundry/actions/runs/35993622361) untuk commit `1376eb8` lulus: PostgreSQL 18 migration/test, Redis 8 smoke, Larastan, Pint, ESLint, TypeScript, production build, Composer/npm audit, dan Gitleaks full-history.

[Hosted CI run #10](https://github.com/FatahiraAnggitaS/klik-laundry/actions/runs/35745849646) untuk commit `618b7f7` lulus pada job quality dan secrets: PostgreSQL 18 migration/test, Redis 8 smoke, Larastan, Pint, ESLint, TypeScript, production build, dependency audit, actionlint, dan Gitleaks full-history.

[Hosted CI run #7](https://github.com/FatahiraAnggitaS/klik-laundry/actions/runs/35245827541) untuk implementation commit M4 `af34964` lulus pada job quality dan secrets.

[Hosted CI run #5](https://github.com/FatahiraAnggitaS/klik-laundry/actions/runs/35216592473) untuk implementation commit M3 `2b9366d` lulus pada kedua job: quality menggunakan PostgreSQL 18/Redis 8 dan secret scan menggunakan Gitleaks full-history.

[Hosted CI run #4](https://github.com/FatahiraAnggitaS/klik-laundry/actions/runs/35181807014) untuk implementation commit M2 `3af7fdb` lulus pada kedua job: quality menggunakan PostgreSQL 18/Redis 8 dan secret scan Gitleaks full-history.

Hosted run pertama pada commit `9c5d770` membuktikan job quality lulus penuh pada PostgreSQL 18 dan Redis 8. Job secret awal gagal pada contoh credential palsu di dokumentasi skill. Contoh kemudian di-redact dan satu fingerprint historis ditambahkan. [Hosted run #2](https://github.com/FatahiraAnggitaS/klik-laundry/actions/runs/35100746075) lulus penuh dan memenuhi exit criteria Milestone 1.

`.gitleaksignore` hanya memuat dua fingerprint historis spesifik: contoh credential palsu pada dokumentasi skill yang sudah di-redact dan prose schema M5 yang menyebut field token hash tanpa nilainya. Tidak ada allowlist path atau rule global.

## Menjalankan project lokal

```powershell
nvm use 24.21.0
composer setup
composer dev
```

`composer setup` membuat `.env` bila belum ada, menghasilkan development `APP_KEY`, menjalankan migration SQLite, menginstal frontend dependency, dan membuat production asset build. Secret nyata hanya boleh berada di `.env` atau secret manager dan tidak boleh masuk repository/frontend/log.
