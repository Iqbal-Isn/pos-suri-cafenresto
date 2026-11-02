# PRD — Sistem POS Cafe & Resto

*Versi 1.0 · 2 November 2026 · Stack: Laravel 13 (REST API) + React JS · Status: Draft untuk review client*

## 1. Ringkasan Produk

Sistem POS (Point of Sale) berbasis web untuk Cafe & Resto yang mencakup transaksi kasir, manajemen menu dan meja, pembayaran, alur dapur/bar, inventory, pelanggan, laporan, serta manajemen pengguna. Backend berupa REST API (Laravel 13), frontend berupa SPA/PWA (React JS) yang bisa dipakai di tablet, laptop, dan HP, dengan dukungan mode offline untuk kasir.

## 2. Latar Belakang & Masalah

- Pencatatan order dan kas masih manual atau memakai aplikasi terpisah, sehingga rawan salah hitung dan selisih kas.
- Order dapur/bar disampaikan lisan atau kertas, sehingga mudah terlewat dan lambat.
- Owner sulit memantau penjualan, stok bahan, dan margin secara real-time.
- Split bill, pindah meja, dan diskon menyulitkan kasir jika tidak ada sistem.
- Koneksi internet di outlet tidak selalu stabil, sehingga kasir tidak boleh berhenti saat offline.

## 3. Tujuan & Metrik Keberhasilan

| Tujuan | Metrik |
| --- | --- |
| Transaksi cepat | Input order sampai bayar < 60 detik untuk 3 item |
| Kas akurat | Setiap selisih kas per shift tercatat dan dapat ditelusuri |
| Order dapur tepat | 100% order terkirim otomatis ke bar/kitchen sesuai stasiun |
| Kontrol stok (Fase 2) | Stok bahan berkurang otomatis; selisih stok opname < 5% |
| Andal | Uptime ≥ 99,5%; kasir tetap bisa transaksi saat offline |
| Mudah dipakai | Staf kasir baru mahir dalam < 1 hari pelatihan |

## 4. Ruang Lingkup

**Dalam lingkup:** seluruh modul pada bagian 7 (Fase 1–3).

**Di luar lingkup:** akuntansi lengkap (hanya export/integrasi), payroll, aplikasi native Android/iOS, manufaktur/produksi massal, aplikasi pelanggan mandiri (selain QR order di Fase 3).

## 5. Persona & Role

| Role | Deskripsi | Akses utama |
| --- | --- | --- |
| Owner | Pemilik usaha | Semua fitur, dashboard, laporan, pengaturan |
| Manager | Pengelola outlet | Menu, stok, laporan, approval void/diskon, tutup shift |
| Kasir | Operator transaksi | Order, pembayaran, shift sendiri |
| Waiter | Pelayan | Input order meja, lihat status meja, minta bill |
| Dapur/Bar | Produksi | Lihat dan ubah status order di KDS |
| Pelanggan (Fase 3) | Tamu | QR order dan bayar dari meja |

## 6. Roadmap Fase

- **Fase 1 — MVP (P1):** transaksi, menu, meja dasar, pembayaran, struk, printer dapur, laporan dasar, shift dan kas, user dan role, offline mode.
- **Fase 2 (P2):** KDS, inventory dan resep, pembelian, pelanggan dan loyalty, laporan lanjutan, DP/tips/piutang, bundle dan harga terjadwal.
- **Fase 3 (P3):** QR order, integrasi online delivery, multi-outlet, payment gateway, reservasi, integrasi akuntansi.

## 7. Kebutuhan Fungsional

Prioritas: **P1** = MVP, **P2** = Fase 2, **P3** = Fase 3.

