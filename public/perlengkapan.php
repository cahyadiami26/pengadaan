<?php
/**
 * Perlengkapan / ATK — Stok masuk & keluar, permintaan, persetujuan, distribusi,
 * stok minimum (stok_minimum_warning), riwayat transaksi.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Perlengkapan / ATK'; $active = 'perlengkapan';

$CATS = ['atk', 'perlengkapan', 'sparepart', 'barang_habis_pakai', 'peralatan'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // ---- Master barang (admin/staff) ----
    if ($act === 'save_item') {
        require_role(['admin', 'staff_pengadaan']);
        $id = (int)($_POST['id'] ?? 0);
        $minStock = (int)($_POST['min_stock'] ?? 0);
        if ($id) {
            q("UPDATE supplies SET name=?, category=?, unit=?, min_stock=?, last_price=?, location_id=?, note=? WHERE id=?",
              [trim($_POST['name']), in_array($_POST['category'], $CATS) ? $_POST['category'] : 'atk',
               trim($_POST['unit'] ?: 'pcs'), $minStock, (float)($_POST['last_price'] ?? 0),
               (int)($_POST['location_id'] ?? 0) ?: null, trim($_POST['note'] ?? ''), $id]);
            update_stock_warning($id);
            flash_set('success', 'Data barang diperbarui.');
        } else {
            $code = 'SPL-' . str_pad((string)(count_rows("SELECT COUNT(*) FROM supplies") + 1), 4, '0', STR_PAD_LEFT);
            while (q("SELECT id FROM supplies WHERE code=?", [$code])->fetch()) { $code++; }
            q("INSERT INTO supplies (code,name,category,unit,stock,min_stock,last_price,location_id,note) VALUES (?,?,?,?,0,?,?,?,?,?)",
              [$code, trim($_POST['name']), in_array($_POST['category'], $CATS) ? $_POST['category'] : 'atk',
               trim($_POST['unit'] ?: 'pcs'), $minStock, (float)($_POST['last_price'] ?? 0),
               (int)($_POST['location_id'] ?? 0) ?: null, trim($_POST['note'] ?? '')]);
            flash_set('success', 'Barang ditambahkan.');
        }
        header('Location: perlengkapan.php'); exit;
    }

    // ---- Stok masuk / keluar manual ----
    if ($act === 'transact') {
        require_role(['admin', 'staff_pengadaan']);
        $sid = (int)$_POST['supply_id'];
        $type = in_array($_POST['type'], ['MASUK', 'KELUAR']) ? $_POST['type'] : 'MASUK';
        $qty = max(1, (int)$_POST['qty']);
        $sup = q("SELECT * FROM supplies WHERE id=?", [$sid])->fetch();
        if (!$sup) { flash_set('danger', 'Barang tidak ditemukan.'); header('Location: perlengkapan.php'); exit; }
        if ($type === 'KELUAR' && $qty > (int)$sup['stock']) {
            flash_set('danger', 'Stok tidak mencukupi (sisa ' . (int)$sup['stock'] . ').'); header('Location: perlengkapan.php'); exit;
        }
        $delta = $type === 'MASUK' ? $qty : -$qty;
        q("UPDATE supplies SET stock = stock + ?, last_price = IF(? > 0, ?, last_price) WHERE id=?", [$delta, (float)($_POST['price'] ?? 0), (float)($_POST['price'] ?? 0), $sid]);
        q("INSERT INTO supply_transactions (supply_id,type,qty,ref_type,ref_id,from_unit,to_unit,note,created_by) VALUES (?,?,'','','',?,?,?,?)",
          [$sid, $type, $qty, 'manual', null, $type === 'KELUAR' ? ($_SESSION['nama_bidang'] ?? '') : null,
           $type === 'MASUK' ? 'Gudang Umum' : trim($_POST['to_unit'] ?? ''), trim($_POST['note'] ?? ''), $_SESSION['user_id']]);
        update_stock_warning($sid);
        refresh_notification_cache();
        flash_set('success', "Stok $type " . e($sup['name']) . " sebanyak $qty " . e($sup['unit']) . '.');
        header('Location: perlengkapan.php?tab=riwayat'); exit;
    }

    // ---- Permintaan baru (semua unit kerja) ----
    if ($act === 'create_request') {
        $no = gen_no('supply_requests', 'request_no', 'PA');
        q("INSERT INTO supply_requests (request_no,user_id,nama_bidang,nama_divisi,purpose,status) VALUES (?,?,?,?,?,'MENUNGGU')",
          [$no, $_SESSION['user_id'], $_SESSION['nama_bidang'] ?? '', $_SESSION['nama_divisi'] ?? '', trim($_POST['purpose'] ?? '')]);
        $rid = (int)db()->lastInsertId();
        $added = 0;
        foreach (($_POST['items'] ?? []) as $sid => $d) {
            $qty = max(0, (int)($d['qty'] ?? 0));
            if ($qty > 0 && $sid) {
                q("INSERT INTO supply_request_items (request_id,supply_id,qty_requested,qty_approved) VALUES (?,?,?,0)", [$rid, (int)$sid, $qty]);
                $added++;
            }
        }
        if (!$added) { q("DELETE FROM supply_requests WHERE id=?", [$rid]); flash_set('danger', 'Tidak ada item permintaan.'); }
        else { refresh_notification_cache(); flash_set('success', "Permintaan <b>$no</b> dikirim ke Bagian Umum."); }
        header('Location: perlengkapan.php?tab=request'); exit;
    }

    require_role(['admin', 'staff_pengadaan']);

    // ---- Verifikasi stok tersedia? ----
    if ($act === 'verify_request') {
        $rid = (int)$_POST['id'];
        $req = q("SELECT * FROM supply_requests WHERE id=? AND status='MENUNGGU'", [$rid])->fetch();
        if (!$req) { flash_set('danger', 'Permintaan tidak ditemukan / sudah diproses.'); header('Location: perlengkapan.php?tab=request'); exit; }
        $items = q("SELECT i.*, s.stock, s.name FROM supply_request_items i JOIN supplies s ON s.id=i.supply_id WHERE i.request_id=?", [$rid])->fetchAll();
        $available = true;
        foreach ($items as $it) {
            if ((int)$it['stock'] < (int)$it['qty_requested']) { $available = false; break; }
        }
        foreach ($items as $it) {
            q("UPDATE supply_request_items SET qty_approved=? WHERE id=?", [$available ? (int)$it['qty_requested'] : 0, $it['id']]);
        }
        q("UPDATE supply_requests SET status='DIPROSES', stock_available=?, verified_by=? WHERE id=?", [$available ? 1 : 0, $_SESSION['user_id'], $rid]);
        if (!$available) {
            // buat draft rencana pengadaan dari item yang tidak tersedia
            $pn = gen_no('procurement_plans', 'plan_no', 'RP');
            q("INSERT INTO procurement_plans (plan_no,plan_date,requesting_units,budget,priority,status,note,created_by) VALUES (?,CURDATE(),?,0,'Sedang','DRAFT',?,?)",
              [$pn, $req['nama_bidang'] ?: user_name_of($req['user_id']), 'Otomatis dari permintaan ATK ' . $req['request_no'] . ' (stok tidak tersedia)', $_SESSION['user_id']]);
            $planId = (int)db()->lastInsertId();
            foreach ($items as $it) {
                if ((int)$it['stock'] < (int)$it['qty_requested']) {
                    q("INSERT INTO procurement_plan_items (plan_id,item_name,category,qty,unit,est_price) VALUES (?,?,?,?,?,?)",
                      [$planId, $it['name'], 'barang_habis_pakai', (int)$it['qty_requested'] - (int)$it['stock'], 'pcs', (float)q("SELECT last_price FROM supplies WHERE id=?", [$it['supply_id']])->fetchColumn()]);
                }
            }
            flash_set('warning', "Stok tidak mencukupi. Draft rencana pengadaan <b>$pn</b> dibuat otomatis.");
        } else {
            flash_set('success', 'Stok tersedia. Silakan setujui & distribusikan.');
        }
        refresh_notification_cache();
        header('Location: perlengkapan.php?tab=request'); exit;
    }

    // ---- Persetujuan + distribusi (kurangi stok otomatis setelah serah terima) ----
    if ($act === 'approve_request') {
        $rid = (int)$_POST['id'];
        q("UPDATE supply_requests SET status='DISETUJUI', approved_by=? WHERE id=? AND status IN ('MENUNGGU','DIPROSES')", [$_SESSION['user_id'], $rid]);
        flash_set('success', 'Permintaan disetujui. Buat serah terima untuk mendistribusikan.');
        header('Location: perlengkapan.php?tab=request'); exit;
    }
    if ($act === 'reject_request') {
        $rid = (int)$_POST['id'];
        q("UPDATE supply_requests SET status='DITOLAK', approved_by=? WHERE id=?", [$_SESSION['user_id'], $rid]);
        refresh_notification_cache();
        flash_set('warning', 'Permintaan ditolak.');
        header('Location: perlengkapan.php?tab=request'); exit;
    }

    // ---- Selesai manual (arsip permintaan yang sudah terdistribusi) ----
    if ($act === 'finish_request') {
        $rid = (int)$_POST['id'];
        q("UPDATE supply_requests SET status='SELESAI' WHERE id=? AND status IN ('DISTRIBUSI','DISETUJUI')", [$rid]);
        refresh_notification_cache();
        flash_set('success', 'Permintaan diarsipkan sebagai SELESAI.');
        header('Location: perlengkapan.php?tab=request'); exit;
    }
}

$fCat = $_GET['kategori'] ?? '';
$fWarn = isset($_GET['warning']);
$tab = $_GET['tab'] ?? 'stok';

$where = []; $params = [];
if ($fCat && in_array($fCat, $CATS)) { $where[] = 's.category=?'; $params[] = $fCat; }
if ($fWarn) { $where[] = 's.stock_minimum_warning=1'; }
$sql = "SELECT s.*, l.name AS lokasi,
        (SELECT COALESCE(SUM(qty),0) FROM supply_transactions t WHERE t.supply_id=s.id AND t.type='MASUK') AS total_masuk,
        (SELECT COALESCE(SUM(qty),0) FROM supply_transactions t WHERE t.supply_id=s.id AND t.type='KELUAR') AS total_keluar
        FROM supplies s LEFT JOIN locations l ON l.id=s.location_id"
       . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY s.name';
$stmt = db()->prepare($sql); $stmt->execute($params); $supplies = $stmt->fetchAll();

$myRequests = q("SELECT r.*, (SELECT COUNT(*) FROM supply_request_items i WHERE i.request_id=r.id) AS item_count
                 FROM supply_requests r ORDER BY r.id DESC LIMIT 100")->fetchAll();
$rooms = q("SELECT id,name FROM locations WHERE type='ruang' ORDER BY name")->fetchAll();
$lowCount = count_rows("SELECT COUNT(*) FROM supplies WHERE stock_minimum_warning=1");

require __DIR__ . '/../includes/header.php';
?>
<div class="card">
<h3>🖇️ Perlengkapan Kantor / ATK
  <?php if ($lowCount): ?><span class="badge badge-danger">⚠ <?= (int)$lowCount ?> stok di bawah minimum</span><?php endif; ?>
  <?php if (is_in_role(['admin','staff_pengadaan'])): ?><button class="btn btn-sm btn-primary" onclick="openModal('mItem')">➕ Tambah Barang</button><?php endif; ?>
  <a class="btn btn-sm btn-teal" href="laporan_pdf.php?jenis=stok_atk">📄 Export PDF Stok</a>
</h3>
<div class="tabs">
  <a class="tab <?= $tab==='stok'?'active':'' ?>" href="perlengkapan.php">Stok Barang</a>
  <a class="tab <?= $tab==='request'?'active':'' ?>" href="perlengkapan.php?tab=request">Permintaan & Distribusi</a>
  <a class="tab <?= $tab==='riwayat'?'active':'' ?>" href="perlengkapan.php?tab=riwayat">Riwayat Transaksi</a>
</div>

<?php if ($tab === 'stok'): ?>
<form method="get" class="filters">
  <input type="hidden" name="tab" value="stok">
  <div class="form-group"><label>Kategori</label><select name="kategori" onchange="this.form.submit()">
    <option value="">Semua</option><?php foreach ($CATS as $c): ?><option value="<?= $c ?>" <?= $fCat===$c?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$c)) ?></option><?php endforeach; ?>
  </select></div>
  <label style="display:flex;align-items:center;gap:6px;padding-bottom:8px"><input type="checkbox" name="warning" value="1" <?= $fWarn?'checked':'' ?> onchange="this.form.submit()"> Hanya stok di bawah minimum</label>
</form>
<div class="table-wrap"><table class="tbl">
<tr><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th class="num">Stok</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Min.</th><th>Peringatan</th><th>Lokasi</th><th>Aksi</th></tr>
<?php foreach ($supplies as $s): ?>
<tr>
  <td><?= e($s['code']) ?></td>
  <td><b><?= e($s['name']) ?></b><br><small class="muted"><?= e($s['unit']) ?> · harga terakhir <?= rupiah($s['last_price']) ?></small></td>
  <td><?= e(str_replace('_',' ',$s['category'])) ?></td>
  <td class="num"><b><?= (int)$s['stock'] ?></b></td>
  <td class="num"><?= (int)$s['total_masuk'] ?></td><td class="num"><?= (int)$s['total_keluar'] ?></td>
  <td class="num"><?= (int)$s['min_stock'] ?></td>
  <td><?= $s['stock_minimum_warning'] ? '<span class="badge badge-danger">⚠ MENENGAH/HABIS</span>' : '<span class="badge badge-success">AMAN</span>' ?></td>
  <td><?= e($s['lokasi'] ?? 'Gudang Umum') ?></td>
  <td style="white-space:nowrap">
    <?php if (is_in_role(['admin','staff_pengadaan'])): ?>
    <button class="btn btn-sm btn-outline" onclick="editItem(<?= json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)">✏️</button>
    <button class="btn btn-sm btn-teal" title="Stok Masuk/Keluar" onclick="openTr(<?= (int)$s['id'] ?>,'<?= e($s['name']) ?>','<?= (int)$s['stock'] ?>')">🔄</button>
    <?php else: ?>—<?php endif; ?>
  </td>
</tr>
<?php endforeach; if (!$supplies): ?><tr><td colspan="10"><div class="empty-state"><div class="big">🖇️</div>Belum ada data barang. Tambahkan barang atau teruskan item dari Penerimaan Barang.</div></td></tr><?php endif; ?>
</table></div>

<?php elseif ($tab === 'request'): ?>
<button class="btn btn-sm btn-primary" onclick="openModal('mReq')">➕ Buat Permintaan ATK</button>
<div class="table-wrap" style="margin-top:12px"><table class="tbl">
<tr><th>No. Permintaan</th><th>Pemohon</th><th>Bidang/Divisi</th><th class="num">Item</th><th>Tgl</th><th>Stok?</th><th>Status</th><th>Aksi</th></tr>
<?php foreach ($myRequests as $rq): ?>
<tr>
  <td><b><?= e($rq['request_no']) ?></b><br><small class="muted"><?= e(mb_strimwidth($rq['purpose'] ?? '', 0, 40, '…')) ?></small></td>
  <td><?= avatar_html(q("SELECT avatar FROM user_cache WHERE user_id=?", [$rq['user_id']])->fetchColumn() ?: null, user_name_of($rq['user_id']), 24) ?> <?= e(user_name_of($rq['user_id'])) ?></td>
  <td><?= e($rq['nama_bidang'] ?? '-') ?><br><small class="muted"><?= e($rq['nama_divisi'] ?? '') ?></small></td>
  <td class="num"><?= (int)$rq['item_count'] ?></td><td><?= tgl($rq['created_at']) ?></td>
  <td><?= $rq['stock_available'] === null ? '-' : ($rq['stock_available'] ? '<span class="badge badge-success">YA</span>' : '<span class="badge badge-danger">TIDAK</span>') ?></td>
  <td><?= status_badge($rq['status']) ?></td>
  <td style="white-space:nowrap">
    <?php if (is_in_role(['admin','staff_pengadaan'])): ?>
      <?php if ($rq['status'] === 'MENUNGGU'): ?>
      <form method="post" style="display:inline"><input type="hidden" name="action" value="verify_request"><input type="hidden" name="id" value="<?= (int)$rq['id'] ?>"><button class="btn btn-sm btn-outline">✔ Verifikasi Stok</button></form>
      <?php elseif ($rq['status'] === 'DIPROSES' && $rq['stock_available']): ?>
      <form method="post" style="display:inline"><input type="hidden" name="action" value="approve_request"><input type="hidden" name="id" value="<?= (int)$rq['id'] ?>"><button class="btn btn-sm btn-teal">Setujui</button></form>
      <form method="post" style="display:inline" onsubmit="return confirm('Tolak permintaan ini?')"><input type="hidden" name="action" value="reject_request"><input type="hidden" name="id" value="<?= (int)$rq['id'] ?>"><button class="btn btn-sm btn-danger">Tolak</button></form>
      <?php elseif ($rq['status'] === 'DISETUJUI'): ?>
      <a class="btn btn-sm btn-primary" href="serah_terima_form.php?ref_type=permintaan_atk&ref_id=<?= (int)$rq['id'] ?>">🤝 Serah Terima</a>
      <?php elseif ($rq['status'] === 'DISTRIBUSI'): ?>
      <a class="btn btn-sm btn-outline" href="serah_terima.php?id=<?= (int)$rq['handover_id'] ?>">Lihat Serah Terima</a>
      <form method="post" style="display:inline"><input type="hidden" name="action" value="finish_request"><input type="hidden" name="id" value="<?= (int)$rq['id'] ?>"><button class="btn btn-sm btn-teal">Arsipkan Selesai</button></form>
      <?php elseif ($rq['status'] === 'SELESAI'): ?>
      <a class="btn btn-sm btn-outline" href="serah_terima.php?id=<?= (int)$rq['handover_id'] ?>">Lihat Serah Terima</a>
      <?php endif; ?>
    <?php else: ?>—<?php endif; ?>
  </td>
</tr>
<?php endforeach; if (!$myRequests): ?><tr><td colspan="8"><div class="empty-state"><div class="big">📄</div>Belum ada permintaan ATK.</div></td></tr><?php endif; ?>
</table></div>
<p class="muted" style="margin-top:8px">Alur: Unit Kerja → Bagian Umum verifikasi stok → (tidak tersedia ⇒ Rencana Pengadaan otomatis) → Persetujuan → Pengambilan → Distribusi via form Serah Terima → stok otomatis berkurang → riwayat tersimpan.</p>

<?php else: ?>
<div class="table-wrap"><table class="tbl">
<tr><th>Tanggal</th><th>Barang</th><th>Jenis</th><th class="num">Qty</th><th>Dari/Ke</th><th>Referensi</th><th>Catatan</th><th>Oleh</th></tr>
<?php
$txs = q("SELECT t.*, s.name, s.unit, uc.name AS pencatat FROM supply_transactions t
          JOIN supplies s ON s.id=t.supply_id LEFT JOIN user_cache uc ON uc.user_id=t.created_by
          ORDER BY t.id DESC LIMIT 200")->fetchAll();
foreach ($txs as $t): ?>
<tr>
  <td><?= tgl($t['created_at'], true) ?></td>
  <td><?= e($t['name']) ?></td>
  <td><?= $t['type'] === 'MASUK' ? '<span class="badge badge-success">MASUK</span>' : ($t['type'] === 'KELUAR' ? '<span class="badge badge-danger">KELUAR</span>' : '<span class="badge badge-info">MUTASI</span>') ?></td>
  <td class="num"><?= (int)$t['qty'] ?> <?= e($t['unit']) ?></td>
  <td><?= e($t['from_unit'] ?: '-') ?> → <?= e($t['to_unit'] ?: '-') ?></td>
  <td><small><?= e($t['ref_type'] ?? '') ?><?= $t['ref_id'] ? ' #' . (int)$t['ref_id'] : '' ?></small></td>
  <td><?= e($t['note'] ?: '-') ?></td>
  <td><?= e($t['pencatat'] ?? user_name_of($t['created_by'])) ?></td>
</tr>
<?php endforeach; if (!$txs): ?><tr><td colspan="8"><div class="empty-state"><div class="big">🧾</div>Belum ada transaksi stok.</div></td></tr><?php endif; ?>
</table></div>
<?php endif; ?>
</div>

<!-- Modal master barang -->
<div class="modal-backdrop" id="mItem"><div class="modal">
  <h3 id="itTitle">Tambah Barang</h3>
  <form method="post">
    <input type="hidden" name="action" value="save_item"><input type="hidden" name="id" id="itId">
    <div class="form-row">
      <div class="form-group"><label>Nama Barang *</label><input type="text" name="name" id="itName" required></div>
      <div class="form-group"><label>Kategori</label><select name="category" id="itCat"><?php foreach ($CATS as $c): ?><option value="<?= $c ?>"><?= ucfirst(str_replace('_',' ',$c)) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Satuan</label><input type="text" name="unit" id="itUnit" value="pcs"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Stok Minimum</label><input type="number" name="min_stock" id="itMin" min="0" value="0"></div>
      <div class="form-group"><label>Harga Terakhir (Rp)</label><input type="number" name="last_price" id="itPrice" min="0" step="100" value="0"></div>
      <div class="form-group"><label>Lokasi Simpan</label><select name="location_id" id="itLoc"><option value="">Gudang Umum</option><?php foreach ($rooms as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-group"><label>Catatan</label><textarea name="note" id="itNote" rows="2"></textarea></div>
    <div style="display:flex;gap:10px;justify-content:flex-end"><button type="button" class="btn btn-outline" onclick="closeModal('mItem')">Batal</button><button class="btn btn-primary">Simpan</button></div>
  </form>
</div></div>

<!-- Modal transaksi stok -->
<div class="modal-backdrop" id="mTr"><div class="modal">
  <h3>Stok Masuk / Keluar — <span id="trName"></span></h3>
  <p class="muted">Stok saat ini: <b id="trStock"></b></p>
  <form method="post">
    <input type="hidden" name="action" value="transact"><input type="hidden" name="supply_id" id="trSid">
    <div class="form-row">
      <div class="form-group"><label>Jenis</label><select name="type" id="trType"><option value="MASUK">MASUK (pembelian/pengembalian)</option><option value="KELUAR">KELUAR (pemakaian)</option></select></div>
      <div class="form-group"><label>Jumlah *</label><input type="number" name="qty" min="1" required></div>
      <div class="form-group"><label>Unit Peminta (jika keluar)</label><input type="text" name="to_unit"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Harga Satuan (jika masuk)</label><input type="number" name="price" min="0" step="100" value="0"></div>
      <div class="form-group"><label>Catatan</label><input type="text" name="note"></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end"><button type="button" class="btn btn-outline" onclick="closeModal('mTr')">Batal</button><button class="btn btn-primary">Simpan Transaksi</button></div>
  </form>
</div></div>

<!-- Modal permintaan ATK -->
<div class="modal-backdrop" id="mReq"><div class="modal" style="max-width:760px">
  <h3>➕ Permintaan Perlengkapan / ATK</h3>
  <form method="post">
    <input type="hidden" name="action" value="create_request">
    <div class="form-group"><label>Keperluan</label><input type="text" name="purpose" placeholder="mis. kebutuhan operasional bulan ini"></div>
    <div class="table-wrap"><table class="tbl">
      <tr><th>Barang</th><th class="num">Stok</th><th class="num">Jumlah Diminta</th></tr>
      <?php foreach ($supplies as $s): ?>
      <tr>
        <td><?= e($s['name']) ?> <small class="muted">(<?= e($s['unit']) ?>)</small></td>
        <td class="num"><?= (int)$s['stock'] ?><?= $s['stock_minimum_warning'] ? ' ⚠' : '' ?></td>
        <td><input type="number" name="items[<?= (int)$s['id'] ?>][qty]" min="0" value="0" style="width:90px"></td>
      </tr>
      <?php endforeach; if (!$supplies): ?><tr><td colspan="3" class="muted center">Belum ada barang terdaftar.</td></tr><?php endif; ?>
    </table></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:10px"><button type="button" class="btn btn-outline" onclick="closeModal('mReq')">Batal</button><button class="btn btn-primary">Kirim Permintaan</button></div>
  </form>
</div></div>

<script>
function editItem(s){
  document.getElementById('itTitle').textContent='Edit '+s.name;
  const v=(i,val)=>document.getElementById(i).value=val??'';
  v('itId',s.id);v('itName',s.name);v('itCat',s.category);v('itUnit',s.unit);v('itMin',s.min_stock);v('itPrice',s.last_price);v('itLoc',s.location_id);v('itNote',s.note);
  openModal('mItem');
}
function openTr(id,name,stock){
  document.getElementById('trSid').value=id;
  document.getElementById('trName').textContent=name;
  document.getElementById('trStock').textContent=stock;
  openModal('mTr');
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
