<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'staff_pengadaan']);
$pageTitle = 'Catat Pemeliharaan'; $active = 'pemeliharaan';
$preAsset = (int)($_GET['asset'] ?? 0); $preDmg = (int)($_GET['damage'] ?? 0);
$assets = q("SELECT id,code,name FROM assets WHERE status NOT IN ('DIHAPUS') ORDER BY code")->fetchAll();
$damages = q("SELECT d.id,d.report_no,d.damage_desc,d.asset_id,a.code AS aset_code FROM damage_reports d LEFT JOIN assets a ON a.id=d.asset_id WHERE d.status NOT IN ('SELESAI','DITOLAK') ORDER BY d.id DESC")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:760px"><h3>🛠️ Form Pencatatan Pemeliharaan</h3>
<form method="post" action="pemeliharaan_proses.php">
  <div class="form-group"><label>Laporan Kerusakan Terkait</label><select name="damage_report_id">
    <option value="">- Tidak terkait -</option>
    <?php foreach ($damages as $d): ?><option value="<?= (int)$d['id'] ?>" data-asset="<?= (int)$d['asset_id'] ?>" <?= $preDmg===(int)$d['id']?'selected':'' ?>><?= e($d['report_no']) ?> — <?= e($d['aset_code'] ?? '') ?> <?= e(mb_strimwidth($d['damage_desc'],0,50,'…')) ?></option><?php endforeach; ?>
  </select></div>
  <div class="form-group"><label>Aset *</label><select name="asset_id" id="fAsset" required>
    <option value="">- Pilih Aset -</option>
    <?php foreach ($assets as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $preAsset===(int)$a['id']?'selected':'' ?>><?= e($a['code']) ?> — <?= e($a['name']) ?></option><?php endforeach; ?>
  </select></div>
  <script>document.querySelector('[name=damage_report_id]').addEventListener('change',e=>{const a=e.selectedOptions[0]?.dataset.asset; if(a) document.getElementById('fAsset').value=a;});</script>
  <div class="form-group"><label>Uraian Kerusakan / Pekerjaan</label><textarea name="damage_desc" rows="3"></textarea></div>
  <div class="form-row">
    <div class="form-group"><label>Tanggal Pengerjaan *</label><input type="date" name="maint_date" value="<?= date('Y-m-d') ?>" required></div>
    <div class="form-group"><label>Vendor / Bengkel</label><input type="text" name="vendor"></div>
    <div class="form-group"><label>Teknisi</label><input type="text" name="technician"></div>
    <div class="form-group"><label>Biaya (Rp)</label><input type="number" name="cost" min="0" step="1000" value="0"></div>
    <div class="form-group"><label>Status</label><select name="status"><option>JANJI</option><option>PENGERJAAN</option><option>SELESAI</option></select></div>
  </div>
  <div class="form-group"><label>Tindakan yang Dilakukan</label><textarea name="action_taken" rows="3"></textarea></div>
  <div style="display:flex;gap:10px"><button class="btn btn-primary">Simpan</button><a class="btn btn-outline" href="pemeliharaan.php">Batal</a></div>
</form></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
