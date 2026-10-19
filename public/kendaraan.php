<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Manajemen Kendaraan'; $active = 'kendaraan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['admin', 'staff_pengadaan']);
    $act = $_POST['action'] ?? ''; $id = (int)$_POST['id'];
    if ($act === 'save') {
        $data = [':tnkb'=>trim($_POST['tnkb']),':bm'=>trim($_POST['brand_model']),':yr'=>(int)($_POST['year']?:0)?:null,
                 ':fn'=>trim($_POST['frame_no']??''),':en'=>trim($_POST['engine_no']??''),
                 ':cond'=>$_POST['condition_status'],':loc'=>trim($_POST['location']??''),':pic'=>trim($_POST['pic']??''),
                 ':st'=>$_POST['status'],':km'=>(int)($_POST['last_km']?:0),':si'=>(int)($_POST['service_interval_km']?:5000)];
        if ($id) { q("UPDATE vehicles SET tnkb=:tnkb,brand_model=:bm,year=:yr,frame_no=:fn,engine_no=:en,condition_status=:cond,location=:loc,pic=:pic,status=:st,last_km=:km,service_interval_km=:si WHERE id=:id", $data+[':id'=>$id]); flash_set('success','Data kendaraan diperbarui.'); }
        else { q("INSERT INTO vehicles (tnkb,brand_model,year,frame_no,engine_no,condition_status,location,pic,status,last_km,service_interval_km,next_service_km) VALUES (:tnkb,:bm,:yr,:fn,:en,:cond,:loc,:pic,:st,:km,:si,:nxt)", $data+[':nxt'=>(int)$data[':km']+(int)$data[':si']]); flash_set('success','Kendaraan ditambahkan.'); }
        header('Location: kendaraan.php'); exit;
    }
    if ($act === 'log_km') {
        $v = q("SELECT * FROM vehicles WHERE id=?", [$id])->fetch();
        $newKm = max(0, (int)$_POST['new_km']);
        if ($v && $newKm >= $v['last_km']) {
            q("UPDATE vehicles SET last_km=?, next_service_km=?+service_interval_km WHERE id=?", [$newKm, $newKm, $id]);
            flash_set('success', 'Kilometer diperbarui.');
        } else flash_set('danger', 'KM baru harus ≥ KM terakhir (' . (int)($v['last_km'] ?? 0) . ').');
        header('Location: kendaraan_detail.php?id=' . $id); exit;
    }
    if ($act === 'service') {
        q("INSERT INTO vehicle_services (vehicle_id,service_date,service_type,km_at_service,vendor,cost,note) VALUES (?,?,?,?,?,?,?)",
          [$id, $_POST['service_date'] ?: date('Y-m-d'), trim($_POST['service_type']), (int)($_POST['km_at_service'] ?: 0),
           trim($_POST['vendor'] ?? ''), (float)($_POST['cost'] ?: 0), trim($_POST['note'] ?? '')]);
        $svcId = (int)db()->lastInsertId();
        if (!empty($_POST['mark_done'])) { q("UPDATE vehicles SET status='TERSEDIA', next_service_km=? WHERE id=?", [(int)$_POST['km_at_service'] + q("SELECT service_interval_km FROM vehicles WHERE id=?",[$id])->fetchColumn(), $id]); }
        flash_set('success', 'Riwayat servis dicatat.');
        header('Location: kendaraan_detail.php?id=' . $id . '&svc=' . $svcId); exit;
    }
}

$rows = q("SELECT v.*, (SELECT SUM(cost) FROM vehicle_services s WHERE s.vehicle_id=v.id) AS total_svc_cost FROM vehicles v ORDER BY v.tnkb")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><h3>🚗 Kendaraan Operasional &amp; Truk (<?= count($rows) ?>)
  <span style="float:right;display:flex;gap:8px">
  <?php if (can('peminjaman')): ?><a class="btn btn-sm btn-teal" href="peminjaman.php">🔑 Peminjaman</a><?php endif; ?>
  <?php if (is_in_role(['admin','staff_pengadaan'])): ?><button class="btn btn-sm btn-primary" onclick="openModal('mAdd')">➕ Tambah Kendaraan</button><?php endif; ?>
  </span></h3>
