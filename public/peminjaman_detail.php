<?php
/**
 * Detail Peminjaman Kendaraan: persetujuan, serah terima keluar (handover +
 * tanda tangan canvas), dan pengembalian (KM akhir, kondisi, catatan).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
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
