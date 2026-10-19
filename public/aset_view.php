<?php
/** Halaman publik hasil scan QR (tetap butuh login SSO untuk detail). Menampilkan ringkasan aset. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$code = trim($_GET['code'] ?? $_GET['q'] ?? '');
$a = $code ? q("SELECT a.*, l.name AS lokasi FROM assets a LEFT JOIN locations l ON l.id=a.location_id WHERE a.code=?", [$code])->fetch() : null;
$pageTitle = 'Informasi Aset'; $active = 'inventaris';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:640px;margin:0 auto">
  <?php if (!$a): ?>
    <div class="empty-state"><div class="big">❓</div>Aset dengan kode <b><?= e($code) ?></b> tidak ditemukan.</div>
  <?php else: ?>
    <h3>🏷️ <?= e($a['name']) ?> <?= status_badge($a['status']) ?></h3>
    <table class="tbl">
      <tr><th>Kode</th><td><?= e($a['code']) ?></td></tr>
      <tr><th>Kategori</th><td><?= e(ucfirst($a['category'])) ?><?= $a['sub_category']?' · '.e($a['sub_category']):'' ?></td></tr>
      <tr><th>Tanggal</th><td><?= tgl($a['purchase_date']) ?></td></tr>
      <tr><th>Nilai</th><td><?= rupiah($a['value']) ?></td></tr>
      <tr><th>Lokasi</th><td><?= e($a['lokasi'] ?? '-') ?></td></tr>
      <tr><th>Pengguna</th><td><?= e(user_name_of($a['user_id'])) ?></td></tr>
      <tr><th>Unit</th><td><?= e($a['unit'] ?? '-') ?></td></tr>
      <tr><th>Kondisi</th><td><?= status_badge($a['condition_status']) ?></td></tr>
    </table>
    <div style="margin-top:12px"><a class="btn btn-primary btn-sm" href="aset_detail.php?id=<?= (int)$a['id'] ?>">Buka Detail Lengkap</a></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
