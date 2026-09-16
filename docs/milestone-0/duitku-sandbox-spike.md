# Duitku Sandbox Spike

> Live status: **not executed — blocked**. Credential sandbox dan callback HTTPS publik belum tersedia pada 16 September 2026.

## Tujuan dan batas

Spike membuktikan create invoice, HMAC, unique order ID, expiry 60 menit, callback server-to-server, duplicate/out-of-order behavior, inquiry, fee data, dan timeout. Ia tidak menjadi payment implementation production, tidak memakai database, dan tidak boleh mengubah domain payment.

Kontrak diperiksa terhadap dokumentasi resmi:

- [Duitku POP API](https://docs.duitku.com/pop/id/)
- [Duitku API dan transaction inquiry](https://docs.duitku.com/api/id/)

Dokumentasi provider berubah; signature, endpoint, status, channel, fee, dan callback requirements harus diperiksa ulang sebelum setiap live spike serta implementasi Milestone 6.

## Safe configuration

Isi nilai berikut hanya pada `.env` lokal atau secret manager, bukan repository/CLI argument/frontend:

```env
DUITKU_ENVIRONMENT=sandbox
DUITKU_MERCHANT_CODE=
DUITKU_API_KEY=
DUITKU_CALLBACK_URL=https://public-test-host.example/callback
DUITKU_RETURN_URL=https://public-test-host.example/payment/return
```

Command menolak application environment production, provider environment selain `sandbox`, endpoint non-HTTPS, dan host provider selain host sandbox yang diizinkan.

## Menjalankan

Create invoice menggunakan kode channel yang benar-benar aktif pada merchant sandbox:

```powershell
php artisan duitku:sandbox-spike create --amount=10000 --payment-method=NQ
```

Email test diminta secara interaktif agar tidak muncul pada process list. Command membuat `merchantOrderId` unik dan tidak melakukan automatic retry.

Inquiry menggunakan ID yang sama:

```powershell
php artisan duitku:sandbox-spike inquiry --merchant-order-id=KL-SBX-REPLACE_WITH_ID
```

Jika create timeout atau response hilang, jangan menjalankan create kedua. Catat ID dari sesi dan lakukan inquiry terkontrol.

## Scenario matrix

| Scenario | Expected evidence | Status |
| --- | --- | --- |
| Create invoice QRIS | HTTP/result code, masked IDs, expiry, latency | Blocked |
| Create invoice E-Wallet | Channel availability dan required fields | Blocked |
| HTTP callback sukses | Public POST, valid signature, HTTP 200 | Blocked |
| Tampered callback | Ditolak tanpa payment mutation | Offline contract tested; live blocked |
| Duplicate callback | No-op dan side effect satu kali | Offline contract tested; DB/live blocked |
| Out-of-order setelah paid | Paid tidak turun | Offline contract tested; DB/live blocked |
| Expiry 60 menit | Provider inquiry menunjukkan terminal state yang sesuai | Blocked |
| Inquiry paid/pending/failed | Status/reference/amount konsisten | Blocked |
| Fee data | Tipe, unit, timing availability, dan nilai aktual | Blocked |
| Timeout/lost response | Local state harus `needs_inquiry`, tanpa blind retry | HTTP fake only; live blocked |

## Evidence template

Simpan evidence sensitif di lokasi akses-terbatas di luar Git. Repository hanya boleh menerima ringkasan berikut:

```text
Run ID:
Executed at UTC:
Operator role:
Provider environment: sandbox
Scenario:
Payment method category: QRIS / E-Wallet
Merchant order ID: MASKED
Provider reference: MASKED
Amount range: test nominal, tanpa PII
HTTP/result status:
Normalized status:
Callback received server-to-server: yes/no
Duplicate/out-of-order outcome:
Fee field present: yes/no
Fee unit/format observation:
Latency milliseconds:
Result: passed/failed/blocked
External evidence reference:
Notes (redacted):
```

## Data yang dilarang pada evidence/log

- API key, complete signature, credential, Authorization header;
- raw callback atau full request/response body;
- payment URL/QR string/token;
- email, phone, address, Customer name, cookie/session;
- full merchant order ID atau provider reference jika dokumen dibagikan.

Hasil HTTP fake atau signature fixture tidak boleh dilabeli sebagai live sandbox pass.
