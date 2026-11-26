<?php
// P9 BARU - application/views/admin/mpembayaran_view.php
// Satu View untuk dua mode: 'daftar' dan 'detail' (pola sama dengan mobil_view.php).
require_once APPPATH . 'helpers/csrf_helper.php';
require APPPATH . '/views/templates/header.php';
require APPPATH . '/views/templates/sidebar.php';

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$mode = $mode ?? 'daftar';
$flash = $flash ?? null;
$flashClass = (($flash['type'] ?? '') === 'success') ? 'alert-success' : 'alert-danger';
$daftar = is_array($daftar ?? null) ? $daftar : [];
$ringkasan = is_array($ringkasan ?? null) ? $ringkasan : [];
$filter = (string) ($filter ?? '');
$d = is_array($detail ?? null) ? $detail : [];
$bisaDiverifikasi = (bool) ($bisaDiverifikasi ?? false);

$formatRupiah = static fn($n): string => 'Rp ' . number_format((float) $n, 0, ',', '.');
$formatWaktu = static function ($v): string {
  $ts = $v ? strtotime((string) $v) : false;
  return $ts === false ? '-' : date('d-m-Y H:i', $ts);
};
// Label tampilan: valid = Diterima, invalid = Ditolak.
$labelStatus = [
  'pending' => ['Menunggu', 'label-warning'],
  'valid' => ['Diterima', 'label-success'],
  'invalid' => ['Ditolak', 'label-danger'],
];
$urlBukti = static fn(string $kode): string =>
  site_url('pembayaran/bukti?kode_bayar=' . rawurlencode($kode));
