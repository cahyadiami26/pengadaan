<?php
/**
 * Detail Peminjaman Kendaraan: persetujuan, serah terima keluar (handover +
 * tanda tangan canvas), dan pengembalian (KM akhir, kondisi, catatan).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

/* ================= POST (approve / reject / handover_out / return) =================
   Form pada halaman ini sebelumnya tidak memiliki atribut action sehingga POST
   dikirim kembali ke peminjaman_detail.php — namun handler POST hanya ada di
   peminjaman.php sehingga tombol Setujui/Tolak tampak tidak berfungsi.
   Handler kini ditempatkan langsung di halaman detail ini. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $lid = (int)($_POST['id'] ?? 0);
    $canManageNow = is_in_role(['admin', 'staff_pengadaan']);
    if (!$canManageNow) { flash_set('danger', 'Anda tidak memiliki hak akses untuk memproses peminjaman.'); header('Location: peminjaman_detail.php?id=' . $lid); exit; }

    /* ---- Persetujuan / Penolakan ---- */
    if ($act === 'approve' || $act === 'reject') {
        $cur = q("SELECT * FROM vehicle_loans WHERE id=?", [$lid])->fetch();
        if ($cur && $cur['status'] === 'MENUNGGU') {
            if ($act === 'approve') {
                q("UPDATE vehicle_loans SET status='DISETUJUI', approved_by=?, approve_note=? WHERE id=?",
                  [$_SESSION['user_id'], trim($_POST['approve_note'] ?? ''), $lid]);
                flash_set('success', 'Pengajuan <b>' . e($cur['loan_no']) . '</b> disetujui. Silakan lakukan serah terima kendaraan (KM awal otomatis dari data kendaraan).');
            } else {
                $reason = trim($_POST['reject_reason'] ?? '');
                if ($reason === '') $reason = 'Ditolak oleh Bagian Umum/Pengadaan';
                q("UPDATE vehicle_loans SET status='DITOLAK', approved_by=?, approve_note=?, rejected_at=NOW() WHERE id=?",
                  [$_SESSION['user_id'], $reason, $lid]);
                flash_set('info', 'Pengajuan <b>' . e($cur['loan_no']) . '</b> ditolak.');
            }
            refresh_notification_cache();
        } else {
            flash_set('warning', 'Peminjaman tidak berada pada status MENUNGGU (mungkin sudah diproses sebelumnya).');
        }
        header('Location: peminjaman_detail.php?id=' . $lid); exit;
    }

    /* ---- Serah terima kendaraan keluar (DISETUJUI -> DIGUNAKAN) ---- */
    if ($act === 'handover_out') {
        $l2 = q("SELECT * FROM vehicle_loans WHERE id=?", [$lid])->fetch();
        $v2 = $l2 ? q("SELECT * FROM vehicles WHERE id=?", [$l2['vehicle_id']])->fetch() : null;
        $toUserId  = (int)($_POST['to_user_id'] ?? 0);
        $date      = $_POST['handover_date'] ?: date('Y-m-d');
        $time      = $_POST['handover_time'] ?: date('H:i');
        $kmInput   = (int)($_POST['start_km'] ?? 0);
        $itemsDesc = trim($_POST['items_desc'] ?? '');
        $note      = trim($_POST['note'] ?? '');
        $sigGiver  = save_signature_png($_POST['sig_giver'] ?? null);
        $sigReceiv = save_signature_png($_POST['sig_receiver'] ?? null);

        $errors = [];
        if (!$l2 || $l2['status'] !== 'DISETUJUI') $errors[] = 'Pengajuan tidak berada pada status DISETUJUI.';
        if (!$toUserId) $errors[] = 'Penerima (peminjam) wajib dipilih.';
        if ($v2 && $kmInput < (int)$v2['last_km']) $errors[] = 'KM awal tidak boleh kurang dari KM terakhir kendaraan (' . number_format((int)$v2['last_km'], 0, ',', '.') . ' km).';
        if ($itemsDesc === '') $errors[] = 'Detail serah terima wajib diisi.';
        if (!$sigReceiv) $errors[] = 'Tanda tangan penerima wajib dibuat pada canvas.';

        if (!$errors) {
            db()->beginTransaction();
            try {
                $no = gen_no('handovers', 'handover_no', 'ST');
                q("INSERT INTO handovers (handover_no, ref_type, ref_id, from_user_id, to_user_id, handover_date, items_desc, sig_giver, sig_receiver, note)
                   VALUES (?, 'peminjaman_kendaraan', ?, ?, ?, ?, ?, ?, ?, ?)",
                  [$no, $l2['id'], $_SESSION['user_id'], $toUserId, $date . ' ' . $time . ':00', $itemsDesc, $sigGiver, $sigReceiv, $note]);
                $hid = (int)db()->lastInsertId();
                q("UPDATE vehicle_loans SET status='DIGUNAKAN', start_km=?, handover_out_id=?, start_date=? WHERE id=?",
                  [$kmInput, $hid, $date . ' ' . $time . ':00', $l2['id']]);
                q("UPDATE vehicles SET status='DIPINJAM', last_km=? WHERE id=?", [max($kmInput, (int)$v2['last_km']), $v2['id']]);
                q("INSERT INTO vehicle_usage_logs (vehicle_id, loan_id, user_id, destination, borrow_date, start_km, status)
                   VALUES (?,?,?,?,?,?, 'DIGUNAKAN')",
                  [$v2['id'], $l2['id'], $toUserId, mb_strimwidth($l2['purpose'], 0, 190, '…'), $date . ' ' . $time . ':00', $kmInput]);
                db()->commit();
                refresh_notification_cache();
                flash_set('success', 'Serah terima kendaraan <b>' . e($no) . '</b> tersimpan. Status peminjaman: DIGUNAKAN.');
                header('Location: peminjaman_detail.php?id=' . $l2['id']); exit;
            } catch (Throwable $ex) {
                db()->rollBack();
                flash_set('danger', 'Gagal menyimpan: ' . $ex->getMessage());
            }
        }
        foreach ($errors as $er) flash_set('danger', $er);
        header('Location: peminjaman_detail.php?id=' . $lid . '#serahkan'); exit;
    }

    /* ---- Pengembalian kendaraan (DIGUNAKAN -> DIKEMBALIKAN, update KM) ---- */
    if ($act === 'return') {
        $l2 = q("SELECT * FROM vehicle_loans WHERE id=?", [$lid])->fetch();
        $v2 = $l2 ? q("SELECT * FROM vehicles WHERE id=?", [$l2['vehicle_id']])->fetch() : null;
        $fromUserId = (int)($_POST['from_user_id'] ?? 0);
        $date       = $_POST['handover_date'] ?: date('Y-m-d');
        $time       = $_POST['handover_time'] ?: date('H:i');
        $endKm      = isset($_POST['end_km']) && $_POST['end_km'] !== '' ? (int)$_POST['end_km'] : null;
        $condition  = trim($_POST['return_condition'] ?? '');
        $note       = trim($_POST['notes'] ?? '');
        $fuelCost   = (float)($_POST['fuel_cost'] ?? 0);
        $itemsDesc  = trim($_POST['items_desc'] ?? '');
        $sigGiver   = save_signature_png($_POST['sig_giver'] ?? null);
        $sigReceiv  = save_signature_png($_POST['sig_receiver'] ?? null);

        $errors = [];
        if (!$l2 || $l2['status'] !== 'DIGUNAKAN') $errors[] = 'Peminjaman tidak berada pada status DIGUNAKAN.';
        if (!$fromUserId) $errors[] = 'Pihak yang mengembalikan (peminjam) wajib dipilih.';
        if ($endKm === null) $errors[] = 'KM akhir wajib diisi.';
        elseif ($l2 && $endKm < (int)$l2['start_km']) $errors[] = 'KM akhir tidak boleh kurang dari KM awal (' . number_format((int)$l2['start_km'], 0, ',', '.') . ' km).';
        if ($condition === '') $errors[] = 'Kondisi pengembalian wajib dipilih.';
        if ($itemsDesc === '') $errors[] = 'Detail pengembalian wajib diisi.';
        if (!$sigReceiv) $errors[] = 'Tanda tangan penerima kembali (Bagian Umum) wajib dibuat pada canvas.';

        if (!$errors) {
            db()->beginTransaction();
            try {
                $no = gen_no('handovers', 'handover_no', 'ST');
                q("INSERT INTO handovers (handover_no, ref_type, ref_id, from_user_id, to_user_id, handover_date, items_desc, sig_giver, sig_receiver, note)
                   VALUES (?, 'pengembalian_kendaraan', ?, ?, ?, ?, ?, ?, ?, ?)",
                  [$no, $l2['id'], $fromUserId, $_SESSION['user_id'], $date . ' ' . $time . ':00', $itemsDesc, $sigGiver, $sigReceiv, $note]);
                $hid = (int)db()->lastInsertId();
                q("UPDATE vehicle_loans SET status='DIKEMBALIKAN', end_km=?, end_date=?, return_condition=?, return_note=?, fuel_cost=?,
                        handover_in_id=?, returned_at=?, confirmed_by=? WHERE id=?",
                  [$endKm, $date . ' ' . $time . ':00', $condition, $note, $fuelCost, $hid, $date . ' ' . $time . ':00', $_SESSION['user_id'], $l2['id']]);
                // Update KM kendaraan & kembalikan status ke TERSEDIA
                if ($v2) {
                    $svcCol = q("SHOW COLUMNS FROM vehicles LIKE 'next_service_km'")->fetch();
                    $intCol = q("SHOW COLUMNS FROM vehicles LIKE 'service_interval_km'")->fetch();
                    if ($svcCol && $intCol) {
                        q("UPDATE vehicles SET last_km=?, next_service_km=?+service_interval_km, status='TERSEDIA' WHERE id=?",
                          [$endKm, $endKm, $v2['id']]);
                    } else {
                        q("UPDATE vehicles SET last_km=?, status='TERSEDIA' WHERE id=?", [$endKm, $v2['id']]);
                    }
                }
                q("UPDATE vehicle_usage_logs SET return_date=?, end_km=?, status='SELESAI' WHERE loan_id=?",
                  [$date . ' ' . $time . ':00', $endKm, $l2['id']]);
                db()->commit();
                refresh_notification_cache();
                $usage = $endKm - (int)$l2['start_km'];
                flash_set('success', 'Pengembalian <b>' . e($no) . '</b> tersimpan. Pemakaian ' . number_format($usage, 0, ',', '.') . ' km — KM kendaraan diperbarui ke ' . number_format($endKm, 0, ',', '.') . ' km.');
                header('Location: peminjaman_detail.php?id=' . $l2['id']); exit;
            } catch (Throwable $ex) {
                db()->rollBack();
                flash_set('danger', 'Gagal menyimpan: ' . $ex->getMessage());
            }
        }
        foreach ($errors as $er) flash_set('danger', $er);
        header('Location: peminjaman_detail.php?id=' . $lid . '#kembalikan'); exit;
    }

    flash_set('danger', 'Aksi tidak dikenali.');
    header('Location: peminjaman_detail.php?id=' . $lid); exit;
}

