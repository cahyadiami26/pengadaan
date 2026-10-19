<?php
/**
 * Modul Stock Opname: buat opname -> pilih lokasi/unit -> scan aset (input/QR)
 * -> sistem mencocokkan data -> periksa kondisi -> validasi -> laporan PDF.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'staff_pengadaan']);

$rooms = q("SELECT id, name FROM locations WHERE type='ruang' AND is_active=1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? ''; $id = (int)$_POST['id'];

    if ($act === 'create') {
        $no = gen_no('stock_opnames', 'opname_no', 'SO');
        q("INSERT INTO stock_opnames (opname_no, title, location_id, unit, opname_date, status, created_by)
           VALUES (?,?,?,?,?, 'OPEN', ?)",
          [$no, trim($_POST['title'] ?? ''), (int)$_POST['location_id'] ?: null,
           trim($_POST['unit'] ?? ''), $_POST['opname_date'] ?: date('Y-m-d'), $_SESSION['user_id']]);
        flash_set('success', "Opname <b>$no</b> dibuat. Silakan scan aset.");
        header('Location: stock_opname.php?id=' . db()->lastInsertId()); exit;
    }

    if ($act === 'scan') {
        $op = q("SELECT * FROM stock_opnames WHERE id=?", [$id])->fetch();
        if (!$op || $op['status'] !== 'OPEN') { flash_set('danger', 'Opname tidak ditemukan / sudah divalidasi.'); header('Location: stock_opname.php?id=' . $id); exit; }
        $code = trim($_POST['scan_code'] ?? '');
        // terima input berupa kode aset polos maupun URL hasil scan QR
        if (preg_match('#[?&](?:code|q)=([^&]+)#', $code, $m)) $code = urldecode($m[1]);
        if ($code === '') { flash_set('danger', 'Kode kosong.'); header('Location: stock_opname.php?id=' . $id); exit; }

        $asset = q("SELECT a.*, l.name AS lokasi FROM assets a LEFT JOIN locations l ON l.id=a.location_id WHERE a.code=?", [$code])->fetch();
        $exists = q("SELECT id FROM stock_opname_items WHERE opname_id=? AND (asset_id=? OR scanned_code=?)",
                    [$id, $asset['id'] ?? null, $code])->fetch();
        if ($exists) { flash_set('warning', 'Aset <b>' . e($code) . '</b> sudah di-scan pada opname ini.'); header('Location: stock_opname.php?id=' . $id); exit; }

        $match = 'EXTRA'; $note = '';
        if ($asset) {
            $inScope = true;
            if ($op['location_id']) $inScope = ((int)$asset['location_id'] === (int)$op['location_id']);
            elseif ($op['unit']) $inScope = (stripos((string)$asset['unit'], $op['unit']) !== false);
            $match = $inScope ? 'COOK' : 'EXTRA';
            $note = $inScope ? '' : 'Ditemukan di luar cakupan lokasi/unit opname.';
        } else {
            $note = 'Kode tidak dikenali dalam database aset.';
        }
        q("INSERT INTO stock_opname_items (opname_id, asset_id, scanned_code, match_status, checked_condition, note)
           VALUES (?,?,?,?,?,?)",
          [$id, $asset['id'] ?? null, $code, $match, $asset['condition_status'] ?? null, $note]);
        flash_set($asset ? 'success' : 'warning', $asset ? 'Aset <b>' . e($asset['name']) . '</b> tercatat (' . $match . ').' : 'Kode <b>' . e($code) . '</b> tidak dikenali — dicatat sebagai EXTRA.');
        header('Location: stock_opname.php?id=' . $id); exit;
    }

    if ($act === 'update_item') {
        q("UPDATE stock_opname_items SET match_status=?, checked_condition=?, note=? WHERE id=? AND opname_id=?",
          [$_POST['match_status'], $_POST['checked_condition'] ?: null, trim($_POST['note'] ?? ''), (int)$_POST['item_id'], $id]);
        header('Location: stock_opname.php?id=' . $id); exit;
    }

    if ($act === 'delete_item') {
        q("DELETE FROM stock_opname_items WHERE id=? AND opname_id=?", [(int)$_POST['item_id'], $id]);
        header('Location: stock_opname.php?id=' . $id); exit;
    }

    if ($act === 'validate') {
        // tandai aset yang terdaftar di scope tetapi tidak ter-scan sebagai MISSING
        $op = q("SELECT * FROM stock_opnames WHERE id=?", [$id])->fetch();
        $scanned = array_column(q("SELECT asset_id FROM stock_opname_items WHERE opname_id=? AND asset_id IS NOT NULL", [$id])->fetchAll(), 'asset_id');
        $where = "a.status IN ('AKTIF','RUSAK','DIPINDAHKAN')"; $params = [];
        if ($op['location_id']) { $where .= ' AND a.location_id=?'; $params[] = $op['location_id']; }
        elseif ($op['unit']) { $where .= ' AND a.unit LIKE ?'; $params[] = '%' . $op['unit'] . '%'; }
        $scope = q("SELECT a.id, a.code FROM assets a WHERE $where", $params)->fetchAll();
        foreach ($scope as $s) {
            if (!in_array($s['id'], $scanned)) {
                q("INSERT INTO stock_opname_items (opname_id, asset_id, scanned_code, match_status, note) VALUES (?,?,?,'MISSING','Terdaftar tapi tidak ditemukan saat opname.')",
                  [$id, $s['id'], $s['code']]);
            }
        }
        q("UPDATE stock_opnames SET status='VALIDATED' WHERE id=?", [$id]);
        flash_set('success', 'Hasil hitung cocok tervalidasi. Laporan dapat dicetak.');
        header('Location: stock_opname.php?id=' . $id); exit;
    }

    if ($act === 'reopen') {
        q("UPDATE stock_opnames SET status='OPEN' WHERE id=?", [$id]);
        q("DELETE FROM stock_opname_items WHERE opname_id=? AND match_status='MISSING'", [$id]);
        header('Location: stock_opname.php?id=' . $id); exit;
    }
}

$id = (int)($_GET['id'] ?? 0);
$pageTitle = 'Stock Opname'; $active = 'stock_opname';
require __DIR__ . '/../includes/header.php';

if ($id):
    $op = q("SELECT so.*, l.name AS lokasi, uc.name AS pembuat FROM stock_opnames so
             LEFT JOIN locations l ON l.id=so.location_id LEFT JOIN user_cache uc ON uc.user_id=so.created_by
             WHERE so.id=?", [$id])->fetch();
    if (!$op): echo '<div class="empty-state">Opname tidak ditemukan.</div>'; require __DIR__.'/../includes/footer.php'; exit; endif;
    $items = q("SELECT i.*, a.code, a.name AS asset_name, a.condition_status, l.name AS lokasi
                FROM stock_opname_items i LEFT JOIN assets a ON a.id=i.asset_id
                LEFT JOIN locations l ON l.id=a.location_id WHERE i.opname_id=? ORDER BY i.match_status, i.id", [$id])->fetchAll();
    $nCook = count(array_filter($items, fn($i) => $i['match_status'] === 'COOK'));
    $nMissing = count(array_filter($items, fn($i) => $i['match_status'] === 'MISSING'));
    $nExtra = count(array_filter($items, fn($i) => $i['match_status'] === 'EXTRA'));
?>
<div class="card">
  <h3>🔍 <?= e($op['opname_no']) ?> — <?= e($op['title'] ?: 'Stock Opname') ?> <?= status_badge($op['status']) ?>
    <span>
      <?php if ($op['status'] !== 'OPEN'): ?><a class="btn btn-sm btn-outline" href="stock_opname_pdf.php?id=<?= $id ?>" target="_blank">📄 Laporan PDF</a><?php endif; ?>
      <a class="btn btn-sm btn-outline" href="stock_opname.php">← Daftar</a>
    </span>
  </h3>
  <p class="muted">Tanggal: <b><?= tgl($op['opname_date']) ?></b> · Cakupan: <b><?= e($op['lokasi'] ?? ($op['unit'] ?: 'Semua lokasi')) ?></b> · Dibuat oleh <?= e($op['pembuat'] ?? '-') ?></p>
  <div class="grid grid-3">
    <div class="stat-card"><div><b style="color:#166534"><?= $nCook ?></b><br><small class="muted">Cocok (COOK)</small></div></div>
    <div class="stat-card"><div><b style="color:#991b1b"><?= $nMissing ?></b><br><small class="muted">Tidak Ditemukan (MISSING)</small></div></div>
    <div class="stat-card"><div><b style="color:#92400e"><?= $nExtra ?></b><br><small class="muted">Di Luar Daftar (EXTRA)</small></div></div>
  </div>
</div>

<?php if ($op['status'] === 'OPEN'): ?>
<div class="card">
  <h3>📷 Scan Aset</h3>
  <form method="post" style="display:flex;gap:10px;align-items:flex-end">
    <input type="hidden" name="action" value="scan"><input type="hidden" name="id" value="<?= $id ?>">
    <div class="form-group" style="flex:1;margin:0">
      <label>Kode Aset / Hasil Scan QR</label>
      <input type="text" name="scan_code" id="scanInput" placeholder="AST-GA-L1-RIT-IT-2026-000001 (atau paste URL QR)" autocomplete="off" required autofocus>
    </div>
    <button class="btn btn-primary">➕ Tambahkan</button>
  </form>
  <p class="muted" style="margin-top:8px">Fokuskan kursor di kolom ini lalu tembakkan barcode scanner / tempel URL dari QR. Tekan Enter untuk menambah.</p>
</div>
<?php endif; ?>

<div class="card">
  <h3>Hasil Pemeriksaan (<?= count($items) ?> item)
    <?php if ($op['status'] === 'OPEN'): ?>
    <form method="post" onsubmit="return confirm('Validasi hasil opname? Item MISSING akan otomatis ditambahkan dari data aset yang belum ter-scan.')">
      <input type="hidden" name="action" value="validate"><input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn btn-sm btn-teal">✔ Validasi &amp; Hitung Cocok</button>
    </form>
    <?php elseif ($op['status'] === 'VALIDATED'): ?>
    <form method="post"><input type="hidden" name="action" value="reopen"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-sm btn-outline">↩ Buka Kembali</button></form>
    <?php endif; ?>
  </h3>
  <div class="table-wrap"><table class="tbl">
    <tr><th>Status</th><th>Kode</th><th>Nama Aset</th><th>Lokasi</th><th>Kondisi Hasil Cek</th><th>Catatan</th><th>Aksi</th></tr>
    <?php foreach ($items as $i): ?>
    <tr>
      <td><?= $i['match_status'] === 'COOK' ? '<span class="badge badge-success">COOK</span>' : ($i['match_status'] === 'MISSING' ? '<span class="badge badge-danger">MISSING</span>' : '<span class="badge badge-warning">EXTRA</span>') ?></td>
      <td><b><?= e($i['code'] ?: $i['scanned_code']) ?></b></td>
      <td><?= e($i['asset_name'] ?? '(tidak dikenali)') ?></td>
      <td><?= e($i['lokasi'] ?? '-') ?></td>
      <td><?= e($i['checked_condition'] ?? $i['condition_status'] ?? '-') ?></td>
      <td><small><?= e($i['note'] ?? '') ?></small></td>
      <td style="white-space:nowrap"><?php if ($op['status'] === 'OPEN'): ?>
        <form method="post" style="display:inline-flex;gap:4px">
          <input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>">
          <select name="match_status" style="font-size:11px;padding:2px 4px;border:1px solid var(--line);border-radius:5px">
            <?php foreach (['COOK','MISSING','EXTRA'] as $ms): ?><option <?= $i['match_status']===$ms?'selected':'' ?>><?= $ms ?></option><?php endforeach; ?>
          </select>
          <select name="checked_condition" style="font-size:11px;padding:2px 4px;border:1px solid var(--line);border-radius:5px">
            <option value="">-</option>
            <?php foreach (['BAIK','RUSAK RINGAN','RUSAK BERAT'] as $c): ?><option <?= $i['checked_condition']===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-outline">Simpan</button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Hapus item ini?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>"><button class="btn btn-sm btn-danger">×</button></form>
      <?php else: ?>—<?php endif; ?></td>
    </tr>
    <?php endforeach; if (!$items): ?>
    <tr><td colspan="7"><div class="empty-state"><div class="big">📷</div>Belum ada aset ter-scan.</div></td></tr><?php endif; ?>
  </table></div>
  <?php if ($op['status'] !== 'OPEN'): ?>
  <div style="margin-top:10px"><a class="btn btn-teal" href="stock_opname_pdf.php?id=<?= $id ?>" target="_blank">📄 Cetak Laporan Stock Opname (PDF)</a></div>
  <?php endif; ?>
</div>

<script>
// Auto-focus kembali ke input scan setelah tambah (mode scanner terus-menerus)
var si = document.getElementById('scanInput');
if (si) si.addEventListener('keydown', function(e){ if (e.key === 'Enter') { this.form.requestSubmit ? this.form.requestSubmit() : si.form.submit(); } });
document.querySelectorAll('form').forEach(function(f){ f.addEventListener('submit', function(){ /* biarkan */ }); });
</script>

