<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$a = q("SELECT a.*, l.name AS lokasi, l.code AS lokasi_code FROM assets a LEFT JOIN locations l ON l.id=a.location_id WHERE a.id=?", [$id])->fetch();
if (!$a) { http_response_code(404); require __DIR__ . '/../includes/header.php'; echo '<div class="empty-state"><div class="big">🔍</div>Aset tidak ditemukan.</div>'; require __DIR__ . '/../includes/footer.php'; exit; }
$pageTitle = 'Aset ' . $a['code']; $active = 'inventaris';

$muts     = q("SELECT * FROM asset_mutations WHERE asset_id=? ORDER BY id DESC", [$id])->fetchAll();
$maints   = q("SELECT m.*, u.name AS pelapor FROM maintenances m LEFT JOIN user_cache u ON u.user_id=m.created_by WHERE m.asset_id=? ORDER BY m.id DESC", [$id])->fetchAll();
$damages  = q("SELECT * FROM damage_reports WHERE asset_id=? ORDER BY id DESC", [$id])->fetchAll();
$disps    = q("SELECT * FROM asset_disposals WHERE asset_id=? ORDER BY id DESC", [$id])->fetchAll();
$qrText   = APP_URL . '/public/aset_view.php?code=' . $a['code'];

require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2">
  <div class="card">
    <h3>🏷️ <?= e($a['name']) ?> <?= status_badge($a['status']) ?></h3>
    <table class="tbl">
      <tr><th>Kode Aset</th><td><b><?= e($a['code']) ?></b></td></tr>
      <tr><th>Kategori</th><td><?= e(ucfirst($a['category'])) ?><?= $a['sub_category'] ? ' · ' . e($a['sub_category']) : '' ?></td></tr>
      <tr><th>Merk / Model / Tahun</th><td><?= e(trim($a['brand'].' '.$a['model']) ?: '-') ?> · <?= e($a['year'] ?: '-') ?></td></tr>
      <tr><th>Nomor Seri</th><td><?= e($a['serial_number'] ?: '-') ?></td></tr>
      <tr><th>Tanggal Perolehan</th><td><?= tgl($a['purchase_date']) ?></td></tr>
      <tr><th>Nilai</th><td><?= rupiah($a['value']) ?></td></tr>
      <tr><th>Lokasi</th><td><?= e($a['lokasi'] ?? '-') ?></td></tr>
      <tr><th>Pengguna</th><td><?= avatar_html(q("SELECT avatar FROM user_cache WHERE user_id=?",[$a['user_id']])->fetchColumn() ?: null, user_name_of($a['user_id']), 26) ?> <?= e(user_name_of($a['user_id'])) ?></td></tr>
      <tr><th>Unit</th><td><?= e($a['unit'] ?? '-') ?></td></tr>
      <tr><th>Kondisi</th><td><?= status_badge($a['condition_status']) ?></td></tr>
      <tr><th>Catatan</th><td><?= nl2br(e($a['note'] ?? '-')) ?></td></tr>
    </table>
    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
      <?php if (is_in_role(['admin','staff_pengadaan'])): ?>
      <a class="btn btn-sm btn-outline" href="inventaris.php?edit=<?= (int)$id ?>">✏️ Edit lewat daftar aset</a>
      <form method="post" action="inventaris.php" style="display:inline"><input type="hidden" name="action" value="regen_qr"><input type="hidden" name="id" value="<?= (int)$id ?>"><button class="btn btn-sm btn-outline">♻️ Regenerasi QR</button></form>
      <?php endif; ?>
      <a class="btn btn-sm btn-teal" href="qr_print.php?id=<?= (int)$id ?>">🖨️ Cetak Label QR</a>
      <a class="btn btn-sm btn-outline" href="mutasi.php?asset=<?= (int)$id ?>">🔄 Mutasi</a>
      <a class="btn btn-sm btn-outline" href="kerusakan_form.php?asset=<?= (int)$id ?>">⚠️ Lapor Kerusakan</a>
      <a class="btn btn-sm btn-outline" href="pemeliharaan_form.php?asset=<?= (int)$id ?>">🛠️ Catat Perbaikan</a>
      <?php if ($a['status'] !== 'DIHAPUS'): ?><a class="btn btn-sm btn-danger" href="penghapusan_form.php?asset=<?= (int)$id ?>">🗑️ Ajukan Penghapusan</a><?php endif; ?>
    </div>
  </div>
  <div class="card" style="text-align:center">
    <h3 style="justify-content:center">QR Code Aset</h3>
    <img src="<?= e(qr_img_src($qrText)) ?>" alt="QR <?= e($a['code']) ?>" style="width:240px;height:240px;border:1px solid var(--line);border-radius:12px;padding:8px">
    <div style="margin-top:8px;font-weight:700"><?= e($a['code']) ?></div>
    <div class="muted" style="font-size:12px"><?= e($qrText) ?></div>
    <p class="muted" style="font-size:12px;margin-top:8px">Tempelkan QR pada aset — scan akan membuka halaman informasi aset.</p>
  </div>
