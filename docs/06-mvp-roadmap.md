# Roadmap MVP

> Roadmap menggunakan [`01-product-scope.md`](01-product-scope.md) sebagai scope contract dan [`02-architecture.md`](02-architecture.md) sebagai architecture contract. Milestone berbasis outcome, bukan estimasi kalender.

> Status per 17 September 2026: Milestone 0 tetap memiliki external blocker. Milestone 1–4 **selesai — hosted CI hijau**. Implementation commit M4 `af34964` lulus pada run #7 dengan PostgreSQL 18, Redis 8, seluruh quality gate, dependency audit, dan secret scan.

## Prinsip delivery

- Bangun vertical slice kecil dan lanjut hanya setelah exit criteria terpenuhi.
- Setiap HTTP use case wajib mengikuti `Form Request -> Controller -> Service -> Repository Interface -> Eloquent Repository`.
- Semua business rule dan transaction boundary berada di Service; Controller dan Repository tidak boleh mengambil keputusan bisnis.
- Setiap feature menyertakan authorization, validation, audit, error/loading/empty/pending state, dan tests yang relevan.
- Jangan membuat generic BaseRepository, God Service, atau abstraction spekulatif.
- Latest compatible dependency dan API provider diverifikasi dari dokumentasi resmi saat bootstrap/implementasi.

## Prioritas MVP

### Must have

- Empat role: Customer, Tenant owner, Driver, dan satu Super User.
- Email verification; TOTP 2FA wajib bagi Tenant owner dan Super User.
- Tenant self-registration, approval, suspension/closure, payout account, dan audit.
- Multi-outlet, paket global Tenant, radius, fees, hours, slots, blackout, dan readiness.
- Discovery berdasarkan area/lokasi serta order fixed/per-kg.
- Fulfillment state machine, reschedule/cancel, delay indicators, dan timeline.
- Driver invitation/availability, one-at-a-time offer, one active task, proof, dan commission.
- Duitku QRIS/E-Wallet, global allowlist, maintenance mode, callback, inquiry, dan reconciliation.
- Realtime private notification dengan durable history serta polling fallback.
- Full-refund workflow, Tenant payout, Driver payout, report, dan CSV.
- PII masking/reveal, retention, automated tests, CI, backup, observability, serta runbook.

### Explicit non-goals

Ikuti bagian non-goals pada [`01-product-scope.md`](01-product-scope.md): native/PWA, public API, live GPS/map interaktif, auto dispatch/route optimization, staff permission granular, COD/cash/manual transfer/card/VA, partial/automated refund, automated payout/split settlement, promo/loyalty, dan general ledger.

## Milestone 0 — Risk validation dan domain contract

> Status per 16 September 2026: **in progress — external blockers**. Domain contract, threat model, wireflow responsive, dan sandbox harness tersedia di [`milestone-0/`](milestone-0/). Live sandbox callback serta sign-off bisnis/legal/Duitku belum tersedia sehingga exit criteria belum dinyatakan selesai.

Deliverables:

- Validasi tertulis model satu merchant account platform, merchant-of-record, fee, payout, refund manual, privacy, dan retention bersama pihak bisnis/legal/Duitku.
- Sandbox spike untuk create invoice, callback, signature, duplicate/out-of-order event, expiry, inquiry, fee data, dan timeout.
- Glossary enum/status, transition matrix, calculation examples, dan sequence diagram final.
- Wireflow responsive untuk empat role.
- Threat model awal untuk tenant isolation, payment callback, payout, PII reveal, dan file proof.

Exit criteria:

- Tidak ada ambiguity yang mengubah ownership dana, payment timing fixed/per-kg, atau actor transition.
- Callback server-to-server dapat diverifikasi tanpa mengandalkan browser redirect.
- Constraint provider yang belum terbukti ditandai sebagai release blocker, bukan diasumsikan.

## Milestone 1 — Foundation dan architecture enforcement

> Status implementasi: **selesai — CI hijau pada run #2 (commit 9b23f68)**. SQLite menjadi default local/test. Hosted CI PostgreSQL/Redis, quality job, dan secret scan lulus.

