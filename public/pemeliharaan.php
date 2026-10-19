<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Pemeliharaan & Perbaikan'; $active = 'pemeliharaan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['admin', 'staff_pengadaan']);
    $id = (int)$_POST['id'];
    if (($_POST['action'] ?? '') === 'status') {
        $st = in_array($_POST['status'], ['JANJI','PENGERJAAN','SELESAI','BATAL']) ? $_POST['status'] : 'JANJI';
        q("UPDATE maintenances SET status=? WHERE id=?", [$st, $id]);
        if ($st === 'SELESAI') {
            $m = q("SELECT * FROM maintenances WHERE id=?", [$id])->fetch();
            if ($m && $m['asset_id']) q("UPDATE assets SET condition_status='BAIK', status='AKTIF' WHERE id=?", [$m['asset_id']]);
        }
        flash_set('success', 'Status pemeliharaan diperbarui.');
    }
    header('Location: pemeliharaan.php'); exit;
}

$rows = q("SELECT m.*, a.code AS aset_code, a.name AS aset_name, uc.name AS pencatat
           FROM maintenances m LEFT JOIN assets a ON a.id=m.asset_id
           LEFT JOIN user_cache uc ON uc.user_id=m.created_by ORDER BY m.id DESC")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><h3>🛠️ Pemeliharaan &amp; Perbaikan Inventaris
  <?php if (is_in_role(['admin','staff_pengadaan'])): ?><a class="btn btn-sm btn-primary" href="pemeliharaan_form.php">➕ Catat Pemeliharaan</a><?php endif; ?></h3>
<div class="table-wrap"><table class="tbl">
<tr><th>No. PM</th><th>Aset</th><th>Kerusakan</th><th>Tanggal</th><th>Vendor/Teknisi</th><th class="num">Biaya</th><th>Status</th><th>Aksi</th></tr>
<?php foreach ($rows as $m): ?>
<tr>
  <td><b><a href="pemeliharaan_detail.php?id=<?= (int)$m['id'] ?>"><?= e($m['maint_no']) ?></a></b></td>
  <td><?= $m['aset_code'] ? '<a href="aset_detail.php?id='.(int)$m['asset_id'].'">'.e($m['aset_code']).'</a><br><small>'.e($m['aset_name']).'</small>' : '-' ?></td>
  <td><?= e($m['damage_desc']) ?></td><td><?= tgl($m['maint_date']) ?></td>
  <td><?= e($m['vendor'] ?: '-') ?><br><small class="muted"><?= e($m['technician'] ?: '') ?></small></td>
  <td class="num"><?= rupiah($m['cost']) ?></td><td><?= status_badge($m['status']) ?></td>
  <td style="white-space:nowrap">
    <?php if (is_in_role(['admin','staff_pengadaan'])): ?>
    <form method="post" style="display:flex;gap:4px">
      <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <select name="status" style="width:110px"><?php foreach (['JANJI','PENGERJAAN','SELESAI','BATAL'] as $s): ?><option <?= $m['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
      <button class="btn btn-sm btn-outline">Simpan</button>
    </form>
    <?php else: ?>—<?php endif; ?>
  </td>
</tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8"><div class="empty-state"><div class="big">🛠️</div>Belum ada catatan pemeliharaan.</div></td></tr><?php endif; ?>
</table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
