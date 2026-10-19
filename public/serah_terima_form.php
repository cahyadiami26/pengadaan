<?php
/**
 * Form Serah Terima Barang.
 * Penerima dipilih dari data pegawai (user_cache), tanggal, detail item yang
 * diserahterimakan, dan tanda tangan pada canvas (pemberi & penerima).
 * ref_type: permintaan_atk | penerimaan | mutasi | lainnya
 * Untuk permintaan ATK: stok otomatis berkurang saat serah terima disimpan.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'staff_pengadaan', 'kepala_bidang', 'kepala_divisi']);

$refType = $_GET['ref_type'] ?? 'permintaan_atk';
$refId   = (int)($_GET['ref_id'] ?? 0);

// ---- Muat konteks referensi ----
$supplyReq = null; $items = []; $prefillDesc = '';
if ($refType === 'permintaan_atk' && $refId) {
    $supplyReq = q("SELECT * FROM supply_requests WHERE id=?", [$refId])->fetch();
    if ($supplyReq) {
        $items = q("SELECT i.*, s.name, s.unit, s.stock, s.category
                    FROM supply_request_items i JOIN supplies s ON s.id = i.supply_id
                    WHERE i.request_id=?", [$refId])->fetchAll();
        $prefillDesc = "Penyerahan barang sesuai Permintaan ATK No. " . $supplyReq['request_no'];
    }
}
$reception = null;
if ($refType === 'penerimaan' && $refId) {
    $reception = q("SELECT r.*, uc.name AS penerima_barang FROM receptions r
                    LEFT JOIN user_cache uc ON uc.user_id = r.received_by WHERE r.id=?", [$refId])->fetch();
    if ($reception) $prefillDesc = "Serah terima barang atas penerimaan No. " . $reception['receive_no'];
}
$users = q("SELECT user_id, name, position, nama_bidang FROM user_cache WHERE is_active=1 ORDER BY name")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toUserId   = (int)($_POST['to_user_id'] ?? 0);
    $date       = $_POST['handover_date'] ?: date('Y-m-d');
    $itemsDesc  = trim($_POST['items_desc'] ?? '');
    $note       = trim($_POST['note'] ?? '');
    $sigGiver   = save_signature_png($_POST['sig_giver'] ?? null);
    $sigReceiver= save_signature_png($_POST['sig_receiver'] ?? null);

    if (!$toUserId) $errors[] = 'Penerima wajib dipilih.';
    if ($itemsDesc === '') $errors[] = 'Detail item yang diserahterimakan wajib diisi.';
    if (!$sigReceiver) $errors[] = 'Tanda tangan penerima wajib dibuat pada canvas.';

    if (!$errors) {
        $no = gen_no('handovers', 'handover_no', 'ST');
        q("INSERT INTO handovers (handover_no, ref_type, ref_id, from_user_id, to_user_id, handover_date, items_desc, sig_giver, sig_receiver, note)
           VALUES (?,?,?,?,?,?,?,?,?,?)",
          [$no, $refType, $refId ?: null, $_SESSION['user_id'], $toUserId, $date, $itemsDesc, $sigGiver, $sigReceiver, $note]);
        $hid = (int)db()->lastInsertId();

        // ==== Distribusi permintaan ATK: stok otomatis berkurang ====
        if ($refType === 'permintaan_atk' && $supplyReq) {
            db()->beginTransaction();
            try {
                foreach ($items as $it) {
                    $qty = max(0, (int)($_POST['deliver']['supply_' . (int)$it['supply_id']] ?? $it['qty_approved'] ?: $it['qty_requested']));
                    if ($qty <= 0) continue;
                    $sup = q("SELECT * FROM supplies WHERE id=? FOR UPDATE", [$it['supply_id']])->fetch();
                    if (!$sup || (int)$sup['stock'] < $qty) throw new RuntimeException('Stok "' . $sup['name'] . '" tidak mencukupi (sisa ' . (int)$sup['stock'] . ').');
                    q("UPDATE supplies SET stock = stock - ? WHERE id=?", [$qty, $it['supply_id']]);
                    q("INSERT INTO supply_transactions (supply_id,type,qty,ref_type,ref_id,from_unit,to_unit,note,created_by)
                       VALUES (?,'KELUAR',?,?,?,?,?,?,?)",
                      [$it['supply_id'], $qty, 'serah_terima', $hid, 'Gudang Umum',
                       user_name_of($toUserId), 'Distribusi via ' . $no, $_SESSION['user_id']]);
                    update_stock_warning($it['supply_id']);
                }
                q("UPDATE supply_requests SET status='DISTRIBUSI', distributed_by=?, handover_id=? WHERE id=?",
                  [$_SESSION['user_id'], $hid, $supplyReq['id']]);
                db()->commit();
            } catch (RuntimeException $ex) {
                db()->rollBack();
                q("DELETE FROM handovers WHERE id=?", [$hid]);
                $errors[] = $ex->getMessage();
            }
        }
        if (!$errors) {
            refresh_notification_cache();
            flash_set('success', 'Berita acara serah terima <b>' . e($no) . '</b> tersimpan. Stok terkait otomatis berkurang.');
            header('Location: serah_terima_detail.php?id=' . $hid);
            exit;
        }
    }
}

$pageTitle = 'Form Serah Terima'; $active = 'perlengkapan';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:900px;margin:0 auto">
  <h3>🤝 Berita Acara Serah Terima Barang</h3>
  <?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
  <form method="post">
    <div class="form-row">
      <div class="form-group"><label>Nomor Referensi</label>
        <input type="text" class="input" readonly value="<?= e($supplyReq['request_no'] ?? ($reception['receive_no'] ?? '-')) ?>">
        <input type="hidden" name="ref_dummy" value="1">
      </div>
      <div class="form-group"><label>Tanggal Serah Terima *</label><input type="date" name="handover_date" value="<?= e($_POST['handover_date'] ?? date('Y-m-d')) ?>" required></div>
      <div class="form-group"><label>Pihak Menerima (dari Data Pegawai) *</label>
        <select name="to_user_id" required>
          <option value="">- Pilih Penerima -</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= (int)$u['user_id'] ?>" <?= (int)($_POST['to_user_id'] ?? ($supplyReq['user_id'] ?? 0)) === (int)$u['user_id'] ? 'selected' : '' ?>>
            <?= e($u['name']) ?> — <?= e($u['position'] ?: 'Staff') ?><?= $u['nama_bidang'] ? ' (' . e($u['nama_bidang']) . ')' : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if ($items): ?>
    <div class="table-wrap"><table class="tbl">
      <tr><th>Barang</th><th class="num">Diminta</th><th class="num">Disetujui</th><th class="num">Stok Gudang</th><th class="num">Jumlah Diserahkan *</th></tr>
      <?php foreach ($items as $it): $sid = (int)$it['supply_id']; ?>
      <tr>
        <td><?= e($it['name']) ?> <small class="muted">(<?= e($it['unit']) ?>)</small></td>
        <td class="num"><?= (int)$it['qty_requested'] ?></td>
        <td class="num"><?= (int)$it['qty_approved'] ?></td>
        <td class="num"><?= (int)$it['stock'] ?><?= $it['stock'] < $it['qty_requested'] ? ' ⚠' : '' ?></td>
        <td><input type="number" min="0" max="<?= (int)$it['stock'] ?>" style="width:100px"
            name="deliver[supply_<?= $sid ?>]"
            value="<?= e($_POST['deliver']['supply_' . $sid] ?? max(1, (int)($it['qty_approved'] ?: $it['qty_requested']))) ?>"></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>

    <div class="form-group"><label>Detail Item yang Diserahterimakan *</label>
      <textarea name="items_desc" rows="<?= $items ? 2 : 4 ?>" required placeholder="Contoh: 10 rim kertas A4, 5 ballpoint, 1 unit monitor..."><?= e($_POST['items_desc'] ?? $prefillDesc) ?></textarea>
    </div>
    <div class="form-group"><label>Catatan Tambahan</label><textarea name="note" rows="2"><?= e($_POST['note'] ?? '') ?></textarea></div>

    <div class="form-row" style="margin-top:10px">
      <div class="form-group">
        <label>Tanda Tangan Pihak Menyerahkan</label>
        <canvas id="sigG" class="sig-canvas" width="400" height="150" style="border:1.5px dashed #94a3b8;border-radius:8px;background:#fff;width:100%;touch-action:none"></canvas>
        <button type="button" class="btn btn-sm btn-outline" onclick="clearSignature('sigG')">Bersihkan</button>
      </div>
      <div class="form-group">
        <label>Tanda Tangan Pihak Menerima *</label>
        <canvas id="sigR" class="sig-canvas" width="400" height="150" style="border:1.5px dashed #94a3b8;border-radius:8px;background:#fff;width:100%;touch-action:none"></canvas>
        <button type="button" class="btn btn-sm btn-outline" onclick="clearSignature('sigR')">Bersihkan</button>
      </div>
    </div>
    <input type="hidden" name="sig_giver" id="sigGiverData">
    <input type="hidden" name="sig_receiver" id="sigReceiverData">

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px">
      <a class="btn btn-outline" href="<?= $supplyReq ? 'perlengkapan.php?tab=request' : 'serah_terima.php' ?>">Batal</a>
      <button class="btn btn-primary"
        onsubmit="" onclick="exportSignature('sigG','sigGiverData');exportSignature('sigR','sigReceiverData')">
        💾 Simpan &amp; Proses Distribusi
      </button>
    </div>
  </form>
</div>
<script>
document.querySelector('form').addEventListener('submit', function () {
  exportSignature('sigG', 'sigGiverData');
  exportSignature('sigR', 'sigReceiverData');
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
