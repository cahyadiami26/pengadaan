<?php
/**
 * Modul Perawatan Kendaraan: pengingat jadwal servis (KM), riwayat servis,
 * dan total biaya perawatan per kendaraan.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'staff_pengadaan', 'pimpinan']);

$vehicles = q("SELECT v.*,
  (SELECT COUNT(*) FROM vehicle_services s WHERE s.vehicle_id=v.id) AS svc_count,
  (SELECT IFNULL(SUM(s.cost),0) FROM vehicle_services s WHERE s.vehicle_id=v.id) AS total_cost,
  (SELECT MAX(s.service_date) FROM vehicle_services s WHERE s.vehicle_id=v.id) AS last_service_date
  FROM vehicles v ORDER BY v.tnkb")->fetchAll();

// Ringkasan pengingat
$nDue = 0; $nSoon = 0;
foreach ($vehicles as $v) {
    if (!$v['next_service_km']) continue;
    $remain = (int)$v['next_service_km'] - (int)$v['last_km'];
    if ($remain <= 0) $nDue++; elseif ($remain <= 1500) $nSoon++;
}

$pageTitle = 'Perawatan Kendaraan'; $active = 'perawatan_kendaraan';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-3">
  <div class="card stat-card"><span class="stat-icon" style="background:#fee2e2">🔧</span><div><b><?= $nDue ?></b><br><small class="muted">Sudah melewati jadwal servis</small></div></div>
  <div class="card stat-card"><span class="stat-icon" style="background:#fef3c7">⚠️</span><div><b><?= $nSoon ?></b><br><small class="muted">Mendekati jadwal (&le;1.500 km)</small></div></div>
  <div class="card stat-card"><span class="stat-icon" style="background:#dcfce7">💰</span><div><b><?= rupiah(array_sum(array_map(fn($v) => (float)$v['total_cost'], $vehicles))) ?></b><br><small class="muted">Total biaya perawatan</small></div></div>
</div>

<div class="card">
  <h3>🔧 Jadwal &amp; Riwayat Perawatan Kendaraan</h3>
  <div class="table-wrap"><table class="tbl">
    <tr><th>Kendaraan</th><th>KM Terakhir</th><th>Jadwal Servis Berikutnya</th><th>Status</th><th class="num">Riwayat Servis</th><th>Tgl Servis Terakhir</th><th class="num">Total Biaya</th><th>Aksi</th></tr>
    <?php foreach ($vehicles as $v):
      $remain = $v['next_service_km'] ? (int)$v['next_service_km'] - (int)$v['last_km'] : null;
      if ($remain === null) $stBadge = '<span class="badge badge-secondary">INTERVAL BELUM DISET</span>';
      elseif ($remain <= 0) $stBadge = '<span class="badge badge-danger">HARUS SERVIS</span>';
      elseif ($remain <= 1500) $stBadge = '<span class="badge badge-warning">⚠ MENDEKATI JADWAL</span>';
      else $stBadge = '<span class="badge badge-success">AMAN</span>';
    ?>
    <tr>
      <td><b><?= e($v['tnkb']) ?></b><br><small class="muted"><?= e($v['brand_model'] ?? '') ?></small></td>
      <td><?= number_format((int)$v['last_km'], 0, ',', '.') ?> km</td>
      <td><?= $v['next_service_km'] ? number_format((int)$v['next_service_km'], 0, ',', '.') . ' km' : '-' ?><br>
        <small class="muted"><?= $remain !== null ? 'sisa ' . number_format(max(0, $remain), 0, ',', '.') . ' km' : '' ?></small></td>
      <td><?= $stBadge ?></td>
      <td class="num"><?= (int)$v['svc_count'] ?></td>
      <td><?= tgl($v['last_service_date']) ?></td>
      <td class="num"><?= rupiah($v['total_cost']) ?></td>
      <td><a class="btn btn-sm btn-outline" href="kendaraan_detail.php?id=<?= (int)$v['id'] ?>">Kelola</a></td>
    </tr>
    <?php endforeach; if (!$vehicles): ?>
    <tr><td colspan="8"><div class="empty-state"><div class="big">🚗</div>Belum ada data kendaraan.</div></td></tr><?php endif; ?>
  </table></div>
  <p class="muted" style="margin-top:8px">Pengingat dihitung otomatis dari KM terakhir vs jadwal servis berikutnya. Catat servis baru melalui halaman detail kendaraan.</p>
</div>

<div class="card">
  <h3>📄 Riwayat Servis Terbaru</h3>
  <div class="table-wrap"><table class="tbl">
    <tr><th>Tanggal</th><th>Kendaraan</th><th>Jenis</th><th class="num">KM</th><th>Vendor/Bengkel</th><th class="num">Biaya</th><th>Catatan</th></tr>
    <?php $svcs = q("SELECT s.*, v.tnkb FROM vehicle_services s JOIN vehicles v ON v.id=s.vehicle_id ORDER BY s.service_date DESC, s.id DESC LIMIT 30")->fetchAll();
    foreach ($svcs as $s): ?>
    <tr>
      <td><?= tgl($s['service_date']) ?></td><td><b><?= e($s['tnkb']) ?></b></td>
      <td><?= e($s['service_type'] ?? '-') ?></td>
      <td class="num"><?= number_format((int)$s['km_at_service'], 0, ',', '.') ?></td>
      <td><?= e($s['vendor'] ?? '-') ?></td>
      <td class="num"><?= rupiah($s['cost']) ?></td>
      <td><small><?= e($s['note'] ?? '') ?></small></td>
    </tr>
    <?php endforeach; if (!$svcs): ?><tr><td colspan="7" class="muted center">Belum ada riwayat servis.</td></tr><?php endif; ?>
  </table></div>
  <div style="margin-top:10px"><a class="btn btn-teal" href="laporan_pdf.php?jenis=kendaraan_servis" target="_blank">📄 Cetak Rekap Servis &amp; Biaya (PDF)</a></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
