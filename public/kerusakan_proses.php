<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: kerusakan_form.php'); exit; }
$aid = (int)($_POST['asset_id'] ?? 0) ?: null;
$a = $aid ? q("SELECT * FROM assets WHERE id=?", [$aid])->fetch() : null;
$no = gen_no('damage_reports', 'report_no', 'KB');
q("INSERT INTO damage_reports (report_no,asset_id,item_name,location_id,reported_by,damage_type,damage_desc,status) VALUES (?,?,?,?,?,?,?,'DILAPORKAN')",
  [$no, $aid, trim($_POST['item_name'] ?? '') ?: ($a['name'] ?? ''),
   $a['location_id'] ?? null, $_SESSION['user_id'], $_POST['damage_type'] ?? 'Fisik', trim($_POST['damage_desc'])]);
if ($a) q("UPDATE assets SET condition_status=IF(condition_status='BAIK','RUSAK RINGAN',condition_status), status='RUSAK' WHERE id=?", [$aid]);
flash_set('success', "Laporan <b>$no</b> terkirim. Tim pengadaan akan menindaklanjuti.");
header('Location: kerusakan.php');
