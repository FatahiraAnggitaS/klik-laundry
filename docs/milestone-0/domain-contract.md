# Domain Contract Milestone 0

Dokumen ini mengunci bahasa domain dan rule lintas milestone. Logical storage tetap mengikuti [`../04-domain-and-data-model.md`](../04-domain-and-data-model.md); dokumen ini bukan migration.

## Glossary

| Istilah | Definisi |
| --- | --- |
| Platform | Aplikasi Klik Laundry dan operator lintas Tenant |
| Tenant | Satu usaha laundry dengan satu owner dan satu atau lebih outlet |
| Outlet | Lokasi operasional Tenant yang memiliki radius, fee, jam, slot, dan readiness |
| Customer | User yang membuat dan membayar order |
| Driver | User milik satu Tenant yang menangani pickup/delivery task |
| Super User | Satu operator platform terkontrol, bukan anggota Tenant |
| Order | Satu transaksi Customer untuk satu outlet dan satu package |
| Payment attempt | Satu invoice provider dengan `merchantOrderId` unik dan expiry 60 menit |
| Fulfillment status | Tahap operasional order; terpisah dari payment status |
| Indicator | Sinyal perhatian yang tidak mengubah fulfillment state |
| Settlement | Pergerakan hak dana Tenant setelah fee dan adjustment |
| Payout | Pencatatan transfer manual kepada Tenant atau Driver |
| Financial adjustment | Koreksi append-only, termasuk refund negatif, tanpa mengubah record final lama |
| Proof | Foto timbang/task privat yang opsional; actor dan timestamp tetap wajib |

## Enum dan status vocabulary

| Context | Nilai |
| --- | --- |
| User role | `customer`, `tenant_owner`, `driver`, `super_user` |
| User lifecycle | `active`, `suspended`, `closed` |
| Tenant onboarding | `pending`, `approved`, `rejected` |
| Tenant operational | `inactive`, `active`, `suspended`, `closed` |
| Payout account verification | `pending`, `verified`, `rejected`, `superseded` |
| Outlet/package lifecycle | `draft`, `active`, `archived` |
| Pricing type | `fixed`, `per_kg` |
| Fulfillment | `awaiting_payment`, `awaiting_pickup`, `pickup_assigned`, `picked_up`, `awaiting_weight`, `processing`, `ready_for_delivery`, `delivery_assigned`, `out_for_delivery`, `completed`, `cancelled` |
| Indicator | `delayed`, `pickup_delayed`, `delivery_delayed`, `awaiting_customer` |
| Order payment | `unpaid`, `pending`, `paid`, `failed`, `expired` |
| Reconciliation | `not_required`, `needs_inquiry`, `matched`, `mismatch` |
| Driver availability | `available`, `unavailable` |
| Task offer | `offered`, `accepted`, `rejected`, `expired`, `withdrawn` |
| Delivery task | `pending`, `offered`, `accepted`, `in_progress`, `completed` |
| Driver commission | `earned`, `paid` |
| Refund | `submitted`, `approved`, `rejected`, `completed` |
| Tenant/Driver payout | `pending_transfer`, `finalized`, `voided` |

`completed`, `cancelled`, `paid`, refund `completed/rejected`, dan payout `finalized/voided` adalah terminal untuk record yang sama. Koreksi setelah final menggunakan history/adjustment baru, bukan edit diam-diam.

## Calculation contract

Semua uang adalah integer rupiah dan semua berat adalah integer gram.

### Fixed

```text
items_subtotal = unit_price * quantity
grand_total    = items_subtotal + pickup_fee + delivery_fee
```

Contoh: Rp24.000 x 2 unit + Rp5.000 pickup + Rp7.000 delivery = **Rp60.000**.

### Per-kg

```text
chargeable_base_grams = max(actual_grams, minimum_grams)
billable_grams        = ceil(chargeable_base_grams / 100) * 100
items_subtotal        = (unit_price_per_kg / 10) * (billable_grams / 100)
grand_total           = items_subtotal + pickup_fee + delivery_fee
```

`unit_price_per_kg` wajib positif dan habis dibagi 10. Dengan harga Rp12.000/kg, minimum 3.000 gram, actual 3.241 gram, maka billable 3.300 gram dan subtotal **Rp39.600**. Jika actual 2.340 gram, minimum berlaku: billable 3.000 gram dan subtotal **Rp36.000**.

### Settlement, report, dan refund

```text
net_after_gateway_fee = gross_paid - gateway_fee_actual
settlement_movement   = net_after_gateway_fee + financial_adjustment
net_operational       = gross_paid - gateway_fee_actual - driver_commission
refund_adjustment     = -paid_amount
```