<div class="table-wrap"><table class="tbl">
<tr><th>TNKB</th><th>Kendaraan</th><th>Tahun</th><th>Kondisi</th><th>Lokasi</th><th>PIC</th><th>Status</th><th class="num">KM Terakhir</th><th>Service Berikutnya</th><th class="num">Total Biaya Servis</th><th></th></tr>
<?php foreach ($rows as $v): $due = $v['next_service_km'] && $v['last_km'] >= $v['next_service_km'] - 1000; ?>
<tr>
  <td><b><a href="kendaraan_detail.php?id=<?= (int)$v['id'] ?>"><?= e($v['tnkb']) ?></a></b></td>
  <td><?= e($v['brand_model']) ?></td><td><?= e($v['year'] ?: '-') ?></td>
  <td><?= status_badge($v['condition_status']) ?></td><td><?= e($v['location'] ?: '-') ?></td><td><?= e($v['pic'] ?: '-') ?></td>
  <td><?= status_badge($v['status']) ?></td><td class="num"><?= number_format((int)$v['last_km'], 0, ',', '.') ?> km</td>
  <td><?= $v['next_service_km'] ? number_format((int)$v['next_service_km'],0,',','.') . ' km' . ($due ? ' <span class="badge badge-danger">Hampir Waktunya</span>' : '') : '-' ?></td>
  <td class="num"><?= rupiah($v['total_svc_cost'] ?? 0) ?></td>
  <td><?php if (is_in_role(['admin','staff_pengadaan'])): ?><button class="btn btn-sm btn-outline" onclick='editVeh(<?= json_encode($v, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button><?php endif; ?></td>
</tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="11"><div class="empty-state"><div class="big">🚗</div>Belum ada data kendaraan.</div></td></tr><?php endif; ?>
</table></div></div>

<div class="modal-backdrop" id="mAdd"><div class="modal">
  <h3 id="vTitle">Tambah Kendaraan</h3>
  <form method="post">
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" id="vId">
    <div class="form-row">
      <div class="form-group"><label>TNKB *</label><input type="text" name="tnkb" id="vTnkb" required placeholder="BE 1234 XY"></div>
      <div class="form-group"><label>Merk/Model *</label><input type="text" name="brand_model" id="vBm" required></div>
      <div class="form-group"><label>Tahun</label><input type="number" name="year" id="vYear" min="1980" max="2100"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>No. Rangka</label><input type="text" name="frame_no" id="vFn"></div>
      <div class="form-group"><label>No. Mesin</label><input type="text" name="engine_no" id="vEn"></div>
      <div class="form-group"><label>Kondisi</label><select name="condition_status" id="vCond"><option>BAIK</option><option>RUSAK RINGAN</option><option>RUSAK BERAT</option></select></div>
      <div class="form-group"><label>Status</label><select name="status" id="vSt"><option>TERSEDIA</option><option>DIGUNAKAN</option><option>SERVICE</option><option>NON OPERASIONAL</option></select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Lokasi Parkir</label><input type="text" name="location" id="vLoc"></div>
      <div class="form-group"><label>PIC / Driver</label><input type="text" name="pic" id="vPic"></div>
      <div class="form-group"><label>KM Terakhir</label><input type="number" name="last_km" id="vKm" min="0" value="0"></div>
      <div class="form-group"><label>Interval Service (km)</label><input type="number" name="service_interval_km" id="vSi" min="1000" value="5000"></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button type="button" class="btn btn-outline" onclick="closeModal('mAdd')">Batal</button><button class="btn btn-primary">Simpan</button></div>
  </form>
</div></div>
<script>
function editVeh(v){
  document.getElementById('vTitle').textContent='Edit '+v.tnkb;
  const s=(i,val)=>document.getElementById(i).value=val??'';
  s('vId',v.id);s('vTnkb',v.tnkb);s('vBm',v.brand_model);s('vYear',v.year);s('vFn',v.frame_no);s('vEn',v.engine_no);
  s('vCond',v.condition_status);s('vSt',v.status);s('vLoc',v.location);s('vPic',v.pic);s('vKm',v.last_km);s('vSi',v.service_interval_km);
  openModal('mAdd');
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
