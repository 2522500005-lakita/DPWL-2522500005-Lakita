<?php
// P9 BARU - application/controllers/Pembayaran_controller.php
// Pembayaran DP oleh penyewa: daftar, halaman unggah bukti, dan penyajian
// berkas bukti (privat) dengan pemeriksaan hak akses.
require_once APPPATH . 'models/Pembayaran_model.php';
require_once APPPATH . 'models/Transaksi_model.php';
require_once APPPATH . 'helpers/auth_helper.php';
require_once APPPATH . 'helpers/csrf_helper.php';

class Pembayaran_controller extends Controller
{
  private const POLA_NAMA_BUKTI =
    '/^ft_byr_[A-Za-z0-9_]{1,80}\.(jpg|jpeg|png|webp)$/';

  private Pembayaran_model $model;
  private Pembayaran_model $pembayaranModel; // TAMBAHAN: dipakai method P10
  private Transaksi_model $transaksiModel;

  public function __construct()
  {
    $this->model = new Pembayaran_model();
    $this->pembayaranModel = $this->model; // TAMBAHAN: objek yang sama
    $this->transaksiModel = new Transaksi_model();
  }

  // GET /pembayaran
  public function index(): void
  {
    $userPlg = $this->wajibLoginPenyewa();
    $this->wajibMetode('GET');
    try {
      $daftar = $this->model->getDaftarDpPenyewa($userPlg);
      $flash = $this->ambilFlash();
      $this->view('pembayaran/index', [
        'daftar' => $daftar,
        'flash' => $flash,
        'jumlahKeranjang' => $this->transaksiModel->getJumlahMobilKeranjang($userPlg)
      ]);
    } catch (Throwable $e) {
      error_log('Daftar pembayaran penyewa gagal: ' . $e->getMessage());
      http_response_code(500);
      exit('Daftar pembayaran belum dapat ditampilkan. Silakan coba kembali.');
    }
  }

  // GET  /pembayaran/bayar?kode_sewa=...  -> halaman pembayaran
  // POST /pembayaran/bayar                -> proses unggah bukti, lalu PRG
  public function bayar(): void
  {
    $userPlg = $this->wajibLoginPenyewa();
    $metode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($metode === 'POST') {
      $this->prosesUnggah($userPlg);
      return;
    }
    $this->wajibMetode('GET');
    $kodeSewa = trim((string) ($_GET['kode_sewa'] ?? ''));
    if ($kodeSewa === '') {
      $this->redirectFlash(
        'pembayaran',
        'error',
        'Kode transaksi tidak ditemukan. Pilih transaksi dari daftar pembayaran.'
      );
    }
    try {
      $dp = $this->model->getDpPenyewa($kodeSewa, $userPlg);
      if ($dp === null) {
        http_response_code(404);
        exit('Data pembayaran tidak ditemukan atau bukan milik akun ini.');
      }
      $flash = $this->ambilFlash();
      $this->view('pembayaran/form', [
        'dp' => $dp,
        'bisaUnggah' => $this->model->bisaUnggah($dp),
        'flash' => $flash,
        'jumlahKeranjang' => $this->transaksiModel->getJumlahMobilKeranjang($userPlg)
      ]);
    } catch (Throwable $e) {
      error_log('Halaman pembayaran gagal: ' . $e->getMessage());
      http_response_code(500);
      exit('Halaman pembayaran belum dapat ditampilkan. Silakan coba kembali.');
    }
  }

