# Product Scope MVP

> Status: final berdasarkan rangkaian keputusan Q&A MVP per 15 September 2026.

## Tujuan

MVP membuktikan satu alur end-to-end: customer menemukan outlet, membuat order pickup, outlet memproses laundry, driver menangani pickup/delivery, customer membayar secara online, semua pihak melihat status yang benar, dan tenant memperoleh laporan dasar.

Produk MVP berbentuk satu responsive web application yang dioptimalkan untuk mobile browser dan desktop. Customer, Tenant, Driver, dan Super User menggunakan aplikasi web yang sama dengan dashboard sesuai role.

Lokalisasi MVP menggunakan Bahasa Indonesia, mata uang IDR, dan timezone bisnis `Asia/Jakarta`. Timestamp disimpan dalam UTC dan dikonversi saat ditampilkan atau membentuk laporan.

Target operasional awal adalah **minimal 10 tenant aktif**, bukan 10 concurrent users. Profil load test MVP menggunakan 10 Tenant, rata-rata dua outlet dan lima Driver per Tenant, total 500 order per hari, serta 100 client realtime bersamaan. Angka ini adalah skenario verifikasi, bukan hard limit produk.

Per 22 September 2026, flow invoice/callback/reconciliation M6 tersedia untuk sandbox dan diuji memakai HTTP fake. Aktivasi production tetap di luar scope sampai seluruh blocker legal/provider Milestone 0 selesai dan live sandbox membuktikan create invoice, payment, callback HTTPS, expiry, inquiry, timeout, serta actual fee.

## Ringkasan keputusan final

| Area | Keputusan MVP |
| --- | --- |
| Role | Customer, Tenant owner, Driver, dan satu Super User platform |
| Tenant onboarding | Self-registration, lalu approval manual Super User |
| Multi-outlet | Satu Tenant dapat memiliki beberapa outlet; katalog/harga berlaku global per Tenant |
| Order | Satu outlet dan satu paket per order; beberapa order aktif per Customer diperbolehkan |
| Pricing | Harga fixed per unit atau per kg dengan minimum dan pembulatan naik per 100 gram |
| Scheduling | Slot pickup/delivery buatan Tenant, lead time dua jam, horizon tujuh hari, serta blackout date |
| Dispatch | Offer ke satu Driver selama 10 menit; satu task aktif per Driver |
| Payment | QRIS/E-Wallet Duitku melalui satu merchant account platform; tidak ada COD |
| Settlement | Fee Duitku ditanggung Tenant; tidak ada komisi platform; payout Tenant manual berbentuk batch |
| Driver commission | Nominal tetap per pickup/delivery leg dan payout manual berbentuk batch per Driver |
| Realtime | Status/timeline in-app melalui WebSocket dengan history dan polling fallback; tanpa live GPS |
| Refund | Full refund manual melalui workflow Tenant → Super User, maksimal 3×24 jam setelah completion |
| Security | Tenant isolation, 2FA wajib untuk Tenant/Super User, PII masking, re-authentication, dan audit trail |
| Client | Responsive web, Bahasa Indonesia, IDR, timezone bisnis Asia/Jakarta |

## Persona dan hak utama

| Persona | Kebutuhan utama | Batas MVP |
| --- | --- | --- |
| Customer | Cari outlet terdekat, buat order, pilih channel bayar, lihat tagihan, bayar, lacak status | Tidak dapat melihat data customer atau tenant lain |
| Tenant | Daftar dan menunggu approval, lalu kelola satu atau beberapa outlet, paket dan harga, konfirmasi berat/total, ubah status proses, assign driver, lihat pemasukan | Satu akun owner mengoperasikan seluruh outlet milik tenant; akun operator tambahan dan permission granular ditunda |
| Driver | Lihat tugas miliknya dari seluruh outlet Tenant, terima/tolak tugas yang ditawarkan, update pickup/delivery, lihat komisi | Driver terikat ke satu tenant pada MVP; dispatch dan route optimization otomatis ditunda |
| Super User | Kelola tenant dan akun, pantau seluruh order/payment, tangani support dan rekonsiliasi | Bukan bagian dari tenant dan tidak menjalankan operasional laundry harian; tindakan sensitif dibatasi dan diaudit |

`Super User` adalah platform operator terkontrol. Role ini boleh mengakses data lintas tenant hanya untuk administrasi platform, support, dan rekonsiliasi. Role ini tidak boleh menandai pembayaran sebagai `paid` secara manual atau melewati state machine order. Setiap tindakan sensitif wajib mencatat actor, waktu, target, alasan, serta perubahan yang dilakukan.

Super User tidak dapat login atau bertindak sebagai Customer, Tenant, maupun Driver (`impersonation`) pada MVP. Dukungan dilakukan melalui halaman support khusus dan action administratif yang terbatas agar actor asli selalu dapat diidentifikasi.

MVP memiliki satu akun Super User yang dibuat melalui command administratif interaktif. Tidak tersedia registrasi web, invitation, atau promosi user menjadi Super User.

## Core journey

1. Calon tenant mendaftar dan melengkapi data onboarding minimum.
2. Super User meninjau pendaftaran, lalu menyetujui atau menolak dengan alasan.
3. Customer mendaftar/login dan memberikan lokasi serta alamat pickup.
4. Sistem menampilkan outlet aktif dalam radius layanan, diurutkan berdasarkan jarak.
5. Customer memilih outlet, paket, jadwal pickup, dan alamat; channel payment fixed-price dipilih saat checkout.
6. Sistem memvalidasi readiness lalu otomatis menerima dan membuat order dengan snapshot harga serta alamat.
7. Tenant menawarkan/menetapkan tugas pickup kepada driver.
8. Driver mengambil cucian dan tenant mengonfirmasi berat/kuantitas final.
9. Sistem menghitung total final dan memberi tahu Customer bahwa tagihan siap dibayar.
10. Customer memilih channel QRIS/E-Wallet, lalu sistem membuat invoice Duitku.
11. Callback server-to-server yang valid mengubah pembayaran menjadi `paid`.
12. Tenant memproses order; customer menerima perubahan status secara realtime.
13. Saat laundry siap, Customer menerima notifikasi dan memilih slot delivery yang tersedia.
14. Tenant menetapkan Driver; Driver mengantar order dan menyimpan bukti serah-terima sederhana.
15. Tenant melihat pemasukan dan Driver melihat komisi yang sudah diperoleh.

