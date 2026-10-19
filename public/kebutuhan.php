<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Pengajuan Kebutuhan';
$active = 'kebutuhan';

$statuses = ['DRAFT','DIAJUKAN','DIVERIFIKASI','DISETUJUI_KABAG','DALAM_PROSES_PENGADAAN','MASUK_RENCANA_PENGADAAN','FINALISASI_KEUANGAN','DITERIMA','DITOLAK'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    if ($act === 'save' && in_array($_SESSION['app_role'], ['kepala_bidang','kepala_divisi','admin'])) {
        // Buat/update draft pengajuan (hanya kepala bidang / kepala divisi)
        $items = $_POST['item_name'] ?? [];
        if (!$id) {
            $no = gen_no('needs_requests', 'request_no', 'KB');
            q("INSERT INTO needs_requests (request_no,user_id,nama_bidang,nama_divisi,purpose,needed_date,status) VALUES (?,?,?,?,?,?, 'DRAFT')",
              [$no, $_SESSION['user_id'], $_SESSION['division'] ?? null, $_SESSION['department'] ?? null, trim($_POST['purpose'] ?? ''), $_POST['needed_date'] ?: null]);
            $id = (int)db()->lastInsertId();
        } else {
            $cur = q("SELECT * FROM needs_requests WHERE id=?", [$id])->fetch();
            if (!$cur || !in_array($cur['status'], ['DRAFT']) || ($cur['user_id'] != $_SESSION['user_id'] && !is_in_role(['admin']))) {
                flash_set('danger', 'Pengajuan tidak dapat diubah.'); header('Location: kebutuhan.php'); exit;
            }
            q("UPDATE needs_requests SET purpose=?, needed_date=? WHERE id=?", [trim($_POST['purpose'] ?? ''), $_POST['needed_date'] ?: null, $id]);
            q("DELETE FROM needs_items WHERE need_id=?", [$id]);
        }
        foreach ($items as $i => $nm) {
            if (trim($nm) === '') continue;
            q("INSERT INTO needs_items (need_id,item_name,category,qty,unit,est_price,specification) VALUES (?,?,?,?,?,?,?)",
              [$id, trim($nm), $_POST['category'][$i], max(1,(int)$_POST['qty'][$i]), $_POST['unit'][$i] ?: 'unit', (float)$_POST['est_price'][$i], trim($_POST['specification'][$i] ?? '')]);
        }
        flash_set('success', 'Draft pengajuan disimpan. Ajukan untuk memproses workflow.');
        header('Location: kebutuhan_detail.php?id=' . $id); exit;
    }

    $row = $id ? q("SELECT * FROM needs_requests WHERE id=?", [$id])->fetch() : null;
    if (!$row) { flash_set('danger', 'Data tidak ditemukan.'); header('Location: kebutuhan.php'); exit; }

    switch ($act) {
        case 'submit': // DRAFT -> DIAJUKAN (oleh pemohon)
            if ($row['status'] !== 'DRAFT' || ($row['user_id'] != $_SESSION['user_id'] && !is_in_role(['admin']))) break;
            q("UPDATE needs_requests SET status='DIAJUKAN' WHERE id=?", [$id]);
            flash_set('success', 'Pengajuan dikirim ke bagian Pengadaan.'); break;

        case 'verify': // DIAJUKAN -> DIVERIFIKASI (bagian pengadaan)
            require_role(['admin','staff_pengadaan']);
            if ($row['status'] !== 'DIAJUKAN') break;
            q("UPDATE needs_requests SET status='DIVERIFIKASI', verified_by=?, verified_at=NOW() WHERE id=?", [$_SESSION['user_id'], $id]);
            flash_set('success', 'Pengajuan terverifikasi.'); break;

        case 'approve1': // DIVERIFIKASI -> DISETUJUI_KABAG (step 1)
            // Jika diajukan kepala bidang -> disetujui kepala divisi yang sama; jika diajukan kepala divisi -> langsung Direktur Operasional
            if ($row['status'] !== 'DIVERIFIKASI') break;
            $pemohonPos = null;
            try { $pemohonPos = ssoDb()->prepare("SELECT position FROM users WHERE id=?"); $pemohonPos->execute([$row['user_id']]); $pemohonPos = $pemohonPos->fetchColumn(); } catch (Throwable $e) {}
            if ($pemohonPos === 'Kepala Divisi') {
                require_role(['pimpinan']);
                q("UPDATE needs_requests SET status='DISETUJUI_KABAG', approved1_by=?, approved1_at=NOW() WHERE id=?", [$_SESSION['user_id'], $id]);
                flash_set('success', 'Disetujui langsung oleh Direktur Operasional (pemohon Kepala Divisi). Lanjut finalisasi anggaran/proses pengadaan.');
            } else {
                require_role(['kepala_divisi','admin']);
                q("UPDATE needs_requests SET status='DISETUJUI_KABAG', approved1_by=?, approved1_at=NOW() WHERE id=?", [$_SESSION['user_id'], $id]);
                flash_set('success', 'Disetujui Kepala Divisi (step 1). Menunggu persetujuan Direktur Operasional (step 2).');
            }
            break;

        case 'approve2': // DISETUJUI_KABAG -> finalisasi anggaran / proses (step 2: Direktur Operasional)
            require_role(['pimpinan','admin']);
            if ($row['status'] !== 'DISETUJUI_KABAG') break;
            q("UPDATE needs_requests SET status='FINALISASI_KEUANGAN', approved2_by=?, approved2_at=NOW() WHERE id=?", [$_SESSION['user_id'], $id]);
            flash_set('success', 'Disetujui Direktur Operasional. Menunggu finalisasi anggaran Divisi Keuangan & Umum.'); break;

        case 'finance': // FINALISASI_KEUANGAN -> DALAM PROSES PENGADAAN
            if ($row['status'] !== 'FINALISASI_KEUANGAN') break;
            $ok = false;
            try {
                $pos = ssoDb()->prepare("SELECT position, division FROM users WHERE id=?"); $pos->execute([$_SESSION['user_id']]); $pu = $pos->fetch();
                $ok = ($pu && $pu['position'] === 'Kepala Divisi' && $pu['division'] === 'Keuangan');
            } catch (Throwable $e) {}
            require_role($ok ? ['kepala_divisi','admin'] : ['admin']);
            q("UPDATE needs_requests SET status='DALAM_PROSES_PENGADAAN', finance_by=?, finance_at=NOW() WHERE id=?", [$_SESSION['user_id'], $id]);
            flash_set('success', 'Anggaran difinalisasi. Pengadaan dalam proses.'); break;

        case 'to_plan': // -> MASUK RENCANA PENGADAAN: buat draft rencana otomatis dari item yang diajukan
            require_role(['admin','staff_pengadaan']);
            if (!in_array($row['status'], ['DIVERIFIKASI','DISETUJUI_KABAG'])) break;
            $pn = gen_no('procurement_plans', 'plan_no', 'RP');
            q("INSERT INTO procurement_plans (plan_no,plan_date,requesting_units,budget,priority,status,created_by) VALUES (?,CURDATE(),?,?, 'Sedang','DRAFT',?)",
              [$pn, $row['nama_bidang'] ?: user_name_of($row['user_id']),
               (float)q("SELECT COALESCE(SUM(qty*est_price),0) FROM needs_items WHERE need_id=?", [$row['id']])->fetchColumn(),
               $_SESSION['user_id']]);
            $planId = (int)db()->lastInsertId();
            foreach (q("SELECT * FROM needs_items WHERE need_id=?", [$row['id']])->fetchAll() as $it) {
                q("INSERT INTO procurement_plan_items (plan_id,need_item_id,item_name,category,qty,unit,est_price) VALUES (?,?,?,?,?,?,?)",
                  [$planId, $it['id'], $it['item_name'], $it['category'], $it['qty'], $it['unit'], $it['est_price']]);
            }
            q("UPDATE needs_requests SET status='MASUK_RENCANA_PENGADAAN', plan_id=? WHERE id=?", [$planId, $id]);
            flash_set('success', "Draft rencana pengadaan $pn dibuat dari item yang diajukan."); break;

        case 'reject':
            require_role(['kepala_divisi','pimpinan','admin','staff_pengadaan']);
            if ($row['status'] === 'DRAFT') break;
            q("UPDATE needs_requests SET status='DITOLAK', rejected_reason=? WHERE id=?", [trim($_POST['reason'] ?? ''), $id]);
            flash_set('warning', 'Pengajuan ditolak.'); break;

        case 'receive': // DALAM PROSES -> DITERIMA (setelah penerimaan barang selesai dicatat)
            require_role(['admin','staff_pengadaan','kepala_bidang','kepala_divisi']);
            if ($row['status'] !== 'DALAM_PROSES_PENGADAAN') break;
            q("UPDATE needs_requests SET status='DITERIMA' WHERE id=?", [$id]);
            flash_set('success', 'Pengajuan ditandai diterima.'); break;

        case 'delete':
            if ($row['status'] !== 'DRAFT') break;
            require_role(['admin']);
            q("DELETE FROM needs_requests WHERE id=?", [$id]);
            flash_set('success', 'Draft dihapus.'); break;
    }
    header('Location: ' . (isset($_POST['back_detail']) ? 'kebutuhan_detail.php?id=' . $id : 'kebutuhan.php')); exit;
}

