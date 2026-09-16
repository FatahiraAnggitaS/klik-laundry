# Responsive Wireflows

Prototype interaktif menggunakan route `/milestone-0/wireflows/{role}?step={step-id}`. Query `step` hanya memilih fixture layar; tidak ada mutation, authentication palsu, atau persistence.

## Responsive contract

- Mobile: satu active step, horizontal step picker, lalu CTA previous/next selebar container.
- Desktop: process rail di kiri dan panel detail di kanan.
- Setiap step menampilkan actor action, system response, current status, privacy/security guard, serta branch/failure mode.
- Role dan step dapat diakses keyboard, memiliki visible focus, dan URL dapat dibagikan.
- Invalid role atau step menghasilkan 404 agar prototype tidak menampilkan kontrak yang tidak dikenal.

## Customer

```mermaid
flowchart LR
    A[Discover outlet] --> B[Package + address + slot]
    B --> C{Pricing type}
    C -->|fixed| D[Invoice + pay]
    D --> E[Pickup]
    C -->|per_kg| E
    E --> F[Weight final]
    F -->|per_kg| G[Invoice + pay]
    D --> H[Processing]
    G --> H
    H --> I[Choose delivery slot]
    I --> J[Completed]
```

## Tenant owner

```mermaid
flowchart LR
    A[Onboarding + TOTP] --> B[Payout account + outlet readiness]
    B --> C[Order board]
    C --> D[Dispatch pickup]
    D --> E[Weight / payment gate]
    E --> F[Processing + delivery]
    F --> G[Report / payout / refund]
```

## Driver

```mermaid
flowchart LR
    A[Accept invitation] --> B[Set availability]
    B --> C[Masked offer]
    C -->|accept atomic| D[Temporary contact access]
    C -->|reject/expiry| B
    D --> E[In progress]
    E --> F[Completion proof]
    F --> G[Commission earned]
```

## Super User

```mermaid
flowchart LR
    A[Tenant review] --> B[Payment controls]
    B --> C[Reconciliation inquiry]
    C --> D[Refund / payout]
    D --> E[Time-limited PII reveal]
    E --> F[Audit + release readiness]
```

Wireflow ini adalah UX/domain contract, bukan bukti bahwa feature sudah diimplementasikan. Dashboard dan wireflow selalu menyebut data sebagai fixture tanpa database.
