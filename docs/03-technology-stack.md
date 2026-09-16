# Technology Stack

> Source of truth bisnis: [`01-product-scope.md`](01-product-scope.md). Arsitektur layer wajib mengikuti [`02-architecture.md`](02-architecture.md).

## Baseline yang direkomendasikan

Versi di bawah adalah baseline major version untuk bootstrap, divalidasi pada 15 September 2026. Gunakan latest stable patch yang kompatibel dan commit lockfile. Jangan menyalin nomor minor/patch dari dokumen ini tanpa mengecek package manager dan dokumentasi resmi.

| Area | Rekomendasi | Digunakan untuk | Alasan dan trade-off |
| --- | --- | --- | --- |
| Runtime | PHP 8.5 | Menjalankan Laravel | Versi stable yang didukung Laravel 13; cek extension dan image production sebelum mengunci |
| JavaScript runtime | Node.js 24 LTS + npm | Menjalankan Vite, lint, typecheck, dan frontend build | LTS memberi baseline CI/deploy yang stabil; npm dipilih agar setup familiar dan lockfile tunggal |
| Backend | Laravel 13.x | HTTP, auth, validation, policy, Eloquent, queue, notification, cache | Convention lengkap mengurangi custom infrastructure; framework tetap harus di-upgrade berkala |
| Frontend bridge | Inertia.js 3.x | Menghubungkan controller Laravel dengan page React | Tidak perlu API terpisah untuk web app; coupling ke server-driven navigation memang disengaja |
| UI | React 19 + TypeScript | Presentation dan interaction | Sesuai official React starter kit; TypeScript menambah correctness dengan sedikit compile overhead |
| Styling | Tailwind CSS 4 | Design system dan responsive UI | Sudah menjadi baseline starter kit; jaga reusable component agar class tidak terduplikasi |
| Build | Vite (versi starter kit) | Dev server dan production assets | Integrasi resmi Laravel; versi aktual mengikuti starter kit/lockfile |
| Database | SQLite local awal + PostgreSQL 18 target/CI | Local onboarding ringan dan transactional source of truth production | Portability wajib dibuktikan di CI PostgreSQL; SQLite bukan pengganti behavior concurrency PostgreSQL |
| Cache/queue | Database/array pada foundation; Redis 8 target | Queue, cache, rate-limit coordination, session | Redis diaktifkan saat workload background/realtime masuk scope dan diverifikasi pada CI |
| Realtime | Laravel Reverb + Echo | WebSocket private-channel status updates | First-party dan self-hosted; perlu process, TLS/proxy, monitoring, serta polling fallback |
| Payment | Duitku POP API via Laravel HTTP client | Invoice QRIS/E-Wallet dan callback | Direct integration kecil lebih mudah diaudit daripada package pihak ketiga; perubahan provider menjadi tanggung jawab kita |
| File | S3-compatible private object storage | Proof pickup/delivery | Stateless deployment dan signed access; biaya/service eksternal bertambah |
| Test | Pest versi stable yang kompatibel (di atas PHPUnit), Laravel HTTP/database test, dan Vitest bila unit UI diperlukan | Behavior, layer contract, dan regression test | Utamakan Service/feature test; Repository diuji dengan database nyata dan browser E2E dibatasi ke critical flow |
| Quality | Pint, PHPStan/Larastan setelah compatibility dicek, ESLint, TypeScript, dan architecture checks | Static analysis, coding convention, serta dependency direction | Menangkap defect dan layer violation lebih awal; aturan harus memberi signal yang jelas dan tidak boleh dinonaktifkan diam-diam |
| CI | GitHub Actions | Test, lint, typecheck, build | Reproducible quality gate; secret CI harus memakai encrypted secrets dan sandbox credential |
| Local dev | SQLite terlebih dahulu | Menjalankan foundation tanpa service eksternal | Cepat dan sederhana; PostgreSQL CI wajib menjaga compatibility dan Docker dapat ditambahkan saat dibutuhkan |

