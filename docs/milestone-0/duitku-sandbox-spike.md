# Duitku Sandbox Spike

> Live status per 22 September 2026: **not executed — blocked**. Automated contract tests tersedia, tetapi credential runtime, private evidence manifest, dan callback HTTPS publik belum tersedia pada workspace ini.

## Tujuan dan batas

Spike membuktikan create invoice, HMAC, unique order ID, expiry 60 menit, callback server-to-server, duplicate/out-of-order behavior, inquiry, fee data, dan timeout. Action `create`/`inquiry` tidak memakai database; action `callback` hanya membaca payment M6 nyata yang sudah `paid` untuk membuktikan replay tetap monotonic. Production payment tidak diaktifkan.

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
DUITKU_CALLBACK_URL=https://public-test-host.example/webhooks/duitku
DUITKU_RETURN_URL=https://public-test-host.example/payments/return
```

Command menolak application environment production, provider environment selain `sandbox`, endpoint non-HTTPS, dan host provider selain host sandbox yang diizinkan.

Salin [`evidence-manifest.example.json`](evidence-manifest.example.json) ke `storage/app/private/milestone-0/evidence.json`, lalu isi hanya reference aman, role approver, tanggal review, retention contract, dan staging URL. Directory tersebut di-ignore Git. Jangan memasukkan nama personal, credential, raw callback, signature, payment URL, atau PII.

## Preflight

```powershell
php artisan duitku:sandbox-spike preflight
```

Preflight gagal jika DEC-001–DEC-014 tidak lengkap, approver role/tanggal/reference hilang, retention contract belum final, manifest memuat key sensitif, URL manifest berbeda dari runtime, callback bukan public HTTPS `/webhooks/duitku`, credential kosong, environment bukan sandbox, atau production payment flag aktif.

## Menjalankan probe mandiri

Create invoice menggunakan kode channel yang benar-benar aktif pada merchant sandbox:

```powershell
php artisan duitku:sandbox-spike create --amount=10000 --payment-method=NQ
```

Nama dan email test diminta secara interaktif agar tidak muncul pada process list. Gunakan data disposable, bukan PII nyata. Command membuat `merchantOrderId` unik, mengirim `customerVaName`, dan tidak melakukan automatic retry.

Inquiry menggunakan ID yang sama. Expected amount diminta interaktif dan provider reference diminta tersembunyi agar response identity dapat diverifikasi:

```powershell
php artisan duitku:sandbox-spike inquiry --merchant-order-id=KL-SBX-REPLACE_WITH_ID
```

Jika create timeout atau response hilang, jangan menjalankan create kedua. Catat ID dari sesi dan lakukan inquiry terkontrol.

## Live validation melalui payment M6 nyata

Probe mandiri tidak membuat local payment record. Bukti callback dan state transition wajib memakai data disposable melalui flow aplikasi staging:

1. Fixed + QRIS: buat order, invoice, selesaikan payment, lalu buktikan callback mengubah payment menjadi `paid` dan fulfillment menjadi `awaiting_pickup`.
2. Per-kg + E-Wallet: selesaikan pickup dan weight confirmation, buat invoice final, bayar, lalu buktikan callback mengubah payment menjadi `paid` dan fulfillment menjadi `processing`.
3. Buka return URL sebelum callback pada attempt terpisah dan buktikan status tidak berubah.
4. Jalankan callback replay pada payment paid:

```powershell
php artisan duitku:sandbox-spike callback --payment-public-id=REPLACE_WITH_PUBLIC_ID --scenario=duplicate
php artisan duitku:sandbox-spike callback --payment-public-id=REPLACE_WITH_PUBLIC_ID --scenario=out-of-order
```

Simulator hanya berjalan pada sandbox/non-production, mengirim form POST signed ke callback HTTPS yang dikonfigurasi, tidak mencetak payload/signature, dan menolak payment selain `paid`.

5. Biarkan invoice disposable lain selama 60 menit, jalankan scheduler expiry dan inquiry, lalu cocokkan terminal state provider/application.
6. Untuk timeout/lost response, operator menerapkan fault injection staging yang disetujui sebelum create. Pastikan local attempt menjadi `needs_inquiry`, jangan create ulang, pulihkan egress, lalu inquiry merchant order ID yang sama.

## Scenario matrix

| Scenario | Expected evidence | Status |
| --- | --- | --- |
| Create invoice QRIS | HTTP/result code, masked IDs, expiry, latency | Blocked |
| Create invoice E-Wallet | Channel availability dan required fields | Blocked |
| HTTP callback sukses | Public POST, valid signature, HTTP 200 | Blocked |
| Tampered callback | Ditolak tanpa payment mutation | Offline contract tested; live blocked |
| Duplicate callback | No-op dan side effect satu kali | DB contract tested; guarded live replay blocked |
| Out-of-order setelah paid | Paid tidak turun | DB contract tested; guarded live replay blocked |
| Expiry 60 menit | Provider inquiry menunjukkan terminal state yang sesuai | Blocked |
| Inquiry paid/pending/failed | Status/reference/amount konsisten | Blocked |
| Fee data | Tipe, unit, timing availability, dan nilai aktual | Blocked |
| Timeout/lost response | Local state harus `needs_inquiry`, tanpa blind retry | HTTP fake tested; live fault injection blocked |

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