Untuk paket `fixed`, pembayaran dilakukan setelah order dibuat dan sebelum pickup task dijalankan. Untuk paket `per_kg`, channel dipilih dan invoice dibuat setelah berat final ditetapkan agar tidak memerlukan partial payment/refund pada MVP.

## Fitur wajib MVP

### Shared

- Registrasi, login, logout, reset password, dan email verification.
- Authentication utama menggunakan email dan password. Nomor telepon wajib sebagai kontak operasional, tetapi tidak diverifikasi melalui OTP pada MVP.
- Super User dan owner Tenant wajib menggunakan TOTP two-factor authentication dan memiliki recovery codes.
- Role-aware dashboard dan backend authorization melalui policies.
- Profil dan nomor telepon.
- In-app notification yang durable serta update status realtime.
- Tampilan responsive, keyboard-accessible, loading/empty/error/pending state.

### Customer

- Wajib memiliki akun dan login sebelum menyimpan alamat atau membuat order.
- Dapat memiliki beberapa order aktif yang masing-masing diproses secara independen.
- Menyimpan beberapa alamat beserta latitude/longitude dan memilih alamat pickup serta delivery pada order.
- Menggunakan alamat pickup sebagai default delivery, tetapi dapat memilih alamat delivery lain.
- Temukan outlet aktif dalam radius layanan dan lihat estimasi jarak garis lurus.
- Melihat outlet sebagai daftar terurut berdasarkan approximate distance; map interaktif tidak termasuk MVP.
- Jika izin lokasi ditolak, tetap dapat browsing/filter outlet berdasarkan kota/area tanpa informasi jarak.
- Mencari nama area/outlet dan memfilter tipe paket `fixed` atau `per_kg`.
- Lihat paket aktif, harga, satuan, minimum order, dan estimasi durasi.
- Memilih tepat satu paket dari satu outlet untuk setiap order.
- Untuk paket `per_kg`, dapat memasukkan estimasi berat opsional dan melihat estimasi biaya yang diberi label non-final.
- Untuk paket `fixed`, memilih quantity unit bilangan bulat sebelum checkout.
- Memilih tanggal dan slot pickup yang tersedia pada outlet.
- Setelah laundry siap, memilih slot delivery yang tersedia sebelum Driver ditetapkan.
- Menjadwalkan ulang pickup/delivery sendiri selama task belum diterima Driver.
- Membatalkan order sendiri hanya selama payment belum `paid` dan Driver belum menerima pickup task.
- Pilih channel QRIS/E-Wallet yang aktif.
- Membuka checkout/payment URL Duitku dan melihat status pembayaran.
- Melihat halaman receipt payment yang dapat dicetak tanpa membuat file PDF.
- Melihat payment status standar tanpa detail error internal provider.
- Membuat invoice baru untuk order yang masih payable setelah payment attempt sebelumnya kedaluwarsa.
- Melihat timeline status order dan detail biaya.
- Melihat fulfillment status dan payment status sebagai informasi terpisah.
- Menerima perubahan status/timeline secara realtime tanpa pelacakan lokasi GPS Driver.
- Melihat indikator dan notifikasi jika estimasi proses laundry terlewati.
- Menerima notifikasi dan slot baru jika pickup terlambat karena tidak ada Driver.
- Menerima notifikasi dan slot baru jika delivery terlambat karena tidak ada Driver.
- Mengajukan penutupan akun dan melihat blocker jika masih memiliki order/payment/refund aktif.
- Melihat nomor telepon dan tautan WhatsApp outlet untuk pertanyaan atau keluhan di luar aplikasi.

### Tenant

- Self-registration dan status onboarding yang jelas: `pending`, `approved`, atau `rejected`.
- Mengisi nama usaha, nama owner, email terverifikasi, nomor telepon, serta alamat dan koordinat outlet pertama saat onboarding.
- Melihat alasan penolakan dan mengajukan ulang setelah memperbaiki data.
- Setelah disetujui, melengkapi rekening payout untuk diverifikasi sebelum payout pertama.
- Mengajukan perubahan rekening payout; payout ditahan sampai rekening baru diverifikasi Super User.
- Wajib menyelesaikan setup TOTP 2FA sebelum mengakses fitur operasional, laporan, atau rekening payout.
- Mengajukan penutupan Tenant kepada Super User dan melihat kewajiban yang masih harus diselesaikan.
- Jika ditangguhkan, melihat alasan/status penangguhan dan tetap dapat menyelesaikan order yang sudah berjalan.
- Kelola profil tenant dan outlet: nama, alamat, koordinat, jam operasional sederhana, radius layanan, status aktif.
- Mengaktifkan outlet setelah seluruh readiness requirement terpenuhi tanpa approval outlet tambahan dari Super User.
- Mengatur slot pickup dan delivery berulang per hari untuk setiap outlet berdasarkan jam operasional.
- Mengatur blackout date per outlet untuk hari libur atau penutupan sementara.
- Menetapkan biaya pickup dan delivery tetap per outlet; masing-masing dapat bernilai nol.
- CRUD katalog paket global Tenant: `fixed` atau `per_kg`, harga, minimum quantity/weight, estimasi durasi, dan status aktif yang berlaku di seluruh outlet.
- Daftar order terfilter dan detail order.
- Melihat order `delayed` sebagai perhatian operasional tanpa mengubah fulfillment status.
- Melihat alert order `awaiting_customer` ketika slot delivery belum dipilih dalam tujuh hari.
- Menjadwalkan ulang pickup dengan alasan ketika tidak ada Driver yang menerima sampai slot terlewati.
- Menjadwalkan ulang delivery dengan alasan ketika tidak ada Driver yang menerima sampai slot terlewati.
- Membatalkan order setelah Driver menerima task hanya melalui tindakan operasional yang diizinkan dan wajib disertai alasan.
- Konfirmasi berat/kuantitas final dan breakdown biaya.
- Menambahkan foto timbangan opsional pada konfirmasi berat paket `per_kg`.
- Assign pickup/delivery task ke driver dan update status operasional yang diizinkan.
- Menawarkan setiap pickup/delivery task kepada satu Driver pada satu waktu dan menawarkannya kembali jika ditolak.
- Menetapkan nominal komisi tetap untuk pickup dan delivery; perubahan hanya berlaku pada task baru.
- Mengundang, melihat, mengaktifkan, dan menonaktifkan Driver milik tenant.
- Menandai komisi `earned` sebagai `paid` setelah payout di luar sistem, disertai tanggal, nominal, metode, dan referensi/catatan.
- Membuat payout komisi batch per Driver berdasarkan cutoff date untuk seluruh komisi `earned` yang belum dibayar.
- Laporan dengan filter rentang tanggal dan outlet: gross paid, fee Duitku, nilai setelah fee, komisi Driver, dan indikator net operational.
- Rincian payment, payout Tenant, dan komisi Driver yang dapat ditelusuri ke order/task sumber.
- Mengajukan full refund manual melalui form sederhana dengan memilih order/payment dan alasan, lalu melihat status permintaannya.
- Export CSV dasar menggunakan filter yang sama; file besar/asynchronous export ditunda.
- Melihat audit trail operasional dan finansial milik Tenant sendiri.