</div>

<div class="card"><h3>🔄 Riwayat Mutasi (<?= count($muts) ?>)</h3>
  <?php if (!$muts): ?><div class="empty-state">Belum ada riwayat mutasi.</div><?php else: ?>
  <div class="table-wrap"><table class="tbl">
    <tr><th>Tanggal</th><th>Dari</th><th>Ke</th><th>Alasan</th><th>Oleh</th></tr>
    <?php foreach ($muts as $m): ?>
    <tr><td><?= tgl($m['mutated_at'], true) ?></td>
      <td><?= e($m['from_unit']) ?> · <?= e($m['from_location']) ?> · <?= e($m['from_user']) ?></td>
      <td><?= e($m['to_unit']) ?> · <?= e($m['to_location']) ?> · <?= e($m['to_user']) ?></td>
      <td><?= e($m['reason']) ?></td><td><?= e(user_name_of($m['mutated_by'])) ?></td></tr>
    <?php endforeach; ?></table></div><?php endif; ?>
</div>

<div class="grid grid-2">
  <div class="card"><h3>🛠️ Riwayat Pemeliharaan (<?= count($maints) ?>)</h3>
    <?php if (!$maints): ?><div class="empty-state">Belum ada pemeliharaan.</div><?php else: ?>
    <div class="table-wrap"><table class="tbl">
      <tr><th>No</th><th>Tanggal</th><th>Vendor</th><th>Tindakan</th><th class="num">Biaya</th><th>Status</th></tr>
      <?php foreach ($maints as $m): ?>
      <tr><td><a href="pemeliharaan_detail.php?id=<?= (int)$m['id'] ?>"><?= e($m['maint_no']) ?></a></td><td><?= tgl($m['maint_date']) ?></td>
      <td><?= e($m['vendor'] ?: '-') ?></td><td><?= e($m['action_taken'] ?: '-') ?></td><td class="num"><?= rupiah($m['cost']) ?></td><td><?= status_badge($m['status']) ?></td></tr>
      <?php endforeach; ?></table></div><?php endif; ?>
  </div>
  <div class="card"><h3>⚠️ Riwayat Kerusakan &amp; Penghapusan</h3>
    <?php if (!$damages && !$disps): ?><div class="empty-state">Tidak ada laporan kerusakan/penghapusan.</div><?php endif; ?>
    <?php foreach ($damages as $d): ?>
      <div class="notif-item"><span>⚠️</span><div><a href="kerusakan_detail.php?id=<?= (int)$d['id'] ?>"><?= e($d['report_no']) ?></a> — <?= e($d['damage_desc']) ?> <?= status_badge($d['status']) ?></div></div>
    <?php endforeach; ?>
    <?php foreach ($disps as $dp): ?>
      <div class="notif-item"><span>🗑️</span><div><a href="penghapusan_detail.php?id=<?= (int)$dp['id'] ?>"><?= e($dp['disposal_no']) ?></a> — <?= e($dp['reason']) ?> <?= status_badge($dp['status']) ?></div></div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
