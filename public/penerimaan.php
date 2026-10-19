<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Penerimaan Barang/Jasa';
$active = 'penerimaan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['admin', 'staff_pengadaan']);
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    if ($act === 'save') {
        // Buat/update penerimaan + item
        $rid = (int)($_POST['id'] ?? 0);
        $status = in_array($_POST['status'], ['DITERIMA','DITERIMA SEBAGIAN','DITOLAK']) ? $_POST['status'] : 'DITERIMA';
        if (!$rid) {
            $no = gen_no('receptions', 'receive_no', 'PB');
            q("INSERT INTO receptions (receive_no,plan_id,need_id,supplier,invoice_no,receive_date,received_by,status,chk_qty,chk_spec,chk_condition,chk_docs,note)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
              [$no, $_POST['plan_id'] ?: null, $_POST['need_id'] ?: null, trim($_POST['supplier'] ?? ''), trim($_POST['invoice_no'] ?? ''),
               $_POST['receive_date'] ?: date('Y-m-d'), $_SESSION['user_id'], $status,
               isset($_POST['chk_qty'])?1:0, isset($_POST['chk_spec'])?1:0, isset($_POST['chk_condition'])?1:0, isset($_POST['chk_docs'])?1:0,
               trim($_POST['note'] ?? '')]);
            $rid = (int)db()->lastInsertId();
        } else {
            q("UPDATE receptions SET plan_id=?,need_id=?,supplier=?,invoice_no=?,receive_date=?,status=?,chk_qty=?,chk_spec=?,chk_condition=?,chk_docs=?,note=? WHERE id=?",
              [$_POST['plan_id'] ?: null, $_POST['need_id'] ?: null, trim($_POST['supplier']), trim($_POST['invoice_no']),
               $_POST['receive_date'], $status,
               isset($_POST['chk_qty'])?1:0, isset($_POST['chk_spec'])?1:0, isset($_POST['chk_condition'])?1:0, isset($_POST['chk_docs'])?1:0,
               trim($_POST['note']), $rid]);
            q("DELETE FROM reception_items WHERE reception_id=? AND to_stock=0", [$rid]);
        }
        foreach ($_POST['item_name'] ?? [] as $i => $nm) {
            if (trim($nm) === '') continue;
            q("INSERT INTO reception_items (reception_id,item_name,category,qty_ordered,qty_received,unit,price,note) VALUES (?,?,?,?,?,?,?,?)",
              [$rid, trim($nm), $_POST['category'][$i] ?: 'asset', max(0,(int)$_POST['qty_ordered'][$i]), max(0,(int)$_POST['qty_received'][$i]),
               $_POST['unit'][$i] ?: 'unit', (float)$_POST['price'][$i], trim($_POST['item_note'][$i] ?? '')]);
        }
        // update status rencana terkait -> DITERIMA jika seluruhnya diterima
        if ($status === 'DITERIMA') {
            $rec = q("SELECT * FROM receptions WHERE id=?", [$rid])->fetch();
            if ($rec['plan_id']) q("UPDATE procurement_plans SET status='DITERIMA' WHERE id=? AND status IN ('DALAM_PROSES_PENGADAAN','FINAL_KEUANGAN')", [$rec['plan_id']]);
            if ($rec['need_id']) q("UPDATE needs_requests SET status='DITERIMA' WHERE id=? AND status='DALAM_PROSES_PENGADAAN'", [$rec['need_id']]);
        }
        flash_set('success', 'Penerimaan disimpan. Teruskan item ke modul stok sesuai kategori pada halaman detail.');
        header('Location: penerimaan_detail.php?id=' . $rid); exit;
    }

    if ($act === 'push_to_stock') {
        // Aksi per item: teruskan ke penambahan stok sesuai kategori item
        $it = q("SELECT ri.*, r.receive_no FROM reception_items ri JOIN receptions r ON r.id=ri.reception_id WHERE ri.id=?", [$id])->fetch();
        if (!$it || $it['to_stock']) { flash_set('danger', 'Item tidak dapat diproses.'); header('Location: penerimaan_detail.php?id=' . (int)$_GET['rid']); exit; }

        $catMap = ['peralatan_kantor' => 'perlengkapan', 'sparepart' => 'sparepart', 'barang_habis_pakai' => 'barang_habis_pakai', 'jasa' => null];
        if (in_array($it['category'], ['asset', 'inventaris'])) {
            // Aset/inventaris -> buat record aset baru
            $loc = q("SELECT id, code FROM locations WHERE type='ruang' ORDER BY id LIMIT 1")->fetch();
            $code = generate_asset_code($loc['code'] ?? 'UMUM', $_SESSION['nama_bidang'] ?? 'ADM');
            q("INSERT INTO assets (code,name,category,purchase_date,value,location_id,unit,status,condition_status,created_by)
               VALUES (?,?,?,CURDATE(),?,?,?,?, 'AKTIF','BAIK',?)",
              [$code, $it['item_name'], $it['category'], $it['price'] * $it['qty_received'], $loc['id'] ?? null, $_SESSION['nama_bidang'] ?? '', $_SESSION['user_id']]);
            $assetId = (int)db()->lastInsertId();
            $qrPath = generate_qr(APP_URL . '/public/aset_view.php?code=' . $code);
            q("UPDATE assets SET qr_file=? WHERE id=?", [$qrPath, $assetId]);
            q("UPDATE reception_items SET to_stock=1, asset_id=? WHERE id=?", [$assetId, $id]);
            flash_set('success', "Aset/inventaris dibuat: <b>$code</b> (QR tersimpan di uploads/qr).");
        } elseif (isset($catMap[$it['category']]) && $catMap[$it['category']] === null) {
            q("UPDATE reception_items SET to_stock=1 WHERE id=?", [$id]);
            flash_set('info', 'Item jasa — tidak memerlukan penambahan stok. Ditandai selesai.');
        } else {
            $sCat = $catMap[$it['category']] ?? 'atk';
            $sup = q("SELECT id, stock FROM supplies WHERE name=? AND category=?", [$it['item_name'], $sCat])->fetch();
            if ($sup) {
                $newStock = (int)$sup['stock'] + (int)$it['qty_received'];
                q("UPDATE supplies SET stock=?, last_price=IF(? > 0, ?, last_price) WHERE id=?", [$newStock, $it['price'], $it['price'], $sup['id']]);
                q("INSERT INTO supply_transactions (supply_id,type,qty,ref_type,ref_id,note,created_by) VALUES (?, 'MASUK', ?, 'penerimaan', ?, ?, ?)",
                  [$sup['id'], $it['qty_received'], $it['reception_id'], 'Penerimaan ' . $it['receive_no'], $_SESSION['user_id']]);
                $sid = $sup['id'];
            } else {
                $code = 'SPL-' . str_pad((string)(count_rows("SELECT COUNT(*) FROM supplies") + 1), 4, '0', STR_PAD_LEFT);
                q("INSERT INTO supplies (code,name,category,unit,stock,min_stock,last_price) VALUES (?,?,?,?,?,?,?)",
                  [$code, $it['item_name'], $sCat, $it['unit'], $it['qty_received'], 0, $it['price']]);
                $sid = (int)db()->lastInsertId();
                q("INSERT INTO supply_transactions (supply_id,type,qty,ref_type,ref_id,note,created_by) VALUES (?, 'MASUK', ?, 'penerimaan', ?, ?, ?)",
                  [$sid, $it['qty_received'], $it['reception_id'], 'Penerimaan ' . $it['receive_no'] . ' (barang baru)', $_SESSION['user_id']]);
            }
            q("UPDATE supplies SET stock_minimum_warning=(stock<=min_stock AND min_stock>0) WHERE id=?", [$sid]);
            q("UPDATE reception_items SET to_stock=1 WHERE id=?", [$id]);
            flash_set('success', 'Stok bertambah ' . (int)$it['qty_received'] . ' ' . e($it['unit']) . ' untuk "' . e($it['item_name']) . '".');
        }
        header('Location: penerimaan_detail.php?id=' . (int)$_GET['rid']); exit;
    }

    if ($act === 'delete' && is_in_role(['admin'])) {
        q("DELETE FROM receptions WHERE id=?", [$id]);
        flash_set('success', 'Penerimaan dihapus.'); header('Location: penerimaan.php'); exit;
    }
}