Contoh payment Rp60.000 dengan fee aktual Rp1.200 memberi hak dasar Tenant Rp58.800. Full refund membuat adjustment `-Rp60.000`; payout lama tidak diubah dan adjustment dibawa ke batch berikutnya. Komisi Driver disajikan pada `net_operational`, tetapi tidak dikurangkan lagi dari hak settlement gateway karena pembayarannya merupakan arus terpisah Tenant.

Fee `null/unknown` tidak boleh dinormalisasi menjadi nol dan memblokir finalisasi payout terkait.

## Transition dan actor matrix

### Fulfillment

| Dari | Ke | Actor pemicu | Precondition utama |
| --- | --- | --- | --- |
| create fixed | `awaiting_payment` | Customer melalui Service | Order/snapshot valid |
| create per-kg | `awaiting_pickup` | Customer melalui Service | Order/snapshot valid |
| `awaiting_payment` | `awaiting_pickup` | Callback/inquiry service | Fixed payment provider-verified paid |
| `awaiting_pickup` | `pickup_assigned` | Driver accept | Offer aktif, Driver eligible, atomic accept |
| `pickup_assigned` | `awaiting_pickup` | Tenant/offer lifecycle | Reject, expiry, atau reassign beralasan |
| `pickup_assigned` | `picked_up` | Driver | Task pickup accepted/in_progress dan proof minimum |
| `picked_up` | `awaiting_weight` | Sistem | Package per-kg |
| `picked_up` | `processing` | Sistem | Package fixed dan payment paid |
| `awaiting_weight` | `awaiting_payment` | Tenant | Berat final valid dan total dikunci |
| `awaiting_payment` | `processing` | Callback/inquiry service | Per-kg payment provider-verified paid |
| `processing` | `ready_for_delivery` | Tenant | Proses laundry selesai |
| `ready_for_delivery` | `delivery_assigned` | Driver accept | Customer telah memilih slot valid |
| `delivery_assigned` | `ready_for_delivery` | Tenant/offer lifecycle | Reject, expiry, atau reassign beralasan |
| `delivery_assigned` | `out_for_delivery` | Driver | Task accepted |
| `out_for_delivery` | `completed` | Driver melalui completion service | Actor/timestamp wajib; task/commission atomic |
| eligible pre-pickup | `cancelled` | Customer/Tenant | Rule cancellation, ownership, dan audit terpenuhi |

Super User tidak memiliki transition fulfillment generik. Tenant dan Super User tidak dapat mengubah payment menjadi `paid`.

### Payment attempt

| Dari | Ke | Sumber | Rule |
| --- | --- | --- | --- |
| create | `pending` | Create invoice service | Satu active attempt, unique `merchantOrderId` |
| `pending` | `paid` | Callback/inquiry valid | Merchant, amount, order ID, reference, dan signature cocok |
| `pending` | `failed`/`expired` | Callback/inquiry/expiry service | Transition monotonic |
| `pending` | `pending + needs_inquiry` | Timeout/response uncertain | Tidak membuat attempt baru secara buta |
| `failed`/`expired` | new `pending` attempt | Customer melalui Service | Order masih payable; ID baru |
| `paid` | `paid` | Duplicate/out-of-order event | No-op sukses; side effect tidak diulang |

### Driver, refund, dan payout

| Context | Transition | Actor | Guard |
| --- | --- | --- | --- |
| Offer | `offered -> accepted/rejected/expired/withdrawn` | Driver/System/Tenant | Satu offer aktif; expiry 10 menit |
| Task | `pending -> offered -> accepted -> in_progress -> completed` | Tenant/Driver | Satu active task per Driver |
| Commission | create `earned -> paid` | Completion service/Tenant | Unique per task; payout batch immutable |
| Refund | `submitted -> approved -> completed` | Tenant/Super User | Full amount, deadline 3x24 jam, reason/re-auth/audit |
| Refund | `submitted -> rejected` | Super User | Reason/re-auth/audit |
| Payout | `pending_transfer -> finalized` | Super User atau Tenant sesuai payout type | Membership dipilih Service; reference transfer wajib |
| Payout | `pending_transfer -> voided` | Authorized actor | Hanya sebelum transfer; reason dan audit wajib |

## Forbidden transitions dan invariants

- Client tidak menentukan actor identity, Tenant, Customer, total, fee, weight result, batch membership, atau status.
- Tidak ada `unpaid/failed/expired -> paid` dari redirect browser, JS callback, Tenant, atau Super User.
- `paid` tidak pernah turun; refund tidak mengubah payment asli.
- Order tidak masuk `processing` tanpa paid yang diverifikasi provider.
- Per-kg total tidak berubah setelah invoice dibuat.
- Driver tidak menerima task kedua saat mempunyai task `accepted/in_progress`.
- Cross-Tenant read/write/export/job/callback side effect selalu ditolak.
- Final payout dan completed history immutable; koreksi memakai adjustment/audit.
- PII reveal tidak dapat diekspor dan selalu time-limited, beralasan, serta diaudit.
