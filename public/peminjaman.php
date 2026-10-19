<?php
/**
 * Peminjaman Kendaraan - PT. Lampung Jasa Utama
 * Alur: MENUNGGU -> (persetujuan staff/admin) DISETUJUI -> serah terima keluar
 *       (form handover + tanda tangan canvas; KM awal otomatis dari data kendaraan,
 *        status kendaraan menjadi DIPINJAM) -> DIGUNAKAN -> pengembalian via form
 *       serah terima (tanggal+jam, KM akhir, kondisi, catatan) -> DIKEMBALIKAN
 *       (KM kendaraan otomatis ter-update).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Peminjaman Kendaraan'; $active = 'peminjaman';

$canManage = is_in_role(['admin', 'staff_pengadaan']);

/* ================= POST ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    /* ---- Ajukan peminjaman (semua pegawai) ---- */
    if ($act === 'ajukan') {
        $vid      = (int)$_POST['vehicle_id'];
        $startAt  = trim($_POST['start_at'] ?? '');
        $endAt    = trim($_POST['end_at'] ?? '');
        $purpose  = trim($_POST['purpose'] ?? '');
        $driver   = trim($_POST['driver'] ?? '');
        $passengers = trim($_POST['passengers'] ?? '');
        $errors = [];

        $v = q("SELECT * FROM vehicles WHERE id=?", [$vid])->fetch();
        if (!$v) $errors[] = 'Kendaraan tidak ditemukan.';
        elseif (!in_array($v['status'], ['TERSEDIA'], true)) $errors[] = 'Kendaraan ' . e($v['tnkb']) . ' sedang tidak tersedia (status: ' . e($v['status']) . ').';
        if (!$startAt || !$endAt) $errors[] = 'Tanggal & jam pemakaian wajib diisi.';
        elseif (strtotime($endAt) <= strtotime($startAt)) $errors[] = 'Tanggal kembali harus setelah tanggal berangkat.';
        if ($purpose === '') $errors[] = 'Keperluan/purpose wajib diisi.';

        if (!$errors) {
            $no = gen_no('vehicle_loans', 'loan_no', 'PIN');
            /* CATATAN: semua nilai dipakai sebagai placeholder (?) — jangan campur
               literal string di dalam VALUES karena parser MySQL lama dapat salah
               menghitung jumlah kolom (error #1136). */
            q("INSERT INTO vehicle_loans (loan_no, vehicle_id, user_id, purpose, driver, passengers, start_date, end_date, start_km, status)
               VALUES (?,?,?,?,?,?,?,?,?,?)",
              [$no, $vid, $_SESSION['user_id'], $purpose, $driver, $passengers, $startAt, $endAt, null, 'MENUNGGU']);
            refresh_notification_cache();
            flash_set('success', 'Pengajuan peminjaman <b>' . e($no) . '</b> dikirim. Menunggu persetujuan Bagian Umum/Pengadaan.');
            header('Location: peminjaman.php'); exit;
        }
        foreach ($errors as $er) flash_set('danger', $er);
        header('Location: peminjaman.php#mAjukan'); exit;
    }

    /* ---- Persetujuan / penolakan (staff pengadaan / admin) ---- */
    if ($act === 'approve' || $act === 'reject') {
        require_role(['admin', 'staff_pengadaan']);
        $l = q("SELECT * FROM vehicle_loans WHERE id=?", [$id])->fetch();
        if ($l && $l['status'] === 'MENUNGGU') {
            if ($act === 'approve') {
                q("UPDATE vehicle_loans SET status='DISETUJUI', approved_by=?, approve_note=? WHERE id=?",
                  [$_SESSION['user_id'], trim($_POST['approve_note'] ?? ''), $id]);
                flash_set('success', 'Pengajuan disetujui. Silakan lakukan serah terima kendaraan (KM awal otomatis diambil dari data kendaraan).');
            } else {
                q("UPDATE vehicle_loans SET status='DITOLAK', approved_by=?, approve_note=?, rejected_at=NOW() WHERE id=?",
                  [$_SESSION['user_id'], trim($_POST['reject_reason'] ?? ''), $id]);
                flash_set('info', 'Pengajuan ditolak.');
            }
            refresh_notification_cache();
        }
        header('Location: peminjaman.php'); exit;
    }

    /* ---- Pembatalan oleh pemohon selama masih MENUNGGU ---- */
    if ($act === 'batalkan') {
        $l = q("SELECT * FROM vehicle_loans WHERE id=?", [$id])->fetch();
        if ($l && ((int)$l['user_id'] === (int)$_SESSION['user_id'] || $canManage) && $l['status'] === 'MENUNGGU') {
            q("UPDATE vehicle_loans SET status='DITOLAK', approve_note=?, rejected_at=NOW() WHERE id=?",
              ['Dibatalkan oleh pemohon', $id]);
            flash_set('info', 'Pengajuan dibatalkan.');
        } else flash_set('danger', 'Hanya dapat dibatalkan selama masih menunggu persetujuan.');
        header('Location: peminjaman.php'); exit;
    }

    /* ---- Serah terima kendaraan keluar (DISETUJUI -> DIGUNAKAN) ---- */
    if ($act === 'handover_out') {
        require_role(['admin', 'staff_pengadaan']);
        $l = q("SELECT * FROM vehicle_loans WHERE id=?", [$id])->fetch();
        $v = $l ? q("SELECT * FROM vehicles WHERE id=?", [$l['vehicle_id']])->fetch() : null;
        $toUserId  = (int)($_POST['to_user_id'] ?? 0);
        $date      = $_POST['handover_date'] ?: date('Y-m-d');
        $time      = $_POST['handover_time'] ?: date('H:i');
        $kmInput   = (int)($_POST['start_km'] ?? 0);
        $itemsDesc = trim($_POST['items_desc'] ?? '');
        $note      = trim($_POST['note'] ?? '');
        $sigGiver  = save_signature_png($_POST['sig_giver'] ?? null);
        $sigReceiv = save_signature_png($_POST['sig_receiver'] ?? null);

        $errors = [];
        if (!$l || $l['status'] !== 'DISETUJUI') $errors[] = 'Pengajuan tidak berada pada status DISETUJUI.';
        if (!$toUserId) $errors[] = 'Penerima (peminjam) wajib dipilih.';
        if ($v && $kmInput < (int)$v['last_km']) $errors[] = 'KM awal tidak boleh kurang dari KM terakhir kendaraan (' . number_format((int)$v['last_km'], 0, ',', '.') . ' km).';
        if ($itemsDesc === '') $errors[] = 'Detail serah terima wajib diisi.';
        if (!$sigReceiv) $errors[] = 'Tanda tangan penerima wajib dibuat pada canvas.';

        if (!$errors) {
            db()->beginTransaction();
            try {
                $no = gen_no('handovers', 'handover_no', 'ST');
                q("INSERT INTO handovers (handover_no, ref_type, ref_id, from_user_id, to_user_id, handover_date, items_desc, sig_giver, sig_receiver, note)
                   VALUES (?, 'peminjaman_kendaraan', ?, ?, ?, ?, ?, ?, ?, ?)",
                  [$no, $l['id'], $_SESSION['user_id'], $toUserId, $date, $itemsDesc, $sigGiver, $sigReceiv, $note]);
                $hid = (int)db()->lastInsertId();

                q("UPDATE vehicle_loans SET status='DIGUNAKAN', start_km=?, handover_out_id=?, start_date=? WHERE id=?",
                  [$kmInput, $hid, $date . ' ' . $time . ':00', $l['id']]);
                q("UPDATE vehicles SET status='DIPINJAM', last_km=? WHERE id=?", [max($kmInput, (int)$v['last_km']), $v['id']]);
                q("INSERT INTO vehicle_usage_logs (vehicle_id, loan_id, user_id, destination, borrow_date, start_km, status)
                   VALUES (?,?,?,?,?,?, 'DIGUNAKAN')",
                  [$v['id'], $l['id'], $toUserId, mb_strimwidth($l['purpose'], 0, 190, '…'), $date . ' ' . $time . ':00', $kmInput]);
                db()->commit();
                refresh_notification_cache();
                flash_set('success', 'Serah terima kendaraan <b>' . e($no) . '</b> tersimpan. Status peminjaman: DIGUNAKAN.');
                header('Location: peminjaman_detail.php?id=' . $l['id']); exit;
            } catch (Throwable $ex) {
                db()->rollBack();
                flash_set('danger', 'Gagal menyimpan: ' . $ex->getMessage());
            }
        }
        foreach ($errors as $er) flash_set('danger', $er);
        header('Location: peminjaman_detail.php?id=' . $id . '#serahkan'); exit;
    }

    /* ---- Pengembalian kendaraan (DIGUNAKAN -> DIKEMBALIKAN, update KM) ---- */
    if ($act === 'return') {
        require_role(['admin', 'staff_pengadaan']);
        $l = q("SELECT * FROM vehicle_loans WHERE id=?", [$id])->fetch();
        $v = $l ? q("SELECT * FROM vehicles WHERE id=?", [$l['vehicle_id']])->fetch() : null;
        $fromUserId  = (int)($_POST['from_user_id'] ?? 0); // pihak menyerahkan kembali (peminjam)
        $date        = $_POST['handover_date'] ?: date('Y-m-d');
        $time        = $_POST['handover_time'] ?: date('H:i');
        $endKm       = isset($_POST['end_km']) && $_POST['end_km'] !== '' ? (int)$_POST['end_km'] : null;
        $condition   = trim($_POST['return_condition'] ?? '');
        $note        = trim($_POST['notes'] ?? '');
        $fuelCost    = (float)($_POST['fuel_cost'] ?? 0);
        $itemsDesc   = trim($_POST['items_desc'] ?? '');
        $sigGiver    = save_signature_png($_POST['sig_giver'] ?? null);
        $sigReceiv   = save_signature_png($_POST['sig_receiver'] ?? null);

        $errors = [];
        if (!$l || $l['status'] !== 'DIGUNAKAN') $errors[] = 'Peminjaman tidak berada pada status DIGUNAKAN.';
        if (!$fromUserId) $errors[] = 'Pihak yang mengembalikan (peminjam) wajib dipilih.';
        if ($endKm === null) $errors[] = 'KM akhir wajib diisi.';
        elseif ($v && $endKm < (int)$l['start_km']) $errors[] = 'KM akhir tidak boleh kurang dari KM awal (' . number_format((int)$l['start_km'], 0, ',', '.') . ' km).';
        if ($condition === '') $errors[] = 'Kondisi pengembalian wajib dipilih.';
        if ($itemsDesc === '') $errors[] = 'Detail pengembalian wajib diisi.';
        if (!$sigReceiv) $errors[] = 'Tanda tangan penerima kembali (Bagian Umum) wajib dibuat pada canvas.';

        if (!$errors) {
            db()->beginTransaction();
            try {
                $no = gen_no('handovers', 'handover_no', 'ST');
                q("INSERT INTO handovers (handover_no, ref_type, ref_id, from_user_id, to_user_id, handover_date, items_desc, sig_giver, sig_receiver, note)
                   VALUES (?, 'pengembalian_kendaraan', ?, ?, ?, ?, ?, ?, ?, ?)",
                  [$no, $l['id'], $fromUserId, $_SESSION['user_id'], $date, $itemsDesc, $sigGiver, $sigReceiv, $note]);
                $hid = (int)db()->lastInsertId();

                q("UPDATE vehicle_loans SET status='DIKEMBALIKAN', end_km=?, end_date=?, return_condition=?, return_note=?, fuel_cost=?,
                        handover_in_id=?, returned_at=?, confirmed_by=? WHERE id=?",
                  [$endKm, $date . ' ' . $time . ':00', $condition, $note, $fuelCost, $hid, $date . ' ' . $time . ':00', $_SESSION['user_id'], $l['id']]);
                // Update KM kendaraan & kembalikan status ke TERSEDIA
                q("UPDATE vehicles SET last_km=?, next_service_km=?+service_interval_km, status='TERSEDIA' WHERE id=?",
                  [$endKm, $endKm, $v['id']]);
                q("UPDATE vehicle_usage_logs SET return_date=?, end_km=?, status='SELESAI' WHERE loan_id=?",
                  [$date . ' ' . $time . ':00', $endKm, $l['id']]);
                db()->commit();
                refresh_notification_cache();
                $usage = $endKm - (int)$l['start_km'];
                flash_set('success', 'Pengembalian <b>' . e($no) . '</b> tersimpan. Pemakaian ' . number_format($usage, 0, ',', '.') . ' km — KM kendaraan diperbarui ke ' . number_format($endKm, 0, ',', '.') . ' km.');
                header('Location: peminjaman_detail.php?id=' . $l['id']); exit;
            } catch (Throwable $ex) {
                db()->rollBack();
                flash_set('danger', 'Gagal menyimpan: ' . $ex->getMessage());
            }
        }
        foreach ($errors as $er) flash_set('danger', $er);
        header('Location: peminjaman_detail.php?id=' . $id . '#kembalikan'); exit;
    }
}