### Driver

- Menerima undangan Tenant yang masih valid dan membuat credential akun sendiri.
- Mengubah availability menjadi `available` atau `unavailable` untuk offer baru.
- Daftar tugas yang ditawarkan dan ditugaskan kepadanya.
- Saat offer, hanya melihat outlet, slot, area, tipe task, komisi, dan perkiraan jarak; detail Customer belum ditampilkan.
- Terima/tolak tugas yang ditawarkan.
- Transisi status pickup/delivery yang valid.
- Menetapkan delivery task sebagai selesai sehingga order menjadi `completed`.
- Bukti penyelesaian berupa actor dan timestamp wajib; foto serta catatan singkat bersifat opsional dengan validasi file.
- Ringkasan komisi earned dan paid per periode.

Boundary implementasi M5: invitation, availability, offer, pickup task, weight confirmation, optional private proof, dan commission `earned` sudah tersedia. Delivery completion, commission `paid`, payout batch, notification durable/realtime, dan proof cleanup tetap milestone berikutnya.

### Super User

- Dashboard kesehatan operasional: jumlah tenant per status, order per status, payment berhasil/pending/bermasalah, dan payout Tenant yang belum dibayar.
- Tabel lintas tenant untuk investigasi order, payment, dan payout dengan filter serta tautan ke record sumber.
- Export CSV operasional/finansial terfilter dengan PII tetap masked; data hasil reveal tidak dapat diekspor.
- Mengelola batas maksimum radius layanan global; nilai awal MVP adalah 20 km.
- Mengelola allowlist global channel QRIS/E-Wallet berdasarkan channel yang aktif pada merchant Duitku.
- Mengaktifkan atau menonaktifkan payment maintenance mode tanpa menghentikan operasional aplikasi lainnya.
- Meninjau pendaftaran tenant, lalu menyetujui atau menolak dengan alasan.
- Memproses penutupan Tenant setelah seluruh order, refund, payout, dan adjustment selesai.
- Memverifikasi data rekening payout Tenant sebelum payout pertama.
- Menyetujui atau menolak perubahan rekening payout tanpa mengedit data rekening atas nama Tenant.
- Melihat, mengaktifkan, menangguhkan, dan menonaktifkan tenant.
- Menangguhkan dan mengaktifkan kembali akun user untuk kebutuhan keamanan atau support, disertai alasan dan audit trail.
- Melihat order dan payment lintas tenant untuk investigasi dan rekonsiliasi.
- Melihat PII Customer dalam bentuk masked dan melakukan reveal terbatas untuk order tertentu dengan alasan serta audit trail.
- Memicu tindakan support yang aman, seperti status inquiry atau resend notification, tanpa mengubah payment menjadi `paid` secara manual.
- Memverifikasi refund manual dan mencatat financial adjustment tanpa mengubah histori payment asli.
- Menyetujui atau menolak refund request dengan alasan, lalu menandainya `completed` setelah refund di luar sistem memiliki referensi.
- Mencatat payout Tenant setelah transfer di luar sistem dan menghubungkannya dengan payment yang dicakup.
- Membuat satu payout batch per Tenant berdasarkan cutoff date untuk seluruh payment eligible yang belum dibayar.
- Menetapkan atau melepas `payout_hold` Tenant dengan alasan tanpa menghentikan operasional laundry.
- Melihat audit trail tindakan sensitif.
- Melihat security audit lintas platform, termasuk reveal PII dan tindakan Super User.
- Tidak dapat mengambil alih sesi atau impersonate user lain.
- Wajib mengaktifkan TOTP 2FA sebelum dapat mengakses fungsi Super User.

### Payment dan notification

- Satu merchant account Duitku milik platform digunakan untuk seluruh transaksi MVP, setelah model ini disetujui oleh Duitku dan tervalidasi secara legal/operasional.
- Duitku sandbox dan production dipisahkan lewat configuration/environment.
- Pembuatan invoice idempotent dengan `merchantOrderId` unik.
- Setiap payment attempt berlaku 60 menit; attempt baru menggunakan `merchantOrderId` baru.
- Callback tervalidasi, idempotent, dicatat secara aman, dan diproses dalam database transaction.
- Status query digunakan untuk rekonsiliasi manual/on-demand atau callback meragukan, bukan aggressive polling.
- Broadcast status hanya melalui private channel yang di-authorize.
- Status order/payment dikirim melalui in-app realtime notification dan disimpan sebagai history; polling menjadi fallback ketika WebSocket tidak tersedia.
- Event payment untuk Customer adalah tagihan siap, `paid`, `failed`, dan `expired`. MVP tidak mengirim reminder pembayaran terjadwal.
- Payment maintenance mode menghentikan create-invoice baru, tetapi tidak menghentikan callback atau reconciliation untuk attempt yang sudah ada.

## Business rules final

### Order, catalog, dan histori

