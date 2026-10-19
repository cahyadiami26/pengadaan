<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$v = q("SELECT * FROM vehicles WHERE id=?", [$id])->fetch();
if (!$v) { require __DIR__ . '/../includes/header.php'; echo '<div class="empty-state">Kendaraan tidak ditemukan.</div>'; require __DIR__ . '/../includes/footer.php'; exit; }
$svcs = q("SELECT * FROM vehicle_services WHERE vehicle_id=? ORDER BY service_date DESC, id DESC", [$id])->fetchAll();
$logs = q("SELECT * FROM vehicle_usage_logs WHERE vehicle_id=? ORDER BY id DESC LIMIT 30", [$id])->fetchAll();
$pageTitle = 'Kendaraan ' . $v['tnkb']; $active = 'kendaraan';
require __DIR__ . '/../includes/header.php';
$due = $v['next_service_km'] && $v['last_km'] >= $v['next_service_km'] - 1000;
?>
<?php if ($due): ?><div class="alert alert-warning">⏰ Kilometer kendaraan mendekati jadwal servis berikutnya (<?= number_format((int)$v['next_service_km'],0,',','.') ?> km).</div><?php endif; ?>
<div class="grid grid-2">
<div class="card"><h3>🚗 <?= e($v['tnkb']) ?> <?= status_badge($v['status']) ?></h3>
<table class="tbl">
  <tr><th>Kendaraan</th><td><?= e($v['brand_model']) ?> (<?= e($v['year'] ?: '-') ?>)</td></tr>
  <tr><th>No. Rangka / Mesin</th><td><?= e($v['frame_no'] ?: '-') ?> / <?= e($v['engine_no'] ?: '-') ?></td></tr>
  <tr><th>Kondisi</th><td><?= status_badge($v['condition_status']) ?></td></tr>
  <tr><th>Lokasi / PIC</th><td><?= e($v['location'] ?: '-') ?> · <?= e($v['pic'] ?: '-') ?></td></tr>
  <tr><th>KM Terakhir</th><td><?= number_format((int)$v['last_km'],0,',','.') ?> km — servis berikutnya <?= number_format((int)($v['next_service_km'] ?: 0),0,',','.') ?> km</td></tr>
</table>
<form method="post" class="filters" style="margin-top:12px">
  <input type="hidden" name="action" value="log_km"><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="form-group"><label>Update Kilometer Saat Ini</label><input type="number" name="new_km" min="0" required></div>
  <button class="btn btn-sm btn-primary">💾 Simpan KM</button>
</form></div>
<div class="card"><h3>🔧 Catat Servis / Perbaikan</h3>
<form method="post">
  <input type="hidden" name="action" value="service"><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="form-row">
    <div class="form-group"><label>Tanggal *</label><input type="date" name="service_date" value="<?= date('Y-m-d') ?>" required></div>
    <div class="form-group"><label>Jenis Servis *</label><input type="text" name="service_type" required placeholder="Ganti Oli / Tune Up / Kanvas Rem"></div>
    <div class="form-group"><label>KM Saat Servis</label><input type="number" name="km_at_service" min="0" value="<?= (int)$v['last_km'] ?>"></div>
    <div class="form-group"><label>Vendor/Bengkel</label><input type="text" name="vendor"></div>
    <div class="form-group"><label>Biaya (Rp)</label><input type="number" name="cost" min="0" step="1000" value="0"></div>
  </div>
  <div class="form-group"><label>Catatan</label><input type="text" name="note"></div>
  <label style="font-size:13px"><input type="checkbox" name="mark_done" value="1" checked> Tandai servis selesai (status kembali TERSEDIA)</label><br>
  <button class="btn btn-primary" style="margin-top:8px">Simpan Servis</button>
</form></div>
</div>
<div class="card"><h3>📜 Riwayat Servis &amp; Perbaikan</h3>
<div class="table-wrap"><table class="tbl">
<tr><th>Tanggal</th><th>Jenis</th><th class="num">KM</th><th>Vendor</th><th class="num">Biaya</th><th>Catatan</th></tr>
<?php foreach ($svcs as $s): ?>
<tr><td><?= tgl($s['service_date']) ?></td><td><?= e($s['service_type']) ?></td><td class="num"><?= number_format((int)$s['km_at_service'],0,',','.') ?></td>
<td><?= e($s['vendor'] ?: '-') ?></td><td class="num"><?= rupiah($s['cost']) ?></td><td><?= e($s['note'] ?: '-') ?></td></tr>
<?php endforeach; if (!$svcs): ?><tr><td colspan="6"><div class="empty-state">Belum ada riwayat servis.</div></td></tr><?php endif; ?>
<tr><th colspan="4">Total Biaya Servis</th><th class="num"><?= rupiah(array_sum(array_map(fn($s)=>(float)$s['cost'],$svcs))) ?></th><th></th></tr>
</table></div></div>
<?php if ($logs): ?>
<div class="card"><h3>🕒 Pemakaian Terakhir</h3><div class="table-wrap"><table class="tbl">
<tr><th>Tanggal Pinjam</th><th>Tanggal Kembali</th><th>Tujuan</th><th>KM Awal</th><th>KM Akhir</th><th>Status</th></tr>
<?php foreach ($logs as $l): ?><tr><td><?= tgl($l['borrow_date'], true) ?></td><td><?= $l['return_date'] ? tgl($l['return_date'], true) : '-' ?></td>
<td><?= e($l['destination']) ?></td><td><?= (int)$l['start_km'] ?></td><td><?= $l['end_km'] !== null ? (int)$l['end_km'] : '-' ?></td><td><?= status_badge($l['status']) ?></td></tr><?php endforeach; ?>
</table></div></div>
<?php endif; ?>
<div style="margin-bottom:20px"><a class="btn btn-sm btn-teal" href="laporan_pdf.php?jenis=kendaraan&id=<?= (int)$id ?>">📄 Export PDF Riwayat Servis</a> <a class="btn btn-sm btn-outline" href="kendaraan.php">← Kembali</a></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