Laravel 13 membutuhkan PHP minimal 8.3 dan mendukung sampai PHP 8.5 menurut [release notes resmi](https://laravel.com/docs/13.x/releases). Official [React starter kit](https://laravel.com/starter-kits) saat baseline ini memakai React 19, TypeScript, Inertia 3, Tailwind 4, dan menyertakan CI workflow.

Node.js 24 berstatus LTS menurut [jadwal rilis resmi Node.js](https://nodejs.org/en/about/previous-releases). PostgreSQL 18 telah menjadi stable major release menurut [release notes PostgreSQL](https://www.postgresql.org/docs/release/18.0/), dan Redis 8 tersedia sebagai stable release family pada [release notes Redis Open Source](https://redis.io/docs/latest/operate/oss_and_stack/stack-with-enterprise/release-notes/redisce/). Patch image/container tetap dipilih saat bootstrap berdasarkan compatibility dan security update terbaru.

## Dependency decisions

### Penerapan stack pada project pattern

Stack tidak mengubah dependency direction berikut:

```text
Inertia route
  -> Form Request + Policy
  -> Controller
  -> Service
  -> Repository Interface
  -> Eloquent Repository
  -> Eloquent Model / PostgreSQL
```

Provider eksternal menggunakan jalur terpisah `Service -> Gateway Interface -> Provider Client`. Duitku bukan Repository karena ia bukan persistence abstraction milik aggregate aplikasi.

Laravel service container digunakan untuk constructor injection dan binding:

```text
OrderRepositoryInterface     -> EloquentOrderRepository
PaymentRepositoryInterface   -> EloquentPaymentRepository
PaymentGatewayInterface      -> DuitkuGateway
```

Binding didefinisikan eksplisit di Service Provider. Jangan resolve dependency melalui `app()` di Controller/Service, jangan memakai static singleton buatan sendiri, dan jangan membuat generic repository package. Eloquent hanya boleh digunakan di `Repositories/Eloquent` serta Model; Laravel HTTP client untuk Duitku hanya boleh digunakan di implementation Gateway. Pendekatan constructor injection dan interface binding mengikuti [Laravel 13 service container](https://laravel.com/docs/13.x/container#binding-interfaces-to-implementations), sedangkan validasi request mengikuti [Laravel 13 Form Request](https://laravel.com/docs/13.x/validation#form-request-validation).

### Authentication dan authorization

Gunakan built-in authentication dari official Laravel React starter kit, session/cookie auth, email verification, TOTP 2FA, middleware, gates/policies, dan enum untuk empat role: `customer`, `tenant_owner`, `driver`, dan `super_user`. TOTP wajib untuk Tenant owner dan Super User. Jangan memasang Sanctum kecuali public/mobile API benar-benar masuk scope, dan jangan memasang permission package untuk role MVP yang statis.

Pros:

- lebih sedikit dependency dan configuration;
- authorization tetap eksplisit dalam policy;
- sesuai server-rendered Inertia navigation.

Trade-off: satu akun owner per Tenant dan satu Super User menyederhanakan authorization, tetapi belum menyediakan segregation of duties atau granular staff permission.

### Payment client

Gunakan Laravel HTTP client di balik `DuitkuGateway` yang mengimplementasikan `PaymentGatewayInterface`, bukan SDK community yang belum diverifikasi. Gateway menangani base URL, timeout, HMAC signature sesuai produk Duitku yang dipilih, request/response mapping, dan safe logging. Semua keputusan payment state, retry eligibility, invoice expiry, maintenance mode, serta idempotency tetap berada di Service; Gateway tidak memuat business rule.

Pros:

- surface area kecil dan audit signature lebih mudah;
- tidak tergantung maintenance SDK;
- sandbox test dapat memakai `Http::fake()`.

Trade-off: tim wajib mengikuti changelog dan memperbarui mapping bila API berubah.

### Repository implementation

Gunakan Repository interface buatan project dan implementasi Eloquent yang kecil. Tidak diperlukan package repository pihak ketiga. Repository mengelola query/scoping/eager loading/pagination/persistence, sedangkan Service mengelola business rule dan transaction boundary.

Pros:

- Eloquent tidak bocor ke Controller dan detail query terpusat;
- Service dapat diuji dengan fake/mock interface pada unit test;
- query tenant dan Super User dapat dipisahkan secara eksplisit.

Trade-off: lebih banyak class dan binding. Hindari satu interface per tabel jika tidak ada use case, generic CRUD, dan wrapper yang hanya menyalin seluruh method Eloquent.

### Geolocation

MVP menggunakan browser Geolocation API (dengan consent), koordinat tersimpan, dan kalkulasi Haversine/bounding radius di backend. Daftar outlet adalah UI utama; map visual bukan dependency wajib.

Pros: cukup untuk 10 tenant dan tidak membutuhkan key provider peta. Trade-off: jarak adalah garis lurus, bukan driving distance/ETA. Jika akurasi rute menjadi kebutuhan, pilih provider map/geocoding setelah mengevaluasi harga, coverage Indonesia, privacy, dan terms.

### UI components

Mulai dari komponen starter kit/shadcn/ui yang memang dibawa official starter kit, lalu bangun shared component seperti `PageHeader`, `FormField`, `StatusBadge`, `EmptyState`, `ConfirmDialog`, dan `Pagination`. Jangan menambah UI framework kedua.

### Date, money, and identifiers

- Uang: integer IDR di database; formatting melalui `Intl.NumberFormat('id-ID', { currency: 'IDR' })`.
- Waktu: simpan UTC, tampilkan `Asia/Jakarta`; gunakan API Laravel/Carbon dan `Intl.DateTimeFormat` sebelum menambah date library.
- Primary key: bigint internal; `public_id` ULID unik untuk URL/order reference agar ID berurutan tidak diekspos.
- Status: backed enum PHP dan union/string enum TypeScript yang dibangkitkan atau dijaga melalui shared contract test; jangan memakai magic string tersebar.

## Komponen yang sengaja tidak dipilih

| Teknologi | Alasan ditunda |
| --- | --- |
| Kubernetes | Beban operasional tidak sebanding dengan target MVP |
| Microservices/message broker terpisah | Laravel queue + database transaction cukup untuk core flow |
| Elasticsearch | Pencarian outlet/paket masih kecil dan dapat ditangani PostgreSQL |
| PostGIS | Haversine/koordinat cukup pada volume awal; tambahkan ketika query geospasial kompleks terbukti perlu |
| Redux/Zustand | Server state dari Laravel/Inertia dan local React state sudah cukup |
| Separate API + JWT | Menambah duplicated validation/auth/client state tanpa kebutuhan mobile/public API |
| Generic multi-tenancy package | Isolasi satu schema sederhana lebih transparan jika dibuat dengan convention dan test |
| Repository package/generic base repository | Contract domain yang kecil lebih jelas dan tidak membuka CRUD arbitrary |
| Accounting package | Laporan MVP bukan general ledger; business definition belum cukup matang |

## Environment dan configuration

Rahasia hanya berada di server environment/secret manager. Nama variable akhir mengikuti konfigurasi yang benar-benar dibuat; contoh kategori:

```text
APP_*
DB_*
REDIS_*
QUEUE_CONNECTION
BROADCAST_CONNECTION
REVERB_*
DUITKU_ENVIRONMENT
DUITKU_MERCHANT_CODE
DUITKU_API_KEY
DUITKU_CALLBACK_URL
DUITKU_RETURN_URL
DUITKU_CREATE_INVOICE_URL
DUITKU_INQUIRY_URL
DUITKU_CONNECT_TIMEOUT_SECONDS
DUITKU_TIMEOUT_SECONDS
FILESYSTEM_DISK
AWS_*
```

`DUITKU_API_KEY`, database password, `APP_KEY`, Redis credential, dan object-storage secret tidak boleh memakai prefix `VITE_` atau dikirim sebagai Inertia props. `.env.example` hanya berisi placeholder aman.

Konfigurasi Duitku yang tersedia pada Milestone 0 hanya digunakan command sandbox opt-in dan menolak application environment production serta host non-sandbox. Kontrak Gateway production tetap dikerjakan pada Milestone 6 setelah blocker provider/legal selesai.

## Version verification saat bootstrap

1. Buat project dengan Laravel installer dan official React starter kit.
2. Catat versi PHP/Composer/Node yang benar-benar dipakai.
3. Periksa `composer.json`, `package.json`, dan lockfile.
4. Baca upgrade/release notes untuk major version yang terpasang.
5. Verifikasi extension PHP, PostgreSQL, Redis, dan image container mendukung versi tersebut.
6. Jalankan backend tests, TypeScript check, lint, dan production build sebelum baseline commit.
7. Jalankan architecture checks yang membuktikan Controller tidak mengakses Model/Repository/DB, Service tidak mengakses Model/query builder, dan Repository tidak memanggil Service.

Jangan menjalankan command contoh dari dokumentasi versi lama. Inertia v3 memiliki perubahan API dari v2; gunakan [dokumentasi v3](https://inertiajs.com/docs/v3/getting-started/upgrade-guide) sebagai acuan.
