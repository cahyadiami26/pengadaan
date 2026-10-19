<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Dashboard';
$active = 'dashboard';

// ---- Kartu statistik ----
$stats = [
    ['label' => 'Total Aset',                'icon' => '🏷️', 'sql' => "SELECT COUNT(*) FROM assets WHERE category='asset' AND status NOT IN ('DIHAPUS')"],
    ['label' => 'Total Inventaris',          'icon' => '📋', 'sql' => "SELECT COUNT(*) FROM assets WHERE category='inventaris' AND status NOT IN ('DIHAPUS')"],
    ['label' => 'Total ATK',                 'icon' => '🖇️', 'sql' => "SELECT COALESCE(SUM(stock),0) FROM supplies WHERE category='atk'"],
    ['label' => 'Permintaan ATK Menunggu',   'icon' => '⏳', 'sql' => "SELECT COUNT(*) FROM supply_requests WHERE status IN ('MENUNGGU','DIPROSES')"],
    ['label' => 'Pengadaan Berjalan',        'icon' => '🚧', 'sql' => "SELECT COUNT(*) FROM procurement_plans WHERE status IN ('DALAM_PROSES_PENGADAAN','FINAL_KEUANGAN','DISETUJUI_DIREKTUR')"],
    ['label' => 'Barang Belum Diterima',     'icon' => '📦', 'sql' => "SELECT COUNT(*) FROM procurement_plans WHERE status='DALAM_PROSES_PENGADAAN' AND id NOT IN (SELECT plan_id FROM receptions WHERE plan_id IS NOT NULL)"],
    ['label' => 'Kerusakan Belum Ditangani', 'icon' => '⚠️', 'sql' => "SELECT COUNT(*) FROM damage_reports WHERE status IN ('DILAPORKAN','DIVERIFIKASI')"],
    ['label' => 'Perbaikan Berjalan',        'icon' => '🛠️', 'sql' => "SELECT COUNT(*) FROM maintenances WHERE status IN ('TERENCANA','DALAM PERBAIKAN')"],
    ['label' => 'Kendaraan Tersedia',        'icon' => '🚗', 'sql' => "SELECT COUNT(*) FROM vehicles WHERE status='TERSEDIA'"],
    ['label' => 'Kendaraan Digunakan',       'icon' => '🛣️', 'sql' => "SELECT COUNT(*) FROM vehicles WHERE status IN ('DIPINJAM','DIGUNAKAN')"],
    ['label' => 'Kendaraan Diperbaiki',      'icon' => '🔧', 'sql' => "SELECT COUNT(*) FROM vehicles WHERE status='SERVICE'"],
];

// ---- Statistik kerusakan & kendaraan untuk chart sederhana ----
try { $openTickets = count_rows("SELECT COUNT(*) FROM facility_tickets WHERE status <> 'SELESAI'"); } catch (Throwable $e) { $openTickets = 0; }
try { $lowStock = q("SELECT s.* FROM supplies s WHERE stock_minimum_warning=1 ORDER BY stock ASC LIMIT 8")->fetchAll(); } catch (Throwable $e) { $lowStock = []; }