- Semua nilai uang disimpan sebagai integer rupiah; jangan gunakan float.
- Satu order hanya milik satu tenant dan satu outlet.
- Customer boleh memiliki beberapa order aktif, termasuk dari Tenant/outlet berbeda. Order tidak digabungkan dalam payment, task, atau payout.
- Satu Tenant dapat memiliki beberapa outlet. Tidak ada batas jumlah outlet buatan pada MVP, tetapi seluruh daftar wajib dipaginasi dan setiap order tetap terikat pada tepat satu outlet.
- Katalog paket, harga, dan status availability berlaku global untuk seluruh outlet dalam satu Tenant. Konfigurasi paket/harga berbeda per outlet tidak termasuk MVP.
- Satu order hanya berisi satu paket. Kombinasi beberapa paket harus dibuat sebagai order terpisah pada MVP.
- Paket `fixed` menggunakan harga per unit. Customer memilih quantity bilangan bulat minimal satu dan total item dihitung `unit price × quantity` oleh backend.
- Harga paket `per_kg` wajib berupa integer rupiah positif yang habis dibagi 10 agar setiap increment 100 gram menghasilkan nominal integer tanpa pembulatan uang tersembunyi.
- Invoice fixed-price dibuat berdasarkan quantity yang sudah dikunci. Barang tambahan di luar quantity tersebut membutuhkan order terpisah; perubahan quantity setelah payment tidak termasuk flow normal MVP.
- Target selesai dihitung dari estimasi durasi paket ketika order masuk `processing`. Target ditampilkan sebagai perkiraan, bukan jaminan SLA atau dasar kompensasi otomatis.
- Jika target selesai terlewati, order tetap `processing` dan mendapat indikator `delayed`; Tenant serta Customer menerima notifikasi. Tidak ada penalti, refund, atau state transition otomatis.
- Jika tidak ada Driver yang menerima pickup sampai slot terlewati, order tetap aktif dan mendapat indikator `pickup_delayed`. Tenant wajib memilih slot baru dengan alasan; sistem memberi notifikasi kepada Customer tanpa cancel/refund otomatis.
- Jika tidak ada Driver yang menerima delivery sampai slot terlewati, order tetap `ready_for_delivery` dan mendapat indikator `delivery_delayed`. Tenant memilih slot baru dengan alasan dan Customer menerima notifikasi.
- Paket/harga/alamat/nama customer yang relevan disnapshot ke order agar histori tidak berubah.
- Durasi estimasi paket disimpan sebagai integer menit dan dapat ditampilkan sebagai jam/hari oleh UI.
- Outlet, paket, dan Driver yang sudah direferensikan transaksi/task tidak dapat di-hard-delete; gunakan status inactive/archive. Draft yang belum pernah direferensikan boleh dihapus permanen setelah authorization.
- Order, payment, payout, refund, commission, status history, dan audit record tidak memiliki action hard delete pada aplikasi MVP.

### Address, status, pricing, dan payment timing

- Alamat pickup dan delivery boleh berbeda, tetapi keduanya harus berada dalam radius layanan outlet. Satu order hanya memiliki satu tujuan pickup dan satu tujuan delivery.
- Order memiliki status fulfillment dan payment terpisah.
- Payment status MVP adalah `unpaid`, `pending`, `paid`, `failed`, dan `expired`. Kondisi provider yang belum pasti disimpan untuk rekonsiliasi dan ditampilkan sebagai “sedang diverifikasi”.
- Status mentah/error internal Duitku tidak ditampilkan langsung kepada Customer; UI menggunakan pesan aman dan actionable.
- Receipt printable menampilkan nomor order, referensi payment, waktu bayar, channel, dan rincian biaya. Receipt tidak memuat credential, signature, payload callback mentah, atau data provider yang tidak diperlukan.
- Fulfillment status MVP adalah `awaiting_payment`, `awaiting_pickup`, `pickup_assigned`, `picked_up`, `awaiting_weight`, `processing`, `ready_for_delivery`, `delivery_assigned`, `out_for_delivery`, `completed`, dan `cancelled`.
- Status bersifat standar platform dan tidak dapat ditambah, dihapus, atau diganti alurnya oleh Tenant. UI boleh memakai label Bahasa Indonesia yang mudah dipahami.
- Hanya transisi state yang diizinkan yang boleh dilakukan; status tidak boleh diubah bebas dari client.
- Actor transisi ditetapkan platform: Duitku/system untuk payment, Tenant untuk penimbangan/proses/siap delivery, Driver untuk pickup/delivery, dan Customer untuk cancel/reschedule yang memenuhi syarat.
- Super User tidak memiliki generic status override. Koreksi dilakukan melalui action support khusus, precondition backend, alasan, dan audit trail.
- Total pembayaran ditentukan server. Client hanya mengirim pilihan, quantity estimate, dan channel.
- Order otomatis diterima jika Tenant/outlet/paket aktif, slot valid, alamat berada dalam radius, dan seluruh readiness rule terpenuhi. Tidak ada approval Tenant per order pada MVP.
- Tenant mengendalikan order baru dengan mengaktifkan/menonaktifkan outlet, paket, dan slot; perubahan tidak membatalkan order yang sudah dibuat.
- Estimasi berat Customer untuk paket `per_kg` bersifat opsional dan tidak menjadi dasar tagihan final.
- Setelah Tenant mengonfirmasi berat final, backend menghitung serta mengunci total dan menandai tagihan siap dibayar tanpa approval terpisah. Customer menyetujui rincian melalui tindakan membayar.
- Untuk order `per_kg`, Customer memilih channel setelah total final tersedia, lalu sistem membuat invoice. Untuk order `fixed`, channel dipilih saat checkout karena total sudah final.
- Konfirmasi berat wajib menyimpan berat, actor Tenant, timestamp, minimum, pembulatan, dan hasil perhitungan. Foto timbangan bersifat opsional, disimpan privat, dan terikat pada order.
- Tenant tidak dapat mengubah berat atau total setelah invoice dibuat. Koreksi terhadap invoice belum dibayar dilakukan melalui pembatalan attempt dan pembuatan invoice baru yang diaudit.
- Payment attempt yang belum dibayar menjadi `expired` setelah 60 menit. Order tetap `awaiting_payment`, tidak dibatalkan otomatis, dan Customer dapat membuat attempt baru.
- Hanya satu payment attempt aktif yang diizinkan per order. Callback valid yang lebih dahulu menetapkan order `paid` membuat attempt lain tidak boleh memicu pembayaran atau side effect kedua.
- Biaya pickup dan delivery menggunakan nominal tetap yang dikonfigurasi Tenant per outlet dan disnapshot saat order dibuat. Perhitungan berbasis jarak tidak termasuk MVP.
- Order `per_kg` memakai berat final tenant sebagai dasar invoice.
- Paket `per_kg` memiliki minimum billable weight yang ditentukan Tenant dan terlihat sebelum order. Billable weight adalah nilai yang lebih besar antara berat aktual dan minimum.
- Cucian di bawah minimum tidak ditolak setelah pickup; Customer tetap ditagih sesuai minimum yang telah diinformasikan.
- Berat aktual disimpan sebagai integer gram. Setelah minimum diterapkan, billable weight dibulatkan ke atas ke kelipatan 100 gram dan formula terlihat pada rincian tagihan.
- Order hanya dapat masuk status `processing` setelah payment menjadi `paid` melalui callback atau inquiry Duitku yang tervalidasi. Tenant dan Super User tidak memiliki override pada MVP.
- Untuk order `per_kg`, cucian berada pada status menunggu pembayaran setelah berat final dikonfirmasi. Untuk order `fixed`, pickup task baru dapat dilanjutkan setelah payment `paid`.