$l = q("SELECT l.*, v.tnkb, v.brand_model, v.year, v.last_km AS veh_km, v.location AS veh_loc, v.pic AS veh_pic,
               uc.name AS peminjam, uc.avatar AS peminjam_avatar, uc.position AS peminjam_jab, uc.nama_bidang,
               ab.name AS approving_name
        FROM vehicle_loans l
        JOIN vehicles v ON v.id = l.vehicle_id
        LEFT JOIN user_cache uc ON uc.user_id = l.user_id
        LEFT JOIN user_cache ab ON ab.user_id = l.approved_by
        WHERE l.id=?", [$id])->fetch();
if (!$l) { require __DIR__ . '/../includes/header.php'; echo '<div class="empty-state">Data peminjaman tidak ditemukan.</div>'; require __DIR__ . '/../includes/footer.php'; exit; }

$hoOut = $l['handover_out_id'] ? q("SELECT * FROM handovers WHERE id=?", [$l['handover_out_id']])->fetch() : null;
$hoIn  = $l['handover_in_id']  ? q("SELECT * FROM handovers WHERE id=?", [$l['handover_in_id']])->fetch() : null;
$users = q("SELECT user_id, name, position, nama_bidang FROM user_cache WHERE is_active=1 ORDER BY name")->fetchAll();
$canManage = is_in_role(['admin', 'staff_pengadaan']);
$usageKm = ($l['start_km'] !== null && $l['end_km'] !== null) ? max(0, (int)$l['end_km'] - (int)$l['start_km']) : null;

$pageTitle = 'Peminjaman ' . $l['loan_no']; $active = 'peminjaman';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:980px;margin:0 auto 16px">
  <h3>🔑 Peminjaman <?= e($l['loan_no']) ?> <?= status_badge($l['status']) ?>
    <span style="float:right;display:flex;gap:8px">
      <?php if ($hoOut): ?><a class="btn btn-sm btn-teal" href="serah_terima_detail.php?id=<?= (int)$hoOut['id'] ?>">🤝 BAST Penyerahan</a><?php endif; ?>
      <?php if ($hoIn): ?><a class="btn btn-sm btn-teal" href="serah_terima_detail.php?id=<?= (int)$hoIn['id'] ?>">🤝 BAST Pengembalian</a><?php endif; ?>
      <a class="btn btn-sm btn-outline" href="peminjaman.php">← Kembali</a>
    </span>
  </h3>
  <div class="grid grid-2">
    <table class="tbl">
      <tr><th>Tanggal Diajukan</th><td><?= date('d-m-Y H:i', strtotime($l['created_at'])) ?></td></tr>
      <tr><th>Peminjam</th><td><?= avatar_html($l['peminjam_avatar'], $l['peminjam'], 32) ?> <b><?= e($l['peminjam']) ?></b> — <?= e($l['peminjam_jab'] ?: 'Staff') ?><?= $l['nama_bidang'] ? ' (' . e($l['nama_bidang']) . ')' : '' ?></td></tr>
      <tr><th>Keperluan</th><td><?= nl2br(e($l['purpose'] ?? '-')) ?></td></tr>
      <tr><th>Pengemudi / Penumpang</th><td><?= e($l['driver'] ?: '-') ?> / <?= e($l['passengers'] ?: '-') ?></td></tr>
      <tr><th>Jadwal Berangkat</th><td><?= $l['start_date'] ? date('d-m-Y H:i', strtotime($l['start_date'])) : '-' ?></td></tr>
      <tr><th>Jadwal/Riwayat Kembali</th><td><?= $l['end_date'] ? date('d-m-Y H:i', strtotime($l['end_date'])) : '-' ?><?= $l['status']==='DIGUNAKAN' ? ' <small class="muted">(rencana)</small>' : '' ?></td></tr>
      <tr><th>KM Awal → KM Akhir</th><td><b><?= $l['start_km'] !== null ? number_format((int)$l['start_km'],0,',','.') : '-' ?></b> → <b><?= $l['end_km'] !== null ? number_format((int)$l['end_km'],0,',','.') : '-' ?></b>
        <?= $usageKm !== null ? ' <span class="badge badge-info">Pemakaian ' . number_format($usageKm,0,',','.') . ' km</span>' : '' ?></td></tr>
      <?php if ((float)$l['fuel_cost'] > 0): ?><tr><th>BBM</th><td><?= rupiah($l['fuel_cost']) ?></td></tr><?php endif; ?>
      <tr><th>Persetujuan</th><td>
        <?php if ($l['status'] === 'DITOLAK'): ?>
          <?= e($l['approving_name'] ?? '-') ?> · <?= $l['rejected_at'] ? date('d-m-Y H:i', strtotime($l['rejected_at'])) : '' ?> <span class="badge badge-danger">DITOLAK</span>
        <?php elseif ($l['approved_by']): ?>
          <?= e($l['approving_name'] ?? '-') ?> <span class="badge badge-success">DISETUJUI</span>
        <?php else: ?>Menunggu persetujuan Bagian Umum/Pengadaan<?php endif; ?>
        <?php if ($l['approve_note']): ?><br><small class="muted">Catatan: <?= e($l['approve_note']) ?></small><?php endif; ?>
      </td></tr>
      <?php if ($l['return_condition']): ?>
      <tr><th>Kondisi Pengembalian</th><td><?= status_badge($l['return_condition']) ?></td></tr>
      <tr><th>Catatan Pengembalian</th><td><?= nl2br(e($l['return_note'])) ?></td></tr>
      <?php endif; ?>
    </table>
    <div>
      <table class="tbl">
        <tr><th>Kendaraan</th><td><b><a href="kendaraan_detail.php?id=<?= (int)$l['vehicle_id'] ?>"><?= e($l['tnkb']) ?></a></b> — <?= e($l['brand_model']) ?> (<?= e($l['year'] ?: '-') ?>)</td></tr>
        <tr><th>Lokasi / PIC</th><td><?= e($l['veh_loc'] ?: '-') ?> · <?= e($l['veh_pic'] ?: '-') ?></td></tr>
        <tr><th>KM Terakhir Sistem</th><td><?= number_format((int)$l['veh_km'],0,',','.') ?> km</td></tr>
      </table>
      <!-- Timeline alur -->
      <div style="margin-top:14px;font-size:13px;line-height:2.1">
        <div>📮 Diajukan <b><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></b></div>
        <div><?= in_array($l['status'],['MENUNGGU'],true) ? '⏳' : '✅' ?> Persetujuan Bagian Umum</div>
        <div><?= $hoOut ? '✅' : ($l['status']==='DISETUJUI'?'⏭':'⬜') ?> Serah terima kendaraan (KM awal tercatat)</div>
        <div><?= $l['status']==='DIGUNAKAN' ? '🚗' : ($l['end_km']!==null?'✅':'⬜') ?> Kendaraan digunakan</div>
        <div><?= $hoIn ? '✅ Pengembalian dikonfirmasi' : '⬜ Pengembalian + update KM' ?></div>
      </div>
    </div>
  </div>
</div>

<?php /* =============== FORM PERSETUJUAN =============== */ ?>
<?php if ($canManage && $l['status'] === 'MENUNGGU'): ?>
<div class="card" style="max-width:980px;margin:0 auto 16px"><h3>✔ Persetujuan Bagian Umum / Pengadaan</h3>
  <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="form-group" style="flex:2;min-width:240px"><label>Catatan Persetujuan</label><input type="text" name="approve_note" placeholder="Opsional"></div>
    <button class="btn btn-primary">✅ Setujui</button>
  </form>
  <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-top:8px" onsubmit="return confirm('Tolak pengajuan ini?')">
    <input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="form-group" style="flex:2;min-width:240px"><label>Alasan Penolakan</label><input type="text" name="reject_reason" required></div>
    <button class="btn btn-danger">✖ Tolak</button>
  </form>
</div>
<?php endif; ?>

<?php /* =============== FORM SERAH TERIMA KELUAR =============== */ ?>
<?php if ($canManage && $l['status'] === 'DISETUJUI'): $now = new DateTime('now'); ?>
<div class="card" style="max-width:980px;margin:0 auto 16px" id="serahkan"><h3>🤝 Serah Terima Kendaraan ke Peminjam</h3>
  <p class="muted" style="font-size:13px">KM awal otomatis diambil dari data kendaraan (<b><?= number_format((int)$l['veh_km'],0,',','.') ?> km</b>). Setelah disimpan: status kendaraan menjadi <b>DIPINJAM</b> dan peminjaman <b>DIGUNAKAN</b>.</p>
  <form method="post">
    <input type="hidden" name="action" value="handover_out"><input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="form-row">
      <div class="form-group"><label>Penerima / Peminjam *</label>
        <select name="to_user_id" required>
          <option value="">- Pilih Pegawai -</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= (int)$u['user_id'] ?>" <?= (int)$l['user_id']===(int)$u['user_id']?'selected':'' ?>><?= e($u['name']) ?> — <?= e($u['position'] ?: 'Staff') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Tanggal Penyerahan *</label><input type="date" name="handover_date" value="<?= $now->format('Y-m-d') ?>" required></div>
      <div class="form-group"><label>Jam *</label><input type="time" name="handover_time" value="<?= $now->format('H:i') ?>" required></div>
      <div class="form-group"><label>KM Awal *</label><input type="number" name="start_km" min="0" value="<?= (int)$l['veh_km'] ?>" required></div>
    </div>
    <div class="form-group"><label>Detail yang Diserahterimakan *</label>
      <textarea name="items_desc" rows="3" required>1 (satu) unit kendaraan <?= e($l['tnkb']) ?> — <?= e($l['brand_model']) ?> beserta STNK, kunci kontak, dan kelengkapan kendaraan. Kondisi saat diserahkan: baik, KM awal <?= number_format((int)$l['veh_km'],0,',','.') ?> km.</textarea>
    </div>
    <div class="form-group"><label>Catatan</label><input type="text" name="note" placeholder="Opsional"></div>
    <div class="form-row" style="margin-top:10px">
      <div class="form-group"><label>Tanda Tangan Pihak Menyerahkan (Bagian Umum)</label>
        <canvas id="sigG" width="400" height="150" style="border:1.5px dashed #94a3b8;border-radius:8px;background:#fff;width:100%;touch-action:none"></canvas>
        <button type="button" class="btn btn-sm btn-outline" onclick="clearSignature('sigG')">Bersihkan</button></div>
      <div class="form-group"><label>Tanda Tangan Penerima *</label>
        <canvas id="sigR" width="400" height="150" style="border:1.5px dashed #94a3b8;border-radius:8px;background:#fff;width:100%;touch-action:none"></canvas>
        <button type="button" class="btn btn-sm btn-outline" onclick="clearSignature('sigR')">Bersihkan</button></div>
    </div>
    <input type="hidden" name="sig_giver" id="sigGiverData"><input type="hidden" name="sig_receiver" id="sigReceiverData">
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
      <a class="btn btn-outline" href="peminjaman.php">Batal</a>
      <button class="btn btn-primary">💾 Simpan Serah Terima</button>
    </div>
  </form>
</div>
<?php endif; ?>

<?php /* =============== FORM PENGEMBALIAN =============== */ ?>
<?php if ($canManage && $l['status'] === 'DIGUNAKAN'): $now = new DateTime('now'); ?>
<div class="card" style="max-width:980px;margin:0 auto 16px" id="kembalikan"><h3>↩ Pengembalian Kendaraan</h3>
  <p class="muted" style="font-size:13px">Setelah disimpan: status kendaraan kembali <b>TERSEDIA</b>, <b>KM kendaraan otomatis ter-update</b> ke KM akhir, dan pemakaian tercatat dalam log.</p>
  <form method="post">
    <input type="hidden" name="action" value="return"><input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="form-row">
      <div class="form-group"><label>Pihak Mengembalikan (Peminjam) *</label>
        <select name="from_user_id" required>
          <option value="">- Pilih Pegawai -</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= (int)$u['user_id'] ?>" <?= (int)$l['user_id']===(int)$u['user_id']?'selected':'' ?>><?= e($u['name']) ?> — <?= e($u['position'] ?: 'Staff') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Tanggal Pengembalian *</label><input type="date" name="handover_date" value="<?= $now->format('Y-m-d') ?>" required></div>
      <div class="form-group"><label>Jam *</label><input type="time" name="handover_time" value="<?= $now->format('H:i') ?>" required></div>
      <div class="form-group"><label>KM Akhir * <small>(≥ <?= (int)$l['start_km'] ?>)</small></label>
        <input type="number" name="end_km" min="<?= (int)$l['start_km'] ?>" value="<?= (int)$l['start_km'] ?>" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Kondisi Pengembalian *</label>
        <select name="return_condition" required>
          <option value="">- Pilih Kondisi -</option>
          <option>BAIK</option><option>RUSAK RINGAN</option><option>RUSAK BERAT</option>
        </select>
      </div>
      <div class="form-group"><label>Biaya BBM (Rp)</label><input type="number" name="fuel_cost" min="0" step="1000" value="0"></div>
    </div>
    <div class="form-group"><label>Detail Pengembalian *</label>
      <textarea name="items_desc" rows="3" required>Kembali: 1 (satu) unit kendaraan <?= e($l['tnkb']) ?> — <?= e($l['brand_model']) ?> beserta STNK dan kunci kontak. KM akhir saat pengembalian: ______ km.</textarea>
    </div>
    <div class="form-group"><label>Catatan / Notes</label><textarea name="notes" rows="2" placeholder="Contoh: ada baret di bumper kiri, ban belakang kiri kurang angin..."></textarea></div>
    <div class="form-row" style="margin-top:10px">
      <div class="form-group"><label>Tanda Tangan Pihak Menyerahkan Kembali (Peminjam)</label>
        <canvas id="sigG" width="400" height="150" style="border:1.5px dashed #94a3b8;border-radius:8px;background:#fff;width:100%;touch-action:none"></canvas>
        <button type="button" class="btn btn-sm btn-outline" onclick="clearSignature('sigG')">Bersihkan</button></div>
      <div class="form-group"><label>Tanda Tangan Penerima Kembali (Bagian Umum) *</label>
        <canvas id="sigR" width="400" height="150" style="border:1.5px dashed #94a3b8;border-radius:8px;background:#fff;width:100%;touch-action:none"></canvas>
        <button type="button" class="btn btn-sm btn-outline" onclick="clearSignature('sigR')">Bersihkan</button></div>
    </div>
    <input type="hidden" name="sig_giver" id="sigGiverData"><input type="hidden" name="sig_receiver" id="sigReceiverData">
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
      <a class="btn btn-outline" href="peminjaman.php">Batal</a>
      <button class="btn btn-warning">💾 Simpan Pengembalian &amp; Update KM</button>
    </div>
  </form>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('form').forEach(function (f) {
  f.addEventListener('submit', function () {
    if (document.getElementById('sigG')) {
      exportSignature('sigG', 'sigGiverData');
      exportSignature('sigR', 'sigReceiverData');
    }
  });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
