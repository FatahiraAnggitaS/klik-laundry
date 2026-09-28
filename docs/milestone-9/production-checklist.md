# Production rollout checklist

Checklist ini tidak mengizinkan aktivasi payment production. Seluruh blocker M0/M6 dan manifest M9 tetap wajib lulus lebih dahulu.

## Preflight

- [ ] Build berasal dari commit immutable yang seluruh hosted CI-nya hijau.
- [ ] Secret scan lulus; `.env`, cookie, token, callback payload, payment URL, dan PII tidak ada dalam artifact.
- [ ] PostgreSQL backup terenkripsi terbaru memiliki checksum dan external evidence reference.
- [ ] Restore drill terisolasi membuktikan schema serta row-count integrity.
- [ ] RPO/RTO disetujui dan dicatat; tidak memakai nilai asumsi.
- [ ] `CACHE_LIMITER_STORE=redis`; queue, cache, session, Reverb scaling, dan Pulse memakai koneksi staging/production yang benar.
- [ ] `PULSE_ENABLED=true` hanya pada PostgreSQL; dashboard hanya Super User aktif dengan 2FA.
- [ ] `DUITKU_PRODUCTION_ENABLED=false` tetap dipertahankan.

## Deploy

1. Aktifkan maintenance yang tidak memblokir callback lama. Aktifkan payment maintenance untuk menolak invoice baru.
2. Jalankan migration backward-compatible sebelum mengganti seluruh web process.
3. Deploy artifact tanpa menyalin `.env` ke image.
4. Jalankan `config:cache`, `route:cache`, `view:cache`, dan optimasi produksi yang sudah diuji pada artifact.
5. Restart queue worker secara graceful. Restart scheduler, Reverb, lalu `pulse:check`.
6. Verifikasi `/up`, `/ready`, queue monitor, Reverb private connection, Pulse ingest, dan backup freshness.
7. Jalankan canary read/mutation menggunakan fixture aman. Jangan membuat pembayaran production.
8. Matikan maintenance umum. Pertahankan payment production disabled sampai release approval terpisah.

## Rollback

- Rollback code ke artifact kompatibel sebelumnya. Jangan menjalankan database reset atau migration destructive.
- Jika migration baru sudah dipakai atau privacy deletion terjadi, jangan menjalankan `migrate:rollback`; gunakan forward fix.
- Pertahankan callback endpoint dan source-of-truth transaction selama rollback.
- Restart worker/Reverb/Pulse setelah artifact rollback dan ulangi readiness/canary.

## Post-deploy

- [ ] Pantau HTTP p95/error, slow query, queue depth/failure, Reverb connection/message, dan polling fallback.
- [ ] Pantau callback anomaly, reconciliation mismatch, unknown fee, refund/payout age, dan negative adjustment.
- [ ] Pantau PII reveal/denial, proof cleanup failure, backup age, dan readiness failure.
- [ ] Catat availability window, process recovery, browser UAT, load test, dan external evidence reference pada manifest private.
- [ ] Jalankan `php artisan m9:readiness-check`; hasil gagal berarti release pilot tetap diblokir.