// Nama pemohon: users ada di database SSO terpisah -> join via aplikasi DB jika tersedia,
// jika tidak ambil dari cache session / tampilkan ID.
$recentNeeds = [];
try {
    $recentNeeds = q("SELECT n.*, ua.name AS pemohon FROM needs_requests n
                      LEFT JOIN user_cache ua ON ua.user_id = n.user_id
                      ORDER BY n.id DESC LIMIT 6")->fetchAll();
} catch (Throwable $e) {
    try { $recentNeeds = q("SELECT * FROM needs_requests ORDER BY id DESC LIMIT 6")->fetchAll(); } catch (Throwable $e2) {}
}
$activeEmployees = 0;
try {
    $stmt = ssoDb()->query("SELECT COUNT(*) FROM users WHERE is_active=1");
    $activeEmployees = (int)$stmt->fetchColumn();
} catch (Throwable $e) {}
$notifs = work_notifications();

require __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-4">
  <?php foreach ($stats as $s): ?>
  <div class="card stat-card" style="margin-bottom:0">
    <div class="stat-icon"><?= $s['icon'] ?></div>
    <div><div class="stat-value"><?= count_rows($s['sql']) ?></div><div class="stat-label"><?= e($s['label']) ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="grid grid-2" style="margin-top:16px">
  <div class="card">
    <h3>🔔 Notifikasi Pekerjaan</h3>
    <?php if (!$notifs): ?>
      <div class="empty-state"><div class="big">✅</div>Tidak ada tugas yang menunggu.</div>
    <?php else: foreach ($notifs as [$ic, $msg, $link]): ?>
      <a class="notif-item" href="<?= e($link) ?>" style="color:inherit">
        <span class="notif-icon"><?= $ic ?></span><span><?= e($msg) ?></span>
        <span style="margin-left:auto;color:var(--muted)">›</span>
      </a>
    <?php endforeach; endif; ?>
  </div>

  <div class="card">
    <h3>📉 Stok Minimum Terancam (<?= count($lowStock) ?>)</h3>
    <?php if (!$lowStock): ?>
      <div class="empty-state"><div class="big">📭</div>Tidak ada stok di bawah minimum.</div>
    <?php else: ?>
    <div class="table-wrap"><table class="tbl">
      <tr><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th class="num">Stok</th><th class="num">Min</th></tr>
      <?php foreach ($lowStock as $ls): ?>
      <tr><td><?= e($ls['code']) ?></td><td><?= e($ls['name']) ?></td><td><?= e(ucwords(str_replace('_',' ',$ls['category']))) ?></td>
          <td class="num"><b style="color:var(--danger)"><?= (int)$ls['stock'] ?></b></td><td class="num"><?= (int)$ls['min_stock'] ?></td></tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>📝 Pengajuan Kebutuhan Terbaru <a class="btn btn-sm btn-outline" href="kebutuhan.php">Lihat semua</a></h3>
    <?php if (!$recentNeeds): ?>
      <div class="empty-state"><div class="big">🗒️</div>Belum ada pengajuan kebutuhan.</div>
    <?php else: ?>
    <div class="table-wrap"><table class="tbl">
      <tr><th>No</th><th>Pemohon</th><th>Bidang</th><th>Status</th></tr>
      <?php foreach ($recentNeeds as $n): ?>
      <tr><td><a href="kebutuhan_detail.php?id=<?= (int)$n['id'] ?>"><?= e($n['request_no']) ?></a></td>
          <td><?= e($n['pemohon'] ?? '-') ?></td><td><?= e($n['nama_bidang'] ?? '-') ?></td>
          <td><?= status_badge($n['status']) ?></td></tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>🏢 Ringkasan Operasional</h3>
    <div class="grid grid-2">
      <div class="stat-card"><div class="stat-icon">🎫</div><div><div class="stat-value"><?= (int)$openTickets ?></div><div class="stat-label">Tiket fasilitas aktif</div></div></div>
      <div class="stat-card"><div class="stat-icon">🔍</div><div><div class="stat-value"><?= count_rows("SELECT COUNT(*) FROM stock_opnames WHERE status='OPEN'") ?></div><div class="stat-label">Stock opname berjalan</div></div></div>
      <div class="stat-card"><div class="stat-icon">🔄</div><div><div class="stat-value"><?= count_rows("SELECT COUNT(*) FROM asset_mutations WHERE DATE(mutated_at)=CURDATE()") ?></div><div class="stat-label">Mutasi hari ini</div></div></div>
      <div class="stat-card"><div class="stat-icon">👥</div><div><div class="stat-value"><?= (int)$activeEmployees ?></div><div class="stat-label">Pegawai aktif</div></div></div>
    </div>
    <div class="workflow" style="margin-top:14px">
      <span class="wf-step <?= count_rows("SELECT COUNT(*) FROM needs_requests WHERE status='DIAJUKAN'")?'current':'' ?>">DIAJUKAN</span>
      <span class="wf-step">→ DIVERIFIKASI</span>
      <span class="wf-step">→ DISETUJUI</span>
      <span class="wf-step">→ PENGADAAN</span>
      <span class="wf-step">→ DITERIMA</span>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