### 7.1 Transaksi / Kasir (TRX)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| TRX-01 | Input order cepat: grid menu, pencarian, kategori, favorit | P1 |
| TRX-02 | Tipe order: dine-in, take away, delivery, pre-order | P1 |
| TRX-03 | Catatan per item (misal tanpa es, pedas) | P1 |
| TRX-04 | Diskon per item/transaksi (persen/nominal), voucher, promo | P1 |
| TRX-05 | Hold order dan buka kembali | P1 |
| TRX-06 | Void/cancel item dan refund dengan PIN manager dan alasan wajib | P1 |
| TRX-07 | Nomor antrean/pager untuk counter-service | P2 |
| TRX-08 | Jumlah tamu (pax) per order | P2 |
| TRX-09 | Scan barcode produk retail | P2 |
| TRX-10 | Reorder, duplikat order, tombol quick cash | P2 |

### 7.2 Menu & Produk (MNU)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| MNU-01 | Kategori dan subkategori | P1 |
| MNU-02 | Varian (ukuran/suhu) dan modifier/add-on (extra shot, topping, level gula) | P1 |
| MNU-03 | Harga berbeda per tipe order (dine-in, take away, online) | P1 |
| MNU-04 | Status tersedia/habis | P1 |
| MNU-05 | Penugasan stasiun produksi (bar, kitchen, pastry) | P1 |
| MNU-06 | Paket/bundle/combo | P2 |
| MNU-07 | Menu terjadwal dan harga dinamis (breakfast, happy hour) | P2 |
| MNU-08 | Foto, deskripsi, alergen | P2 |
| MNU-09 | Import/export menu via CSV/Excel | P2 |

### 7.3 Meja & Dine-in (TBL)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| TBL-01 | Denah meja visual (drag and drop) dan pengelompokan area/lantai | P1 |
| TBL-02 | Status meja: kosong, terisi, minta bill, perlu dibersihkan | P1 |
| TBL-03 | Pindah meja dan gabung meja | P1 |
| TBL-04 | Split bill: per item, rata, per orang | P1 |
| TBL-05 | Open bill: tambah order bertahap pada meja yang sama | P1 |
| TBL-06 | Reservasi meja dan waiting list | P3 |
| TBL-07 | Course/urutan penyajian | P3 |
| TBL-08 | Panggil waiter lewat QR meja | P3 |

### 7.4 Pembayaran (PAY)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| PAY-01 | Metode: tunai (kembalian otomatis), QRIS, debit/kredit, transfer, e-wallet | P1 |
| PAY-02 | Split payment (campur metode) | P1 |
| PAY-03 | Pajak (PB1) dan service charge dapat diatur, inklusif/eksklusif | P1 |
| PAY-04 | Pembulatan otomatis | P1 |
| PAY-05 | Struk cetak dan struk digital (WhatsApp/email/QR) | P1 |
| PAY-06 | Down payment (DP) dan deposit | P2 |
| PAY-07 | Tips/gratuity | P2 |
| PAY-08 | Piutang/kasbon langganan | P2 |
| PAY-09 | Voucher dan gift card | P2 |
| PAY-10 | Payment gateway (Midtrans/Xendit): QRIS dinamis dan konfirmasi otomatis via webhook | P3 |

### 7.5 Dapur & Bar (KIT)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| KIT-01 | Kirim order otomatis ke printer dapur/bar sesuai stasiun | P1 |
| KIT-02 | Kitchen Display System (KDS): status baru, diproses, siap | P2 |
| KIT-03 | Timer order dan penanda terlambat | P2 |
| KIT-04 | Notifikasi pesanan siap ke kasir/waiter | P2 |
| KIT-05 | Penanda khusus untuk perubahan/pembatalan order | P2 |
| KIT-06 | Laporan waktu penyajian | P3 |

### 7.6 Inventory & Resep (INV)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| INV-01 | Stok bahan baku dan barang jadi | P2 |
| INV-02 | Resep (BOM): stok bahan berkurang otomatis saat menu terjual | P2 |
| INV-03 | HPP per menu dan analisis margin | P2 |
| INV-04 | Peringatan stok minimum | P2 |
| INV-05 | Stok opname dan penyesuaian (waste, rusak, expired) | P2 |
| INV-06 | Konversi satuan (kg ke gram, dus ke pcs) | P2 |
| INV-07 | Batch dan tanggal kedaluwarsa | P3 |
| INV-08 | Transfer stok antar outlet/gudang | P3 |

