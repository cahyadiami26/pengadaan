<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'staff_pengadaan', 'pimpinan']);
$pageTitle = 'Ajukan Penghapusan Aset'; $active = 'penghapusan';
$preAsset = (int)($_GET['asset'] ?? 0);
$assets = q("SELECT a.id,a.code,a.name,a.status,a.condition_status,l.name AS lokasi FROM assets a LEFT JOIN locations l ON l.id=a.location_id WHERE a.status NOT IN ('DIHAPUS') ORDER BY a.code")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:720px">
  <h3>🗑️ Form Pengajuan Penghapusan Aset</h3>
  <form method="post" action="penghapusan_proses.php">
    <div class="form-group"><label>Aset *</label><select name="asset_id" required>
      <option value="">- Pilih Aset -</option>
      <?php foreach ($assets as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $preAsset===(int)$a['id']?'selected':'' ?>><?= e($a['code']) ?> — <?= e($a['name']) ?> [<?= e($a['condition_status']) ?>/<?= e($a['status']) ?>] (<?= e($a['lokasi'] ?? '-') ?>)</option><?php endforeach; ?>
    </select></div>
    <div class="form-group"><label>Alasan Penghapusan *</label><select name="reason" required>
      <option>RUSAK BERAT</option><option>HILANG</option><option>TIDAK EKONOMIS</option><option>TIDAK DIPAKAI</option><option>LAINNYA</option></select></div>
    <div class="form-group"><label>Uraian / Deskripsi Kondisi</label><textarea name="description" rows="4" placeholder="Jelaskan kondisi aset dan dasar penghapusan..."></textarea></div>
    <div style="display:flex;gap:10px"><button class="btn btn-primary">Kirim Pengajuan</button><a class="btn btn-outline" href="penghapusan.php">Batal</a></div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
