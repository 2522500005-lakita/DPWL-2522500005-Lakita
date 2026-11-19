<?php
// P9 BARU - application/controllers/Mpembayaran_controller.php
// Verifikasi pembayaran DP oleh admin: daftar, detail, dan keputusan valid/invalid.
class Mpembayaran_controller extends Controller
{
  private Pembayaran_model $model;

  public function __construct()
  {
    require_once APPPATH . 'models/Pembayaran_model.php';
    require_once APPPATH . 'helpers/auth_helper.php';
    require_once APPPATH . 'helpers/flash_helper.php';
    require_once APPPATH . 'helpers/csrf_helper.php';
    $this->model = new Pembayaran_model();
  }

  // GET /admin/pembayaran[?status=pending|valid|invalid]
  public function index(): void
  {
    require_admin_login();
    $this->wajibMetode('GET');
    $status = trim((string) ($_GET['status'] ?? ''));
    if (!in_array($status, ['pending', 'valid', 'invalid'], true)) {
      $status = '';
    }
    try {
      $this->view('admin/mpembayaran_view', [
        'username' => $_SESSION['admin_username'],
        'mode' => 'daftar',
        'daftar' => $this->model->getDaftarAdmin($status === '' ? null : $status),
        'ringkasan' => $this->model->getRingkasanStatus(),
        'filter' => $status,
        'detail' => null,
        'bisaDiverifikasi' => false,
        'flash' => get_flash_message()
      ]);
    } catch (Throwable $e) {
      error_log('Daftar verifikasi pembayaran gagal: ' . $e->getMessage());
      http_response_code(500);
      exit('Daftar pembayaran belum dapat ditampilkan. Silakan coba kembali.');
    }
  }

  // GET /admin/pembayaran/detail?kode_bayar=...
  public function detail(): void
  {
    require_admin_login();
    $this->wajibMetode('GET');
    $kodeBayar = trim((string) ($_GET['kode_bayar'] ?? ''));
    if ($kodeBayar === '') {
      $this->kembaliKeDaftar('danger', 'Kode pembayaran wajib diisi.');
    }
    try {
      $detail = $this->model->getDetailAdmin($kodeBayar);
      if ($detail === null) {
        $this->kembaliKeDaftar('danger', 'Data pembayaran tidak ditemukan.');
      }
      $this->view('admin/mpembayaran_view', [
        'username' => $_SESSION['admin_username'],
        'mode' => 'detail',
        'daftar' => [],
        'ringkasan' => [],
        'filter' => '',
        'detail' => $detail,
        'bisaDiverifikasi' => $this->model->bisaDiverifikasi($detail),
        'flash' => get_flash_message()
      ]);
    } catch (Throwable $e) {
      error_log('Detail verifikasi pembayaran gagal: ' . $e->getMessage());
      http_response_code(500);
      exit('Detail pembayaran belum dapat ditampilkan. Silakan coba kembali.');
    }
  }

  // POST /admin/pembayaran/verifikasi -> proses keputusan, lalu PRG
  public function verifikasi(): void
  {
    require_admin_login();
    $this->wajibMetode('POST');
    $kodeBayar = trim((string) ($_POST['kode_bayar'] ?? ''));
    $keputusan = trim((string) ($_POST['keputusan'] ?? ''));
    if ($kodeBayar === '') {
      $this->kembaliKeDaftar('danger', 'Kode pembayaran wajib diisi.');
    }
    $balik = 'admin/pembayaran/detail?kode_bayar=' . rawurlencode($kodeBayar);
    if (!csrf_valid()) {
      set_flash_message('danger', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');
      header('Location: ' . site_url($balik));
      exit;
    }
    try {
      $this->model->verifikasi($kodeBayar, $keputusan);
      set_flash_message(
        'success',
        $keputusan === 'valid'
          ? 'Pembayaran DP diterima. Transaksi disetujui.'
          : 'Pembayaran DP ditolak. Penyewa dapat mengunggah ulang bukti.'
      );
    } catch (InvalidArgumentException $e) {
      set_flash_message('danger', $e->getMessage());
    } catch (Throwable $e) {
      error_log('Verifikasi pembayaran gagal: ' . $e->getMessage());
      set_flash_message(
        'danger',
        'Verifikasi belum berhasil diproses. Silakan coba kembali.'
      );
    }
    header('Location: ' . site_url($balik));
    exit;
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

  private function kembaliKeDaftar(string $jenis, string $pesan): never
  {
    set_flash_message($jenis, $pesan);
    header('Location: ' . site_url('admin/pembayaran'));
    exit;
  }
}