Deliverables:

- Bootstrap Laravel 13 official React starter kit, Inertia, React/TypeScript, dan Tailwind berdasarkan versi aktual yang terverifikasi.
- SQLite local-first dengan schema portable ke PostgreSQL, PostgreSQL/Redis service pada CI, safe `.env.example`, dan `.gitignore`. `.dockerignore` tidak diperlukan karena Docker lokal belum dipakai.
- Folder Controller, Form Request, DTO, Service, Repository Contract/Eloquent, Gateway, Model, serta interface bindings. Policy dibuat pada Milestone 2 ketika actor dan authorization use case nyata tersedia; tidak dibuat placeholder.
- Satu reference vertical slice sederhana yang membuktikan Controller -> Service -> Repository.
- Central domain exception mapping dan safe HTTP error handling.
- CI untuk test, Pint, static analysis, ESLint, TypeScript, build, secret/dependency scan.
- Architecture checks untuk larangan bypass layer.

Exit criteria:

- Clean checkout dapat di-install dan seluruh CI gate hijau.
- Controller/Form Request tidak import atau query Model/Repository/DB; Service tidak query Model; Repository tidak memanggil Service.
- Binding interface dapat di-resolve dan reference slice diuji.
- Tidak ada secret atau debug artifact pada tracked files.

## Milestone 2 — Identity, Tenant, dan Super User

> Status implementasi per 17 September 2026: **selesai — hosted CI hijau pada run #4 (commit `3af7fdb`)**. Session auth Fortify, lifecycle Tenant, payout account terenkripsi, re-auth sensitif, audit, isolation tests, responsive Inertia UI, migration round-trip SQLite, serta PostgreSQL 18/Redis 8 CI telah terverifikasi.

Deliverables:

- Registration/login/logout/reset/email verification dan phone contact.
- Role/status enum; session revocation; TOTP setup/recovery untuk Tenant owner/Super User.
- Tenant registration/review/rejection/resubmission/suspension/reactivation/closure request.
- Secure interactive provisioning untuk satu Super User tanpa password argument/log.
- Payout account submission, verification/change, masked display, dan payout hold.
- Policy, Service invariant, tenant-scoped Repository, reason/re-auth/audit baseline.

Exit criteria:

- Pending/rejected/suspended/closed Tenant mendapat kemampuan tepat sesuai scope.
- Tenant A tidak dapat read/mutate Tenant B pada endpoint, Service, Repository, job, atau export.
- Super User tidak dapat impersonate atau melakukan generic status/payment override.
- Sensitive action menolak stale re-auth dan mencatat audit tanpa secret.

## Milestone 3 — Outlet, catalog, discovery, dan scheduling

> Status implementasi per 17 September 2026: **selesai — hosted CI hijau pada run #5 (commit `2b9366d`)**. Schema/backfill reversible, lifecycle/readiness, discovery, address book, scheduling preview, responsive Inertia UI, isolation, query-count fixture, PostgreSQL 18/Redis 8, dan Gitleaks telah terverifikasi.

Deliverables:

- Multi-outlet profile, coordinates, radius `1..global max`, fixed pickup/delivery fees, hours, activation/readiness.
- Paket global Tenant untuk fixed/per-kg, minimum dan estimated duration, archive rules.
- Repeating pickup/delivery slots, two-hour lead time, seven-day horizon, dan blackout.
- Customer address dengan consent; browsing by area fallback; distance-sorted eligible outlet list.
- Search/filter name/area/pricing type, pagination, dan responsive states.

Exit criteria:

- Outlet tidak aktif sebelum seluruh readiness condition terpenuhi.
- Customer tanpa koordinat dapat browse tetapi tidak order.
- Kedua alamat order candidate tervalidasi terhadap radius.
- Query list tidak N+1 dan memenuhi pagination/representative performance check.

## Milestone 4 — Order, scheduling, dan cancellation slice

