<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Rencana Pengadaan';
$active = 'rencana_pengadaan';

$statuses = ['DRAFT','DIAJUKAN','DISSETUJUI_KADIV','DISETUJUI_DIREKTUR','FINAL_KEUANGAN','DALAM_PROSES_PENGADAAN','DITERIMA','DITOLAK'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    if ($act === 'save') {
        require_role(['admin', 'staff_pengadaan']);
        $planId = (int)($_POST['id'] ?? 0);
        $itemNames = $_POST['item_name'] ?? [];
        $calcTotal = 0;
        foreach ($itemNames as $i => $nm) { if (trim($nm) !== '') $calcTotal += max(1,(int)$_POST['qty'][$i]) * (float)$_POST['est_price'][$i]; }
        $budget = (float)($_POST['budget'] ?: $calcTotal);
        if (!$planId) {
            $no = gen_no('procurement_plans', 'plan_no', 'RP');
            q("INSERT INTO procurement_plans (plan_no,plan_date,requesting_units,budget,priority,status,note,created_by) VALUES (?,?,?,?,?, 'DRAFT',?,?)",
              [$no, $_POST['plan_date'] ?: date('Y-m-d'), trim($_POST['requesting_units'] ?? ''), $budget, $_POST['priority'], trim($_POST['note'] ?? ''), $_SESSION['user_id']]);
            $planId = (int)db()->lastInsertId();
        } else {
            q("UPDATE procurement_plans SET plan_date=?, requesting_units=?, budget=?, priority=?, note=? WHERE id=? AND status='DRAFT'",
              [$_POST['plan_date'], trim($_POST['requesting_units']), $budget, $_POST['priority'], trim($_POST['note'] ?? ''), $planId]);
            q("DELETE FROM procurement_plan_items WHERE plan_id=?", [$planId]);
        }
        foreach ($itemNames as $i => $nm) {
            if (trim($nm) === '') continue;
            q("INSERT INTO procurement_plan_items (plan_id,item_name,category,qty,unit,est_price) VALUES (?,?,?,?,?,?)",
              [$planId, trim($nm), $_POST['category'][$i], max(1,(int)$_POST['qty'][$i]), $_POST['unit'][$i] ?: 'unit', (float)$_POST['est_price'][$i]]);
        }
        flash_set('success', 'Rencana pengadaan disimpan.');
        header('Location: rencana_pengadaan_detail.php?id=' . $planId); exit;
    }

    // Gabungkan beberapa pengajuan DIAJUKAN/DIVERIFIKASI menjadi satu rencana (fitur bagian Umum-Pengadaan)
    if ($act === 'merge_needs') {
        require_role(['admin', 'staff_pengadaan']);
        $needIds = array_map('intval', $_POST['need_ids'] ?? []);
        $needIds = array_filter($needIds, fn($x) => $x > 0);
        if (!$needIds) { flash_set('danger', 'Pilih minimal satu permintaan unit.'); header('Location: rencana_pengadaan.php'); exit; }
        $in = implode(',', array_fill(0, count($needIds), '?'));
        $needs = q("SELECT * FROM needs_requests WHERE id IN ($in) AND status IN ('DIAJUKAN','DIVERIFIKASI')", $needIds)->fetchAll();
        if (!$needs) { flash_set('danger', 'Tidak ada permintaan valid untuk digabung.'); header('Location: rencana_pengadaan.php'); exit; }

        $no = gen_no('procurement_plans', 'plan_no', 'RP');
        $units = implode(', ', array_unique(array_filter(array_column($needs, 'nama_bidang'))));
        q("INSERT INTO procurement_plans (plan_no,plan_date,requesting_units,budget,priority,status,note,created_by) VALUES (?,CURDATE(),?,?, 'Sedang','DRAFT',?,?)",
          [$no, $units ?: '-', 0, 'Gabungan ' . count($needs) . ' permintaan unit', $_SESSION['user_id']]);
        $planId = (int)db()->lastInsertId();
        $total = 0;
        foreach ($needs as $n) {
            foreach (q("SELECT * FROM needs_items WHERE need_id=?", [$n['id']])->fetchAll() as $it) {
                q("INSERT INTO procurement_plan_items (plan_id,need_item_id,item_name,category,qty,unit,est_price) VALUES (?,?,?,?,?,?,?)",
                  [$planId, $it['id'], $it['item_name'], $it['category'], $it['qty'], $it['unit'], $it['est_price']]);
                $total += $it['qty'] * $it['est_price'];
            }
            q("UPDATE needs_requests SET status='MASUK_RENCANA_PENGADAAN', plan_id=? WHERE id=?", [$planId, $n['id']]);
        }
        q("UPDATE procurement_plans SET budget=? WHERE id=?", [$total, $planId]);
        flash_set('success', "Rencana $no dibuat dari gabungan " . count($needs) . " permintaan unit.");
        header('Location: rencana_pengadaan_detail.php?id=' . $planId); exit;
    }

    $row = $id ? q("SELECT * FROM procurement_plans WHERE id=?", [$id])->fetch() : null;
    if (!$row) { flash_set('danger', 'Data tidak ditemukan.'); header('Location: rencana_pengadaan.php'); exit; }

    switch ($act) {
        case 'submit': // DRAFT -> DIAJUKAN (oleh bagian pengadaan)
            require_role(['admin', 'staff_pengadaan']);
            if ($row['status'] !== 'DRAFT') break;
            q("UPDATE procurement_plans SET status='DIAJUKAN' WHERE id=?", [$id]);
            flash_set('success', 'Rencana diajukan untuk persetujuan.'); break;

        case 'approve1': // DIAJUKAN -> oleh Kepala Divisi Secretariat / Umum & Keuangan
            require_role(['kepala_divisi', 'admin']);
            if ($row['status'] !== 'DIAJUKAN') break;
            q("UPDATE procurement_plans SET status='DISSETUJUI_KADIV', approved1_by=?, approved1_at=NOW() WHERE id=?", [$_SESSION['user_id'], $id]);
            flash_set('success', 'Disetujui Kepala Divisi (Secretariat/Umum & Keuangan). Menunggu Direktur Operasional.'); break;

        case 'approve2': // -> Direktur Operasional
            require_role(['pimpinan', 'admin']);
            if ($row['status'] !== 'DISSETUJUI_KADIV') break;
            q("UPDATE procurement_plans SET status='DISETUJUI_DIREKTUR', approved2_by=?, approved2_at=NOW() WHERE id=?", [$_SESSION['user_id'], $id]);
            flash_set('success', 'Disetujui Direktur Operasional. Menunggu finalisasi Finance.'); break;

        case 'finance': // -> Final rencana ke Finance (Kadiv Umum & Keuangan)
            require_role(['kepala_divisi', 'admin']);
            if ($row['status'] !== 'DISETUJUI_DIREKTUR') break;
            q("UPDATE procurement_plans SET status='FINAL_KEUANGAN', finance_by=?, finance_at=NOW() WHERE id=?", [$_SESSION['user_id'], $id]);
            flash_set('success', 'Rencana difinalisasi ke Finance. Siap proses pengadaan.'); break;

        case 'process': // -> DALAM PROSES PENGADAAN
            require_role(['admin', 'staff_pengadaan']);
            if (!in_array($row['status'], ['FINAL_KEUANGAN', 'DISETUJUI_DIREKTUR'])) break;
            q("UPDATE procurement_plans SET status='DALAM_PROSES_PENGADAAN' WHERE id=?", [$id]);
            flash_set('success', 'Rencana masuk dalam proses pengadaan.'); break;

        case 'receive': // -> DITERIMA
            require_role(['admin', 'staff_pengadaan']);
            if ($row['status'] !== 'DALAM_PROSES_PENGADAAN') break;
            q("UPDATE procurement_plans SET status='DITERIMA' WHERE id=?", [$id]);
            q("UPDATE needs_requests SET status='DITERIMA' WHERE plan_id=? AND status IN ('MASUK_RENCANA_PENGADAAN','DALAM_PROSES_PENGADAAN')", [$id]);
            flash_set('success', 'Rencana pengadaan selesai — barang diterima.'); break;

        case 'reject':
            require_role(['kepala_divisi', 'pimpinan', 'admin']);
            if ($row['status'] === 'DRAFT') break;
            q("UPDATE procurement_plans SET status='DITOLAK', note=CONCAT(COALESCE(note,''),' | Ditolak: ',?) WHERE id=?", [trim($_POST['reason'] ?? ''), $id]);
            flash_set('warning', 'Rencana ditolak.'); break;
    }
    header('Location: ' . (isset($_POST['back_detail']) ? 'rencana_pengadaan_detail.php?id=' . $id : 'rencana_pengadaan.php')); exit;
}

