<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$d = q("SELECT dr.*, a.code AS aset_code, a.name AS aset_name, l.name AS lokasi, uc.name AS pelapor
        FROM damage_reports dr LEFT JOIN assets a ON a.id=dr.asset_id
        LEFT JOIN locations l ON l.id=dr.location_id LEFT JOIN user_cache uc ON uc.user_id=dr.reported_by WHERE dr.id=?", [$id])->fetch();
if (!$d) { require __DIR__ . '/../includes/header.php'; echo '<div class="empty-state">Data tidak ditemukan.</div>'; require __DIR__ . '/../includes/footer.php'; exit; }
$maints = q("SELECT * FROM maintenances WHERE damage_report_id=? OR asset_id=?", [$id, $d['asset_id'] ?? 0])->fetchAll();
$pageTitle = 'Detail Kerusakan'; $active = 'kerusakan';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:820px"><h3>⚠️ <?= e($d['report_no']) ?> <?= status_badge($d['status']) ?></h3>
<table class="tbl">
  <tr><th>Aset</th><td><?= $d['aset_code'] ? '<a href="aset_detail.php?id='. (int)$d['asset_id'].'">'.e($d['aset_code']).' — '.e($d['aset_name']).'</a>' : e($d['item_name']) ?></td></tr>
  <tr><th>Lokasi</th><td><?= e($d['lokasi'] ?? '-') ?></td></tr>
  <tr><th>Jenis</th><td><?= e($d['damage_type'] ?? '-') ?></td></tr>
  <tr><th>Deskripsi</th><td><?= nl2br(e($d['damage_desc'])) ?></td></tr>
  <tr><th>Pelapor</th><td><?= avatar_html(q("SELECT avatar FROM user_cache WHERE user_id=?",[$d['reported_by']])->fetchColumn()?:null, $d['pelapor']??'-', 26) ?> <?= e($d['pelapor'] ?? '-') ?> · <?= tgl($d['report_date'], true) ?></td></tr>
</table>
<?php if ($maints): ?>
<h3 style="margin-top:16px">🛠️ Pemeliharaan Terkait</h3>
<table class="tbl"><tr><th>No</th><th>Tanggal</th><th>Status</th><th>Biaya</th></tr>
<?php foreach ($maints as $m): ?><tr><td><a href="pemeliharaan_detail.php?id=<?= (int)$m['id'] ?>"><?= e($m['maint_no']) ?></a></td><td><?= tgl($m['maint_date']) ?></td><td><?= status_badge($m['status']) ?></td><td class="num"><?= rupiah($m['cost']) ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>
<div style="margin-top:12px;display:flex;gap:8px">
<?php if ($d['asset_id']): ?><a class="btn btn-sm btn-teal" href="pemeliharaan_form.php?asset=<?= (int)$d['asset_id'] ?>&damage=<?= (int)$id ?>">🛠️ Catat Perbaikan</a><?php endif; ?>
<a class="btn btn-sm btn-outline" href="kerusakan.php">← Kembali</a></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
