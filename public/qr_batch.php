<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'staff_pengadaan']);
$pageTitle = 'Batch Print QR Code'; $active = 'inventaris';

$fLoc = (int)($_GET['lokasi'] ?? 0);
$ids  = array_filter(array_map('intval', (array)($_GET['ids'] ?? [])));
$sql = "SELECT a.*, l.name AS lokasi FROM assets a LEFT JOIN locations l ON l.id=a.location_id WHERE a.status NOT IN ('DIHAPUS')";
$params = [];
if ($fLoc) { $sql .= ' AND a.location_id=?'; $params[] = $fLoc; }
if ($ids)  { $sql .= ' AND a.id IN (' . implode(',', $ids) . ')'; }
$sql .= ' ORDER BY a.code';
$stmt = db()->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
$rooms = q("SELECT id,name FROM locations WHERE type='ruang' ORDER BY name")->fetchAll();
// pastikan file QR tersimpan di uploads/qr
foreach ($rows as $r) generate_qr(APP_URL . '/public/aset_view.php?code=' . $r['code']);
require __DIR__ . '/../includes/header.php';
?>
<div class="card no-print">
  <h3>🖨️ Batch Print QR Code (<?= count($rows) ?> aset)</h3>
  <form method="get" class="filters">
    <div class="form-group"><label>Lokasi/Ruangan</label><select name="lokasi" onchange="this.form.submit()">
      <option value="">Semua lokasi</option>
      <?php foreach ($rooms as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $fLoc===(int)$r['id']?'selected':'' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
    </select></div>
    <button class="btn btn-sm btn-outline">Terapkan</button>
    <button type="button" class="btn btn-sm btn-teal" onclick="window.print()">🖨️ Cetak Semua</button>
  </form>
  <p class="muted">Gunakan filter lokasi untuk mencetak QR satu ruangan sekaligus. Ukuran label ± 45×30 mm cocok untuk stiker aset.</p>
</div>
<div class="qr-grid">
  <?php foreach ($rows as $r): ?>
  <div class="qr-card">
    <img src="<?= e(qr_img_src(APP_URL . '/public/aset_view.php?code=' . $r['code'])) ?>" alt="QR <?= e($r['code']) ?>">
    <div class="code"><?= e($r['code']) ?></div>
    <div class="meta"><?= e($r['name']) ?><br><?= e($r['lokasi'] ?? '') ?> · <?= e($r['unit'] ?? '') ?></div>
  </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="empty-state" style="grid-column:1/-1"><div class="big">▦</div>Tidak ada aset untuk dicetak.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
