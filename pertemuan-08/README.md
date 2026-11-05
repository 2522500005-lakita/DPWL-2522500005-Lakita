# pertemuan-08
### 1. Penambahan Menu Navigasi Samping (Sidebar)
* Menambahkan item menu baru dengan label **Manajemen Penyewa** pada bagian "MENU ADMIN" di sidebar sebelah kiri.
* Menu ini dilengkapi dengan ikon yang konsisten dengan menu lainnya (ikon pengguna/penyewa).

### 2. Formulir dan Tampilan Data
* Terdapat menu khusus bernama Manajemen Penyewa pada panel navigasi sisi kiri halaman admin. Menu ini berfungsi sebagai jalur akses utama yang mengarahkan admin secara langsung ke halaman pengelolaan dan tabel data penyewa.

### 3. Implementasi CRUD Berbasis MVC
Pada bagian ini dilakukan pembuatan logika pengendali (*Controller*) serta antarmuka tampilan (*View*) untuk menampilkan data penyewa ke halaman web.
A. Controller (`Mpenyewa_controller.php`)
* Membuat fungsi atau method baru di dalam controller admin untuk mengambil data dari model penyewa (berisi informasi seperti Username, Nama Lengkap, No. HP, Alamat, serta status unggahan berkas Foto KTP dan SIM A).
* Mengatur alur pengiriman data (*passing data*) dari database agar dapat dibaca dan ditampilkan secara dinamis ke halaman antarmuka admin.
B. View (`mpenyewa_view.php`)
* Merancang struktur tabel data menggunakan komponen AdminLTE yang menampilkan daftar penyewa secara rapi.
* Menampilkan informasi detail per kolom yang meliputi:
  * **No, Username, Nama Lengkap, No. HP, dan Alamat.**
  * **Status Foto KTP & Foto SIM A** (indikator apakah berkas tersedia atau belum ada foto).
* Menyediakan tombol aksi operasional berupa tombol **Ubah** (Edit) dan **Hapus** untuk masing-masing data penyewa, serta tombol **+ Tambah Penyewa** di bagian atas tabel.