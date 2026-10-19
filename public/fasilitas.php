<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Fasilitas Kantor & Gudang'; $active = 'fasilitas';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['admin', 'staff_pengadaan']);
    $act = $_POST['action'] ?? ''; $id = (int)$_POST['id'];
    if ($act === 'save_facility') {
        if ($id) {
            q("UPDATE facilities SET name=?, type=?, location_id=?, quantity=?, capacity=?, status=?, note=? WHERE id=?",
              [trim($_POST['name']), $_POST['type'], (int)$_POST['location_id'] ?: null, (int)$_POST['quantity'] ?: 1,
               trim($_POST['capacity'] ?? ''), in_array($_POST['status'],['BAIK','RUSAK','DIPINJAM']) ? $_POST['status'] : 'BAIK', trim($_POST['note'] ?? ''), $id]);
            flash_set('success', 'Fasilitas diperbarui.');
        } else {
            q("INSERT INTO facilities (name,type,location_id,quantity,capacity,status,note) VALUES (?,?,?,?,?,?,?)",
              [trim($_POST['name']), $_POST['type'], (int)$_POST['location_id'] ?: null, (int)$_POST['quantity'] ?: 1,
               trim($_POST['capacity'] ?? ''), $_POST['status'] ?: 'BAIK', trim($_POST['note'] ?? '')]);
            flash_set('success', 'Fasilitas ditambahkan.');
        }
    }
    if ($act === 'delete_facility' && is_in_role(['admin'])) { q("DELETE FROM facilities WHERE id=?", [$id]); flash_set('success', 'Fasilitas dihapus.'); }
    header('Location: fasilitas.php'); exit;
}

// update tiket (assign/selesai) boleh oleh admin/staff
if ($_SERVER['REQUEST_METHOD'] === 'POST') {} // sudah ditangani di atas

