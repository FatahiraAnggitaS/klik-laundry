# Security, Testing, dan Operations

> Security dan tests wajib membuktikan business scope [`01-product-scope.md`](01-product-scope.md) sekaligus layer contract [`02-architecture.md`](02-architecture.md). Lulus functional test tidak membenarkan pelanggaran Controller–Service–Repository.

## Security baseline

### Tenant isolation dan IDOR

- Tenant context berasal dari authenticated actor/entity, bukan `tenant_id` request.
- `FormRequest::authorize()` memakai Policy/Gate untuk coarse role capability tanpa query Model/Repository.
- Service memverifikasi actor, lifecycle, ownership, dan invariant untuk semua entry point termasuk job/command.
- Repository tenant-owned membutuhkan trusted Tenant context dan fail closed bila context hilang.
- Route membawa public identifier; Service meminta Repository melakukan scoped resolution. Hindari implicit tenant-owned Model binding yang melewati Repository. Public ULID bukan authorization.
- Report/export/job/broadcast/payment side effect wajib mempertahankan Tenant scope.
- Super User memakai repository/service khusus, PII masked default, tanpa global unscoped CRUD.
- Test silang minimal dua Tenant untuk setiap resource dan mutation tenant-owned.

### Authentication, 2FA, dan session

- Gunakan Laravel session authentication, CSRF, secure/HTTP-only/SameSite cookie, dan session rotation.
- Email verification wajib sebelum Customer order dan sebelum actor operasional aktif.
- TOTP 2FA serta recovery code wajib bagi Tenant owner dan satu Super User.
- Customer/Driver tidak memakai 2FA pada MVP; login/reset/invitation tetap rate-limited.
- Sensitive action meminta password/TOTP confirmation jika sensitive authentication lebih lama dari 15 menit.
- Suspend/close/deactivate mencabut active session sesuai lifecycle.
- Super User hanya dibuat melalui interactive command yang tidak menerima/mencetak password pada argument/log.
- Tidak ada impersonation, web signup Super User, role promotion, atau shared owner credential.

Implementasi M2 menggunakan `auth_version` yang distempel saat login. Middleware account-status membandingkan versi tersebut pada route project dan seluruh route account settings Fortify; suspend, close, serta password reset/update menaikkan versi dan menghapus database sessions. Re-auth action terpisah memverifikasi password dan TOTP, berlaku 900 detik, dan tidak digantikan oleh `password.confirm`. Passkeys tidak diaktifkan.

### Authorization dan layer defense

- Controller dan Form Request tidak mengakses/query Model, Repository, DB, Gateway, atau business Job/Event.
- Controller menerima validated/authorized Form Request, membuat DTO, memanggil Service, dan membentuk response.
- Service adalah satu-satunya pemilik business decision, transaction boundary, state transition, dan audit trigger.
- Service hanya mengakses entity melalui Repository interface dan provider melalui Gateway interface.
- Repository hanya mengelola scoped query, lock, aggregate, dan persistence; tidak menetapkan policy/transition/calculation.
- Job/Listener/command yang menjalankan use case harus memanggil Service.
- Repository binding eksplisit; dilarang generic BaseRepository dan service locator.

Layer separation adalah control security: ia mencegah endpoint/job baru melewati tenant scope, validation bisnis, idempotency, atau audit.

### Payment dan callback

- Duitku credential hanya server-side dan dipisahkan sandbox/production.
- Callback endpoint spesifik dikecualikan dari CSRF, tetapi dibatasi method/content type/body size dan rate anomaly.
- Signature diverifikasi constant-time; merchant, amount, merchant order ID, provider reference, dan state ikut diverifikasi.
- Callback duplicate/out-of-order/concurrent diproses idempotent dengan transaction + row lock.
- Redirect/JS callback tidak dipercaya; Super User/Tenant tidak dapat mark paid.
- Satu active attempt per order, expiry 60 menit, dan uncertain response masuk controlled inquiry.
- Maintenance mode menolak invoice baru tetapi tetap menerima callback/reconciliation lama.
- Jangan log API key, signature, raw callback, payment URL, atau data provider yang tidak diperlukan.
- HTTPS dan timeout eksplisit wajib; retry hanya untuk operasi yang dibuktikan aman.

