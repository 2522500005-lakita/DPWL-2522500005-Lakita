<?php
// P9 BARU - application/models/Pembayaran_model.php
// Mengelola pembayaran DP: unggah bukti oleh penyewa dan verifikasi oleh admin.
// Record DP dibuat oleh Transaksi_model::checkout() (P7); model ini hanya
// MEMPERBARUI record tersebut dan tidak pernah melakukan INSERT ke t_bayar.
class Pembayaran_model
{
  private mysqli $db;

  public function __construct()
  {
    global $db;
    $this->db = $db;
  }

  private function ambilBaris(
    string $sql,
    string $tipe,
    array $param,
    string $konteks
  ): array {
    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new RuntimeException($konteks . ' gagal disiapkan.');
    }
    if ($tipe !== '') {
      $stmt->bind_param($tipe, ...$param);
    }
    if (!$stmt->execute()) {
      $stmt->close();
      throw new RuntimeException($konteks . ' gagal dijalankan.');
    }
    $hasil = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $hasil;
  }

  private function ambilSatu(
    string $sql,
    string $tipe,
    array $param,
    string $konteks
  ): ?array {
    $baris = $this->ambilBaris($sql, $tipe, $param, $konteks);
    return $baris[0] ?? null;
  }

  /** Penyewa dapat mengunggah bukti jika transaksi masih diajukan dan
   *  DP belum berbukti (pending tanpa file) atau sebelumnya ditolak (invalid). */
  public function bisaUnggah(array $dp): bool
  {
    if (($dp['status_sewa'] ?? '') !== 'diajukan') {
      return false;
    }
    $status = (string) ($dp['sttus_byr'] ?? '');
    $adaBukti = trim((string) ($dp['ft_byr'] ?? '')) !== '';
    return $status === 'invalid' || ($status === 'pending' && !$adaBukti);
  }

  /** Admin dapat memverifikasi jika DP pending, sudah berbukti, dan transaksi diajukan. */
  public function bisaDiverifikasi(array $dp): bool
  {
    return ($dp['status_sewa'] ?? '') === 'diajukan'
      && ($dp['sttus_byr'] ?? '') === 'pending'
      && trim((string) ($dp['ft_byr'] ?? '')) !== '';
  }

  private function ambilDetailKendaraan(string $kodeSewa): array
  {
    return $this->ambilBaris(
      "SELECT p.no_plat, p.hrg_sewa, p.n_hari, m.nm_mobil
FROM t_pilih p
LEFT JOIN t_mobil m ON m.no_plat = p.no_plat
WHERE p.kode_sewa = ?
ORDER BY p.no_plat ASC",
      's',
      [$kodeSewa],
      'Pengambilan detail kendaraan'
    );
  }

  private function lengkapi(array $dp): array
  {
    $dp['detail'] = $this->ambilDetailKendaraan((string) $dp['kode_sewa']);
    $total = 0;
    foreach ($dp['detail'] as $item) {
      $total += (int) $item['hrg_sewa'] * (int) $item['n_hari'];
    }
    $dp['total'] = $total;
    $dp['dp_seharusnya'] = (int) round($total * 30 / 100);
    return $dp;
  }

  // ---------------------------------------------------------------
  // Sisi penyewa
  // ---------------------------------------------------------------
  public function getDaftarDpPenyewa(string $userPlg): array
  {
    return $this->ambilBaris(
      "SELECT b.kode_bayar, b.tgl_wkt_byr, b.ft_byr, b.nominal,
b.sttus_byr, b.tgl_wkt_ver,
s.kode_sewa, s.tgl_mulai, s.tgl_selesai, s.wkt_ambil, s.status_sewa
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
WHERE s.user_plg = ? AND b.jns_byr = 'dp'
ORDER BY b.tgl_wkt_byr DESC, b.kode_bayar DESC",
      's',
      [$userPlg],
      'Pengambilan daftar pembayaran DP'
    );
  }

  public function getDpPenyewa(string $kodeSewa, string $userPlg): ?array
  {
    $dp = $this->ambilSatu(
      "SELECT b.kode_bayar, b.tgl_wkt_byr, b.ft_byr, b.mtode_byr,
b.nominal, b.sttus_byr, b.tgl_wkt_ver,
s.kode_sewa, s.tgl_mulai, s.tgl_selesai, s.wkt_ambil,
s.status_sewa, s.user_plg
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
WHERE b.kode_sewa = ? AND s.user_plg = ? AND b.jns_byr = 'dp'
ORDER BY b.kode_bayar DESC
LIMIT 1",
      'ss',
      [$kodeSewa, $userPlg],
      'Pengambilan pembayaran DP penyewa'
    );
    return $dp === null ? null : $this->lengkapi($dp);
  }

  /**
   * Menyimpan nama file bukti pada record DP yang SUDAH ADA (UPDATE).
   * Mengembalikan nama file lama (atau null) agar controller dapat menghapusnya
   * setelah commit berhasil.
   */
  public function simpanBukti(
    string $kodeSewa,
    string $userPlg,
    string $namaFile
  ): ?string {
    $this->db->begin_transaction();
    try {
      $dp = $this->ambilSatu(
        "SELECT b.kode_bayar, b.ft_byr, b.sttus_byr, s.status_sewa
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
WHERE b.kode_sewa = ? AND s.user_plg = ? AND b.jns_byr = 'dp'
ORDER BY b.kode_bayar DESC
LIMIT 1
FOR UPDATE",
        'ss',
        [$kodeSewa, $userPlg],
        'Penguncian pembayaran DP'
      );
      if ($dp === null) {
        throw new InvalidArgumentException(
          'Data pembayaran DP tidak ditemukan atau bukan milik akun ini.'
        );
      }
      if (!$this->bisaUnggah($dp)) {
        throw new InvalidArgumentException(
          'Bukti pembayaran tidak dapat diunggah pada status saat ini.'
        );
      }
      $statusLama = (string) $dp['sttus_byr'];
      $kodeBayar = (string) $dp['kode_bayar'];
      $stmt = $this->db->prepare(
        "UPDATE t_bayar
SET ft_byr = ?, mtode_byr = 'transfer', tgl_wkt_byr = NOW(),
sttus_byr = 'pending', tgl_wkt_ver = NULL
WHERE kode_bayar = ? AND jns_byr = 'dp' AND sttus_byr = ?"
      );
      if (!$stmt) {
        throw new RuntimeException('Pembaruan bukti pembayaran gagal disiapkan.');
      }
      $stmt->bind_param('sss', $namaFile, $kodeBayar, $statusLama);
      if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Pembaruan bukti pembayaran gagal dijalankan.');
      }
      $berubah = $stmt->affected_rows === 1;
      $stmt->close();
      if (!$berubah) {
        throw new RuntimeException('Record DP tidak berubah; proses dibatalkan.');
      }
      $this->db->commit();
      $lama = trim((string) ($dp['ft_byr'] ?? ''));
      return $lama === '' ? null : $lama;
    } catch (Throwable $e) {
      $this->db->rollback();
      throw $e;
    }
  }

  // ---------------------------------------------------------------
  // Sisi admin
  // ---------------------------------------------------------------
  public function getDaftarAdmin(?string $status): array
  {
    $sql = "SELECT b.kode_bayar, b.tgl_wkt_byr, b.ft_byr, b.mtode_byr,
b.nominal, b.sttus_byr, b.tgl_wkt_ver,
s.kode_sewa, s.status_sewa, s.user_plg, p.nm_lngkp
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
LEFT JOIN t_penyewa p ON p.user_plg = s.user_plg
WHERE b.jns_byr = 'dp'";
    $tipe = '';
    $param = [];
    if ($status !== null) {
      $sql .= ' AND b.sttus_byr = ?';
      $tipe = 's';
      $param = [$status];
    }
    $sql .= " ORDER BY FIELD(b.sttus_byr, 'pending', 'invalid', 'valid'),
b.tgl_wkt_byr DESC, b.kode_bayar DESC";
    return $this->ambilBaris($sql, $tipe, $param, 'Pengambilan daftar verifikasi');
  }

  public function getRingkasanStatus(): array
  {
    $ringkas = ['pending' => 0, 'valid' => 0, 'invalid' => 0];
    $baris = $this->ambilBaris(
      "SELECT sttus_byr, COUNT(*) AS jumlah
FROM t_bayar
WHERE jns_byr = 'dp'
GROUP BY sttus_byr",
      '',
      [],
      'Penghitungan status pembayaran'
    );
    foreach ($baris as $item) {
      if (isset($ringkas[$item['sttus_byr']])) {
        $ringkas[$item['sttus_byr']] = (int) $item['jumlah'];
      }
    }
    return $ringkas;
  }

  public function getDetailAdmin(string $kodeBayar): ?array
  {
    $dp = $this->ambilSatu(
      "SELECT b.kode_bayar, b.tgl_wkt_byr, b.ft_byr, b.mtode_byr,
b.nominal, b.sttus_byr, b.tgl_wkt_ver,
s.kode_sewa, s.tgl_mulai, s.tgl_selesai, s.wkt_ambil,
s.status_sewa, s.user_plg, p.nm_lngkp, p.no_hp
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
LEFT JOIN t_penyewa p ON p.user_plg = s.user_plg
WHERE b.kode_bayar = ? AND b.jns_byr = 'dp'
LIMIT 1",
      's',
      [$kodeBayar],
      'Pengambilan detail verifikasi'
    );
    return $dp === null ? null : $this->lengkapi($dp);
  }

  /**
   * Verifikasi DP. valid => DP diterima dan transaksi disetujui.
   * invalid => DP ditolak; transaksi tetap diajukan agar penyewa dapat
   * mengunggah ulang bukti. Hanya UPDATE; tidak ada INSERT.
   */
  public function verifikasi(string $kodeBayar, string $keputusan): void
  {
    if (!in_array($keputusan, ['valid', 'invalid'], true)) {
      throw new InvalidArgumentException('Keputusan verifikasi tidak valid.');
    }
    $this->db->begin_transaction();
    try {
      $dp = $this->ambilSatu(
        "SELECT b.kode_bayar, b.kode_sewa, b.ft_byr, b.sttus_byr,
s.status_sewa, s.tgl_mulai, s.tgl_selesai
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
WHERE b.kode_bayar = ? AND b.jns_byr = 'dp'
LIMIT 1
FOR UPDATE",
        's',
        [$kodeBayar],
        'Penguncian verifikasi'
      );
      if ($dp === null) {
        throw new InvalidArgumentException('Data pembayaran tidak ditemukan.');
      }
      if ($dp['sttus_byr'] !== 'pending') {
        throw new InvalidArgumentException(
          'Pembayaran ini sudah diverifikasi sebelumnya.'
        );
      }
      if (trim((string) ($dp['ft_byr'] ?? '')) === '') {
        throw new InvalidArgumentException(
          'Penyewa belum mengunggah bukti pembayaran.'
        );
      }
      if ($dp['status_sewa'] !== 'diajukan') {
        throw new InvalidArgumentException(
          'Status transaksi bukan diajukan; pembayaran tidak dapat diverifikasi.'
        );
      }
      $kodeSewa = (string) $dp['kode_sewa'];
      if ($keputusan === 'valid') {
        // Cegah double booking: kendaraan yang sama pada periode beririsan
        // dengan transaksi lain yang sudah disetujui atau aktif.
        $bentrok = $this->ambilSatu(
          "SELECT s2.kode_sewa
FROM t_pilih p1
INNER JOIN t_pilih p2
  ON p2.no_plat = p1.no_plat AND p2.kode_sewa <> p1.kode_sewa
INNER JOIN t_sewa s2 ON s2.kode_sewa = p2.kode_sewa
WHERE p1.kode_sewa = ?
AND s2.status_sewa IN ('disetujui', 'aktif')
AND s2.tgl_mulai < ?
AND s2.tgl_selesai > ?
LIMIT 1",
          'sss',
          [$kodeSewa, $dp['tgl_selesai'], $dp['tgl_mulai']],
          'Pemeriksaan bentrok kendaraan'
        );
        if ($bentrok !== null) {
          throw new InvalidArgumentException(
            'Kendaraan bentrok dengan transaksi ' . $bentrok['kode_sewa']
            . ' yang sudah disetujui/aktif pada periode yang sama.'
          );
        }
      }
      $stmt = $this->db->prepare(
        "UPDATE t_bayar
SET sttus_byr = ?, tgl_wkt_ver = NOW()
WHERE kode_bayar = ? AND jns_byr = 'dp' AND sttus_byr = 'pending'"
      );
      if (!$stmt) {
        throw new RuntimeException('Pembaruan status pembayaran gagal disiapkan.');
      }
      $stmt->bind_param('ss', $keputusan, $kodeBayar);
      if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Pembaruan status pembayaran gagal dijalankan.');
      }
      $berubah = $stmt->affected_rows === 1;
      $stmt->close();
      if (!$berubah) {
        throw new RuntimeException('Status pembayaran tidak berubah.');
      }
      if ($keputusan === 'valid') {
        $stmt = $this->db->prepare(
          "UPDATE t_sewa SET status_sewa = 'disetujui'
WHERE kode_sewa = ? AND status_sewa = 'diajukan'"
        );
        if (!$stmt) {
          throw new RuntimeException('Persetujuan transaksi gagal disiapkan.');
        }
        $stmt->bind_param('s', $kodeSewa);
        if (!$stmt->execute()) {
          $stmt->close();
          throw new RuntimeException('Persetujuan transaksi gagal dijalankan.');
        }
        $berubah = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$berubah) {
          throw new RuntimeException('Status transaksi tidak berubah.');
        }
      }
      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollback();
      throw $e;
    }
  }

  /** Data minimum untuk menyajikan berkas bukti dengan pemeriksaan akses. */
  public function getBuktiAkses(string $kodeBayar): ?array
  {
    return $this->ambilSatu(
      "SELECT b.ft_byr, s.user_plg
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
WHERE b.kode_bayar = ?
LIMIT 1",
      's',
      [$kodeBayar],
      'Pengambilan akses bukti pembayaran'
    );
  }
}