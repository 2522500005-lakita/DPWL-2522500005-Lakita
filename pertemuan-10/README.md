# pertemuan-10
• Identitas mahasiswa.
NIM: 2522500005
Nama: Lakita[cite: 2, 46]
Mata Kuliah: Desain dan Pemrograman Web Lanjutan (DPWL)[cite: 4]

• Artefak dan kondisi awal aplikasi P9.
- Melanjutkan aplikasi rental kendaraan berbasis PHP & MySQL (MVC) dari P9 dengan basis data `db_dpwl_lakita09` dan `base_url` `http://localhost/dpwl-2522500005/`[cite: 2, 4].
- Fitur pembayaran DP penyewa dan verifikasi DP oleh admin pada P9 tetap dipertahankan[cite: 2, 10, 27].

• Hasil pemeriksaan fitur-fitur dari P9.
- Alur pembayaran DP (`Pembayaran_model.php` dan `Pembayaran_controller.php`) tersedia untuk daftar DP, unggah bukti transfer, dan akses file bukti[cite: 2].
- Pada `Pembayaran_model.php`, konstruktor dicegah agar melempar `RuntimeException` jika `$db` bukan instance `mysqli`, `get_result()` divalidasi sebelum `fetch_all()`, filter `jns_byr = 'dp'` diseragamkan pada `getBuktiAkses()`, dan cek bentrok tanggal sewa disesuaikan (`s2.tgl_mulai <= ?` dan `s2.tgl_selesai >= ?`)[cite: 2].

• Tahapan pengembangan P10.
1. Menyesuaikan struktur kolom `status_sewa` pada tabel `t_sewa` agar mendukung status `lunas`[cite: 12].
2. Menambahkan method pelunasan di `Pembayaran_model.php` (`getDaftarPelunasan`, `getDetailPelunasan`, `buatKodeBayarPelunasan`, `simpanPelunasan`)[cite: 13, 14, 16, 17].
3. Menambahkan method pelunasan dan dokumen transaksi di `Pembayaran_controller.php` (`pelunasanIndex`, `pelunasanDetail`, `prosesPelunasan`, `dokumenTransaksi`)[cite: 23, 24, 26].
4. Menambahkan route P10 di `application/config/routes.php` dan menu **Pelunasan Transaksi** di `application/views/templates/sidebar.php`[cite: 28].
5. Menyiapkan dan merapikan view `pelunasan.php`, `pelunasan_detail.php`, dan `dokumen_transaksi.php` di `application/views/admin/`[cite: 29, 31, 37].

• Daftar berkas yang ditambahkan atau diubah.
- `application/models/Pembayaran_model.php`[cite: 2, 13]
- `application/controllers/Pembayaran_controller.php`[cite: 2, 23]
- `application/config/routes.php`[cite: 2, 28]
- `application/views/templates/sidebar.php`[cite: 2, 28]
- `application/views/admin/pelunasan.php`[cite: 2, 29]
- `application/views/admin/pelunasan_detail.php`[cite: 2, 31]
- `application/views/admin/dokumen_transaksi.php`[cite: 2, 37]

• Perubahan struktur basis data.
- Menambahkan nilai ENUM `'lunas'` pada kolom `t_sewa.status_sewa` sambil mempertahankan nilai yang sudah ada (`'diajukan'`, `'disetujui'`, `'ditolak'`, `'aktif'`, `'selesai'`, `'batal'`, `'keranjang'`)[cite: 12]:
    ```sql
    ALTER TABLE `t_sewa`
      CHANGE `status_sewa` `status_sewa`
      ENUM('diajukan','disetujui','ditolak','aktif','selesai','batal','keranjang','lunas')
      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NULL DEFAULT 'keranjang';
    ```

• Hasil pengujian setiap tahap.
- Halaman daftar pelunasan, detail pelunasan, pencatatan pelunasan (tunai/transfer), perubahan status transaksi menjadi `lunas`, serta tampilan cetak/PDF dokumen transaksi diperiksa sesuai alur[cite: 35, 36, 43, 44].

• Kendala, proses debugging, dan perbaikan yang dilakukan.
- Memastikan isi `application/views/admin/pelunasan.php` menampilkan tabel daftar transaksi pelunasan (bukan isi dokumen cetak) dan melengkapi method model pelunasan serta menu sidebar admin agar seluruh alur P10 terhubung dengan benar[cite: 2, 28, 29].