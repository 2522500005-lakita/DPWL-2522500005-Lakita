<?php
// P9 BARU - application/views/pembayaran/index.php
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
$daftar = is_array($daftar ?? null) ? $daftar : [];
$flashType = (string) ($flash['type'] ?? '');
$flashMessage = (string) ($flash['message'] ?? '');
$flashClass = $flashType === 'success' ? 'alert-success' : 'alert-danger';
?>
<section class="bg-success py-4">
  <div class="container">
    <h1 class="h2 text-white mt-3">Pembayaran DP</h1>
    <p class="text-white mb-0">Unggah bukti transfer uang muka dan pantau hasil verifikasi admin.</p>
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
    <?php if (count($daftar) === 0): ?>
      <div class="alert alert-info mb-0">
        Belum ada transaksi yang memiliki pembayaran DP. Lakukan checkout dari keranjang terlebih dahulu.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle bg-white">
          <thead class="table-light">
            <tr>
              <th scope="col">Kode Transaksi</th>
              <th scope="col">Kode Bayar</th>
              <th scope="col">Periode Sewa</th>
              <th scope="col" class="text-end">Nominal DP</th>
              <th scope="col">Status Pembayaran</th>
              <th scope="col">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftar as $item): ?>
              <?php
              $st = $labelStatus[$item['sttus_byr']] ?? [(string) $item['sttus_byr'], 'bg-secondary'];
              $adaBukti = trim((string) ($item['ft_byr'] ?? '')) !== '';
              $perluUnggah = ($item['status_sewa'] ?? '') === 'diajukan'
                && ($item['sttus_byr'] === 'invalid' || ($item['sttus_byr'] === 'pending' && !$adaBukti));
              ?>
              <tr>
                <td><?= $e($item['kode_sewa']) ?></td>
                <td><?= $e($item['kode_bayar']) ?></td>
                <td><?= $e($item['tgl_mulai']) ?> s.d. <?= $e($item['tgl_selesai']) ?></td>
                <td class="text-end text-nowrap"><?= $e($formatRupiah($item['nominal'])) ?></td>
                <td>
                  <span class="badge <?= $e($st[1]) ?>"><?= $e($st[0]) ?></span>
                  <?php if ($item['sttus_byr'] === 'pending' && !$adaBukti): ?>
                    <div class="small text-muted">Bukti belum diunggah</div>
                  <?php endif; ?>
                  <div class="small text-muted">Transaksi: <?= $e($item['status_sewa']) ?></div>
                </td>
                <td>
                  <a class="btn btn-sm <?= $perluUnggah ? 'btn-success' : 'btn-outline-secondary' ?>"
                    href="<?= $e(site_url('pembayaran/bayar?kode_sewa=' . rawurlencode((string) $item['kode_sewa']))) ?>">
                    <?= $perluUnggah ? 'Bayar / Unggah Bukti' : 'Lihat Detail' ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require APPPATH . 'views/katalog/footer.php'; ?>