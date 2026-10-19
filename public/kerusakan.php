<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Laporan Kerusakan'; $active = 'kerusakan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['admin', 'staff_pengadaan']);
    $id = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    if ($act === 'update_status') {
        $st = in_array($_POST['status'], ['DILAPORKAN','DIAGNOSIS','PERBAIKAN','SELESAI','DITOLAK']) ? $_POST['status'] : 'DILAPORKAN';
        q("UPDATE damage_reports SET status=? WHERE id=?", [$st, $id]);
        if ($st === 'SELESAI') {
            $d = q("SELECT * FROM damage_reports WHERE id=?", [$id])->fetch();
            if ($d && $d['asset_id']) q("UPDATE assets SET condition_status='BAIK', status='AKTIF' WHERE id=?", [$d['asset_id']]);
        }
        flash_set('success', 'Status laporan diperbarui.');
    }
    header('Location: kerusakan.php'); exit;
}

$fStatus = $_GET['status'] ?? '';
$sql = "SELECT dr.*, a.code AS aset_code, a.name AS aset_name, l.name AS lokasi, uc.name AS pelapor
        FROM damage_reports dr LEFT JOIN assets a ON a.id=dr.asset_id
        LEFT JOIN locations l ON l.id=dr.location_id LEFT JOIN user_cache uc ON uc.user_id=dr.reported_by"
     . ($fStatus ? " WHERE dr.status=?" : "") . " ORDER BY dr.id DESC";
$stmt = db()->prepare($sql); $stmt->execute($fStatus ? [$fStatus] : []); $rows = $stmt->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><h3>⚠️ Laporan Kerusakan Aset & Inventaris
  <a class="btn btn-sm btn-primary" href="kerusakan_form.php">➕ Buat Laporan</a></h3>
<form method="get" class="filters"><div class="form-group"><label>Status</label><select name="status" onchange="this.form.submit()">
<option value="">Semua</option><?php foreach (['DILAPORKAN','DIAGNOSIS','PERBAIKAN','SELESAI','DITOLAK'] as $s): ?><option <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
</select></div></form>
<div class="table-wrap"><table class="tbl">
<tr><th>No. Laporan</th><th>Aset</th><th>Lokasi</th><th>Kerusakan</th><th>Pelapor</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr>
<?php foreach ($rows as $d): ?>
<tr>
  <td><b><a href="kerusakan_detail.php?id=<?= (int)$d['id'] ?>"><?= e($d['report_no']) ?></a></b></td>
  <td><?= $d['aset_code'] ? '<a href="aset_detail.php?id='.(int)$d['asset_id'].'">'.e($d['aset_code']).'</a><br><small>'.e($d['aset_name']).'</small>' : e($d['item_name']) ?></td>
  <td><?= e($d['lokasi'] ?? '-') ?></td><td><?= e($d['damage_desc']) ?></td>
  <td><?= avatar_html(q("SELECT avatar FROM user_cache WHERE user_id=?",[$d['reported_by']])->fetchColumn()?:null, $d['pelapor'] ?? '-', 24) ?> <?= e($d['pelapor'] ?? '-') ?></td>
  <td><?= tgl($d['report_date']) ?></td><td><?= status_badge($d['status']) ?></td>
  <td style="white-space:nowrap">
    <?php if (is_in_role(['admin','staff_pengadaan'])): ?>
    <form method="post" style="display:flex;gap:4px">
      <input type="hidden" name="action" value="update_status"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
      <select name="status" style="width:120px"><?php foreach (['DILAPORKAN','DIAGNOSIS','PERBAIKAN','SELESAI','DITOLAK'] as $s): ?><option <?= $d['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
      <button class="btn btn-sm btn-outline">Simpan</button>
    </form>
    <?php else: ?>—<?php endif; ?>
    <?php if ($d['asset_id']): ?><a class="btn btn-sm btn-teal" href="pemeliharaan_form.php?asset=<?= (int)$d['asset_id'] ?>&damage=<?= (int)$d['id'] ?>">🛠️ Perbaiki</a><?php endif; ?>
  </td>
</tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8"><div class="empty-state"><div class="big">⚠️</div>Belum ada laporan kerusakan.</div></td></tr><?php endif; ?>
</table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