$fStatus = $_GET['status'] ?? '';
$sql = "SELECT r.*, (SELECT COUNT(*) FROM reception_items i WHERE i.reception_id=r.id) AS item_count,
        (SELECT COUNT(*) FROM reception_items i WHERE i.reception_id=r.id AND i.to_stock=0 AND i.qty_received>0) AS pending_items
        FROM receptions r" . ($fStatus ? " WHERE r.status=?" : "") . " ORDER BY r.id DESC";
$stmt = db()->prepare($sql);
$stmt->execute($fStatus ? [$fStatus] : []);
$rows = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h3>📦 Penerimaan Barang/Jasa
    <?php if (is_in_role(['admin','staff_pengadaan'])): ?><a class="btn btn-sm btn-primary" href="penerimaan_form.php">➕ Catat Penerimaan</a><?php endif; ?>
  </h3>
  <form method="get" class="filters">
    <div class="form-group"><label>Status</label><select name="status" onchange="this.form.submit()">
      <option value="">Semua</option>
      <?php foreach (['DITERIMA','DITERIMA SEBAGIAN','DITOLAK'] as $s): ?><option <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
    </select></div>
  </form>
  <div class="table-wrap"><table class="tbl">
    <tr><th>Nomor</th><th>Tanggal</th><th>Supplier</th><th>No. Invoice</th><th>Dari</th><th class="num">Item</th><th class="num">Belum Masuk Stok</th><th>Status</th><th></th></tr>
    <?php if (!$rows): ?><tr><td colspan="9"><div class="empty-state"><div class="big">📦</div>Belum ada penerimaan.</div></td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><a href="penerimaan_detail.php?id=<?= (int)$r['id'] ?>"><?= e($r['receive_no']) ?></a></b></td>
      <td><?= tgl($r['receive_date']) ?></td><td><?= e($r['supplier'] ?? '-') ?></td><td><?= e($r['invoice_no'] ?? '-') ?></td>
      <td><?= $r['plan_id'] ? '<a href="rencana_pengadaan_detail.php?id='.(int)$r['plan_id'].'">Rencana</a>' : ($r['need_id'] ? '<a href="kebutuhan_detail.php?id='.(int)$r['need_id'].'">Kebutuhan</a>' : '-') ?></td>
      <td class="num"><?= (int)$r['item_count'] ?></td>
      <td class="num"><?= $r['pending_items'] ? '<span class="badge badge-warning">' . (int)$r['pending_items'] . '</span>' : '✔' ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td><a class="btn btn-sm btn-outline" href="penerimaan_detail.php?id=<?= (int)$r['id'] ?>">Detail</a></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