### 7.7 Pembelian & Supplier (PUR)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| PUR-01 | Data supplier | P2 |
| PUR-02 | Purchase Order, penerimaan barang, retur | P2 |
| PUR-03 | Hutang supplier dan jatuh tempo | P2 |
| PUR-04 | Riwayat harga beli bahan | P2 |

### 7.8 Pelanggan & Loyalty (CRM)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| CRM-01 | Data pelanggan/member dan riwayat pembelian | P2 |
| CRM-02 | Poin, tier member, diskon member | P2 |
| CRM-03 | Voucher dan kode promo | P2 |
| CRM-04 | Broadcast promo dan ucapan ulang tahun via WhatsApp | P3 |
| CRM-05 | Feedback/rating pelanggan | P3 |

### 7.9 Laporan & Analitik (RPT)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| RPT-01 | Penjualan harian/mingguan/bulanan | P1 |
| RPT-02 | Per produk, kategori, kasir, metode bayar, tipe order | P1 |
| RPT-03 | Laporan void, diskon, refund | P1 |
| RPT-04 | Laporan shift dan rekap kas | P1 |
| RPT-05 | Produk terlaris/terlambat, jam ramai, perbandingan periode | P2 |
| RPT-06 | Laba rugi sederhana dan laporan pajak | P2 |
| RPT-07 | Laporan stok, HPP, waste | P2 |
| RPT-08 | Dashboard owner real-time (responsif di HP) | P2 |
| RPT-09 | Export PDF/Excel | P1 |

### 7.10 Shift & Kas (SHF)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| SHF-01 | Buka/tutup shift dengan modal awal | P1 |
| SHF-02 | Cash in/out (petty cash) dengan keterangan | P1 |
| SHF-03 | Rekonsiliasi kas dan pencatatan selisih | P1 |
| SHF-04 | Serah terima shift | P2 |

### 7.11 User, Role & Keamanan (USR)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| USR-01 | Role: owner, manager, kasir, waiter, dapur | P1 |
| USR-02 | Hak akses per fitur (misal hanya manager boleh void) | P1 |
| USR-03 | Login PIN cepat untuk kasir | P1 |
| USR-04 | Audit log (siapa, apa, kapan) | P1 |
| USR-05 | Absensi dan jadwal shift karyawan | P3 |
| USR-06 | Komisi/insentif | P3 |

### 7.12 Pengaturan & Hardware (SET)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| SET-01 | Profil usaha, logo, template struk | P1 |
| SET-02 | Integrasi printer thermal (USB/LAN/Bluetooth) dan cash drawer | P1 |
| SET-03 | Mode offline dengan sinkronisasi otomatis | P1 |
| SET-04 | Pengaturan pajak, service, pembulatan, mata uang | P1 |
| SET-05 | Multi-printer (kasir, dapur, bar) | P1 |
| SET-06 | Customer-facing display, scanner barcode, timbangan | P3 |
| SET-07 | Backup otomatis dan enkripsi data | P1 |

### 7.13 Omnichannel (OMN) — Fase 3

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| OMN-01 | QR order dari meja (pesan dan bayar sendiri) | P3 |
| OMN-02 | Integrasi GoFood/GrabFood/ShopeeFood | P3 |
| OMN-03 | Website/aplikasi pemesanan sendiri | P3 |
| OMN-04 | Sinkronisasi menu dan stok ke semua channel | P3 |
| OMN-05 | Manajemen driver/ongkir delivery sendiri | P3 |

### 7.14 Multi-Outlet (OUT) dan Akuntansi (ACC) — Fase 3

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| OUT-01 | Kontrol pusat menu, harga, promo | P3 |
| OUT-02 | Laporan konsolidasi dan per cabang | P3 |
| OUT-03 | Hak akses per outlet | P3 |
| ACC-01 | Export ke Accurate/Jurnal/Zahir dan jurnal otomatis dari penjualan | P3 |
| ACC-02 | API/webhook untuk integrasi pihak ketiga | P3 |

