<?php
/** Pemetaan Role Aplikasi per User (khusus Admin). */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin']);

$APP_ROLES = [
    'admin'            => ['Admin / Super Admin', 'Mengelola seluruh sistem'],
    'staff_pengadaan'  => ['Staff Pengadaan', 'Kebutuhan, pengadaan, inventaris, ATK, fasilitas, kendaraan, laporan'],
    'kepala_bidang'    => ['Kepala Bidang', 'Mengajukan kebutuhan, persetujuan step 1, melihat laporan & dashboard'],
    'kepala_divisi'    => ['Kepala Divisi', 'Mengajukan & menyetujui kebutuhan, laporan, dashboard'],
    'pimpinan'         => ['Pimpinan / Approver', 'Menyetujui/menolak step 2, laporan, dashboard'],
    'pegawai'          => ['Unit Kerja / Pegawai', 'Ajukan kebutuhan, permintaan ATK, laporan kerusakan, peminjaman kendaraan'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'save') {
        $uid = (int)$_POST['user_id'];
        $role = $_POST['app_role'] ?? 'pegawai';
        if (!isset($APP_ROLES[$role])) $role = 'pegawai';
        q("INSERT INTO role_mapping (user_id, app_role) VALUES (?,?)
           ON DUPLICATE KEY UPDATE app_role = VALUES(app_role)", [$uid, $role]);
        if ($uid === (int)$_SESSION['user_id']) $_SESSION['app_role'] = $role; // efek langsung jika mengubah diri sendiri
        flash_set('success', 'Role user #' . $uid . ' dipetakan ke <b>' . e($APP_ROLES[$role][0]) . '</b>.');
    } elseif ($act === 'reset') {
        q("DELETE FROM role_mapping WHERE user_id=?", [(int)$_POST['user_id']]);
        flash_set('info', 'Pemetaan dihapus — user kembali ke auto-mapping dari jabatan SSO.');
    } elseif ($act === 'sync') {
        $n = sync_user_cache();
        flash_set('success', "Sinkron data pegawai selesai ($n user).");
    }
    header('Location: role_mapping.php'); exit;
}

sync_user_cache(); // pastikan daftar pegawai terbaru
$rows = q("SELECT uc.*, rm.app_role AS mapped_role
           FROM user_cache uc
           LEFT JOIN role_mapping rm ON rm.user_id = uc.user_id
           ORDER BY uc.is_active DESC, uc.name")->fetchAll();
// username & role SSO diambil dari DB SSO bila tersedia
try {
    $ssoUsers = ssoDb()->query("SELECT id, username, role FROM users")->fetchAll();
    $ssoMap = array_column($ssoUsers, null, 'id');
} catch (Throwable $e) { $ssoMap = []; }

$pageTitle = 'Pemetaan Role'; $active = 'role_mapping';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h3>🎭 Pemetaan Role Aplikasi
    <form method="post" style="display:inline"><input type="hidden" name="action" value="sync"><button class="btn btn-sm btn-outline">🔄 Sync Data Pegawai</button></form>
  </h3>
  <p class="muted">Role aplikasi menentukan hak akses modul. Jika belum dipetakan, sistem memakai auto-mapping dari jabatan/role SSO
  (admin → Admin; Direktur Operasional → Pimpinan; Kepala Divisi/Bidang → sesuai jabatan; divisi Pengadaan → Staff Pengadaan).</p>
  <div class="table-wrap"><table class="tbl">
    <tr><th>Pegawai</th><th>Jabatan</th><th>Bidang / Divisi</th><th>Role SSO</th><th>Auto-Mapping</th><th>Role Aplikasi</th><th>Aksi</th></tr>
    <?php foreach ($rows as $u):
        $auto = $u['mapped_role'] ?? 'pegawai';
        if (!$u['mapped_role']) {
            $pos = $u['position']; $div = $u['nama_bidang']; $ssorole = $ssoMap[$u['user_id']]['role'] ?? 'user';
            if ($ssorole === 'admin' || $pos === 'System Administrator') $auto = 'admin';
            elseif ($pos === 'Direktur Operasional') $auto = 'pimpinan';
            elseif ($pos === 'Kepala Divisi') $auto = 'kepala_divisi';
            elseif ($pos === 'Kepala Bidang') $auto = 'kepala_bidang';
            elseif ($div === 'Pengadaan' || $ssorole === 'op_arsip') $auto = 'staff_pengadaan';
        }
    ?>
    <tr>
      <td><?= avatar_html($u['avatar'], $u['name'], 28) ?> <b><?= e($u['name']) ?></b>
          <br><small class="muted"><?= e($ssoMap[$u['user_id']]['username'] ?? '') ?> · ID <?= (int)$u['user_id'] ?><?= $u['nik'] ? ' · NIP ' . e($u['nik']) : '' ?></small>
          <?= $u['is_active'] ? '' : '<span class="badge badge-dark">NONAKTIF</span>' ?></td>
      <td><?= e($u['position'] ?: '-') ?></td>
      <td><?= e($u['nama_bidang'] ?: '-') ?><br><small class="muted"><?= e($u['nama_divisi'] ?: '') ?></small></td>
      <td><code><?= e($ssoMap[$u['user_id']]['role'] ?? '-') ?></code></td>
      <td><span class="badge badge-secondary"><?= e($APP_ROLES[$auto][0]) ?></span></td>
      <td>
        <form method="post" style="display:flex;gap:6px">
          <input type="hidden" name="action" value="save"><input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
          <select name="app_role" style="width:auto;font-size:12px;padding:4px 8px;border:1px solid var(--line);border-radius:6px">
            <?php foreach ($APP_ROLES as $key => [$label, $desc]): ?>
            <option value="<?= $key ?>" <?= ($u['mapped_role'] ?? $auto) === $key ? 'selected' : '' ?> title="<?= e($desc) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-primary">Simpan</button>
        </form>
      </td>
      <td><?php if ($u['mapped_role']): ?>
        <form method="post" onsubmit="return confirm('Hapus pemetaan?')"><input type="hidden" name="action" value="reset"><input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>"><button class="btn btn-sm btn-outline">Reset</button></form>
      <?php else: ?>—<?php endif; ?></td>
    </tr>
    <?php endforeach; if (!$rows): ?>
    <tr><td colspan="7"><div class="empty-state"><div class="big">👥</div>Data pegawai kosong. Klik “Sync Data Pegawai”.</div></td></tr>
    <?php endif; ?>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
