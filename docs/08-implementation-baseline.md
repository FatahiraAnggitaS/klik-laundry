# Implementation Baseline

> Status aktual per 17 September 2026. Dokumen ini menjelaskan implementasi yang benar-benar tersedia dan tidak menggantikan product, domain, atau architecture contract.

## Status milestone

- Milestone 0: **in progress — external blockers**. Contract, threat model, wireflow, dan sandbox harness tersedia; live provider/legal sign-off belum ada.
- Milestone 1: **selesai — CI hijau pada run #2 (commit 9b23f68)**. Persistence slice, boundary enforcement, safe error mapping, static analysis, dan workflow CI PostgreSQL 18/Redis 8 terverifikasi hosted.
- Milestone 2: **selesai — hosted CI hijau pada run #4 (commit `3af7fdb`)**. Identity, Tenant lifecycle, Super User, payout account, security middleware, audit, responsive UI, PostgreSQL 18, Redis 8, dan Gitleaks telah terverifikasi.
- Milestone 3: **selesai — hosted CI hijau pada run #5 (commit `2b9366d`)**. Outlet, katalog, address book, discovery, scheduling preview, lifecycle/readiness, responsive UI, PostgreSQL 18/Redis 8, dan Gitleaks telah terverifikasi.
- Milestone 4: **implementation complete locally — hosted CI pending**. Order fixed/per-kg, snapshot/idempotency, lifecycle, isolation, monitoring, dan responsive Inertia UI tersedia.
- Milestone 5 dan seterusnya: belum diimplementasikan.

Driver invitation, actual weight, payment mutation, payout transaction, dan production integration belum tersedia. Payout hanya mencakup onboarding rekening dan payout hold, bukan pemindahan dana.

## Runtime dan dependency aktual

| Area | Versi terpasang/target |
| --- | --- |
| PHP lokal | 8.4.25 |
| Laravel | 13.31.0 |
| Laravel Fortify | 1.39.0 |
| Inertia Laravel adapter | 3.0.0 |
| Inertia React adapter | 3.0.3 |
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

## Identity, Tenant, dan Super User

Fortify menangani registration Customer, login/logout, reset/update password, email verification, password confirmation, TOTP, recovery codes, dan challenge. Passkeys dinonaktifkan. `/tenant/register` membuat Tenant pending/inactive dan owner; Driver tetap di luar scope hingga M5; Super User hanya dibuat melalui `php artisan super-user:provision` tanpa password argument/output.

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

Struktur frontend tetap memisahkan `pages`, `layouts`, reusable `components/ui`, domain composition, dan shared `types`. Halaman foundation responsive, keyboard-focusable, dan hanya menerima konfigurasi publik yang diperlukan. M3 menambahkan discovery/address/workspace; M4 menambahkan checkout, daftar/filter/detail Order, timeline, lifecycle form, dan printable receipt skeleton. Tidak ada credential, payment URL palsu, alamat lintas owner, atau internal exception message di Inertia props.

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

Verifikasi lokal M4 mencakup 93 Pest test/1.184 assertion, migration round-trip SQLite, snapshot/idempotency/isolation/query-count regression, Larastan tanpa error, Pint, ESLint, TypeScript, production build, Composer/npm audit, dan `git diff --check`. Actionlint, Gitleaks full-history, PostgreSQL 18, serta Redis 8 menunggu hosted CI.

[Hosted CI run #5](https://github.com/FatahiraAnggitaS/klik-laundry/actions/runs/35216592473) untuk implementation commit M3 `2b9366d` lulus pada kedua job: quality menggunakan PostgreSQL 18/Redis 8 dan secret scan menggunakan Gitleaks full-history.

[Hosted CI run #4](https://github.com/FatahiraAnggitaS/klik-laundry/actions/runs/35181807014) untuk implementation commit M2 `3af7fdb` lulus pada kedua job: quality menggunakan PostgreSQL 18/Redis 8 dan secret scan Gitleaks full-history.

Hosted run pertama pada commit `9c5d770` membuktikan job quality lulus penuh pada PostgreSQL 18 dan Redis 8. Job secret awal gagal pada contoh credential palsu di dokumentasi skill. Contoh kemudian di-redact dan satu fingerprint historis ditambahkan. [Hosted run #2](https://github.com/FatahiraAnggitaS/klik-laundry/actions/runs/35100746075) lulus penuh dan memenuhi exit criteria Milestone 1.

`.gitleaksignore` hanya memuat satu fingerprint historis untuk contoh credential palsu pada dokumentasi skill; contoh aktif sudah diganti menjadi `[REDACTED]`. Tidak ada allowlist path atau rule global.

## Menjalankan project lokal

```powershell
nvm use 24.21.0
composer setup
composer dev
```

`composer setup` membuat `.env` bila belum ada, menghasilkan development `APP_KEY`, menjalankan migration SQLite, menginstal frontend dependency, dan membuat production asset build. Secret nyata hanya boleh berada di `.env` atau secret manager dan tidak boleh masuk repository/frontend/log.
