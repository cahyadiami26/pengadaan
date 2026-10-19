<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Inventaris & Aset';
$active = 'inventaris';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['admin', 'staff_pengadaan']);
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    if ($act === 'save') {
        $aid = (int)($_POST['id'] ?? 0);
        $locId = (int)($_POST['location_id'] ?? 0) ?: null;
        $userId = (int)($_POST['user_id'] ?? 0) ?: null;
        $room  = $locId ? q("SELECT code,name FROM locations WHERE id=?", [$locId])->fetch() : null;
        $unit  = trim($_POST['unit'] ?? '') ?: ($_SESSION['nama_bidang'] ?? 'ADM');
        $data = [
            ':name' => trim($_POST['name']), ':cat' => in_array($_POST['category'], ['asset','inventaris']) ? $_POST['category'] : 'asset',
            ':sub' => trim($_POST['sub_category'] ?? ''), ':pdate' => $_POST['purchase_date'] ?: date('Y-m-d'),
            ':value' => (float)($_POST['value'] ?? 0), ':loc' => $locId, ':user' => $userId, ':unit' => $unit,
            ':cond' => in_array($_POST['condition_status'], ['BAIK','RUSAK RINGAN','RUSAK BERAT']) ? $_POST['condition_status'] : 'BAIK',
            ':status' => in_array($_POST['status'], ['AKTIF','RUSAK','DIPINDAHKAN','DIHAPUS','HILANG']) ? $_POST['status'] : 'AKTIF',
            ':sn' => trim($_POST['serial_number'] ?? ''), ':brand' => trim($_POST['brand'] ?? ''), ':model' => trim($_POST['model'] ?? ''),
            ':year' => (int)($_POST['year'] ?? 0) ?: null, ':note' => trim($_POST['note'] ?? ''),
        ];
        if ($aid) {
            $cur = q("SELECT * FROM assets WHERE id=?", [$aid])->fetch();
            if (!$cur) { flash_set('danger', 'Aset tidak ditemukan.'); header('Location: inventaris.php'); exit; }
            // jika lokasi/unit berubah dan bukan mutasi eksplisit -> catat histori otomatis
            if ((int)$cur['location_id'] !== (int)$locId || $cur['unit'] !== $unit || (int)$cur['user_id'] !== (int)$userId) {
                $oldLoc = $cur['location_id'] ? q("SELECT name FROM locations WHERE id=?", [$cur['location_id']])->fetchColumn() : '-';
                $newLoc = $room['name'] ?? '-';
                q("INSERT INTO asset_mutations (asset_id,from_unit,from_location,from_user,to_unit,to_location,to_user,reason,mutated_by)
                   VALUES (?,?,?,?,?,?,?,?,?)",
                  [$aid, $cur['unit'], $oldLoc, user_name_of($cur['user_id']), $unit, $newLoc, user_name_of($userId), 'Perubahan via form edit aset', $_SESSION['user_id']]);
            }
            $sets = []; foreach (array_keys($data) as $k) $sets[] = "$k=" . $k;
            q("UPDATE assets SET " . implode(',', $sets) . " WHERE id=:id", $data + [':id' => $aid]);
            flash_set('success', 'Aset diperbarui.');
        } else {
            $code = generate_asset_code($room['code'] ?? 'UMUM', $unit);
            q("INSERT INTO assets (code,name,category,sub_category,purchase_date,value,location_id,user_id,unit,condition_status,status,serial_number,brand,model,year,note,created_by)
               VALUES (:code,:name,:cat,:sub,:pdate,:value,:loc,:user,:unit,:cond,:status,:sn,:brand,:model,:year,:note,:cb)",
               $data + [':code' => $code, ':cb' => $_SESSION['user_id']]);
            $aid = (int)db()->lastInsertId();
            $qr = generate_qr(APP_URL . '/public/aset_view.php?code=' . $code);
            q("UPDATE assets SET qr_file=? WHERE id=?", [$qr, $aid]);
            flash_set('success', "Aset baru dibuat dengan kode <b>$code</b>.");
        }
        header('Location: aset_detail.php?id=' . $aid); exit;
    }

    if ($act === 'regen_qr') {
        $a = q("SELECT * FROM assets WHERE id=?", [$id])->fetch();
        if ($a) {
            @unlink(BASE_PATH . '/' . $a['qr_file']);
            $qr = generate_qr(APP_URL . '/public/aset_view.php?code=' . $a['code']);
            q("UPDATE assets SET qr_file=? WHERE id=?", [$qr, $id]);
            flash_set('success', 'QR Code dibuat ulang.');
        }
        header('Location: aset_detail.php?id=' . $id); exit;
    }

    if ($act === 'delete' && is_in_role(['admin'])) {
        q("DELETE FROM assets WHERE id=?", [$id]);
        flash_set('success', 'Aset dihapus permanen (gunakan status DIHAPUS untuk soft delete).');
        header('Location: inventaris.php'); exit;
    }
}