<?php else: ?>
<div class="grid grid-2">
  <div class="card">
    <h3>➕ Buat Stock Opname Baru</h3>
    <form method="post">
      <input type="hidden" name="action" value="create">
      <div class="form-group"><label>Judul</label><input type="text" name="title" placeholder="mis. Opname Bulanan Ruang IT"></div>
      <div class="form-row">
        <div class="form-group"><label>Lokasi (Ruang)</label><select name="location_id"><option value="">Semua Lokasi</option>
          <?php foreach ($rooms as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="form-group"><label>Atau Unit</label><input type="text" name="unit" placeholder="mis. IT, Keuangan"></div>
        <div class="form-group"><label>Tanggal *</label><input type="date" name="opname_date" value="<?= date('Y-m-d') ?>" required></div>
      </div>
      <button class="btn btn-primary">Buat &amp; Mulai Scan</button>
    </form>
  </div>
  <div class="card">
    <h3>📋 Riwayat Opname</h3>
    <div class="table-wrap"><table class="tbl">
      <tr><th>No.</th><th>Tanggal</th><th>Cakupan</th><th>Status</th><th></th></tr>
      <?php $list = q("SELECT so.*, l.name AS lokasi FROM stock_opnames so LEFT JOIN locations l ON l.id=so.location_id ORDER BY so.id DESC LIMIT 30")->fetchAll();
      foreach ($list as $o): ?>
      <tr>
        <td><b><?= e($o['opname_no']) ?></b><br><small class="muted"><?= e($o['title'] ?? '') ?></small></td>
        <td><?= tgl($o['opname_date']) ?></td>
        <td><?= e($o['lokasi'] ?? ($o['unit'] ?: 'Semua')) ?></td>
        <td><?= status_badge($o['status']) ?></td>
        <td><a class="btn btn-sm btn-outline" href="stock_opname.php?id=<?= (int)$o['id'] ?>">Buka</a></td>
      </tr>
      <?php endforeach; if (!$list): ?><tr><td colspan="5" class="muted center">Belum ada opname.</td></tr><?php endif; ?>
    </table></div>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