$fStatus = $_GET['status'] ?? '';
$sql = "SELECT p.*, (SELECT COUNT(*) FROM procurement_plan_items i WHERE i.plan_id=p.id) AS item_count ORDER BY p.id DESC";
$rows = db()->prepare($sql); $rows->execute(); $rows = $rows->fetchAll();
// Permintaan unit yang bisa digabung
try { $openNeeds = q("SELECT n.* FROM needs_requests n WHERE n.status IN ('DIAJUKAN','DIVERIFIKASI') ORDER BY n.id DESC")->fetchAll(); } catch (Throwable $e) { $openNeeds = []; }

require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2">
<div class="card">
  <h3>🗂️ Rencana Pengadaan
    <?php if (is_in_role(['admin','staff_pengadaan'])): ?><a class="btn btn-sm btn-primary" href="rencana_pengadaan_form.php">➕ Buat Rencana</a><?php endif; ?>
  </h3>
  <div class="workflow"><span class="wf-step">DRAFT</span><span class="wf-step">→ DIAJUKAN</span><span class="wf-step">→ DISETUJUI KADIV</span><span class="wf-step">→ DIREKTUR OPS</span><span class="wf-step">→ FINAL FINANCE</span><span class="wf-step">→ DALAM PROSES</span><span class="wf-step">→ DITERIMA</span></div>
  <div class="table-wrap"><table class="tbl">
    <tr><th>Nomor Rencana</th><th>Tanggal</th><th>Unit Pemohon</th><th class="num">Item</th><th class="num">Estimasi Total</th><th>Prioritas</th><th>Status</th></tr>
    <?php if (!$rows): ?><tr><td colspan="7"><div class="empty-state"><div class="big">🗂</div>Belum ada rencana pengadaan.</div></td></tr><?php endif; ?>
    <?php foreach ($rows as $r): if ($fStatus && $r['status'] !== $fStatus) continue; ?>
    <tr>
      <td><b><a href="rencana_pengadaan_detail.php?id=<?= (int)$r['id'] ?>"><?= e($r['plan_no']) ?></a></b></td>
      <td><?= tgl($r['plan_date']) ?></td><td><?= e($r['requesting_units'] ?? '-') ?></td>
      <td class="num"><?= (int)$r['item_count'] ?></td><td class="num"><?= rupiah($r['budget']) ?></td>
      <td><?= e($r['priority']) ?></td><td><?= status_badge($r['status']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<div class="card">
  <h3>🧩 Gabungkan Permintaan Unit → Satu Rencana</h3>
  <?php if (!$openNeeds): ?><div class="empty-state"><div class="big">✅</div>Tidak ada permintaan menunggu gabungan.</div>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="action" value="merge_needs">
    <div class="table-wrap"><table class="tbl">
      <tr><th></th><th>Nomor</th><th>Unit/Bidang</th><th>Pemohon</th><th>Status</th></tr>
      <?php foreach ($openNeeds as $n): ?>
      <tr><td><input type="checkbox" name="need_ids[]" value="<?= (int)$n['id'] ?>" style="width:auto"></td>
        <td><?= e($n['request_no']) ?></td><td><?= e($n['nama_bidang'] ?? '-') ?></td>
        <td><?= e(user_name_of($n['user_id'])) ?></td><td><?= status_badge($n['status']) ?></td></tr>
      <?php endforeach; ?>
    </table></div>
    <button class="btn btn-teal" style="margin-top:12px" onclick="return confirm('Gabungkan permintaan terpilih menjadi satu draft rencana pengadaan?')">🔗 Gabungkan Menjadi Rencana</button>
  </form>
  <?php endif; ?>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