  // GET /pembayaran/bukti?kode_bayar=...  (pemilik transaksi atau admin)
  public function bukti(): void
  {
    $this->wajibMetode('GET');
    $kodeBayar = trim((string) ($_GET['kode_bayar'] ?? ''));
    $adalahAdmin = is_admin_logged_in();
    $userPlg = trim((string) ($_SESSION['penyewa_user_plg'] ?? ''));
    if (!$adalahAdmin && $userPlg === '') {
      http_response_code(403);
      exit('Akses ditolak.');
    }
    if ($kodeBayar === '') {
      http_response_code(404);
      exit('Bukti pembayaran tidak ditemukan.');
    }
    try {
      $data = $this->model->getBuktiAkses($kodeBayar);
    } catch (Throwable $e) {
      error_log('Akses bukti pembayaran gagal: ' . $e->getMessage());
      http_response_code(500);
      exit('Bukti pembayaran belum dapat ditampilkan.');
    }
    // 404 (bukan 403) agar penyewa tidak dapat menebak kode pembayaran orang lain.
    if (
      $data === null ||
      (!$adalahAdmin && (string) $data['user_plg'] !== $userPlg)
    ) {
      http_response_code(404);
      exit('Bukti pembayaran tidak ditemukan.');
    }
    $nama = trim((string) ($data['ft_byr'] ?? ''));
    if ($nama === '' || !preg_match(self::POLA_NAMA_BUKTI, $nama)) {
      http_response_code(404);
      exit('Bukti pembayaran tidak ditemukan.');
    }
    $path = $this->folderBukti() . $nama;
    if (!is_file($path)) {
      http_response_code(404);
      exit('Berkas bukti pembayaran tidak ditemukan di server.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($path);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
      http_response_code(404);
      exit('Berkas bukti pembayaran tidak valid.');
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: inline; filename="' . $nama . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
  }

  private function prosesUnggah(string $userPlg): void
  {
    $kodeSewa = trim((string) ($_POST['kode_sewa'] ?? ''));
    if ($kodeSewa === '') {
      // Biasanya terjadi jika ukuran request melebihi post_max_size PHP.
      $this->redirectFlash(
        'pembayaran',
        'error',
        'Permintaan tidak lengkap. Pastikan ukuran file tidak melebihi batas server.'
      );
    }
    $balik = 'pembayaran/bayar?kode_sewa=' . rawurlencode($kodeSewa);
    if (!csrf_valid()) {
      $this->redirectFlash(
        $balik,
        'error',
        'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.'
      );
    }
    $upload = $this->uploadBukti('ft_byr');
    if (!$upload['success']) {
      $this->redirectFlash($balik, 'error', (string) $upload['error']);
    }
    $namaBaru = (string) $upload['filename'];
    try {
      $fotoLama = $this->model->simpanBukti($kodeSewa, $userPlg, $namaBaru);
    } catch (InvalidArgumentException $e) {
      $this->hapusBukti($namaBaru);
      $this->redirectFlash($balik, 'error', $e->getMessage());
    } catch (Throwable $e) {
      $this->hapusBukti($namaBaru);
      error_log('Simpan bukti pembayaran gagal: ' . $e->getMessage());
      $this->redirectFlash(
        $balik,
        'error',
        'Bukti pembayaran belum berhasil disimpan. Silakan coba kembali.'
      );
    }
    // Hapus berkas lama hanya setelah database berhasil diperbarui.
    if ($fotoLama !== null) {
      $this->hapusBukti($fotoLama);
    }
    $this->redirectFlash(
      $balik,
      'success',
      'Bukti pembayaran berhasil diunggah dan menunggu verifikasi admin.'
    );
  }

  private function folderBukti(): string
  {
    return dirname(APPPATH) . DIRECTORY_SEPARATOR . 'uploads'
      . DIRECTORY_SEPARATOR . 'bayar' . DIRECTORY_SEPARATOR;
  }

  private function uploadBukti(string $field): array
  {
    $gagal = static fn(string $pesan): array => [
      'success' => false,
      'filename' => null,
      'error' => $pesan
    ];
    if (
      !isset($_FILES[$field]) ||
      ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
      return $gagal('Bukti pembayaran wajib dipilih.');
    }
    $file = $_FILES[$field];
    if (is_array($file['error'] ?? null)) {
      return $gagal('Unggahan tidak valid.');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      return $gagal('Unggah bukti gagal. Periksa ukuran file dan konfigurasi PHP.');
    }
    if (
      !isset($file['tmp_name'], $file['size']) ||
      !is_uploaded_file($file['tmp_name']) ||
      (int) $file['size'] < 1 ||
      (int) $file['size'] > 5 * 1024 * 1024
    ) {
      return $gagal('Bukti harus berukuran lebih dari 0 byte dan maksimal 5 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    $diizinkan = [
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp'
    ];
    if (
      !isset($diizinkan[$mime]) ||
      @getimagesize($file['tmp_name']) === false
    ) {
      return $gagal('Format bukti harus JPEG, PNG, atau WEBP yang valid.');
    }
    $dir = $this->folderBukti();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
      return $gagal('Folder penyimpanan bukti tidak dapat dibuat.');
    }
    if (!is_writable($dir)) {
      return $gagal('Folder penyimpanan bukti tidak dapat ditulis.');
    }
    try {
      $nama = $field . '_' . date('YmdHis') . '_'
        . bin2hex(random_bytes(8)) . '.' . $diizinkan[$mime];
    } catch (Throwable $e) {
      return $gagal('Nama file bukti gagal dibuat.');
    }
    if (!move_uploaded_file($file['tmp_name'], $dir . $nama)) {
      return $gagal('Bukti gagal dipindahkan ke folder penyimpanan.');
    }
    return ['success' => true, 'filename' => $nama, 'error' => null];
  }

  private function hapusBukti(string $nama): void
  {
    if (!preg_match(self::POLA_NAMA_BUKTI, $nama)) {
      return;
    }
    $path = $this->folderBukti() . $nama;
    if (is_file($path)) {
      @unlink($path);
    }
  }

  private function wajibLoginPenyewa(): string
  {
    $userPlg = trim((string) ($_SESSION['penyewa_user_plg'] ?? ''));
    if ($userPlg === '') {
      $_SESSION['flash_penyewa_nav'] =
        'Silakan login terlebih dahulu untuk mengakses pembayaran.';
      header('Location: ' . site_url('auth/login-penyewa'));
      exit;
    }
    return $userPlg;
  }

  private function wajibMetode(string $metodeDiizinkan): void
  {
    $aktif = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($aktif !== $metodeDiizinkan) {
      http_response_code(405);
      header('Allow: ' . $metodeDiizinkan);
      exit('Method Not Allowed');
    }
  }

  private function ambilFlash(): ?array
  {
    $flash = $_SESSION['flash_pembayaran'] ?? null;
    unset($_SESSION['flash_pembayaran']);
    return is_array($flash) ? $flash : null;
  }

  private function redirectFlash(string $uri, string $jenis, string $pesan): never
  {
    $_SESSION['flash_pembayaran'] = ['type' => $jenis, 'message' => $pesan];
    header('Location: ' . site_url($uri));
    exit;
  }

  // ===== TAMBAHAN: penghubung untuk method P10 =====
  // Method P10 memanggil nama-nama ini; semuanya meneruskan ke method P9.
  private function redirectDenganPesan(string $uri, string $jenis, string $pesan): never
  {
    $this->redirectFlash($uri, $jenis, $pesan);
  }

  private function simpanFileBukti(string $field): array
  {
    return $this->uploadBukti($field);
  }

  private function hapusBuktiBaru(string $nama): void
  {
    $this->hapusBukti($nama);
  }
  // ===== AKHIR TAMBAHAN =====

/**
 * P10: Menampilkan daftar transaksi yang masih memiliki kewajiban pelunasan.
 */
public function pelunasanIndex(): void
{
  require_admin_login();
  $this->wajibMetode('GET');

  try {
    $daftarPelunasan = $this->pembayaranModel
      ->getDaftarPelunasan();

    $flash = $_SESSION['flash_pembayaran'] ?? null;
    unset($_SESSION['flash_pembayaran']);

    $this->view('admin/pelunasan', [
      'username' => $_SESSION['admin_username'],
      'daftarPelunasan' => $daftarPelunasan,
      'flash' => $flash
    ]);
  } catch (Throwable $e) {
    error_log(
      'Daftar pelunasan gagal ditampilkan: ' . $e->getMessage()
    );

    http_response_code(500);
    exit('Daftar transaksi pelunasan belum dapat ditampilkan. '
      . 'Silakan coba kembali.');
  }
}
/**
 * P10: Menampilkan detail transaksi, riwayat pembayaran, dan sisa kewajiban.
 */
public function pelunasanDetail(string $kodeSewa = ''): void
{
  require_admin_login();
  $this->wajibMetode('GET');

  $kodeSewa = trim(str_replace('~', '/', $kodeSewa));

  if ($kodeSewa === '') {
    http_response_code(400);
    exit('Kode transaksi sewa wajib tersedia.');
  }

  try {
    $detailPelunasan = $this->pembayaranModel
      ->getDetailPelunasan($kodeSewa);

    if ($detailPelunasan === null) {
      http_response_code(404);
      exit('Transaksi sewa tidak ditemukan.');
    }

    $flash = $_SESSION['flash_pembayaran'] ?? null;
    unset($_SESSION['flash_pembayaran']);

    $this->view('admin/pelunasan_detail', [
      'username' => $_SESSION['admin_username'],
      'detailPelunasan' => $detailPelunasan,
      'flash' => $flash
    ]);
  } catch (Throwable $e) {
    error_log(
      'Detail pelunasan gagal ditampilkan: ' . $e->getMessage()
    );

    http_response_code(500);
    exit('Detail transaksi pelunasan belum dapat ditampilkan. '
      . 'Silakan coba kembali.');
  }
}
/**
 * P10: Mencatat pelunasan yang diterima langsung oleh admin.
 *
 * Tunai: tidak memerlukan unggahan bukti.
 * Transfer: admin mengunggah bukti yang diterima dari penyewa.
 * Model menghitung nominal sisa kewajiban dan menyimpan pembayaran
 * dengan status valid dalam satu transaksi database.
 */
public function prosesPelunasan(string $kodeSewa = ''): void
{
  require_admin_login();
  $this->wajibMetode('POST');

  $kodeSewa = trim(str_replace('~', '/', $kodeSewa));
  $metodeBayar = trim((string) ($_POST['mtode_byr'] ?? ''));

  if ($kodeSewa === '') {
    http_response_code(400);
    exit('Kode transaksi sewa wajib tersedia.');
  }

  if (!in_array($metodeBayar, ['tunai', 'transfer'], true)) {
    $this->redirectDenganPesan(
      'admin/pembayaran/pelunasan/' . str_replace('/', '~', $kodeSewa),
      'error',
      'Metode pembayaran harus tunai atau transfer.'
    );
  }

  $namaFileBukti = null;

  if ($metodeBayar === 'transfer') {
    $hasilUpload = $this->simpanFileBukti('ft_byr');

    if (!$hasilUpload['success']) {
      $this->redirectDenganPesan(
        'admin/pembayaran/pelunasan/' . str_replace('/', '~', $kodeSewa),
        'error',
        $hasilUpload['error']
      );
    }

    $namaFileBukti = (string) $hasilUpload['filename'];
  }

  try {
    $kodeBayar = $this->pembayaranModel->simpanPelunasan(
      $kodeSewa,
      $metodeBayar,
      $namaFileBukti
    );

    $this->redirectDenganPesan(
      'admin/pembayaran/pelunasan/' . str_replace('/', '~', $kodeSewa),
      'success',
      'Pelunasan berhasil dicatat dengan kode pembayaran ' . $kodeBayar
      . '. Status pembayaran: valid; status transaksi: lunas.'
    );
  } catch (InvalidArgumentException $e) {
    if ($namaFileBukti !== null) {
      $this->hapusBuktiBaru($namaFileBukti);
    }

    $this->redirectDenganPesan(
      'admin/pembayaran/pelunasan/' . str_replace('/', '~', $kodeSewa),
      'error',
      $e->getMessage()
    );
  } catch (Throwable $e) {
    error_log(
      'Proses pelunasan gagal: ' . $e->getMessage()
    );

    if ($namaFileBukti !== null) {
      $this->hapusBuktiBaru($namaFileBukti);
    }

    $this->redirectDenganPesan(
      'admin/pembayaran/pelunasan/' . str_replace('/', '~', $kodeSewa),
      'error',
      'Pelunasan belum berhasil dicatat. Pastikan transaksi masih memiliki '
      . 'sisa kewajiban dan belum pernah dilunasi.'
    );
  }
}
/**
 * P10: Menampilkan dokumen transaksi yang siap dicetak.
 * Data disusun Model dari t_sewa, t_penyewa, t_pilih, t_mobil,
 * dan t_bayar. Method ini hanya menyajikan data.
 */
public function dokumenTransaksi(string $kodeSewa = ''): void
{
  require_admin_login();
  $this->wajibMetode('GET');

  $kodeSewa = trim(str_replace('~', '/', $kodeSewa));

  if ($kodeSewa === '') {
    http_response_code(400);
    exit('Kode transaksi sewa wajib tersedia.');
  }

  try {
    $detailPelunasan = $this->pembayaranModel
      ->getDetailPelunasan($kodeSewa);

    if ($detailPelunasan === null) {
      http_response_code(404);
      exit('Data transaksi tidak ditemukan.');
    }

    $this->view('admin/dokumen_transaksi', [
      'detailPelunasan' => $detailPelunasan
    ]);
  } catch (Throwable $e) {
    error_log(
      'Dokumen transaksi gagal ditampilkan: ' . $e->getMessage()
    );

    http_response_code(500);
    exit('Dokumen transaksi belum dapat ditampilkan. '
      . 'Silakan coba kembali.');
  }
}
}