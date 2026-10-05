# Portfolio readiness

Dokumen ini memisahkan bukti demo lokal dari bukti staging yang masih menunggu akses eksternal. Data demo tidak membuktikan integrasi Duitku, settlement, atau kesiapan pilot.

## Demo lokal

Jalankan migration pada database lokal/staging terisolasi. Tetapkan `PORTFOLIO_DEMO_PASSWORD` melalui secret environment dengan nilai minimal 12 karakter, lalu jalankan `php artisan db:seed --class=PortfolioDemoSeeder`. Jangan memasukkan password ke argumen command, commit, screenshot, atau dokumen. Seeder menolak environment `production` dan aman diulang tanpa menggandakan record.

Fixture membuat 10 Tenant aktif, 20 outlet aktif, 20 paket, 10 owner, 10 Driver, satu Customer dengan alamat, dan rekening payout simulasi. Email memakai domain `portfolio.example.test`; rekening dan alamat hanya data fiktif. Owner harus menyelesaikan setup 2FA sebelum memakai workspace. Super User tidak dibuat oleh seeder; gunakan provisioning interaktif resmi agar invariant satu Super User tetap terjaga. Jangan memakai fixture sebagai transaksi atau rekening nyata.

## Browser journey

Jalankan `php artisan test tests/Browser/MilestoneNineBrowserTest.php tests/Browser/PortfolioJourneyBrowserTest.php` untuk Chromium. Ulangi dengan `--browser firefox`. Journey menguji shell empat role, discovery Customer, checkout fixed sampai order tercipta, dialog hapus yang dibatalkan, pergantian tema, drawer mobile, grafik, fokus keyboard, serta accessibility smoke. Seed browser berada dalam database test sementara dan tidak menulis database aplikasi.

UAT transaksi penuh pada staging: Customer memilih outlet dan paket, membuat order; Tenant menugaskan pickup; Driver menyelesaikan pickup; Tenant mengonfirmasi berat; Customer membayar melalui Duitku sandbox; callback terverifikasi mengubah payment; Tenant memproses order; Driver menyelesaikan delivery; finance menampilkan fee dan komisi. Simpan bukti tiap transisi, termasuk idempotency callback, polling saat Reverb putus, dan angka laporan. Jangan menyebut journey ini lulus sebelum sandbox nyata dan callback HTTPS publik tersedia.

## Staging terbatas

1. Deploy commit dengan CI hijau ke staging terisolasi. Gunakan PostgreSQL/Redis, queue worker, Reverb, storage privat, dan HTTPS.
2. Jalankan seed demo hanya jika staging memang dikhususkan untuk portfolio. Tetapkan kredensial demo di secret store. Jangan campur data demo dengan calon Tenant pilot.
3. Verifikasi `/up`, `/ready`, backup/restore, proses worker/Reverb, dan browser UAT desktop/mobile.
4. Hubungkan Duitku sandbox setelah credential, merchant agreement, dan callback URL tersedia. Simpan hasil spike pada manifest evidence privat.
5. Jalankan `php artisan m9:readiness-check`; pilot tetap diblokir bila M0/M6 atau evidence staging belum lulus.

Sebelum berbagi portfolio, hapus cookie, token, URL pembayaran, kode pemulihan 2FA, nomor rekening, kontak Customer, dan proof privat dari screenshot atau rekaman. Catat tanggal, commit, browser, viewport, hasil gate, serta blocker yang masih terbuka. Lihat [`milestone-9/production-checklist.md`](milestone-9/production-checklist.md) untuk syarat rollout.
