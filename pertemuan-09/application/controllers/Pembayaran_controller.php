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
  private Transaksi_model $transaksiModel;

  public function __construct()
  {
    $this->model = new Pembayaran_model();
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
}