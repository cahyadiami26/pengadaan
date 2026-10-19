<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Form Laporan Kerusakan'; $active = 'kerusakan';
$preAsset = (int)($_GET['asset'] ?? 0);
$assets = q("SELECT a.id,a.code,a.name,l.name AS lokasi FROM assets a LEFT JOIN locations l ON l.id=a.location_id WHERE a.status NOT IN ('DIHAPUS') ORDER BY a.code")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:760px"><h3>⚠️ Lapor Kerusakan</h3>
<form method="post" action="kerusakan_proses.php">
  <div class="form-group"><label>Aset Terkait</label><select name="asset_id">
    <option value="">- Pilih Aset -</option>
    <?php foreach ($assets as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $preAsset===(int)$a['id']?'selected':'' ?>><?= e($a['code']) ?> — <?= e($a['name']) ?> (<?= e($a['lokasi'] ?? '-') ?>)</option><?php endforeach; ?>
  </select></div>
  <div class="form-group"><label>Nama Barang (jika non-aset)</label><input type="text" name="item_name"></div>
  <div class="form-group"><label>Lokasi Kejadian</label><input type="text" name="location_text" placeholder="Nama ruangan/tempat"></div>
  <div class="form-group"><label>Jenis Kerusakan</label><select name="damage_type">
    <option>Fisik</option><option>Fungsi</option><option>Listrik</option><option>Software</option><option>Kehilangan Komponen</option><option>Lainnya</option></select></div>
  <div class="form-group"><label>Deskripsi Kerusakan *</label><textarea name="damage_desc" rows="5" required placeholder="Jelaskan kronologi & gejala kerusakan..."></textarea></div>
  <div style="display:flex;gap:10px"><button class="btn btn-primary">Kirim Laporan</button><a class="btn btn-outline" href="kerusakan.php">Batal</a></div>
</form></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