?>
<div class="content-wrapper">
  <section class="content-header">
    <h1>Verifikasi Pembayaran <small>DP penyewa</small></h1>
    <ol class="breadcrumb">
      <li><a href="<?= $e(site_url('admin')) ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
      <li class="active">Verifikasi Pembayaran</li>
    </ol>
  </section>

  <section class="content">
    <?php if (!empty($flash)): ?>
      <div class="alert <?= $flashClass ?> alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <i class="fa <?= (($flash['type'] ?? '') === 'success') ? 'fa-check' : 'fa-warning' ?>"></i>
        <?= $e($flash['message'] ?? '') ?>
      </div>
    <?php endif; ?>

    <?php if ($mode === 'daftar'): ?>
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Daftar Pembayaran DP</h3>
          <div class="box-tools">
            <a href="<?= $e(site_url('admin/pembayaran')) ?>"
              class="btn btn-xs <?= $filter === '' ? 'btn-primary' : 'btn-default' ?>">Semua</a>
            <a href="<?= $e(site_url('admin/pembayaran?status=pending')) ?>"
              class="btn btn-xs <?= $filter === 'pending' ? 'btn-warning' : 'btn-default' ?>">
              Menunggu (<?= (int) ($ringkasan['pending'] ?? 0) ?>)</a>
            <a href="<?= $e(site_url('admin/pembayaran?status=valid')) ?>"
              class="btn btn-xs <?= $filter === 'valid' ? 'btn-success' : 'btn-default' ?>">
              Diterima (<?= (int) ($ringkasan['valid'] ?? 0) ?>)</a>
            <a href="<?= $e(site_url('admin/pembayaran?status=invalid')) ?>"
              class="btn btn-xs <?= $filter === 'invalid' ? 'btn-danger' : 'btn-default' ?>">
              Ditolak (<?= (int) ($ringkasan['invalid'] ?? 0) ?>)</a>
          </div>
        </div>
        <div class="box-body">
          <div class="table-responsive">
            <table id="pembayaranTable" class="table table-bordered table-striped js-data-table" data-order="[]">
              <thead>
                <tr>
                  <th>No</th>
                  <th>Kode Bayar</th>
                  <th>Kode Sewa</th>
                  <th>Penyewa</th>
                  <th>Nominal</th>
                  <th>Waktu Bayar</th>
                  <th>Bukti</th>
                  <th>Status</th>
                  <th class="no-sort">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($daftar as $no => $item): ?>
                  <?php
                  $st = $labelStatus[$item['sttus_byr']] ?? [(string) $item['sttus_byr'], 'label-default'];
                  $adaBukti = trim((string) ($item['ft_byr'] ?? '')) !== '';
                  ?>
                  <tr>
                    <td><?= (int) $no + 1 ?></td>
                    <td><?= $e($item['kode_bayar']) ?></td>
                    <td><?= $e($item['kode_sewa']) ?>
                      <br><small class="text-muted">transaksi: <?= $e($item['status_sewa']) ?></small></td>
                    <td><?= $e($item['nm_lngkp'] ?? '-') ?>
                      <br><small class="text-muted"><?= $e($item['user_plg']) ?></small></td>
                    <td class="text-right"><?= $e($formatRupiah($item['nominal'])) ?></td>
                    <td><?= $e($formatWaktu($item['tgl_wkt_byr'])) ?></td>
                    <td><?= $adaBukti ? '<span class="text-success">Ada</span>' : '<span class="text-muted">Belum</span>' ?></td>
                    <td><span class="label <?= $e($st[1]) ?>"><?= $e($st[0]) ?></span></td>
                    <td>
                      <a class="btn btn-info btn-xs"
                        href="<?= $e(site_url('admin/pembayaran/detail?kode_bayar=' . rawurlencode((string) $item['kode_bayar']))) ?>">
                        <i class="fa fa-search"></i> Detail
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <?php else: ?>
      <?php
      $st = $labelStatus[$d['sttus_byr'] ?? ''] ?? [(string) ($d['sttus_byr'] ?? ''), 'label-default'];
      $adaBukti = trim((string) ($d['ft_byr'] ?? '')) !== '';
      $kodeBayar = (string) ($d['kode_bayar'] ?? '');
      $selisih = (int) ($d['nominal'] ?? 0) !== (int) ($d['dp_seharusnya'] ?? 0);
      ?>
      <div class="row">
        <div class="col-md-6">
          <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Data Pembayaran</h3></div>
            <div class="box-body">
              <dl class="dl-horizontal">
                <dt>Kode bayar</dt><dd><?= $e($kodeBayar) ?></dd>
                <dt>Kode sewa</dt><dd><?= $e($d['kode_sewa'] ?? '') ?></dd>
                <dt>Status transaksi</dt><dd><?= $e($d['status_sewa'] ?? '') ?></dd>
                <dt>Penyewa</dt><dd><?= $e($d['nm_lngkp'] ?? '-') ?> (<?= $e($d['user_plg'] ?? '') ?>)</dd>
                <dt>No. HP</dt><dd><?= $e($d['no_hp'] ?? '-') ?></dd>
                <dt>Periode sewa</dt><dd><?= $e($d['tgl_mulai'] ?? '') ?> s.d. <?= $e($d['tgl_selesai'] ?? '') ?></dd>
                <dt>Waktu ambil</dt><dd><?= $e(substr((string) ($d['wkt_ambil'] ?? ''), 0, 5)) ?> WIB</dd>
                <dt>Metode</dt><dd><?= $e($d['mtode_byr'] ?? '') ?></dd>
                <dt>Total sewa</dt><dd><?= $e($formatRupiah($d['total'] ?? 0)) ?></dd>
                <dt>DP 30% (hitung)</dt><dd><?= $e($formatRupiah($d['dp_seharusnya'] ?? 0)) ?></dd>
                <dt>Nominal tercatat</dt>
                <dd><?= $e($formatRupiah($d['nominal'] ?? 0)) ?>
                  <?php if ($selisih): ?>
                    <span class="label label-warning">tidak sama dengan DP 30%</span>
                  <?php endif; ?></dd>
                <dt>Waktu bayar</dt><dd><?= $e($formatWaktu($d['tgl_wkt_byr'] ?? null)) ?></dd>
                <dt>Waktu verifikasi</dt><dd><?= $e($formatWaktu($d['tgl_wkt_ver'] ?? null)) ?></dd>
                <dt>Status</dt><dd><span class="label <?= $e($st[1]) ?>"><?= $e($st[0]) ?></span></dd>
              </dl>
              <table class="table table-bordered table-condensed">
                <thead><tr><th>Kendaraan</th><th>No. Plat</th><th class="text-right">Harga/hari</th><th>Hari</th></tr></thead>
                <tbody>
                  <?php foreach (($d['detail'] ?? []) as $item): ?>
                    <tr>
                      <td><?= $e($item['nm_mobil'] ?? '-') ?></td>
                      <td><?= $e($item['no_plat']) ?></td>
                      <td class="text-right"><?= $e($formatRupiah($item['hrg_sewa'])) ?></td>
                      <td><?= $e($item['n_hari']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Bukti Pembayaran</h3></div>
            <div class="box-body text-center">
              <?php if ($adaBukti): ?>
                <a href="<?= $e($urlBukti($kodeBayar)) ?>" target="_blank" rel="noopener">
                  <img src="<?= $e($urlBukti($kodeBayar)) ?>" alt="Bukti <?= $e($kodeBayar) ?>"
                    style="max-width:100%; max-height:420px; border:1px solid #ddd;">
                </a>
                <p class="text-muted" style="margin-top:8px;">Klik gambar untuk membuka ukuran penuh.</p>
              <?php else: ?>
                <p class="text-muted">Penyewa belum mengunggah bukti pembayaran.</p>
              <?php endif; ?>
            </div>
          </div>

          <div class="box">
            <div class="box-header with-border"><h3 class="box-title">Verifikasi</h3></div>
            <div class="box-body">
              <?php if ($bisaDiverifikasi): ?>
                <form method="post" action="<?= $e(site_url('admin/pembayaran/verifikasi')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="kode_bayar" value="<?= $e($kodeBayar) ?>">
                  <button type="submit" name="keputusan" value="valid" class="btn btn-success"
                    onclick="return confirm('Terima pembayaran ini dan setujui transaksi?');">
                    <i class="fa fa-check"></i> Terima (Valid)
                  </button>
                  <button type="submit" name="keputusan" value="invalid" class="btn btn-danger"
                    onclick="return confirm('Tolak pembayaran ini? Penyewa dapat mengunggah ulang bukti.');">
                    <i class="fa fa-times"></i> Tolak (Invalid)
                  </button>
                </form>
              <?php else: ?>
                <p class="text-muted" style="margin:0;">
                  Pembayaran ini tidak dapat diverifikasi (sudah diverifikasi, belum berbukti,
                  atau status transaksi bukan diajukan).
                </p>
              <?php endif; ?>
            </div>
            <div class="box-footer">
              <a href="<?= $e(site_url('admin/pembayaran')) ?>" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Kembali ke Daftar
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php require APPPATH . '/views/templates/footer.php'; ?>