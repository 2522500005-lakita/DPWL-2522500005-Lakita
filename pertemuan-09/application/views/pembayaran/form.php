<?php
// P9 BARU - application/views/pembayaran/form.php
require_once APPPATH . 'helpers/csrf_helper.php';
require APPPATH . 'views/katalog/header.php';
$e = static function ($value): string {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
require APPPATH . 'views/katalog/navbar.php';
$formatRupiah = static function ($nilai): string {
  return 'Rp ' . number_format((float) $nilai, 0, ',', '.');
};
$formatWaktu = static function ($nilai): string {
  $ts = $nilai ? strtotime((string) $nilai) : false;
  return $ts === false ? '-' : date('d-m-Y H:i', $ts);
};
$labelStatus = [
  'pending' => ['Menunggu verifikasi', 'bg-warning text-dark'],
  'valid' => ['Diterima', 'bg-success'],
  'invalid' => ['Ditolak', 'bg-danger'],
];
$detail = is_array($dp['detail'] ?? null) ? $dp['detail'] : [];
$kodeSewa = (string) ($dp['kode_sewa'] ?? '');
$kodeBayar = (string) ($dp['kode_bayar'] ?? '');
$statusBayar = (string) ($dp['sttus_byr'] ?? '');
$st = $labelStatus[$statusBayar] ?? [$statusBayar, 'bg-secondary'];
$adaBukti = trim((string) ($dp['ft_byr'] ?? '')) !== '';
$flashType = (string) ($flash['type'] ?? '');
$flashMessage = (string) ($flash['message'] ?? '');
$flashClass = $flashType === 'success' ? 'alert-success' : 'alert-danger';
$urlBukti = site_url('pembayaran/bukti?kode_bayar=' . rawurlencode($kodeBayar));
?>
<section class="bg-success py-4">
  <div class="container">
    <h1 class="h2 text-white mt-3">Pembayaran DP</h1>
    <p class="text-white mb-0">Transaksi <?= $e($kodeSewa) ?></p>
  </div>
</section>
<section class="py-5">
  <div class="container">
    <?php if ($flashMessage !== ''): ?>
      <div class="alert <?= $e($flashClass) ?> alert-dismissible fade show" role="alert">
        <?= $e($flashMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
      </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-light">
        <h2 class="h5 mb-0">Ringkasan Sewa</h2>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Kode transaksi</dt>
          <dd class="col-sm-8"><?= $e($kodeSewa) ?></dd>
          <dt class="col-sm-4">Status transaksi</dt>
          <dd class="col-sm-8"><?= $e($dp['status_sewa'] ?? '-') ?></dd>
          <dt class="col-sm-4">Periode sewa</dt>
          <dd class="col-sm-8"><?= $e($dp['tgl_mulai'] ?? '-') ?> s.d. <?= $e($dp['tgl_selesai'] ?? '-') ?></dd>
          <dt class="col-sm-4">Waktu pengambilan</dt>
          <dd class="col-sm-8"><?= $e(substr((string) ($dp['wkt_ambil'] ?? '-'), 0, 5)) ?> WIB</dd>
        </dl>
      </div>
    </div>

    <div class="table-responsive mb-4">
      <table class="table table-bordered align-middle bg-white">
        <thead class="table-light">
          <tr>
            <th scope="col">Kendaraan</th>
            <th scope="col">Nomor Polisi</th>
            <th scope="col" class="text-end">Harga per Hari</th>
            <th scope="col" class="text-center">Durasi</th>
            <th scope="col" class="text-end">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($detail as $item): ?>
            <?php $subtotal = (int) $item['hrg_sewa'] * (int) $item['n_hari']; ?>
            <tr>
              <td><?= $e($item['nm_mobil'] ?? '-') ?></td>
              <td><?= $e($item['no_plat']) ?></td>
              <td class="text-end text-nowrap"><?= $e($formatRupiah($item['hrg_sewa'])) ?></td>
              <td class="text-center"><?= $e($item['n_hari']) ?> hari</td>
              <td class="text-end text-nowrap"><?= $e($formatRupiah($subtotal)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="row g-4">
      <div class="col-lg-6">
        <div class="card border-success h-100">
          <div class="card-header bg-light"><h2 class="h5 mb-0">Rincian DP</h2></div>
          <div class="card-body">
            <div class="d-flex justify-content-between gap-3 mb-2">
              <span>Total sewa</span>
              <strong class="text-nowrap"><?= $e($formatRupiah($dp['total'] ?? 0)) ?></strong>
            </div>
            <div class="d-flex justify-content-between gap-3 mb-2">
              <span>Nominal DP yang harus ditransfer</span>
              <strong class="text-nowrap"><?= $e($formatRupiah($dp['nominal'] ?? 0)) ?></strong>
            </div>
            <div class="d-flex justify-content-between gap-3 mb-2">
              <span>Kode pembayaran</span>
              <strong><?= $e($kodeBayar) ?></strong>
            </div>
            <div class="d-flex justify-content-between gap-3 mb-2">
              <span>Waktu pembayaran tercatat</span>
              <span><?= $e($formatWaktu($dp['tgl_wkt_byr'] ?? null)) ?></span>
            </div>
            <div class="d-flex justify-content-between gap-3 mb-2">
              <span>Status pembayaran</span>
              <span class="badge <?= $e($st[1]) ?>"><?= $e($st[0]) ?></span>
            </div>
            <?php if (!empty($dp['tgl_wkt_ver'])): ?>
              <div class="d-flex justify-content-between gap-3">
                <span>Waktu verifikasi</span>
                <span><?= $e($formatWaktu($dp['tgl_wkt_ver'])) ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-light"><h2 class="h5 mb-0">Bukti Pembayaran</h2></div>
          <div class="card-body">
            <?php if ($adaBukti): ?>
              <p class="mb-2">Bukti yang tersimpan:</p>
              <a href="<?= $e($urlBukti) ?>" target="_blank" rel="noopener">
                <img src="<?= $e($urlBukti) ?>" alt="Bukti pembayaran <?= $e($kodeBayar) ?>"
                  class="img-fluid border rounded mb-3" style="max-height: 280px;">
              </a>
            <?php else: ?>
              <p class="text-muted">Bukti pembayaran belum diunggah.</p>
            <?php endif; ?>

            <?php if ($statusBayar === 'invalid'): ?>
              <div class="alert alert-danger">
                Pembayaran Anda ditolak oleh admin. Silakan unggah ulang bukti transfer yang jelas dan sesuai nominal.
              </div>
            <?php elseif ($statusBayar === 'valid'): ?>
              <div class="alert alert-success mb-0">Pembayaran DP telah diterima oleh admin.</div>
            <?php elseif ($statusBayar === 'pending' && $adaBukti): ?>
              <div class="alert alert-warning mb-0">Bukti sudah diterima dan sedang menunggu verifikasi admin.</div>
            <?php endif; ?>

            <?php if ($bisaUnggah): ?>
              <form method="post" enctype="multipart/form-data"
                action="<?= $e(site_url('pembayaran/bayar')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="kode_sewa" value="<?= $e($kodeSewa) ?>">
                <div class="mb-3">
                  <label for="ft_byr" class="form-label">Unggah bukti transfer</label>
                  <input type="file" class="form-control" id="ft_byr" name="ft_byr" required
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                  <div class="form-text">JPG, PNG, atau WEBP; maksimal 5 MB. Transfer sebesar
                    <?= $e($formatRupiah($dp['nominal'] ?? 0)) ?> sebelum mengunggah.</div>
                </div>
                <button type="submit" class="btn btn-success">
                  <i class="fas fa-upload me-1" aria-hidden="true"></i> Kirim Bukti Pembayaran
                </button>
              </form>
            <?php elseif (($dp['status_sewa'] ?? '') !== 'diajukan' && $statusBayar !== 'valid'): ?>
              <div class="alert alert-secondary mt-3 mb-0">
                Transaksi berstatus <?= $e($dp['status_sewa'] ?? '-') ?>, sehingga bukti tidak dapat diunggah.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <a class="btn btn-outline-secondary" href="<?= $e(site_url('pembayaran')) ?>">
        <i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Kembali ke Daftar Pembayaran
      </a>
    </div>
  </div>
</section>
<?php require APPPATH . 'views/katalog/footer.php'; ?>