// ---- Filter ----
$fCat = $_GET['kategori'] ?? ''; $fLoc = (int)($_GET['lokasi'] ?? 0); $fStatus = $_GET['status'] ?? '';
$fCond = $_GET['kondisi'] ?? ''; $fUnit = $_GET['unit'] ?? ''; $s = trim($_GET['q'] ?? '');
$where = []; $params = [];
if ($fCat)   { $where[] = 'a.category = ?'; $params[] = $fCat; }
if ($fLoc)   { $where[] = 'a.location_id = ?'; $params[] = $fLoc; }
if ($fStatus){ $where[] = 'a.status = ?'; $params[] = $fStatus; }
if ($fCond)  { $where[] = 'a.condition_status = ?'; $params[] = $fCond; }
if ($fUnit)  { $where[] = 'a.unit LIKE ?'; $params[] = "%$fUnit%"; }
if ($s)      { $where[] = '(a.code LIKE ? OR a.name LIKE ? OR a.brand LIKE ? OR a.model LIKE ?)'; $params = array_merge($params, ["%$s%","%$s%","%$s%","%$s%"]); }
$sql = "SELECT a.*, l.name AS lokasi FROM assets a LEFT JOIN locations l ON l.id=a.location_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.id DESC';
$stmt = db()->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
$rooms = q("SELECT id,name,code FROM locations WHERE type='ruang' ORDER BY name")->fetchAll();
$totalValue = array_sum(array_map(fn($r) => (float)$r['value'], $rows));

