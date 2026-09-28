# Operations harness

Template di folder ini bersifat provider-agnostic. Salin ke konfigurasi host/container, jangan menjalankannya langsung tanpa review path dan secret manager environment tujuan.

## Process model

- Web runtime melayani `/up` dan `/ready`.
- Queue worker dijalankan sebagai proses long-running dan di-restart secara graceful setelah deploy.
- Scheduler hanya memiliki satu leader efektif; job kritis memakai lock `onOneServer` dan `withoutOverlapping`.
- Reverb dijalankan sebagai proses terpisah dan diverifikasi dengan koneksi private sesudah restart.
- `pulse:check` hanya aktif ketika Pulse memakai PostgreSQL pada staging/production.

Urutan recovery: pulihkan database terisolasi, verifikasi checksum dan jumlah baris, start worker/scheduler, start Reverb, start Pulse, periksa `/ready`, lalu jalankan canary tanpa pembayaran production.

## Backup dan restore

`backup-postgres.sh` membuat PostgreSQL custom dump, mengenkripsinya dengan `age`, lalu membuat checksum pada external mounted destination. Credential PostgreSQL hanya dibaca oleh tool PostgreSQL dari environment atau service file; jangan diberikan sebagai argument.

`restore-postgres.sh` menolak production dan membutuhkan konfirmasi eksplisit. Target wajib database disposable terisolasi. Masukkan count pembanding yang dicatat oleh proses backup melalui `RESTORE_EXPECTED_TABLE_COUNT`, `RESTORE_EXPECTED_USER_COUNT`, dan `RESTORE_EXPECTED_ORDER_COUNT`; restore gagal bila hasil berbeda. Bukti yang boleh masuk manifest hanya run ID, waktu, hasil checksum, agregat jumlah baris, durasi, dan external evidence reference.

RPO dan RTO tidak memiliki default. Isi `OPS_RPO_APPROVED=true` dan `OPS_RTO_APPROVED=true` hanya setelah approval eksternal tercatat di manifest private.
