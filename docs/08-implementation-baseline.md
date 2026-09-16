# Implementation Baseline

> Status aktual per 16 September 2026. Dokumen ini menjelaskan implementasi yang benar-benar tersedia dan tidak menggantikan product, domain, atau architecture contract.

## Status milestone

- Milestone 0: **in progress — external blockers**. Contract, threat model, wireflow, dan sandbox harness tersedia; live provider/legal sign-off belum ada.
- Milestone 1: **in progress — secret scan rerun pending**. Persistence slice, boundary enforcement, safe error mapping, static analysis, dan workflow CI sudah tersedia.
- Milestone 2 dan seterusnya: belum diimplementasikan.

Authentication, actor authorization, tenant isolation, order/payment mutation, payout, dan production integration belum tersedia. `User` masih model Laravel baseline; Policy baru dibuat saat identity dan actor Milestone 2 memiliki behavior nyata.

## Runtime dan dependency aktual

| Area | Versi terpasang/target |
| --- | --- |
| PHP lokal | 8.4.25 |
| Laravel | 13.31.0 |
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

Struktur frontend tetap memisahkan `pages`, `layouts`, reusable `components/ui`, domain composition, dan shared `types`. Halaman foundation responsive, keyboard-focusable, dan hanya menerima konfigurasi publik yang diperlukan. Tidak ada credential atau internal exception message di Inertia props.

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

Hosted run pertama pada commit `9c5d770` membuktikan job quality lulus penuh pada PostgreSQL 18 dan Redis 8. Job secret gagal pada contoh credential palsu di dokumentasi skill. Contoh sudah di-redact dan satu fingerprint historis sudah ditambahkan; hosted rerun masih diperlukan sebelum Milestone 1 dinyatakan selesai.

`.gitleaksignore` hanya memuat satu fingerprint historis untuk contoh credential palsu pada dokumentasi skill; contoh aktif sudah diganti menjadi `[REDACTED]`. Tidak ada allowlist path atau rule global.

## Menjalankan project lokal

```powershell
nvm use 24.21.0
composer setup
composer dev
```

`composer setup` membuat `.env` bila belum ada, menghasilkan development `APP_KEY`, menjalankan migration SQLite, menginstal frontend dependency, dan membuat production asset build. Secret nyata hanya boleh berada di `.env` atau secret manager dan tidak boleh masuk repository/frontend/log.
