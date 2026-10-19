<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'staff_pengadaan', 'pimpinan']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: penghapusan_form.php'); exit; }
$aid = (int)$_POST['asset_id'];
$a = q("SELECT * FROM assets WHERE id=?", [$aid])->fetch();
if (!$a || $a['status'] === 'DIHAPUS') { flash_set('danger', 'Aset tidak valid.'); header('Location: penghapusan_form.php'); exit; }
$exists = count_rows("SELECT COUNT(*) FROM asset_disposals WHERE asset_id=?", [$aid]);
if ($exists) { flash_set('warning', 'Aset sudah dalam proses penghapusan.'); header('Location: penghapusan.php'); exit; }
$no = gen_no('asset_disposals', 'disposal_no', 'PHJ');
q("INSERT INTO asset_disposals (disposal_no,asset_id,reason,description,created_by) VALUES (?,?,?,?,?)",
  [$no, $aid, $_POST['reason'], trim($_POST['description'] ?? ''), $_SESSION['user_id']]);
flash_set('success', "Pengajuan <b>$no</b> dibuat. Menunggu verifikasi & persetujuan.");
header('Location: penghapusan.php');