$fStatus = $_GET['status'] ?? '';
$fMine = isset($_GET['mine']);
$where = []; $params = [];
if ($fStatus && in_array($fStatus, $statuses)) { $where[] = "n.status=?"; $params[] = $fStatus; }
if ($fMine) { $where[] = "n.user_id=?"; $params[] = $_SESSION['user_id']; }
$sql = "SELECT n.*, (SELECT COUNT(*) FROM needs_items i WHERE i.need_id=n.id) AS item_count,
        (SELECT COALESCE(SUM(i.qty*i.est_price),0) FROM needs_items i WHERE i.need_id=n.id) AS total_est
        FROM needs_requests n " . ($where ? "WHERE " . implode(' AND ', $where) : "") . " ORDER BY n.id DESC";
$stmt = db()->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h3>📝 Pengajuan Kebutuhan
    <?php if (in_array($_SESSION['app_role'], ['kepala_bidang','kepala_divisi','admin'])): ?>
    <a class="btn btn-sm btn-primary" href="kebutuhan_form.php">➕ Ajukan Kebutuhan</a>
    <?php endif; ?>
  </h3>
  <div class="workflow">
    <span class="wf-step">DRAFT</span><span class="wf-step">→ DIAJUKAN</span><span class="wf-step">→ DIVERIFIKASI</span>
    <span class="wf-step">→ DISETUJUI (Kabag/Kadiv → DirOps)</span><span class="wf-step">→ PENGADAAN / RENCANA</span><span class="wf-step">→ DITERIMA</span>
  </div>
  <form method="get" class="filters">
    <div class="form-group"><label>Status</label>
      <select name="status" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        <?php foreach ($statuses as $s): ?><option <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
      </select>
    </div>
    <label style="display:flex;align-items:center;gap:6px;padding-bottom:8px"><input type="checkbox" name="mine" value="1" <?= $fMine?'checked':'' ?> onchange="this.form.submit()"> Hanya pengajuan saya</label>
  </form>

  <div class="table-wrap"><table class="tbl">
    <tr><th>Nomor</th><th>Pemohon</th><th>Bidang</th><th>Tgl Butuh</th><th class="num">Item</th><th class="num">Estimasi</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$rows): ?><tr><td colspan="8"><div class="empty-state"><div class="big">🗒️</div>Belum ada pengajuan kebutuhan.</div></td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><a href="kebutuhan_detail.php?id=<?= (int)$r['id'] ?>"><?= e($r['request_no']) ?></a></b></td>
      <td><?= e(user_name_of($r['user_id'])) ?></td>
      <td><?= e($r['nama_bidang'] ?? '-') ?></td>
      <td><?= tgl($r['needed_date']) ?></td>
      <td class="num"><?= (int)$r['item_count'] ?></td>
      <td class="num"><?= rupiah($r['total_est']) ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td><a class="btn btn-sm btn-outline" href="kebutuhan_detail.php?id=<?= (int)$r['id'] ?>">Detail</a></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