### Financial action, audit, dan fraud resistance

- Fee Duitku actual tidak boleh diasumsikan nol; settlement ditahan sampai reconciliation lengkap.
- Payout membership dipilih Service dari seluruh eligible record hingga cutoff, bukan daftar ID client.
- Payout hold mencegah finalization tanpa menghentikan order.
- Final payout/payment/history immutable; correction memakai append-only adjustment.
- Full refund amount berasal dari payment paid, satu completed refund per payment, deadline 3×24 jam.
- Refund tidak menurunkan payment paid dan tidak membalik completed Driver commission.
- Approval/completion refund, payout finalization, payout-account verification, dan PII reveal membutuhkan reason, re-auth, authorization, serta audit.
- Satu Super User berarti belum ada four-eyes approval; monitoring dan audit review menjadi compensating control MVP.

### Privacy dan PII

- Tenant melihat contact Customer hanya selama order aktif sampai 3×24 jam sesudah completion.
- Driver melihat contact lengkap hanya setelah accept sampai task selesai.
- Super User mendapat masked PII; reveal order-specific, time-limited, beralasan, dan diaudit.
- Revealed PII tidak masuk CSV/export.
- Tracking tidak mengumpulkan live GPS Driver.
- Customer anonymization hanya jika tidak ada order/payment/refund aktif; record transaksi minimum dipertahankan.
- Retention finansial dan PII harus mendapat validasi legal sebelum production.

### Input, output, dan CSV

- Semua input memakai Form Request server-side; TypeScript/client validation hanya UX.
- Service tidak mempercayai role, Tenant, Customer, total, fee, weight result, status, atau batch membership dari client.
- React escaping default dipertahankan; hindari `dangerouslySetInnerHTML`.
- Rate limit login/reset/invitation, order, payment create/inquiry, callback anomaly, PII reveal, dan upload.
- Production error generik; domain exception dipetakan ke pesan aman.
- CSV maksimal 5.000 row/90 hari, di-stream tanpa public storage, dan menetralkan prefix `=`, `+`, `-`, `@`.

### File upload

- Hanya raster image MIME yang disetujui; validasi extension, MIME, byte size, dimension, dan count.
- Generate object key; jangan gunakan path/nama user.
- Simpan private dan gunakan temporary authorized URL.
- Buang metadata EXIF jika pipeline mendukung dan test hasilnya.
- Proof dihapus 90 hari setelah order dan seluruh refund terkait selesai; metadata audit dipertahankan.
- Jangan melayani SVG/HTML aktif atau mempercayai client MIME.

### Secret management

- `.env*` sensitif tidak di-track; `.env.example` hanya placeholder aman.
- Jangan bake `.env` ke image/build context.
- `APP_KEY`, Duitku key, database/Redis/storage credential tidak memakai `VITE_*` atau Inertia props.
- CI menggunakan encrypted secret dan sandbox credential; untrusted fork tidak mendapat production secret.
- Secret scan berjalan pada staged diff/CI.
- Pengecualian secret scan harus berupa fingerprint temuan yang spesifik dan memiliki alasan; jangan mengecualikan folder/source secara luas.
- Secret yang pernah terekspos harus revoke/rotate dan diperiksa pada history/log/artifact.

## Test strategy per layer

Prioritaskan behavior dan gunakan database nyata untuk Repository. Fake/mock hanya pada boundary yang memang perlu diisolasi.

### Architecture tests

Checks wajib gagal jika:

- Controller/Form Request import `App\Models`, Repository, `DB`, atau provider Gateway;
- Controller memiliki calculation/state transition/query;
- Service import Model atau membuat Eloquent/query-builder query;
- Repository memanggil Service, event bisnis, notification, atau external Gateway;
- Job/Listener melakukan mutation domain tanpa Service;
- implementation interface tidak terdaftar atau circular dependency muncul.

Gunakan static analysis/architecture test yang kompatibel dengan dependency aktual. Jangan menambah package hanya bila check sederhana dapat dipenuhi melalui test/reflection/static rule yang terawat.

### Controller dan Form Request tests

- Authentication, email verification, 2FA middleware, role route access.
- Validation boundary, authorization response, safe error/redirect, dan duplicate submission.
- Controller meneruskan typed DTO ke Service dan tidak mengubah input menjadi business outcome.
- Callback Controller hanya meneruskan request terbatasi ke callback Service.