> Status per 17 September 2026: **selesai — hosted CI hijau pada run #7 (commit `af34964`)**. Aggregate Order, snapshot, idempotency, lifecycle, isolation, monitoring job, dan UI responsive terverifikasi pada SQLite lokal serta PostgreSQL 18/Redis 8 hosted CI.

Deliverables:

- `CreateOrderService` dengan satu package, snapshots, fixed/per-kg branching, fees, dan idempotency.
- Server calculation untuk integer fixed quantity serta per-kg estimate label.
- Fulfillment/payment status terpisah dan append-only history.
- Customer/Tenant order views, filters, history, printable receipt skeleton.
- Customer self-reschedule/cancel precondition dan Tenant audited reschedule/cancel.
- Delay/awaiting-customer indicators serta monitoring job melalui Service.

Exit criteria:

- Client-supplied total/status/Tenant/Customer authority diabaikan.
- Duplicate request tidak membuat order kedua.
- Perubahan master data tidak mengubah snapshot order.
- Invalid actor/state/schedule/radius/cross-Tenant transition ditolak tests.

## Milestone 5 — Dispatch, weighing, dan commission

Status implementasi per 22 September 2026: **selesai — hosted CI hijau pada run #10 (commit `618b7f7`)**. Pickup flow, invitation, Tenant-scoped revocation, lifecycle guard, privacy window, weighing, private proof, cancellation, commission, dan logout dispatch workspaces tersedia. Delivery completion tetap Milestone 7 agar task, order completion, dan commission selesai dalam transaction yang sama.

Deliverables:

- Driver invitation, activation/deactivation, availability, dan privacy window.
- Pickup/delivery task; one-Driver offer selama 10 menit; accept/reject/expiry/reassign.
- Constraint satu active task per Driver dan atomic acceptance.
- Fixed commission snapshot per leg; earned exactly once ketika task selesai.
- Per-kg actual/minimum/billable weight, pembulatan 100 gram, optional private proof.
- Pickup/delivery delay handling dan notification event.

Exit criteria:

- Race/concurrent accept tidak membuat dua assignee atau task aktif kedua.
- Driver hanya melihat Customer contact pada task accepted hingga selesai.
- Completion retry tidak menggandakan commission/history.
- Per-kg total final akurat dan tidak dapat diubah setelah invoice dibuat.

## Milestone 6 — Duitku payment

Status implementasi per 22 September 2026: **in progress — external sandbox blocker**. Vertical slice invoice, callback, expiry, return, receipt, Tenant payment list, Super User reconciliation/inquiry, channel allowlist, dan maintenance mode tersedia serta lulus hosted CI run #10. Live sandbox belum dijalankan karena credential dan callback HTTPS publik belum tersedia; production tetap dinonaktifkan.

Deliverables:

- `PaymentGatewayInterface` dan `DuitkuGateway` dengan safe config, timeout, normalized result, serta HTTP fakes.
- Global QRIS/E-Wallet allowlist dan payment maintenance mode.
- Fixed invoice sebelum pickup; per-kg invoice setelah weight; expiry 60 menit.
- Local payment attempt, unique merchant order ID, single active attempt, dan uncertain reconciliation state.
- Callback Service dengan signature/merchant/amount/reference validation, lock, monotonic transition, idempotency.
- Controlled inquiry, printable receipt, safe Customer messages, and reconciliation view.

Exit criteria:

- Fixed dan per-kg paid flow lulus sandbox.
- Redirect/JS callback tidak pernah memutasi payment.
- Tampered/duplicate/out-of-order/concurrent callback tidak merusak state atau menggandakan side effect.
- Maintenance mode menolak invoice baru tetapi callback/reconciliation lama tetap berjalan.
- Credential tidak ada di frontend, log, fixture, screenshot, repository, atau artifact.

## Milestone 7 — Processing, realtime, dan completion

Deliverables:

- Payment gate menuju processing, estimated completion, delayed indicator, dan readiness.
- Customer delivery slot selection, awaiting-customer indicator, dispatch, dan atomic completion.
- Reverb/Echo private channels, after-commit events, database notification, unread state.
- Customer timeline live update dan Inertia polling fallback.
- Proof retention scheduling dan access revocation.

