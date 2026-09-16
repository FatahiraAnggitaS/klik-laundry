# Milestone 0 — Risk Validation dan Domain Contract

> Status per 16 September 2026: **in progress — external blockers**.

Milestone ini mengunci kontrak internal yang dapat diputuskan tanpa persistence dan menyediakan alat untuk menguji asumsi provider. Milestone belum memenuhi exit criteria karena belum ada credential Duitku sandbox, callback HTTPS publik, atau sign-off bisnis/legal.

## Artefak

| Dokumen | Fungsi |
| --- | --- |
| [`decision-register.md`](decision-register.md) | Keputusan provisional, owner sign-off, evidence, dan release blocker |
| [`domain-contract.md`](domain-contract.md) | Glossary, status, transition/actor matrix, invariant, dan calculation examples |
| [`sequence-diagrams.md`](sequence-diagrams.md) | Urutan fixed, per-kg, payment, callback, completion, refund, dan payout |
| [`wireflows.md`](wireflows.md) | Screen flow responsive untuk Customer, Tenant owner, Driver, dan Super User |
| [`threat-model.md`](threat-model.md) | Threat, impact, mitigation, verification, dan residual risk |
| [`duitku-sandbox-spike.md`](duitku-sandbox-spike.md) | Runbook serta evidence template sandbox yang aman |

Prototype interaktif tersedia pada `/milestone-0/wireflows/{role}` untuk `customer`, `tenant_owner`, `driver`, dan `super_user`. Semua data merupakan fixture presentasi dan tidak menggunakan database.

## Status exit criteria

| Exit criterion | Status | Bukti / blocker |
| --- | --- | --- |
| Ownership dana dan merchant-of-record tidak ambigu | Blocked | Keputusan internal tersedia, tetapi membutuhkan sign-off bisnis/legal/Duitku |
| Payment timing fixed/per-kg terkunci | Satisfied internally | Lihat domain contract dan wireflow |
| Actor transition terkunci | Satisfied internally | Lihat transition matrix dan sequence diagram |
| Callback server-to-server terbukti | Blocked | Credential dan endpoint HTTPS publik belum tersedia |
| Constraint provider tidak diasumsikan | Satisfied | Seluruh hal yang belum diuji dicatat sebagai release blocker |

Milestone 6 tidak boleh mengaktifkan payment production sampai seluruh blocker pada decision register berstatus `approved` atau `verified` dengan evidence yang dapat ditelusuri.
