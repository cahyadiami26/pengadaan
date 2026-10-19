<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Data Pegawai';
$active = 'pegawai';

// Sinkron dari tabel users (database SSO) saat halaman dibuka / tombol sync ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sync') {
    require_role(['admin', 'staff_pengadaan']);
    $n = sync_user_cache();
    flash_set('success', "Sinkronisasi data pegawai selesai ($n baris disinkronkan dari tabel users).");
    header('Location: pegawai.php'); exit;
}
// Simpan nomor HP (kolom tambahan aplikasi, karena users tidak punya kolom phone)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_phone') {
    require_role(['admin', 'staff_pengadaan']);
    q("UPDATE user_cache SET phone=? WHERE user_id=?", [trim($_POST['phone'] ?? ''), (int)$_POST['user_id']]);
    flash_set('success', 'Nomor HP disimpan.');
    header('Location: pegawai.php'); exit;
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM user_cache" . ($q ? " WHERE name LIKE :q OR nik LIKE :q OR email LIKE :q OR nama_bidang LIKE :q" : "") . " ORDER BY is_active DESC, name";
try {
    $stmt = db()->prepare($sql);
    if ($q) $stmt->execute([':q' => "%$q%"]); else $stmt->execute();
    $rows = $stmt->fetchAll();
} catch (Throwable $e) { $rows = []; }

require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h3>👥 Data Pegawai (sinkron dari tabel users SSO)
    <form method="post" style="display:inline">
      <input type="hidden" name="action" value="sync">
      <button class="btn btn-sm btn-teal">🔄 Sync dari Users</button>
    </form>
  </h3>
  <form method="get" class="filters">
    <div class="form-group"><label>Cari</label><input type="text" name="q" value="<?= e($q) ?>" placeholder="Nama / NIP / Email / Bidang"></div>
    <button class="btn btn-primary btn-sm">Cari</button>
    <a class="btn btn-outline btn-sm" href="pegawai.php">Reset</a>
  </form>

  <div class="table-wrap"><table class="tbl">
    <tr><th></th><th>NIP/ID</th><th>Nama</th><th>Jabatan</th><th>Bidang / Divisi</th><th>Email</th><th>Nomor HP</th><th>Status</th></tr>
    <?php if (!$rows): ?>
      <tr><td colspan="8"><div class="empty-state"><div class="big">👥</div>Belum ada data. Klik <b>Sync dari Users</b> untuk menarik data dari tabel users.</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= avatar_html($r['avatar'], $r['name'], 34) ?></td>
      <td><?= e($r['nik'] ?: ('#' . $r['user_id'])) ?></td>
      <td><b><?= e($r['name']) ?></b></td>
      <td><?= e($r['position'] ?? '-') ?></td>
      <td><?= e($r['nama_bidang'] ?? '-') ?><br><small style="color:var(--muted)">Divisi: <?= e($r['nama_divisi'] ?? '-') ?></small></td>
      <td><?= e($r['email'] ?? '-') ?></td>
      <td>
        <form method="post" style="display:flex;gap:6px">
          <input type="hidden" name="action" value="save_phone">
          <input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>">
          <input type="text" name="phone" value="<?= e($r['phone'] ?? '') ?>" placeholder="08xx...." style="width:120px;padding:4px 8px;font-size:12.5px">
          <button class="btn btn-sm btn-outline">💾</button>
        </form>
      </td>
      <td><?= $r['is_active'] ? '<span class="badge badge-success">AKTIF</span>' : '<span class="badge badge-secondary">NONAKTIF</span>' ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