### Outlet discovery dan lifecycle

- Tenant mengatur radius layanan setiap outlet pada rentang 1–20 km. Super User mengelola batas maksimum global, sedangkan backend memvalidasi bahwa lokasi Customer berada dalam radius outlet.
- Pencarian outlet menggunakan lokasi perangkat yang disetujui Customer atau koordinat alamat tersimpan. Hasil berupa daftar berdasarkan jarak garis lurus, bukan driving distance atau ETA.
- Filter MVP hanya nama area/outlet dan tipe paket; hasil eligible tetap diurutkan berdasarkan approximate distance. Filter harga, rating, promo, popularitas, dan ranking kompleks ditunda.
- Tanpa izin lokasi, Customer dapat browsing outlet berdasarkan kota/area, tetapi tidak dapat membuat order sampai koordinat pickup dan delivery tersedia serta lolos validasi radius.
- Outlet hanya dapat diaktifkan jika Tenant berstatus `approved`, rekening payout sudah terverifikasi, profil/koordinat/radius lengkap, jam dan slot tersedia, serta minimal satu paket aktif.
- Outlet yang belum lolos readiness check tetap `draft` dan tidak tampil kepada Customer. Tenant dapat menonaktifkan outlet untuk menghentikan order baru tanpa mengganggu order berjalan.
- Deaktivasi sementara outlet/paket menggunakan transisi `active -> draft`; `archived` bersifat terminal dan hanya dapat dicapai dari `draft`.
- Permintaan penutupan Tenant langsung menghentikan order baru, tetapi tidak menghapus atau membatalkan proses aktif. Super User hanya dapat memfinalisasi penutupan setelah order, refund, payout, dan adjustment terselesaikan.
- Tenant yang ditutup kehilangan akses operasional; record transaksi dan audit dipertahankan sesuai retention policy.

### Scheduling

- Tenant menentukan slot pickup berbentuk rentang waktu per outlet, misalnya `09:00–12:00`. Customer hanya dapat memilih tanggal dan slot aktif yang belum lewat.
- Pickup dapat dipesan paling jauh tujuh hari ke depan. Backend menghitung booking horizon dalam timezone `Asia/Jakarta` dan mengecualikan blackout date.
- Slot pickup harus memiliki lead time minimal dua jam dari waktu order dibuat agar Tenant sempat menugaskan Driver.
- Slot menggunakan timezone `Asia/Jakarta`. MVP tidak membatasi kapasitas order per slot dan belum menyediakan capacity planning otomatis.
- Blackout date menonaktifkan seluruh slot pada tanggal tertentu untuk satu outlet. Tenant tidak dapat menambahkan blackout tanpa menangani order yang sudah terjadwal pada tanggal tersebut.
- Order terdampak blackout tidak dibatalkan otomatis; Tenant melakukan reschedule dengan alasan dan sistem mengirim notifikasi.
- Slot delivery dipilih Customer setelah order berstatus `ready_for_delivery`. Delivery task baru dapat ditawarkan atau ditetapkan setelah slot dipilih.
- Slot delivery harus dimulai minimal dua jam setelah dipilih agar Tenant sempat menugaskan Driver.
- Customer dapat memilih tanggal delivery maksimal tujuh hari setelah order menjadi `ready_for_delivery`.
- Jika Customer belum memilih slot delivery dalam tujuh hari, status utama tetap `ready_for_delivery` dengan indikator `awaiting_customer`. Tenant menindaklanjuti secara manual; tidak ada auto-delivery atau storage fee.
- Customer dapat self-reschedule ke slot aktif lain selama task belum diterima Driver. Setelah diterima, Tenant harus melakukan reschedule dengan alasan, membatalkan/reassign task secara eksplisit, dan sistem memberi notifikasi kepada Customer serta Driver.

### Driver commission

- Komisi dicatat ketika task pickup/delivery selesai dan tidak dihitung dua kali pada retry.
- Komisi Driver menggunakan nominal tetap per leg: pickup dan delivery dapat memiliki nominal berbeda serta dapat dikerjakan Driver berbeda.
- Nominal komisi disnapshot saat task diberikan kepada Driver. Hak komisi baru berstatus `earned` setelah task tersebut selesai.
- Komisi menjadi eligible untuk payout segera setelah task `completed`; tidak menunggu order selesai atau refund window.
- Setiap tenant menetapkan tarif komisinya sendiri. Super User hanya dapat melihat konfigurasi tersebut untuk support dan audit.
- Tenant bertanggung jawab membayar Driver di luar sistem dan menandai komisi sebagai `paid`. Tindakan ini tidak boleh mengubah nominal komisi yang sudah di-earned dan wajib memiliki audit trail.
- Payout komisi dibuat per Driver dan mencakup seluruh komisi `earned` yang belum `paid` sampai cutoff date. Sistem menentukan membership dan total batch; Tenant tidak memilih task satu per satu.
- Driver dapat melihat status `earned` dan `paid`, tetapi tidak dapat mengubah status payout miliknya.

