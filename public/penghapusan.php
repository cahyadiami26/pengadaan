<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Penghapusan Aset'; $active = 'penghapusan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? ''; $id = (int)$_POST['id'];
    $d = q("SELECT * FROM asset_disposals WHERE id=?", [$id])->fetch();
    if (!$d) { flash_set('danger','Data tidak ditemukan.'); header('Location: penghapusan.php'); exit; }
    $role = $_SESSION['app_role'];
    if ($act === 'verify' && in_array($role, ['admin','staff_pengadaan','kepala_bidang'])) {
        q("UPDATE asset_disposals SET status='VERIFIKASI', verified_by=? WHERE id=?", [$_SESSION['user_id'], $id]);
        flash_set('success', 'Pengajuan diverifikasi. Menunggu persetujuan pimpinan.');
    } elseif ($act === 'approve' && in_array($role, ['admin','pimpinan'])) {
        q("UPDATE asset_disposals SET status='PERSETUJUAN', approved_by=? WHERE id=?", [$_SESSION['user_id'], $id]);
        flash_set('success', 'Penghapusan disetujui. Buat Berita Acara untuk menyelesaikan.');
    } elseif ($act === 'ba' && in_array($role, ['admin','staff_pengadaan'])) {
        $no = trim($_POST['ba_no'] ?? ''); $date = $_POST['ba_date'] ?: date('Y-m-d');
        q("UPDATE asset_disposals SET status='BERITA_ACARA', ba_no=?, ba_date=? WHERE id=?", [$no, $date, $id]);
        flash_set('success', 'Berita Acara dicatat. Konfirmasi penghapusan untuk finalisasi.');
    } elseif ($act === 'finalize' && in_array($role, ['admin','staff_pengadaan'])) {
        q("UPDATE asset_disposals SET status='DIHAPUS' WHERE id=?", [$id]);
        q("UPDATE assets SET status='DIHAPUS' WHERE id=?", [$d['asset_id']]);
        flash_set('success', 'Aset resmi dihapus dari daftar inventaris.');
    } elseif ($act === 'reject' && in_array($role, ['admin','pimpinan','kepala_bidang'])) {
        q("DELETE FROM asset_disposals WHERE id=?", [$id]);
        flash_set('warning', 'Pengajuan penghapusan ditolak/dihapus.');
    }
    header('Location: penghapusan.php'); exit;
}

$rows = q("SELECT d.*, a.code, a.name, a.value, uc.name AS pengaju FROM asset_disposals d
           JOIN assets a ON a.id=d.asset_id LEFT JOIN user_cache uc ON uc.user_id=d.created_by ORDER BY d.id DESC")->fetchAll();
require __DIR__ . '/../includes/header.php';
$role = $_SESSION['app_role'];
?>
<div class="card"><h3>🗑️ Penghapusan Aset
  <?php if (is_in_role(['admin','staff_pengadaan'])): ?><a class="btn btn-sm btn-primary" href="penghapusan_form.php">➕ Ajukan Penghapusan</a><?php endif; ?>
</h3>
<div class="workflow">
  <span class="wf-step">PENGAJUAN</span><span class="wf-step">VERIFIKASI (Staff/Kabag)</span><span class="wf-step">PERSETUJUAN (Pimpinan)</span><span class="wf-step">BERITA ACARA</span><span class="wf-step">DIHAPUS</span>
</div>
<div class="table-wrap"><table class="tbl">
<tr><th>No. Penghapusan</th><th>Aset</th><th>Nilai</th><th>Alasan</th><th>Pengaju</th><th>Status</th><th>Aksi</th></tr>
<?php foreach ($rows as $d): ?>
<tr>
  <td><b><a href="penghapusan_detail.php?id=<?= (int)$d['id'] ?>"><?= e($d['disposal_no']) ?></a></b><br><small class="muted">BA: <?= e($d['ba_no'] ?: '-') ?></small></td>
  <td><a href="aset_detail.php?id=<?= (int)$d['asset_id'] ?>"><?= e($d['code']) ?></a><br><small><?= e($d['name']) ?></small></td>
  <td class="num"><?= rupiah($d['value']) ?></td>
  <td><?= e($d['reason']) ?></td>
  <td><?= e($d['pengaju'] ?? '-') ?></td>
  <td><?= status_badge($d['status']) ?></td>
  <td style="white-space:nowrap">
    <?php if ($d['status']==='PENGAJUAN' && in_array($role,['admin','staff_pengadaan','kepala_bidang'])): ?>
      <form method="post" style="display:inline"><input type="hidden" name="action" value="verify"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-teal">✔ Verifikasi</button></form>
    <?php elseif ($d['status']==='VERIFIKASI' && in_array($role,['admin','pimpinan'])): ?>
      <form method="post" style="display:inline"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-success">✔ Setujui</button></form>
      <form method="post" style="display:inline" onsubmit="return confirm('Tolak pengajuan?')"><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-danger">✖ Tolak</button></form>
    <?php elseif ($d['status']==='PERSETUJUAN' && in_array($role,['admin','staff_pengadaan'])): ?>
      <form method="post" style="display:flex;gap:4px;align-items:center"><input type="hidden" name="action" value="ba"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <input type="text" name="ba_no" placeholder="No. BA" style="width:110px" required>
        <input type="date" name="ba_date" style="width:140px">
        <button class="btn btn-sm btn-primary">📄 BA</button></form>
    <?php elseif ($d['status']==='BERITA_ACARA' && in_array($role,['admin','staff_pengadaan'])): ?>
      <form method="post" style="display:inline" onsubmit="return confirm('Finalisasi penghapusan aset?')"><input type="hidden" name="action" value="finalize"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-danger">🗑️ Eksekusi Hapus</button></form>
    <?php else: ?>—<?php endif; ?>
  </td>
</tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7"><div class="empty-state"><div class="big">🗑️</div>Belum ada pengajuan penghapusan.</div></td></tr><?php endif; ?>
</table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