/* ================= DATA ================= */
$fStatus = trim($_GET['status'] ?? '');
$sql = "SELECT l.*, v.tnkb, v.brand_model, uc.name AS peminjam, uc.avatar AS peminjam_avatar,
               uc.position AS peminjam_jab, uc.nama_bidang,
               ab.name AS approving_name
        FROM vehicle_loans l
        JOIN vehicles v ON v.id = l.vehicle_id
        LEFT JOIN user_cache uc ON uc.user_id = l.user_id
        LEFT JOIN user_cache ab ON ab.user_id = l.approved_by
        WHERE 1";
$args = [];
if ($fStatus !== '') { $sql .= " AND l.status = ?"; $args[] = $fStatus; }
if (!$canManage && !is_in_role(['kepala_bidang','kepala_divisi','pimpinan'])) {
    $sql .= " AND l.user_id = ?"; $args[] = $_SESSION['user_id'];
}
$sql .= " ORDER BY l.id DESC LIMIT 200";
$rows = q($sql, $args)->fetchAll();

$vehiclesAvail = q("SELECT * FROM vehicles WHERE status='TERSEDIA' ORDER BY tnkb")->fetchAll();
$cntWaiting = count_rows("SELECT COUNT(*) FROM vehicle_loans WHERE status='MENUNGGU'");
$cntUsed    = count_rows("SELECT COUNT(*) FROM vehicle_loans WHERE status='DIGUNAKAN'");
$users = q("SELECT user_id, name, position, nama_bidang FROM user_cache WHERE is_active=1 ORDER BY name")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h3>🔑 Peminjaman Kendaraan
    <button class="btn btn-sm btn-primary" onclick="openModal('mAjukan')">➕ Ajukan Peminjaman</button>
  </h3>
  <div class="grid grid-3" style="margin-bottom:14px">
    <div class="stat-card"><div class="stat-value"><?= $cntWaiting ?></div><div class="stat-label">Menunggu Persetujuan</div></div>
    <div class="stat-card"><div class="stat-value"><?= $cntUsed ?></div><div class="stat-label">Sedang Dipinjam</div></div>
    <div class="stat-card"><div class="stat-value"><?= count($vehiclesAvail) ?></div><div class="stat-label">Kendaraan Tersedia</div></div>
  </div>
  <form class="filters" method="get">
    <select name="status" onchange="this.form.submit()">
      <option value="">Semua Status</option>
      <?php foreach (['MENUNGGU','DISETUJUI','DIGUNAKAN','DIKEMBALIKAN','DITOLAK'] as $s): ?>
      <option value="<?= $s ?>" <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-sm">Terapkan</button></noscript>
  </form>
  <div class="table-wrap"><table class="tbl">
    <tr><th>No. Pinjam</th><th>Tanggal</th><th>Kendaraan</th><th>Peminjam</th><th>Keperluan</th>
        <th>Pakai (Jam)</th><th class="num">KM Awal→Akhir</th><th>Status</th><th></th></tr>
    <?php foreach ($rows as $l): ?>
    <tr>
      <td><b><a href="peminjaman_detail.php?id=<?= (int)$l['id'] ?>"><?= e($l['loan_no']) ?></a></b></td>
      <td><?= date('d-m-Y', strtotime($l['created_at'])) ?></td>
      <td><a href="kendaraan_detail.php?id=<?= (int)$l['vehicle_id'] ?>"><?= e($l['tnkb']) ?></a><br><small class="muted"><?= e($l['brand_model'] ?? '') ?></small></td>
      <td><?= avatar_html($l['peminjam_avatar'] ?? null, $l['peminjam'] ?? '?', 24) ?> <b><?= e($l['peminjam'] ?? '-') ?></b><br><small class="muted"><?= e($l['nama_bidang'] ?? '') ?></small></td>
      <td><?= nl2br(e(mb_strimwidth($l['purpose'] ?? '', 0, 60, '…'))) ?></td>
      <td><small><?= $l['start_date'] ? date('d/m H:i', strtotime($l['start_date'])) : '-' ?> →<br><?= $l['end_date'] ? date('d/m H:i', strtotime($l['end_date'])) : ($l['start_date'] ? date('d/m H:i', strtotime($l['start_date'])) : '-') ?></small></td>
      <td class="num"><?= $l['start_km'] !== null ? number_format((int)$l['start_km'],0,',','.') : '-' ?> → <?= $l['end_km'] !== null ? number_format((int)$l['end_km'],0,',','.') : '-' ?></td>
      <td><?= status_badge($l['status']) ?></td>
      <td>
        <?php if ($canManage && $l['status'] === 'MENUNGGU'): ?>
          <a class="btn btn-sm btn-teal" href="peminjaman_detail.php?id=<?= (int)$l['id'] ?>">✔ Proses</a>
        <?php elseif ($l['status'] === 'DISETUJUI' && $canManage): ?>
          <a class="btn btn-sm btn-primary" href="peminjaman_detail.php?id=<?= (int)$l['id'] ?>#serahkan">🤝 Serahkan</a>
        <?php elseif ($l['status'] === 'DIGUNAKAN' && $canManage): ?>
          <a class="btn btn-sm btn-warning" href="peminjaman_detail.php?id=<?= (int)$l['id'] ?>#kembalikan">↩ Kembalikan</a>
        <?php elseif ((int)$l['user_id'] === (int)$_SESSION['user_id'] && $l['status'] === 'MENUNGGU'): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Batalkan pengajuan ini?')">
            <input type="hidden" name="action" value="batalkan"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
            <button class="btn btn-sm btn-outline">Batal</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; if (!$rows): ?>
    <tr><td colspan="9"><div class="empty-state"><div class="big">🔑</div>Belum ada data peminjaman kendaraan.</div></td></tr><?php endif; ?>
  </table></div>
