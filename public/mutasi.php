<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'staff_pengadaan']);
$pageTitle = 'Mutasi Aset'; $active = 'mutasi';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aid = (int)$_POST['asset_id'];
    $a = q("SELECT a.*, l.name AS lokasi FROM assets a LEFT JOIN locations l ON l.id=a.location_id WHERE a.id=?", [$aid])->fetch();
    if (!$a) { flash_set('danger', 'Aset tidak ditemukan.'); header('Location: mutasi.php'); exit; }
    $toLoc = (int)($_POST['to_location_id'] ?: 0) ?: null;
    $toUsr = (int)($_POST['to_user_id'] ?: 0) ?: null;
    $newLoc = $toLoc ? q("SELECT name FROM locations WHERE id=?", [$toLoc])->fetchColumn() : '-';
    q("INSERT INTO asset_mutations (asset_id,from_unit,from_location,from_user,to_unit,to_location,to_user,reason,mutated_by) VALUES (?,?,?,?,?,?,?,?,?)",
      [$aid, $a['unit'], $a['lokasi'] ?? '-', user_name_of($a['user_id']),
       trim($_POST['to_unit'] ?: $a['unit']), $newLoc, $toUsr ? user_name_of($toUsr) : '-',
       trim($_POST['reason'] ?? ''), $_SESSION['user_id']]);
    q("UPDATE assets SET location_id=?, user_id=?, unit=?, status='DIPINDAHKAN' WHERE id=?",
      [$toLoc, $toUsr, trim($_POST['to_unit'] ?: $a['unit']), $aid]);
    flash_set('success', 'Mutasi aset tercatat.');
    header('Location: aset_detail.php?id=' . $aid); exit;
}

$assets = q("SELECT a.id, a.code, a.name, l.name AS lokasi, a.unit FROM assets a LEFT JOIN locations l ON l.id=a.location_id WHERE a.status NOT IN ('DIHAPUS') ORDER BY a.code")->fetchAll();
$rows   = q("SELECT m.*, a.code, a.name FROM asset_mutations m JOIN assets a ON a.id=m.asset_id ORDER BY m.id DESC LIMIT 100")->fetchAll();
$rooms  = q("SELECT id,name FROM locations WHERE type='ruang' ORDER BY name")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2">
<div class="card"><h3>🔄 Proses Mutasi Aset</h3>
<form method="post">
  <div class="form-group"><label>Aset *</label><select name="asset_id" required>
    <option value="">- Pilih Aset -</option>
    <?php foreach ($assets as $a): ?><option value="<?= (int)$a['id'] ?>" <?= ((int)($_GET['asset']??0)===(int)$a['id'])?'selected':'' ?>><?= e($a['code']) ?> — <?= e($a['name']) ?> (<?= e($a['lokasi'] ?? '-') ?>)</option><?php endforeach; ?>
  </select></div>
  <div class="form-row">
    <div class="form-group"><label>Ke Lokasi/Ruangan</label><select name="to_location_id"><option value="">- Tetap -</option>
      <?php foreach ($rooms as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Ke Unit</label><input type="text" name="to_unit" placeholder="Nama bidang/divisi tujuan"></div>
    <div class="form-group"><label>Ke Pengguna</label><select name="to_user_id"><option value="">- Tidak ada -</option>
      <?php foreach (all_users_for_select() as $uid=>$nm): ?><option value="<?= (int)$uid ?>"><?= e($nm) ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="form-group"><label>Alasan Mutasi</label><textarea name="reason" rows="2" required></textarea></div>
  <button class="btn btn-primary">Simpan Mutasi</button>
</form></div>
<div class="card"><h3>📜 Riwayat Mutasi Terakhir</h3>
<div class="table-wrap"><table class="tbl">
<tr><th>Tanggal</th><th>Aset</th><th>Dari</th><th>Ke</th><th>Oleh</th></tr>
<?php foreach ($rows as $m): ?>
<tr><td><?= tgl($m['mutated_at'], true) ?></td>
<td><a href="aset_detail.php?id=<?= (int)$m['asset_id'] ?>"><?= e($m['code']) ?></a><br><small><?= e($m['name']) ?></small></td>
<td><?= e($m['from_unit']) ?> · <?= e($m['from_location']) ?></td>
<td><?= e($m['to_unit']) ?> · <?= e($m['to_location']) ?></td>
<td><?= e(user_name_of($m['mutated_by'])) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5"><div class="empty-state">Belum ada mutasi.</div></td></tr><?php endif; ?>
</table></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