### Cancellation

- Customer dapat self-cancel hanya jika payment belum `paid` dan pickup task belum diterima Driver. Setelah itu, hanya Tenant yang dapat membatalkan sesuai state yang diizinkan dan dengan alasan tercatat.
- Cancellation fee tidak diterapkan pada MVP. Order yang sudah `paid` tidak dapat dibatalkan melalui flow pembatalan biasa; pengembalian dana wajib menggunakan full-refund workflow manual yang tercatat dan diaudit.

### Payment, payout Tenant, report, dan refund

- Laporan tenant hanya memasukkan pembayaran sukses pada tenant tersebut; definisi tanggal memakai `paid_at` di `Asia/Jakarta` untuk tampilan, timestamp disimpan UTC.
- Platform menjadi merchant of record pada MVP. Setiap payment tetap terhubung ke satu order dan tenant agar hak tenant dapat direkonsiliasi serta dibayarkan secara manual.
- Payout tenant tidak mengubah fakta payment customer; status pembayaran dan status payout dicatat terpisah.
- Fee Duitku menjadi beban tenant dan dikurangkan dari hak settlement tenant. Customer tidak dikenai surcharge payment gateway pada MVP.
- Laporan wajib memisahkan `gross paid`, fee Duitku aktual, dan nilai setelah fee. Fee yang belum terverifikasi tidak boleh dianggap nol.
- Platform tidak mengambil service fee/komisi transaksi pada MVP. Hak payout Tenant adalah gross payment dikurangi fee Duitku aktual; komisi Driver dibayar Tenant secara terpisah.
- Channel pembayaran tersedia secara global untuk semua tenant dan dikelola Super User. Customer hanya melihat channel QRIS/E-Wallet yang masuk allowlist aplikasi serta aktif pada merchant Duitku.
- Metode pembayaran MVP hanya QRIS/E-Wallet melalui Duitku. Cash, COD, transfer manual, kartu, dan virtual account tidak tersedia.
- Tenant tidak dapat mengaktifkan atau menonaktifkan channel pembayaran secara mandiri pada MVP.
- Saat Duitku bermasalah, Super User dapat mengaktifkan payment maintenance mode. Customer tetap dapat membuat order dan melihat status, tetapi invoice baru tidak dibuat sampai mode dilepas.
- Callback Duitku selalu tetap diterima selama payment maintenance agar attempt yang sudah berjalan dapat diselesaikan. Sistem tidak membuat retry invoice otomatis dari response yang belum pasti.
- Channel status notification MVP hanya in-app realtime dengan durable history dan polling fallback. Email digunakan untuk authentication flow, bukan perubahan status order.
- Realtime tracking hanya mencakup perubahan order, payment, pickup, dan delivery status. Aplikasi tidak mengumpulkan, menyimpan, atau menampilkan koordinat perjalanan Driver.
- Tenant mempublikasikan nomor kontak outlet yang dapat dibuka sebagai telepon atau tautan WhatsApp. Percakapan tidak disimpan aplikasi; chat dan general-purpose ticketing tidak termasuk MVP.
- Super User mencatat payout Tenant setelah transfer di luar sistem. Catatan payout mencakup payment yang dibayarkan, gross, fee Duitku aktual, nilai net, timestamp, metode, dan referensi.
- Payment hanya boleh masuk satu payout Tenant. Catatan payout yang sudah final tidak diedit diam-diam; koreksi menggunakan pembatalan/adjustment yang diaudit.
- Payment menjadi eligible untuk payout setelah order `completed` melewati 3×24 jam, payment tetap `paid`, fee sudah direkonsiliasi, dan tidak ada refund request aktif.
- Satu payout batch mencakup seluruh payment eligible milik satu Tenant sampai cutoff date. Sistem menentukan membership batch dan total; Super User tidak memilih payment secara arbitrer satu per satu.
- Perhitungan batch memisahkan gross, fee Duitku aktual, refund/adjustment, dan net payout. Payout dengan net tidak positif tidak dapat difinalisasi sebagai transfer normal.
- Tenant dapat melihat riwayat serta rincian payout miliknya, tetapi tidak dapat menandai payout sendiri.
- Laporan menggunakan filter `paid_at` untuk pemasukan, `earned_at` untuk komisi, dan tanggal payout untuk arus payout. Definisi waktu harus terlihat agar angka lintas bagian tidak disalahartikan.
- `Net operational` hanyalah gross paid dikurangi fee Duitku dan komisi Driver yang relevan; angka tersebut bukan laba akuntansi karena tidak mencakup seluruh biaya, pajak, atau adjustment di luar sistem.
- Agregat dashboard Super User harus dapat ditelusuri ke record sumber dan tidak menjadi jalur untuk mengubah state bisnis secara bebas.
- Refund payment `paid` ditangani manual melalui support. Payment asli tetap `paid`; adjustment menyimpan nominal, alasan, actor, timestamp, dan referensi refund serta diperhitungkan pada payout/laporan terkait.
- Automated dan partial refund melalui Duitku tidak termasuk MVP.
- Customer menyampaikan permintaan refund kepada Tenant di luar aplikasi. Hanya Tenant pemilik order yang dapat mengajukan refund kepada Super User; Super User memverifikasi dan mencatat hasilnya.
- Refund request memiliki status standar `submitted`, `approved`, `rejected`, dan `completed`. Setiap transition mencatat actor/timestamp; request selalu terhubung ke tepat satu payment.
- Refund MVP hanya penuh. Nominal berasal dari payment `paid`, tidak dapat diedit, dan satu payment hanya boleh mempunyai satu refund `completed`.
- Komisi Driver dari task yang sudah selesai tetap `earned` atau `paid` ketika order di-refund. Refund tidak membatalkan histori pekerjaan Driver dan menjadi beban operasional Tenant.
- Refund yang selesai mengurangi saldo payable Tenant. Jika payment sudah masuk payout final, sistem membuat adjustment negatif pada payout Tenant berikutnya tanpa mengubah histori payout lama.
- Adjustment negatif harus terlihat pada laporan Tenant dan dashboard rekonsiliasi Super User. Jika Tenant tidak memiliki payout berikutnya, penyelesaian dilakukan manual dan tetap dicatat.
- Refund hanya dapat diajukan paling lambat 3×24 jam setelah order `completed`. Backend menghitung deadline dari completion timestamp dan menolak pengajuan yang terlambat.

