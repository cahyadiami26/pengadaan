<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$t = q("SELECT t.*, l.name AS lokasi, f.name AS fname, uc.name AS pelapor FROM facility_tickets t
        LEFT JOIN locations l ON l.id=t.location_id LEFT JOIN facilities f ON f.id=t.facility_id
        LEFT JOIN user_cache uc ON uc.user_id=t.reported_by WHERE t.id=?", [$id])->fetch();
if (!$t) { require __DIR__ . '/../includes/header.php'; echo '<div class="empty-state">Tiket tidak ditemukan.</div>'; require __DIR__ . '/../includes/footer.php'; exit; }
$pageTitle = 'Tiket ' . $t['ticket_no']; $active = 'fasilitas';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:760px"><h3>🎫 <?= e($t['ticket_no']) ?> <?= status_badge($t['status']) ?> <?= status_badge($t['priority']) ?></h3>
<table class="tbl">
  <tr><th>Masalah</th><td><?= e($t['problem_type']) ?> — <?= nl2br(e($t['problem_desc'])) ?></td></tr>
  <tr><th>Lokasi</th><td><?= e($t['lokasi'] ?? '-') ?></td></tr>
  <tr><th>Pelapor</th><td><?= avatar_html(q("SELECT avatar FROM user_cache WHERE user_id=?",[$t['reported_by']])->fetchColumn()?:null, $t['pelapor']??'-',26) ?> <?= e($t['pelapor'] ?? '-') ?> · <?= tgl($t['created_at'], true) ?></td></tr>
  <tr><th>Fasilitas</th><td><?= e($t['fname'] ?? '-') ?></td></tr>
  <tr><th>Ditangani Oleh</th><td><?= e($t['assigned_to'] ?? '-') ?></td></tr>
</table>
<a class="btn btn-sm btn-outline" style="margin-top:10px" href="fasilitas.php">← Kembali</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
