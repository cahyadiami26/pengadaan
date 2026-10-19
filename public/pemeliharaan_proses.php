<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'staff_pengadaan']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: pemeliharaan_form.php'); exit; }
$aid = (int)$_POST['asset_id'];
if (!q("SELECT id FROM assets WHERE id=?", [$aid])->fetch()) { flash_set('danger', 'Aset tidak valid.'); header('Location: pemeliharaan_form.php'); exit; }
$no = gen_no('maintenances', 'maint_no', 'PM');
q("INSERT INTO maintenances (maint_no,asset_id,damage_report_id,damage_desc,maint_date,vendor,cost,action_taken,technician,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
  [$no, $aid, (int)($_POST['damage_report_id'] ?? 0) ?: null, trim($_POST['damage_desc'] ?? ''), $_POST['maint_date'],
   trim($_POST['vendor'] ?? ''), (float)($_POST['cost'] ?? 0), trim($_POST['action_taken'] ?? ''),
   trim($_POST['technician'] ?? ''), in_array($_POST['status'], ['JANJI','PENGERJAAN','SELESAI','BATAL']) ? $_POST['status'] : 'JANJI',
   $_SESSION['user_id']]);
if ($_POST['status'] === 'SELESAI') {
    q("UPDATE assets SET condition_status='BAIK', status='AKTIF' WHERE id=?", [$aid]);
    if ($did = (int)($_POST['damage_report_id'] ?? 0)) q("UPDATE damage_reports SET status='SELESAI' WHERE id=?", [$did]);
}
flash_set('success', "Pemeliharaan <b>$no</b> tersimpan.");
header('Location: pemeliharaan_detail.php?id=' . db()->lastInsertId());