</div>

<!-- Dialog pengajuan dibuat di level dokumen (di luar .card) agar rendering stabil -->
<div class="modal-backdrop" id="mAjukan"><div class="modal" style="max-width:720px">
  <h3>🚗 Ajukan Peminjaman Kendaraan</h3>
  <?php if (!$vehiclesAvail): ?><div class="alert alert-warning">Tidak ada kendaraan berstatus TERSEDIA saat ini.</div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="action" value="ajukan">
    <div class="form-row">
      <div class="form-group" style="flex:2"><label>Kendaraan Tersedia *</label>
        <select name="vehicle_id" required>
          <option value="">- Pilih Kendaraan -</option>
          <?php foreach ($vehiclesAvail as $v): ?>
          <option value="<?= (int)$v['id'] ?>" data-km="<?= (int)$v['last_km'] ?>">
            <?= e($v['tnkb']) ?> — <?= e($v['brand_model']) ?> (KM: <?= number_format((int)$v['last_km'],0,',','.') ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <small class="muted">KM awal akan otomatis diambil dari data kendaraan saat penyerahan.</small>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Berangkat (tgl & jam) *</label><input type="datetime-local" name="start_at" required></div>
      <div class="form-group"><label>Rencana Kembali (tgl & jam) *</label><input type="datetime-local" name="end_at" required></div>
    </div>
    <div class="form-group"><label>Keperluan / Tujuan *</label><textarea name="purpose" rows="2" required placeholder="Contoh: Dinas ke Bakauheni mengantar dokumen kontrak"></textarea></div>
    <div class="form-row">
      <div class="form-group"><label>Pengemudi</label><input type="text" name="driver" placeholder="Nama pengemudi"></div>
      <div class="form-group"><label>Penumpang</label><input type="text" name="passengers" placeholder="Daftar penumpang"></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button type="button" class="btn btn-outline" onclick="closeModal('mAjukan')">Batal</button>
      <button class="btn btn-primary" <?= $vehiclesAvail ? '' : 'disabled' ?>>📮 Kirim Pengajuan</button>
    </div>
  </form>
</div></div>
<script>
// Auto-buka dialog & pilih kendaraan jika datang dari halaman kendaraan (?vehicle=ID)
(function () {
  var vid = new URLSearchParams(location.search).get('vehicle');
  if (!vid) return;
  var sel = document.querySelector('#mAjukan select[name=vehicle_id]');
  if (sel && sel.querySelector('option[value="' + vid + '"]')) sel.value = vid;
  openModal('mAjukan');
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