require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h3>🏷️ Inventaris &amp; Aset (<?= count($rows) ?> item · Total Nilai <?= rupiah($totalValue) ?>)
    <span>
      <a class="btn btn-sm btn-outline" href="laporan_pdf.php?jenis=inv_lokasi&lokasi=<?= $fLoc ?>">📄 Export PDF Per Lokasi</a>
      <a class="btn btn-sm btn-teal" href="qr_batch.php<?= $fLoc ? '?lokasi=' . $fLoc : '' ?>">🖨️ Batch Print QR</a>
      <?php if (is_in_role(['admin','staff_pengadaan'])): ?><button class="btn btn-sm btn-primary" onclick="openModal('mAdd')">➕ Tambah Aset</button><?php endif; ?>
    </span>
  </h3>
  <form method="get" class="filters">
    <div class="form-group"><label>Cari</label><input type="text" name="q" value="<?= e($s) ?>" placeholder="Kode / nama / merk"></div>
    <div class="form-group"><label>Kategori</label><select name="kategori" onchange="this.form.submit()">
      <option value="">Semua</option><option value="asset" <?= $fCat==='asset'?'selected':'' ?>>Aset</option><option value="inventaris" <?= $fCat==='inventaris'?'selected':'' ?>>Inventaris</option></select></div>
    <div class="form-group"><label>Lokasi/Ruangan</label><select name="lokasi" onchange="this.form.submit()">
      <option value="">Semua</option><?php foreach ($rooms as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $fLoc===(int)$r['id']?'selected':'' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Status</label><select name="status" onchange="this.form.submit()">
      <option value="">Semua</option><?php foreach (['AKTIF','RUSAK','DIPINDAHKAN','DIHAPUS','HILANG'] as $x): ?><option <?= $fStatus===$x?'selected':'' ?>><?= $x ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Kondisi</label><select name="kondisi" onchange="this.form.submit()">
      <option value="">Semua</option><?php foreach (['BAIK','RUSAK RINGAN','RUSAK BERAT'] as $x): ?><option <?= $fCond===$x?'selected':'' ?>><?= $x ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-sm btn-outline">Terapkan</button>
    <a class="btn btn-sm btn-outline" href="inventaris.php">Reset</a>
  </form>
  <div class="table-wrap"><table class="tbl">
    <tr><th>Kode Aset</th><th>Nama</th><th>Kategori</th><th>Tanggal</th><th class="num">Nilai</th><th>Lokasi</th><th>Pengguna</th><th>Unit</th><th>Kondisi</th><th>Status</th><th>QR</th><th></th></tr>
    <?php if (!$rows): ?><tr><td colspan="12"><div class="empty-state"><div class="big">🏷️</div>Belum ada data aset.</div></td></tr><?php endif; ?>
    <?php foreach ($rows as $a): ?>
    <tr>
      <td><b><a href="aset_detail.php?id=<?= (int)$a['id'] ?>"><?= e($a['code']) ?></a></b></td>
      <td><?= e($a['name']) ?><?php if ($a['brand']||$a['model']): ?><br><small class="muted"><?= e(trim($a['brand'].' '.$a['model'])) ?><?= $a['year'] ? ' · '.$a['year'] : '' ?></small><?php endif; ?></td>
      <td><span class="badge badge-<?= $a['category']==='asset'?'primary':'info' ?>"><?= e(ucfirst($a['category'])) ?></span></td>
      <td><?= tgl($a['purchase_date']) ?></td><td class="num"><?= rupiah($a['value']) ?></td>
      <td><?= e($a['lokasi'] ?? '-') ?></td><td><?= e(user_name_of($a['user_id'])) ?></td><td><?= e($a['unit'] ?? '-') ?></td>
      <td><?= status_badge($a['condition_status']) ?></td><td><?= status_badge($a['status']) ?></td>
      <td><a href="<?= e(qr_img_src(APP_URL . '/public/aset_view.php?code=' . $a['code'])) ?>" target="_blank" title="Buka QR">▦</a></td>
      <td style="white-space:nowrap">
        <a class="btn btn-sm btn-outline" href="aset_detail.php?id=<?= (int)$a['id'] ?>">Detail</a>
        <?php if (is_in_role(['admin','staff_pengadaan'])): ?>
        <button class="btn btn-sm btn-outline" onclick='editAsset(<?= json_encode($a, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<!-- Modal form aset -->
<div class="modal-backdrop" id="mAdd"><div class="modal">
  <h3 id="mTitle">Tambah Aset</h3>
  <form method="post">
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" id="fId">
    <div class="form-row">
      <div class="form-group"><label>Nama Barang *</label><input type="text" name="name" id="fName" required></div>
      <div class="form-group"><label>Kategori *</label><select name="category" id="fCat"><option value="asset">Aset</option><option value="inventaris">Inventaris</option></select></div>
      <div class="form-group"><label>Sub Kategori</label><input type="text" name="sub_category" id="fSub" placeholder="Peralatan IT, Furnitur, ..."></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Tanggal Perolehan</label><input type="date" name="purchase_date" id="fDate" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group"><label>Nilai (Rp)</label><input type="number" name="value" id="fValue" min="0" step="1000"></div>
      <div class="form-group"><label>Merk</label><input type="text" name="brand" id="fBrand"></div>
      <div class="form-group"><label>Model</label><input type="text" name="model" id="fModel"></div>
      <div class="form-group"><label>Tahun</label><input type="number" name="year" id="fYear" min="1990" max="2100"></div>
      <div class="form-group"><label>Nomor Seri</label><input type="text" name="serial_number" id="fSn"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Lokasi / Ruangan</label><select name="location_id" id="fLoc"><option value="">- Pilih -</option>
        <?php foreach ($rooms as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?> (<?= e($r['code']) ?>)</option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Unit</label><input type="text" name="unit" id="fUnit" value="<?= e($_SESSION['nama_bidang'] ?? '') ?>"></div>
      <div class="form-group"><label>Pengguna</label><select name="user_id" id="fUser"><option value="">- Pilih -</option>
        <?php foreach (all_users_for_select() as $uid => $nm): ?><option value="<?= (int)$uid ?>"><?= e($nm) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Kondisi</label><select name="condition_status" id="fCond"><option>BAIK</option><option>RUSAK RINGAN</option><option>RUSAK BERAT</option></select></div>
      <div class="form-group"><label>Status</label><select name="status" id="fStatus"><option>AKTIF</option><option>RUSAK</option><option>DIPINDAHKAN</option><option>DIHAPUS</option><option>HILANG</option></select></div>
    </div>
    <div class="form-group"><label>Catatan</label><textarea name="note" id="fNote" rows="2"></textarea></div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button type="button" class="btn btn-outline" onclick="closeModal('mAdd')">Batal</button>
      <button class="btn btn-primary">Simpan</button>
    </div>
  </form>
</div></div>

<script>
function editAsset(a){
  document.getElementById('mTitle').textContent = 'Edit Aset — ' + a.code;
  const set=(id,v)=>{const el=document.getElementById(id); if(el) el.value=v??'';};
  set('fId',a.id); set('fName',a.name); set('fCat',a.category); set('fSub',a.sub_category);
  set('fDate',a.purchase_date); set('fValue',a.value); set('fBrand',a.brand); set('fModel',a.model);
  set('fYear',a.year); set('fSn',a.serial_number); set('fLoc',a.location_id); set('fUnit',a.unit);
  set('fUser',a.user_id); set('fCond',a.condition_status); set('fStatus',a.status); set('fNote',a.note);
  openModal('mAdd');
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