### Service tests

- Fixed quantity, fees, per-kg minimum/pembulatan 100 gram, serta snapshot calculation.
- Tenant/outlet readiness dan lifecycle capabilities.
- Fulfillment/payment/task/refund/payout transition serta actor matrix.
- Schedule lead time/horizon/blackout, delay indicator, dan reschedule rules.
- Offer expiry, single active Driver task, commission idempotency.
- Payment attempt eligibility, maintenance mode, monotonic callback processing.
- Refund deadline/full amount/adjustment dan payout eligibility/batch membership.
- PII visibility windows, re-auth requirement, session revocation, dan audit creation.
- After-commit event semantics.

M3 menambahkan verifikasi lifecycle outlet/package, seluruh blocker readiness, perubahan rekening payout yang menurunkan outlet aktif menjadi draft, konflik global radius, invariant fixed/per-kg, ownership/default address, serta lead time/horizon/weekday/blackout scheduling.

Unit test Service boleh memakai fake Repository/Gateway untuk decision matrix. Race/transaction/constraint behavior wajib diuji lagi menggunakan database.

### Repository integration tests

- Tenant-scoped query fail closed dan tidak bocor antar-Tenant.
- Eager loading/pagination/filter/sort dan selected columns benar.
- Row lock/conditional mutation menghadapi concurrent accept/callback/finalization.
- Unique/partial/check/FK constraint menolak duplicate active payment, task, commission, payout item, atau cross-Tenant reference.
- Report aggregate dan timezone boundary cocok dengan source records.
- Super User read repository memasking PII dan tidak menyediakan arbitrary mutation.

Repository test tidak menguji business decision; ia membuktikan query/persistence contract.

### Gateway dan payment contract tests

- `Http::fake()` untuk create success/pending/error/timeout/malformed response.
- Signature builder/verifier memakai official non-secret fixture.
- Callback valid, invalid signature, unknown ID, wrong merchant/amount/reference.
- Duplicate, out-of-order, concurrent callback dan lost create response.
- Return URL tidak mengubah payment.
- Sandbox smoke terpisah dari per-commit CI agar tidak flaky.

### Feature tests

- Tenant registration sampai approval/2FA/outlet readiness.
- Customer discovery dan fixed/per-kg order end-to-end.
- Tenant A tidak dapat list/show/update/archive/export Tenant B.
- Customer hanya melihat order sendiri; Driver hanya task/contact pada privacy window.
- Cancellation/reschedule sebelum/sesudah task accept.
- Refund, Tenant payout, Driver payout, hold, report, dan masked CSV.
- Private broadcast channel authorization dan minimal payload.
- Upload validation, authorized temporary access, dan cleanup eligibility.

Feature suite M3 juga membuktikan discovery guest/Customer, filter pricing, urutan/radius distance, dua kandidat alamat, cross-Tenant fail-closed, Tenant nonaktif read-only, serta query count bounded pada fixture 10 Tenant/20 outlet.

### Realtime, queue, dan browser tests

- Event/listener berjalan after commit dan retry idempotent.
- Queue/Reverb failure tidak rollback domain state.
- Polling mendapatkan current server state saat WebSocket unavailable.
- Critical browser journeys:
  1. fixed payment sebelum pickup sampai completed;
  2. per-kg pickup, weight, payment, processing, delivery;
  3. Tenant/Super User finance and refund;
  4. cross-role forbidden behavior dan tracking fallback.
- Accessibility smoke: keyboard, focus, label, error, loading, pending, empty, responsive.

## CI quality gates

Setiap pull request menjalankan:

1. install dari lockfile;
2. backend test dengan PostgreSQL/Redis test environment;
3. architecture tests;
4. Laravel Pint check;
5. PHPStan/Larastan pada agreed level;
6. ESLint dan TypeScript no-emit;
7. production frontend build;
8. dependency vulnerability audit dan secret scan.

Test tidak boleh ditandai lulus ketika di-skip. Migration compatibility, sandbox/UAT smoke, load test, dan deployment smoke menjadi release gate sesuai milestone.

## Observability

### Structured logs dan audit