$fType = $_GET['tipe'] ?? '';
$stmtF = db()->prepare("SELECT f.*, l.name AS lokasi FROM facilities f LEFT JOIN locations l ON l.id=f.location_id" . ($fType ? " WHERE f.type=?" : "") . " ORDER BY f.type, f.name");
$stmtF->execute($fType ? [$fType] : []); $facilities = $stmtF->fetchAll();
$tickets = q("SELECT t.*, l.name AS lokasi, f.name AS fname, uc.name AS pelapor FROM facility_tickets t
              LEFT JOIN locations l ON l.id=t.location_id LEFT JOIN facilities f ON f.id=t.facility_id
              LEFT JOIN user_cache uc ON uc.user_id=t.reported_by ORDER BY t.id DESC")->fetchAll();
$rooms = q("SELECT id,name FROM locations WHERE type='ruang' ORDER BY name")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><h3>🏢 Fasilitas Kantor &amp; Gudang
  <?php if (is_in_role(['admin','staff_pengadaan'])): ?><button class="btn btn-sm btn-primary" onclick="openModal('mFac')">➕ Tambah Fasilitas</button><?php endif; ?>
  <a class="btn btn-sm btn-teal" href="laporan_pdf.php?jenis=inv_lokasi">📄 Export PDF Inventaris Ruangan</a></h3>
<form method="get" class="filters"><div class="form-group"><label>Tipe</label><select name="tipe" onchange="this.form.submit()">
<option value="">Semua</option><?php foreach (['AC','Proyektor','Meja','Kursi','Lemari','Printer','Televisi','Lainnya'] as $s): ?><option <?= $fType===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div></form>
<div class="grid grid-4">
<?php foreach ($facilities as $f): ?>
  <div style="border:1px solid var(--line);border-radius:10px;padding:12px">
    <b><?= e($f['name']) ?></b> <span class="badge badge-info"><?= e($f['type']) ?></span><br>
    <small class="muted">📍 <?= e($f['lokasi'] ?? '-') ?> · Qty <?= (int)$f['quantity'] ?><?= $f['capacity'] ? ' · Kapasitas: '.e($f['capacity']) : '' ?></small><br>
    <?= status_badge($f['status']) ?>
    <?php if (is_in_role(['admin','staff_pengadaan'])): ?>
    <div style="margin-top:8px;display:flex;gap:6px">
      <button class="btn btn-sm btn-outline" onclick='editFac(<?= json_encode($f, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️ Edit</button>
      <?php if (is_in_role(['admin'])): ?>
      <form method="post" onsubmit="return confirm('Hapus fasilitas ini?')"><input type="hidden" name="action" value="delete_facility"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn btn-sm btn-danger">🗑</button></form>
      <?php endif; ?>
    </div><?php endif; ?>
  </div>
<?php endforeach; if (!$facilities): ?><div class="empty-state" style="grid-column:1/-1"><div class="big">🏢</div>Belum ada data fasilitas.</div><?php endif; ?>
</div></div>

<div class="card"><h3>🎫 Tiket Keluhan Fasilitas <a class="btn btn-sm btn-primary" href="fasilitas_form.php">➕ Buat Tiket</a></h3>
<div class="table-wrap"><table class="tbl">
<tr><th>No. Tiket</th><th>Fasilitas/Lokasi</th><th>Masalah</th><th>Prioritas</th><th>Pelapor</th><th>Status</th><th>Aksi</th></tr>
<?php foreach ($tickets as $t): ?>
<tr>
  <td><b><a href="fasilitas_detail.php?id=<?= (int)$t['id'] ?>"><?= e($t['ticket_no']) ?></a></b></td>
  <td><?= e($t['fname'] ?? '') ?><?= e($t['lokasi'] ? ($t['fname'] ? ' · ' : '') . $t['lokasi'] : '') ?: '-' ?></td>
  <td><?= e($t['problem_type']) ?>: <?= e(mb_strimwidth($t['problem_desc'],0,60,'…')) ?></td>
  <td><?= status_badge($t['priority']) ?></td>
  <td><?= avatar_html(q("SELECT avatar FROM user_cache WHERE user_id=?",[$t['reported_by']])->fetchColumn()?:null, $t['pelapor']??'-',24) ?> <?= e($t['pelapor'] ?? '-') ?></td>
  <td><?= status_badge($t['status']) ?><?= $t['assigned_to'] ? '<br><small class="muted">→ '.e($t['assigned_to']).'</small>' : '' ?></td>
  <td style="white-space:nowrap">
    <?php if (is_in_role(['admin','staff_pengadaan'])): ?>
    <form method="post" action="fasilitas_tiket_proses.php" style="display:flex;gap:4px">
      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <select name="status" style="width:110px"><?php foreach (['LAPORAN','VERIFIKASI','PENUGASAN','PERBAIKAN','PEMERIKSAAN','SELESAI'] as $s): ?><option <?= $t['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
      <select name="priority" style="width:90px"><?php foreach (['Rendah','Normal','Tinggi','Segera'] as $pr): ?><option <?= $t['priority']===$pr?'selected':'' ?>><?= $pr ?></option><?php endforeach; ?></select>
      <button class="btn btn-sm btn-outline">Simpan</button>
    </form>
    <?php else: ?>—<?php endif; ?>
  </td>
</tr>
<?php endforeach; if (!$tickets): ?><tr><td colspan="7"><div class="empty-state"><div class="big">🎫</div>Belum ada tiket keluhan.</div></td></tr><?php endif; ?>
</table></div></div>

<div class="modal-backdrop" id="mFac"><div class="modal">
  <h3 id="facTitle">Tambah Fasilitas</h3>
  <form method="post">
    <input type="hidden" name="action" value="save_facility"><input type="hidden" name="id" id="fcId">
    <div class="form-row">
      <div class="form-group"><label>Nama *</label><input type="text" name="name" id="fcName" required></div>
      <div class="form-group"><label>Tipe</label><select name="type" id="fcType"><?php foreach (['AC','Proyektor','Meja','Kursi','Lemari','Printer','Televisi','Lainnya'] as $s): ?><option><?= $s ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Ruangan</label><select name="location_id" id="fcLoc"><option value="">- Pilih -</option><?php foreach ($rooms as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Jumlah</label><input type="number" name="quantity" id="fcQty" min="1" value="1"></div>
      <div class="form-group"><label>Kapasasitas</label><input type="text" name="capacity" id="fcCap" placeholder="mis. muat 12 orang"></div>
      <div class="form-group"><label>Status</label><select name="status" id="fcSt"><option>BAIK</option><option>RUSAK</option><option>DIPINJAM</option></select></div>
    </div>
    <div class="form-group"><label>Catatan</label><textarea name="note" id="fcNote" rows="2"></textarea></div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button type="button" class="btn btn-outline" onclick="closeModal('mFac')">Batal</button><button class="btn btn-primary">Simpan</button></div>
  </form>
</div></div>
<script>
function editFac(f){
  document.getElementById('facTitle').textContent='Edit Fasilitas';
  const s=(i,v)=>document.getElementById(i).value=v??'';
  s('fcId',f.id);s('fcName',f.name);s('fcType',f.type);s('fcLoc',f.location_id);s('fcQty',f.quantity);s('fcCap',f.capacity);s('fcSt',f.status);s('fcNote',f.note);
  openModal('mFac');
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