## 8. Alur Pengguna Utama

**A. Transaksi counter-service**

1. Kasir login PIN, buka shift (isi modal awal).
2. Pilih menu, varian, modifier, lalu catatan.
3. Terapkan diskon/voucher bila ada, lalu pilih pembayaran (bisa split).
4. Sistem menghitung pajak, service, dan pembulatan, lalu mencetak struk.
5. Order otomatis tercetak di printer bar/kitchen (KDS di Fase 2).

**B. Dine-in dengan open bill**

1. Waiter memilih meja, lalu input order. Status meja menjadi terisi.
2. Tambahan order dimasukkan ke bill yang sama.
3. Tamu minta bill. Kasir bisa split bill, lalu bayar dan meja menjadi perlu dibersihkan.

**C. Void setelah dikirim ke dapur**

1. Kasir memilih item, lalu Void dan mengisi alasan.
2. Sistem meminta PIN manager.
3. Dapur menerima penanda pembatalan, dan audit log tercatat.

**D. Tutup shift**

1. Kasir menghitung uang fisik dan memasukkannya.
2. Sistem menampilkan selisih terhadap ekspektasi, lalu mencetak laporan shift.

**E. Mode offline**

1. Koneksi putus, aplikasi menampilkan indikator offline, dan transaksi tetap disimpan lokal.
2. Saat online kembali, antrean sinkronisasi dikirim ke server secara otomatis dan idempotent.

## 9. Kebutuhan Non-Fungsional

- **Performa:** endpoint baca p95 < 300 ms; halaman kasir interaktif < 2 detik setelah dimuat.
- **Offline-first (kasir):** katalog menu dan transaksi tersimpan lokal; sinkronisasi otomatis tanpa duplikasi.
- **Ketersediaan:** uptime ≥ 99,5%; backup database harian dan uji restore berkala.
- **Skalabilitas:** siap multi-outlet (outlet\_id di semua tabel transaksional sejak awal).
- **Usability:** UI touch-friendly (target sentuh besar), optimal di tablet 10 inci, juga responsif di HP; bahasa default Indonesia, mata uang IDR.
- **Keamanan:** lihat bagian 15.
- **Observability:** log terstruktur, pelacakan error, dan monitoring queue.
- **Maintainability:** kode modular, test otomatis untuk alur kritis (hitung bill, pajak, split, sinkronisasi offline).

## 10. Arsitektur & Tech Stack

**Backend (Laravel 13, REST API)**

- PHP sesuai persyaratan resmi Laravel 13; database MySQL 8 atau PostgreSQL.
- Autentikasi token dengan Laravel Sanctum; login PIN untuk kasir.
- RBAC dengan spatie/laravel-permission.
- Form Request untuk validasi, API Resource untuk format respons, Policy untuk otorisasi.
- Redis untuk cache dan queue; scheduler untuk tugas berkala (backup, laporan, peringatan stok).
- Realtime (KDS, status meja) dengan Laravel Reverb atau Pusher.
- Testing dengan Pest/PHPUnit.

**Frontend (React JS)**

- React + Vite (TypeScript disarankan), React Router.
- TanStack Query untuk data server, Zustand untuk state lokal (keranjang, sesi kasir).
- Tailwind CSS dan komponen UI (misal shadcn/ui); React Hook Form + Zod untuk form dan validasi.
- PWA: service worker (Workbox) dan IndexedDB (Dexie) untuk cache menu dan antrean transaksi offline.
- Laravel Echo client untuk realtime.

**Pencetakan (perlu proof of concept di awal)**

- Opsi A: print agent lokal / QZ Tray ke printer thermal ESC/POS.
- Opsi B: Web Serial/WebUSB/Bluetooth dari browser (dukungan tergantung browser dan perangkat).
- Opsi C: print service kecil di jaringan lokal yang menerima job dari API.