Log teknis memuat correlation ID, safe actor/Tenant/order/payment public ID, event, result, latency, dan error class. Redact password, TOTP/recovery code, token, cookie/session/auth header, Duitku credential/signature, raw callback, PII, dan signed URL.

Business audit terpisah dari debug log, append-only, dan memuat actor, action, target, reason, safe before/after, serta timestamp. Audit tidak boleh bergantung pada frontend event.

Pada M2, profile audit hanya mencatat boolean field-changed; payout audit hanya memuat status verifikasi dan bank. Phone, email, nama personal, plaintext/ciphertext rekening, password/hash, secret TOTP, serta recovery code tidak disalin ke audit.

### Metrics dan alert

- HTTP p95/error, DB pool/query latency, queue depth/oldest/failed/retry.
- Reverb connection/error/delivery latency dan polling fallback.
- Duitku create/callback/inquiry latency, invalid signature, mismatch, pending age.
- Order stuck/delayed/awaiting Customer per stage.
- Refund/payout age, hold, unknown fee, negative adjustment, reconciliation mismatch.
- 2FA/reset/session-revocation failure, PII reveal, authorization anomaly.
- Storage cleanup, backup age/health, and restore result.

Alert harus actionable, memiliki owner dan runbook. Duplicate callback normal dicatat sebagai metric, bukan otomatis incident.

## Backup, recovery, dan availability

- Automated encrypted PostgreSQL backup dengan retention yang disepakati.
- Private object storage policy sesuai 90-day proof retention.
- Restore drill ke environment terisolasi sebelum pilot dan periodik.
- Redis bukan source of truth transaksi.
- RPO/RTO harus disetujui bisnis; jangan mengarang nilai.
- Availability pilot dihitung terhadap target 99,5% bulanan di luar announced maintenance.

## Deployment safety

- Build immutable artifact tanpa secret.
- Gunakan backward-compatible migration untuk rolling/zero-downtime deployment bila diperlukan.
- Health check web dipisahkan dari monitoring queue, scheduler, Reverb, database, Redis, dan provider.
- Graceful restart worker/Reverb setelah deploy.
- Rollback code tidak memakai database reset/destructive migration.
- Production payment hanya aktif sesudah sandbox/UAT, legal/provider approval, dan config verification.
- Payment maintenance dapat menghentikan invoice baru tanpa mematikan callback.

## Operational runbooks minimum

- Duitku timeout/callback tertunda/resend/inquiry dan local-provider mismatch.
- Payment maintenance activation/deactivation.
- Queue backlog/failed idempotent replay dan Reverb fallback.
- Tenant suspension/closure, user compromise, 2FA recovery, dan session revocation.
- Payout account change/hold, payout mismatch, refund/negative adjustment.
- PII reveal investigation dan suspected privacy incident.
- Secret exposure/revocation/rotation.
- Database restore dan proof cleanup failure.
- Incorrect report correction melalui audited adjustment.

## Production readiness checklist

- [ ] Business/provider/legal decisions merchant, fee, refund, payout, privacy, dan retention disetujui.
- [ ] Empat role, mandatory 2FA, session lifecycle, dan re-auth diuji.
- [ ] Architecture tests membuktikan Controller–Service–Repository boundary.
- [ ] Sepuluh Tenant/20 outlet/50 Driver seed dan load scenario lulus tanpa isolation leak.
- [ ] Policies, Service invariants, Repository scopes, jobs, broadcasts, report/export isolation diuji.
- [ ] Duitku production channel/config/callback HTTPS diverifikasi.
- [ ] Callback tampered/duplicate/out-of-order/concurrent dan maintenance mode tests lulus.
- [ ] Refund/payout/adjustment/commission dapat direkonsiliasi ke source records.
- [ ] Tidak ada production secret di repo, frontend, image, log, artifact, atau test.
- [ ] Queue/Reverb supervisor, retry, alert, dan polling fallback diuji.
- [ ] Backup berhasil direstore dan retention cleanup diuji.
- [ ] Critical browser flow, responsive UI, dan accessibility smoke lulus.
- [ ] Pint, static analysis, architecture, backend, lint, typecheck, build, audit, dan scan hijau.
- [ ] Monitoring dashboard, owner, escalation, dan runbook tersedia.
- [ ] Performance/realtime/availability acceptance criteria tercapai pada profil MVP.