### Tenant lifecycle, authorization, dan privacy

- Super User tidak memiliki `tenant_id`; akses lintas tenant hanya diberikan melalui policy khusus, bukan dengan menonaktifkan tenant scope secara global.
- Perubahan sensitif oleh Super User memerlukan alasan dan audit trail; payment provider tetap menjadi sumber kebenaran status pembayaran.
- Super User dapat melihat seluruh audit trail. Tenant hanya dapat melihat event operasional/finansial miliknya; event security lintas platform dan reveal PII tidak dibagikan kepada Tenant.
- Tenant berstatus `pending` atau `rejected` tidak dapat mempublikasikan outlet/paket, menerima order, mengelola driver, atau mengakses laporan operasional.
- Approval dan rejection tenant mencatat Super User sebagai reviewer, timestamp, serta alasan; keputusan tidak boleh berasal dari input client tanpa authorization backend.
- Onboarding Tenant tidak meminta upload KTP, NIB, NPWP, atau dokumen legal pada MVP. Verifikasi menggunakan data usaha dasar dan proses manual Super User.
- Data rekening payout hanya dapat dilihat Tenant pemilik dan Super User yang berwenang, ditampilkan dalam bentuk masked jika nilai lengkap tidak diperlukan, dan setiap perubahan wajib diaudit serta diverifikasi ulang.
- Perubahan rekening payout diajukan Tenant dan menahan payout baru sampai diverifikasi Super User. Rekening lama tetap tersimpan sebagai snapshot pada payout historis dan ditampilkan masked.
- Super User dapat memberi `payout_hold` terpisah karena masalah rekening, refund, atau rekonsiliasi. Tenant tetap beroperasi dan melihat alasan hold, tetapi payout tidak dapat difinalisasi sampai hold dilepas.
- Menetapkan dan melepas payout hold memerlukan alasan, re-authentication, serta audit trail.
- Tenant `suspended` tidak tampil pada pencarian dan tidak dapat menerima order baru, membuat outlet/paket baru, atau mengaktifkan kembali outlet/paket. Tenant tetap dapat mengakses order yang sudah berjalan, melakukan transisi yang diperlukan untuk menyelesaikannya, dan melihat laporan miliknya.
- Penangguhan tidak membatalkan order atau payment yang sudah ada secara otomatis. Super User wajib memberikan alasan; pengaktifan kembali juga dicatat dalam audit trail.
- Setiap tenant hanya memiliki satu akun owner pada MVP. Credential tidak boleh dibagikan, dan pergantian owner harus melalui flow support yang terverifikasi serta diaudit.
- Super User tidak dapat mengedit profil, mengganti password, atau hard-delete akun pengguna. Password dipulihkan melalui reset flow; histori transaksi tetap dipertahankan.
- PII Customer pada halaman Super User masked secara default. Reveal data lengkap hanya berlaku untuk investigasi/order tertentu, memerlukan alasan, dibatasi waktu, dan dicatat sebagai security audit event.
- Export Super User dapat mencakup tenant, order status, payment, payout, refund, dan adjustment sesuai filter. Export tidak boleh memuat PII lengkap dan setiap pembuatan file dicatat pada audit trail.
- Export CSV Tenant maupun Super User dibatasi maksimal 5.000 baris dan rentang tanggal 90 hari. Jika melebihi batas, pengguna wajib mempersempit filter; queued export tidak termasuk MVP.
- CSV di-stream langsung setelah authorization dan tidak disimpan sebagai file publik/permanen oleh aplikasi.
- Tenant dapat melihat alamat dan nomor Customer lengkap selama order aktif sampai 3×24 jam setelah `completed`. Setelah refund window berakhir, PII dimasking pada tampilan histori.
- Masking tampilan tidak menghapus snapshot transaksi yang perlu dipertahankan untuk rekonsiliasi/audit; akses data mentah tetap dibatasi oleh kebutuhan dan authorization backend.
- Penutupan akun Customer hanya diproses jika tidak ada order, payment, atau refund aktif. Login/sesi dicabut, PII yang tidak wajib disimpan dianonimkan, dan record transaksi minimum dipertahankan sesuai retention policy.
- Retention period final harus divalidasi secara legal sebelum production; hard delete yang merusak histori transaksi tidak tersedia sebagai action biasa.
- Akun `suspended` tidak dapat membuat sesi baru dan sesi aktifnya harus dicabut. Pengaktifan kembali tidak mengubah order, payment, atau histori akun.
- Reset 2FA Super User memerlukan recovery flow terverifikasi dan audit trail; tidak boleh dilakukan melalui perubahan database manual tanpa prosedur insiden.
- TOTP 2FA wajib untuk Super User dan owner Tenant. Customer dan Driver tidak menggunakan 2FA pada MVP.
- Perubahan rekening payout, finalisasi payout Tenant, approval/completion refund, dan penandaan komisi Driver `paid` memerlukan password/TOTP confirmation jika re-authentication terakhir lebih dari 15 menit.
- Re-authentication tidak menggantikan authorization, business validation, idempotency, atau audit trail.
- Provisioning Super User dilakukan melalui command interaktif yang tidak menerima atau mencetak password sebagai argument/log. Akun wajib verifikasi email dan setup 2FA sebelum aktif.
- Customer boleh melihat outlet dan paket aktif tanpa login, tetapi wajib terautentikasi untuk menyimpan alamat, membuat order, membayar, melihat tracking privat, dan mengakses riwayat.
- Guest checkout dan penggabungan riwayat guest ke akun tidak termasuk MVP.
- Akun Customer baru harus menyelesaikan email verification sebelum membuat order.

