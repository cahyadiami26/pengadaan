<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Tiket Keluhan Fasilitas'; $active = 'fasilitas';
$facilities = q("SELECT f.id,f.name,f.type,l.name AS lokasi FROM facilities f LEFT JOIN locations l ON l.id=f.location_id ORDER BY f.name")->fetchAll();
$rooms = q("SELECT id,name FROM locations WHERE type='ruang' ORDER BY name")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:720px"><h3>🎫 Buat Tiket Keluhan Fasilitas</h3>
<form method="post" action="fasilitas_tiket_proses.php">
  <input type="hidden" name="create" value="1">
  <div class="form-group"><label>Fasilitas Terkait</label><select name="facility_id">
    <option value="">- Pilih Fasilitas -</option>
    <?php foreach ($facilities as $f): ?><option value="<?= (int)$f['id'] ?>"><?= e($f['name']) ?> (<?= e($f['type']) ?><?= $f['lokasi'] ? ' · '.$f['lokasi'] : '' ?>)</option><?php endforeach; ?>
  </select></div>
  <div class="form-group"><label>Lokasi/Ruangan</label><select name="location_id"><option value="">- Pilih -</option>
    <?php foreach ($rooms as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
  <div class="form-group"><label>Jenis Masalah</label><select name="problem_type">
    <option>AC</option><option>Proyektor</option><option>Kebersihan</option><option>Sanitasi/Toilet</option><option>Listrik/Pencahayaan</option><option>Furnitur</option><option>Lainnya</option></select></div>
  <div class="form-group"><label>Deskripsi Masalah *</label><textarea name="problem_desc" rows="4" required></textarea></div>
  <div class="form-group"><label>Prioritas</label><select name="priority"><option>Rendah</option><option selected>Normal</option><option>Tinggi</option><option>Segera</option></select></div>
  <div style="display:flex;gap:10px"><button class="btn btn-primary">Kirim Tiket</button><a class="btn btn-outline" href="fasilitas.php">Batal</a></div>
</form></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