**Infrastruktur**

- VPS dengan Nginx + PHP-FPM, HTTPS wajib, environment staging dan production, CI/CD (misal GitHub Actions), backup database terenkripsi.

**Pola**

- Monolit modular: Auth, Catalog, Order, Payment, Table, Kitchen, Shift, Inventory, Customer, Report, Setting.
- API versioning `/api/v1`; semua tabel transaksional memiliki `outlet_id`.

## 11. Entitas Data Utama

| Domain | Tabel |
| --- | --- |
| Akses | users, roles, permissions, outlets, audit\_logs |
| Katalog | categories, products, product\_variants, modifier\_groups, modifiers, product\_prices, bundles |
| Meja | areas, tables, table\_sessions |
| Order | orders, order\_items, order\_item\_modifiers, order\_discounts, kitchen\_tickets |
| Pembayaran | payments, payment\_methods, refunds, vouchers, voucher\_redemptions |
| Shift | shifts, cash\_movements |
| Pelanggan | customers, loyalty\_points, loyalty\_tiers |
| Inventory | ingredients, recipes, recipe\_items, stock\_movements, stock\_opnames |
| Pembelian | suppliers, purchase\_orders, purchase\_order\_items, supplier\_payables |
| Pengaturan | settings, tax\_rules, printers, receipt\_templates |

Catatan: kolom `client_uuid` pada orders dan payments untuk sinkronisasi offline yang idempotent.

## 12. Rancangan Endpoint API (Ringkas)

| Modul | Contoh endpoint |
| --- | --- |
| Auth | POST /auth/login, POST /auth/pin-login, POST /auth/logout, GET /me |
| Menu | GET/POST /categories, GET/POST/PUT/DELETE /products, PATCH /products/{id}/availability |
| Meja | GET/POST /tables, POST /tables/{id}/move, POST /tables/merge |
| Order | POST /orders, GET /orders/{id}, POST /orders/{id}/items, POST /orders/{id}/hold, POST /orders/{id}/void-item, POST /orders/{id}/split |
| Pembayaran | POST /orders/{id}/payments, POST /payments/{id}/refund, GET /payment-methods |
| Dapur | GET /kitchen/tickets, PATCH /kitchen/tickets/{id}/status |
| Shift | POST /shifts/open, POST /shifts/close, POST /shifts/{id}/cash-movements |
| Laporan | GET /reports/sales, /reports/products, /reports/shifts, /reports/voids |
| Inventory | GET/POST /ingredients, POST /stock/adjust, POST /stock/opname |
| Sinkronisasi | POST /sync/batch (idempotent via client\_uuid) |
| Pengaturan | GET/PUT /settings, GET/POST /printers |

Standar: JSON, paginasi, filter, respons error seragam, rate limiting, dan dokumentasi OpenAPI.

## 13. Aturan Bisnis

- **BR-01:** Nominal uang disimpan sebagai integer (Rupiah) untuk menghindari kesalahan pembulatan.
- **BR-02:** Nomor order unik per outlet per hari (misal OUT1-20261102-0001).
- **BR-03:** Perhitungan: subtotal, diskon, service charge, pajak (PB1), pembulatan. Urutan dan dasar perhitungan dapat diatur dan harus dikonfirmasi dengan ketentuan pajak daerah setempat.
- **BR-04:** Shift wajib dibuka sebelum kasir bisa bertransaksi.
- **BR-05:** Transaksi yang sudah dibayar tidak dihapus; koreksi lewat void/refund dengan otorisasi manager dan alasan.
- **BR-06:** Diskon di atas batas tertentu (dapat diatur) memerlukan approval manager.
- **BR-07:** Stok bahan dikurangi berdasarkan resep saat pembayaran selesai (dikonfirmasi dengan client).
- **BR-08:** Item berstatus habis tidak dapat dipilih; manager dapat mengubah status kapan saja.
- **BR-09:** Transaksi offline disinkronkan dengan client\_uuid agar tidak terduplikasi.

