<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$m = q("SELECT m.*, a.code AS aset_code, a.name AS aset_name, uc.name AS pencatat FROM maintenances m
        LEFT JOIN assets a ON a.id=m.asset_id LEFT JOIN user_cache uc ON uc.user_id=m.created_by WHERE m.id=?", [$id])->fetch();
if (!$m) { require __DIR__ . '/../includes/header.php'; echo '<div class="empty-state">Data tidak ditemukan.</div>'; require __DIR__ . '/../includes/footer.php'; exit; }
$pageTitle = 'Detail Pemeliharaan'; $active = 'pemeliharaan';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:820px"><h3>🛠️ <?= e($m['maint_no']) ?> <?= status_badge($m['status']) ?></h3>
<table class="tbl">
  <tr><th>Aset</th><td><a href="aset_detail.php?id=<?= (int)$m['asset_id'] ?>"><?= e($m['aset_code']) ?></a> — <?= e($m['aset_name']) ?></td></tr>
  <tr><th>Uraian Kerusakan</th><td><?= nl2br(e($m['damage_desc'])) ?></td></tr>
  <tr><th>Tindakan</th><td><?= nl2br(e($m['action_taken'] ?: '-')) ?></td></tr>
  <tr><th>Tanggal</th><td><?= tgl($m['maint_date']) ?></td></tr>
  <tr><th>Vendor / Teknisi</th><td><?= e($m['vendor'] ?: '-') ?> / <?= e($m['technician'] ?: '-') ?></td></tr>
  <tr><th>Biaya</th><td><?= rupiah($m['cost']) ?></td></tr>
  <tr><th>Catat oleh</th><td><?= avatar_html(q("SELECT avatar FROM user_cache WHERE user_id=?",[$m['created_by']])->fetchColumn()?:null, $m['pencatat']??'-',26) ?> <?= e($m['pencatat'] ?? '-') ?> · <?= tgl($m['created_at'], true) ?></td></tr>
</table>
<div style="margin-top:12px;display:flex;gap:8px">
<a class="btn btn-sm btn-teal" href="laporan_pdf.php?jenis=pemeliharaan&id=<?= (int)$id ?>">📄 Unduh PDF</a>
<a class="btn btn-sm btn-outline" href="pemeliharaan.php">← Kembali</a></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
