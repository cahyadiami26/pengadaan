<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$d = q("SELECT d.*, a.code,a.name,a.value,a.purchase_date,a.category,l.name AS lokasi FROM asset_disposals d
        JOIN assets a ON a.id=d.asset_id LEFT JOIN locations l ON l.id=a.location_id WHERE d.id=?", [$id])->fetch();
if (!$d) { require __DIR__ . '/../includes/header.php'; echo '<div class="empty-state">Data tidak ditemukan.</div>'; require __DIR__ . '/../includes/footer.php'; exit; }
$pageTitle = 'Detail Penghapusan'; $active = 'penghapusan';
require __DIR__ . '/../includes/header.php';
$steps = ['PENGAJUAN','VERIFIKASI','PERSETUJUAN','BERITA_ACARA','DIHAPUS'];
$cur = array_search($d['status'], $steps);
?>
<div class="card" style="max-width:820px">
  <h3>🗑️ <?= e($d['disposal_no']) ?> <?= status_badge($d['status']) ?></h3>
  <div class="workflow"><?php foreach ($steps as $i=>$s): ?>
    <span class="wf-step <?= $i<$cur?'done':($i===$cur?'current':'') ?>"><?= $i+1 ?>. <?= e(str_replace('_',' ',$s)) ?></span><?php endforeach; ?></div>
  <table class="tbl">
    <tr><th>Aset</th><td><b><?= e($d['code']) ?></b> — <?= e($d['name']) ?> <a class="btn btn-sm btn-outline" href="aset_detail.php?id=<?= (int)$d['asset_id'] ?>">lihat</a></td></tr>
    <tr><th>Kategori / Perolehan / Nilai</th><td><?= e(ucfirst($d['category'])) ?> · <?= tgl($d['purchase_date']) ?> · <?= rupiah($d['value']) ?></td></tr>
    <tr><th>Lokasi</th><td><?= e($d['lokasi'] ?? '-') ?></td></tr>
    <tr><th>Alasan</th><td><?= e($d['reason']) ?></td></tr>
    <tr><th>Uraian</th><td><?= nl2br(e($d['description'] ?: '-')) ?></td></tr>
    <tr><th>Pengaju</th><td><?= avatar_html(q("SELECT avatar FROM user_cache WHERE user_id=?",[$d['created_by']])->fetchColumn()?:null, user_name_of($d['created_by']), 26) ?> <?= e(user_name_of($d['created_by'])) ?> · <?= tgl($d['created_at'], true) ?></td></tr>
    <tr><th>Verifikator</th><td><?= $d['verified_by'] ? e(user_name_of($d['verified_by'])) : '-' ?></td></tr>
    <tr><th>Persetujuan</th><td><?= $d['approved_by'] ? e(user_name_of($d['approved_by'])) : '-' ?></td></tr>
    <tr><th>Berita Acara</th><td><?= e($d['ba_no'] ?: '-') ?><?= $d['ba_date'] ? ' tgl ' . tgl($d['ba_date']) : '' ?></td></tr>
  </table>
  <div style="margin-top:12px;display:flex;gap:8px">
    <a class="btn btn-sm btn-teal" href="laporan_pdf.php?jenis=penghapusan&id=<?= (int)$id ?>">📄 Unduh PDF Berita Acara</a>
    <a class="btn btn-sm btn-outline" href="penghapusan.php">← Kembali</a>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