Exit criteria:

- Order tidak processing tanpa provider-verified paid.
- Delivery completion secara atomic menyelesaikan order dan commission.
- Unauthorized channel subscription dan PII-rich payload ditolak.
- Queue/Reverb failure tidak membatalkan domain transaction; polling memulihkan state.

## Milestone 8 — Finance, refund, dan reporting

Deliverables:

- Tenant report untuk gross, actual fee, net after fee, commission, adjustment, dan net operational.
- Driver earned/paid report dan batch payout per Driver/cutoff.
- Tenant eligible-payment selection setelah 3×24 jam dan batch payout per Tenant/cutoff.
- Full-refund submit/review/complete, immutable amount, negative adjustment, dan post-payout carry-forward.
- Maksimal 90-day/5,000-row streamed CSV dengan formula-injection protection.
- Super User operational/reconciliation dashboard serta masked export.

Exit criteria:

- Batch membership dipilih Service dan tidak dapat dimanipulasi client.
- Final payout immutable dan source record dapat ditelusuri.
- Refund tidak mengubah payment paid atau membalik completed commission.
- Fee unknown tidak ditampilkan sebagai nol/final.
- Report/export tidak bocor lintas Tenant atau mengekspor revealed PII.

## Milestone 9 — Hardening dan pilot

Deliverables:

- Rate limit, PII reveal expiry, account anonymization/closure, proof cleanup, audit review.
- Load test: 10 Tenant, 20 outlet, 50 Driver, 500 order/hari, 100 realtime client.
- Target p95 read 500 ms, internal mutation 1 detik, realtime 3 detik setelah commit.
- Backup/restore drill, queue/Reverb supervision, metrics, alerts, dan runbooks.
- Accessibility/responsive/browser smoke test untuk empat role.
- Production configuration checklist dan staged rollout.

Exit criteria:

- Availability pilot target 99,5% dapat diukur dengan maintenance terjadwal dikecualikan.
- Backup berhasil direstore; worker/Reverb pulih setelah restart.
- Critical tests, architecture checks, Pint, static analysis, lint, typecheck, build, security review, dan UAT lulus.
- Semua legal/provider release blocker Milestone 0 terselesaikan.

## Release strategy

1. Internal demo dengan data seed untuk 10 Tenant dan semua role.
2. Staging end-to-end menggunakan Duitku sandbox dan callback HTTPS publik.
3. Pilot 1–2 Tenant dengan reconciliation harian serta monitoring ketat.
4. Tambah bertahap hingga minimal 10 Tenant aktif setelah support/finance flow stabil.
5. Nyatakan MVP selesai hanya setelah product dan engineering acceptance criteria scope terpenuhi.

Feature flag/config hanya untuk payment production, maintenance mode, dan safe operational switch yang nyata. Jangan membangun framework feature flag generik.

## Pilot metrics

- Order completion/cancellation dan waktu per fulfillment stage.
- Pickup/delivery delayed serta awaiting-customer age.
- Payment success/pending/expired/reconciliation rate per channel.
- Callback invalid/duplicate/processing latency dan provider mismatch.
- Refund rate, payout age, payout hold, serta reconciliation mismatch.
- Queue depth/failure, broadcast latency, dan polling fallback rate.
- Authorization denial anomaly, PII reveal, dan cross-Tenant test pass rate.
- Support incident per Tenant.

## Backlog pasca-MVP berdasarkan bukti

1. Tenant staff membership dan granular permission.
2. Automated/partial provider refund.
3. Automated payout/split settlement setelah provider/legal validation.
4. Route-aware distance, map, dan dispatch optimization.
5. Live Driver location hanya bila kebutuhan/privacy justification terbukti.
6. Promo/loyalty dan multi-package order.
7. Public/mobile API, PWA, atau native app jika usage membenarkan maintenance cost.

Item backlog hanya dimulai dari metric/requirement terverifikasi, bukan karena menarik secara teknis.