## 14. Integrasi & Hardware

| Kebutuhan | Keterangan |
| --- | --- |
| Printer thermal | ESC/POS 58/80 mm, USB/LAN/Bluetooth |
| Cash drawer | Terhubung ke printer kasir (RJ11) |
| EDC/QRIS | Awal: konfirmasi manual; Fase 3: payment gateway via webhook |
| WhatsApp | Struk digital dan broadcast promo (via provider WhatsApp API) |
| Delivery online | GoFood/GrabFood/ShopeeFood (Fase 3, bergantung ketersediaan API partner) |
| Akuntansi | Export CSV/API ke Accurate, Jurnal, Zahir |
| Scanner barcode | Mode keyboard wedge |

## 15. Keamanan

- Autentikasi token (Sanctum), PIN disimpan ter-hash, pembatasan percobaan login.
- RBAC ketat di sisi server (bukan hanya menyembunyikan menu di UI).
- HTTPS wajib, CORS dibatasi, validasi input di semua endpoint.
- Audit log untuk void, refund, diskon, perubahan harga, dan perubahan pengaturan.
- Tidak menyimpan data kartu; pembayaran kartu lewat EDC/payment gateway.
- Data pelanggan diminimalkan dan dilindungi sesuai ketentuan pelindungan data pribadi.
- Backup terenkripsi dan akses server dibatasi.

## 16. Risiko & Mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Integrasi printer sulit di browser | Proof of concept di awal; siapkan print agent lokal |
| Sinkronisasi offline menyebabkan duplikasi/konflik | client\_uuid, antrean sinkronisasi, dan pengujian skenario putus-sambung |
| Scope terlalu besar | Disiplin fase: kirim MVP dulu dan validasi dengan client |
| Aturan pajak/service berbeda | Konfigurasi fleksibel dan konfirmasi dengan client/konsultan pajak |
| Kasir menolak sistem baru | UI sederhana, pelatihan singkat, dan masa pendampingan |
| API partner delivery tidak tersedia | Jadikan fitur opsional di Fase 3 |

## 17. Asumsi & Pertanyaan Terbuka

- Berapa jumlah outlet dan perangkat kasir yang akan dipakai, dan apakah rencana multi-cabang dalam waktu dekat?
- Model layanan: dine-in dengan waiter, counter-service, atau campuran?
- Metode pembayaran apa saja yang dipakai, dan apakah sudah punya EDC/QRIS merchant?
- Merek dan tipe printer thermal yang dipakai atau akan dibeli?
- Apakah pajak PB1 dan service charge diterapkan, dan bagaimana rinciannya?
- Apakah client perlu inventory dan HPP sejak awal?
- Model hosting: server milik client atau layanan cloud yang dikelola developer?
- Ketentuan lisensi/SaaS: apakah satu client saja atau dijual ulang ke banyak cafe (multi-tenant)?

## 18. Kriteria Penerimaan MVP (Fase 1)

- [ ] Kasir dapat membuat order dine-in dan take away lengkap dengan varian, modifier, dan catatan.
- [ ] Diskon, pajak, service charge, dan pembulatan terhitung benar sesuai pengaturan.
- [ ] Pembayaran tunai, QRIS, dan kartu, termasuk split payment, tercatat benar.
- [ ] Struk tercetak di printer thermal; order otomatis tercetak di printer dapur/bar.
- [ ] Denah meja, pindah/gabung meja, open bill, dan split bill berfungsi.
- [ ] Void/refund hanya bisa dengan PIN manager dan tercatat di audit log.
- [ ] Shift buka/tutup, cash in/out, dan rekonsiliasi kas berfungsi.
- [ ] Laporan penjualan, void/diskon, dan shift dapat dilihat dan diexport.
- [ ] Aplikasi tetap bisa transaksi saat offline dan tersinkronisasi tanpa duplikasi.
- [ ] Role dan hak akses berjalan sesuai matriks di bagian 5.