### Driver lifecycle dan dispatch

- Driver dibuat atau diundang oleh Tenant dan hanya dapat terikat ke satu tenant pada MVP. Undangan bersifat sekali pakai, memiliki expiry, dan tidak mengaktifkan akun sebelum diterima.
- Driver aktif dapat menerima task dari seluruh outlet milik Tenant yang sama. Assignment Driver ke outlet tertentu tidak termasuk MVP.
- Hanya Driver dengan akun aktif dan availability `available` yang dapat menerima offer baru. Status `unavailable` tidak membatalkan task yang sudah diterima.
- Driver hanya dapat melihat alamat lengkap dan nomor telepon Customer setelah menerima task, dan hanya sampai task selesai. Offer menampilkan area serta approximate distance tanpa data kontak lengkap.
- Penyelesaian/penolakan/expiry task mencabut akses Driver terhadap data Customer yang tidak lagi diperlukan.
- Tenant hanya dapat mengelola Driver miliknya. Menonaktifkan Driver mencegah penerimaan task baru, tetapi task aktif harus diselesaikan atau di-reassign secara eksplisit.
- Driver hanya dapat dinonaktifkan setelah seluruh task aktif selesai atau di-reassign. Deactivation tidak menghapus histori task maupun komisi `earned`/`paid`.
- Pickup/delivery task hanya dapat ditawarkan kepada satu Driver aktif pada satu waktu. Driver yang menerima menjadi assignee; penolakan mengembalikan task untuk dipilih ulang oleh Tenant.
- Penerimaan task dilakukan secara atomic. Task yang sudah diterima atau ditarik tidak dapat diterima oleh Driver lain.
- Satu Driver hanya boleh mempunyai satu task berstatus `accepted` atau `in_progress`. Driver harus menyelesaikan atau membatalkan task secara sah sebelum menerima offer berikutnya.
- Penawaran task berlaku 10 menit. Offer tanpa respons berubah menjadi `expired` dan kembali ke Tenant untuk ditawarkan ulang tanpa membatalkan order.
- Penyelesaian pickup/delivery wajib mencatat Driver dan timestamp. Foto/catatan bersifat opsional; foto disimpan privat dan hanya dapat diakses pihak yang berwenang.
- Foto bukti task dan timbangan dihapus otomatis 90 hari setelah order selesai dan seluruh refund terkait selesai. Metadata actor, timestamp, dan nilai transaksi tetap dipertahankan.
- Ketika Driver menyelesaikan delivery task yang valid, order langsung menjadi `completed`, komisi delivery dibuat secara idempotent, dan Customer menerima notifikasi. Tidak ada konfirmasi penerimaan tambahan dari Customer pada MVP.

State task terminal `cancelled` digunakan saat task dibatalkan secara sah; histori tersebut tidak boleh dipresentasikan sebagai `pending` atau `completed`.

## Non-goals MVP

- Native mobile app, public REST/GraphQL API, dan offline-first mode.
- Progressive Web App (PWA), service worker, dan installable/offline experience.
- Guest checkout dan pemesanan tanpa akun Customer.
- Database atau deployment terpisah per tenant.
- Custom domain/branding per tenant.
- Cabang franchise/hierarki organisasi kompleks.
- Multi-role per user, staff permissions granular, dan driver lintas tenant.
- Undangan akun operator tambahan untuk tenant.
- User impersonation oleh Super User.
- Dynamic pricing, promo, voucher, loyalty, subscription, dan membership.
- Live GPS driver, route optimization, auto-dispatch, chat, dan ETA traffic-aware.
- Inventory, payroll, accounting double-entry, pajak otomatis, serta invoice fiskal.
- Refund otomatis, partial payment, partial refund, split settlement, dan payout gateway otomatis.
- Merchant account Duitku terpisah untuk setiap tenant.
- WhatsApp/SMS/push notification; email transactional dapat ditambahkan bila kapasitas memungkinkan.
- Login atau verifikasi berbasis SMS/WhatsApp OTP dan social login.
- Multi-language, multi-currency, dan timezone yang dapat dikonfigurasi per Tenant.
- Rating/review dan marketplace ranking kompleks.
- Platform billing, subscription Tenant, dan service fee/komisi transaksi platform.
- Cash/COD, transfer manual, kartu kredit/debit, dan virtual account.

## Success metrics dan acceptance criteria

### Product

- Sepuluh tenant seed/demo dapat menjalankan order end-to-end tanpa data bocor antar-tenant.
- Customer dapat menyelesaikan fixed-price order sampai `completed` dan per-kg order melalui konfirmasi berat.
- Payment sandbox sukses mengubah status melalui HTTP callback yang valid.
- Setiap perubahan status penting muncul pada halaman customer tanpa full page refresh dan tetap dapat dilihat setelah reload.
- Tenant dan driver memperoleh angka laporan yang dapat direkonsiliasi ke payment/task sumber.
- Super User dapat menangani lifecycle tenant dan investigasi transaksi tanpa mengubah fakta pembayaran secara manual.
- Calon tenant dapat mendaftar, tetapi tidak tampil kepada customer atau menerima order sebelum disetujui Super User.

### Engineering

- Availability pilot minimal 99,5% per bulan, tidak termasuk maintenance terjadwal yang diumumkan.
- Load test representatif untuk 10 Tenant, 20 outlet, 50 Driver, total 500 order/hari, dan 100 koneksi realtime bersamaan memenuhi performance target yang disepakati.
- Pada profil tersebut, p95 read/page request maksimal 500 ms, mutation internal maksimal 1 detik, dan update realtime diterima maksimal 3 detik setelah database commit. Latency Duitku diukur terpisah.
- Test otomatis mencakup authentication, tenant isolation, authorization, state transition, total calculation, payment callback, dan commission idempotency.
- Tidak ada N+1 pada daftar order/outlet utama; semua daftar tidak terbatas memakai pagination.
- Callback duplicate/out-of-order tidak menghasilkan payment atau commission ganda.
- TypeScript check, lint, backend test, dan production build berhasil di CI.
- Backup/restore drill, queue retry, dan WebSocket fallback terdokumentasi sebelum production pilot.
