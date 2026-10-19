<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!empty($_POST['create'])) {
    $no = gen_no('facility_tickets', 'ticket_no', 'FK');
    q("INSERT INTO facility_tickets (ticket_no,facility_id,location_id,problem_type,problem_desc,priority,reported_by,status) VALUES (?,?,?,?,?,?,?,'LAPORAN')",
      [$no, (int)($_POST['facility_id'] ?? 0) ?: null, (int)($_POST['location_id'] ?? 0) ?: null,
       $_POST['problem_type'] ?? 'Lainnya', trim($_POST['problem_desc']), in_array($_POST['priority'] ?? '', ['Rendah','Normal','Tinggi','Segera']) ? $_POST['priority'] : 'Normal', $_SESSION['user_id']]);
    flash_set('success', "Tiket <b>$no</b> terkirim.");
    header('Location: fasilitas.php'); exit;
}
require_role(['admin', 'staff_pengadaan']);
$id = (int)$_POST['id'];
q("UPDATE facility_tickets SET status=?, priority=?, assigned_to=? WHERE id=?",
  [in_array($_POST['status'], ['LAPORAN','VERIFIKASI','PENUGASAN','PERBAIKAN','PEMERIKSAAN','SELESAI']) ? $_POST['status'] : 'LAPORAN',
   in_array($_POST['priority'] ?? '', ['Rendah','Normal','Tinggi','Segera']) ? $_POST['priority'] : 'Normal',
   trim($_POST['assigned_to'] ?? '') ?: null, $id]);
flash_set('success', 'Tiket diperbarui.');
header('Location: fasilitas.php');
