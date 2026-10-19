<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Detail Pengajuan Kebutuhan';
$active = 'kebutuhan';

$id = (int)($_GET['id'] ?? 0);
$row = q("SELECT * FROM needs_requests WHERE id=?", [$id])->fetch();
if (!$row) { flash_set('danger', 'Data tidak ditemukan.'); header('Location: kebutuhan.php'); exit; }
$items = q("SELECT * FROM needs_items WHERE need_id=?", [$id])->fetchAll();
$total = 0; foreach ($items as $it) $total += $it['qty'] * $it['est_price'];

$steps = ['DRAFT','DIAJUKAN','DIVERIFIKASI','DISETUJUI_KABAG'];
$curIdx = array_search($row['status'], $steps);
if ($row['status'] === 'FINALISASI_KEUANGAN') $curIdx = 4;
if (in_array($row['status'], ['DALAM_PROSES_PENGADAAN','MASUK_RENCANA_PENGADAAN'])) $curIdx = 5;
if ($row['status'] === 'DITERIMA') $curIdx = 6;

$isOwner = $row['user_id'] == $_SESSION['user_id'];
$pemohonPos = null;
try { $st = ssoDb()->prepare("SELECT position FROM users WHERE id=?"); $st->execute([$row['user_id']]); $pemohonPos = $st->fetchColumn(); } catch (Throwable $e) {}

require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h3>📝 <?= e($row['request_no']) ?> <?= status_badge($row['status']) ?>
    <a class="btn btn-sm btn-outline" href="kebutuhan.php">← Kembali</a>
  </h3>

  <div class="workflow">
    <?php $labels = ['DRAFT','DIAJUKAN','DIVERIFIKASI','DISETUJUI STEP 1','FINALISASI ANGGARAN','PROSES PENGADAAN','DITERIMA'];
      foreach ($labels as $i => $lb): ?>
      <span class="wf-step <?= $i < $curIdx ? 'done' : ($i == $curIdx ? 'current' : '') ?>"><?= $lb ?></span>
      <?php if ($i < count($labels)-1) echo '<span style="align-self:center">→</span>'; ?>
    <?php endforeach; ?>
  </div>
  <?php if ($row['status'] === 'MASUK_RENCANA_PENGADAAN'): ?><p style="margin:6px 0"><span class="badge badge-warning">MASUK RENCANA PENGADAAN</span> <?= $row['plan_id'] ? 'terhubung ke <a href="rencana_pengadaan_detail.php?id='.(int)$row['plan_id'].'">rencana pengadaan</a>' : '' ?></p><?php endif; ?>
  <?php if ($row['status'] === 'DITOLAK'): ?><div class="alert alert-danger">Alasan penolakan: <?= e($row['rejected_reason']) ?></div><?php endif; ?>

  <div class="grid grid-2" style="margin-top:8px">
    <div>
      <table class="tbl"><tr><th>Pemohon</th><td><?= e(user_name_of($row['user_id'])) ?> (<?= e($pemohonPos ?? '-') ?>)</td></tr>
      <tr><th>Bidang</th><td><?= e($row['nama_bidang'] ?? '-') ?></td></tr>
      <tr><th>Divisi</th><td><?= e($row['nama_divisi'] ?? '-') ?></td></tr>
      <tr><th>Tanggal Dibutuhkan</th><td><?= tgl($row['needed_date']) ?></td></tr>
      <tr><th>Dibuat</th><td><?= tgl($row['created_at'], true) ?></td></tr></table>
    </div>
    <div>
      <table class="tbl">
        <tr><th>Verifikasi</th><td><?= $row['verified_by'] ? e(user_name_of($row['verified_by'])) . ' · ' . tgl($row['verified_at'], true) : '-' ?></td></tr>
        <tr><th>Persetujuan Step 1</th><td><?= $row['approved1_by'] ? e(user_name_of($row['approved1_by'])) . ' · ' . tgl($row['approved1_at'], true) : '-' ?></td></tr>
        <tr><th>Persetujuan Step 2 (DirOps)</th><td><?= $row['approved2_by'] ? e(user_name_of($row['approved2_by'])) . ' · ' . tgl($row['approved2_at'], true) : '-' ?></td></tr>
        <tr><th>Finalisasi Keuangan</th><td><?= $row['finance_by'] ? e(user_name_of($row['finance_by'])) . ' · ' . tgl($row['finance_at'], true) : '-' ?></td></tr>
        <tr><th>Keperluan</th><td><?= nl2br(e($row['purpose'] ?? '-')) ?></td></tr>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <h3>Item yang Diajukan — Total Estimasi: <b style="color:var(--navy)"><?= rupiah($total) ?></b></h3>
  <div class="table-wrap"><table class="tbl">
    <tr><th>#</th><th>Nama Barang/Jasa</th><th>Kategori</th><th class="num">Qty</th><th>Satuan</th><th class="num">Estimasi/sat</th><th class="num">Subtotal</th><th>Spesifikasi</th></tr>
    <?php foreach ($items as $i => $it): ?>
    <tr><td><?= $i+1 ?></td><td><b><?= e($it['item_name']) ?></b></td><td><?= e(str_replace('_',' ',$it['category'])) ?></td>
        <td class="num"><?= (int)$it['qty'] ?></td><td><?= e($it['unit']) ?></td>
        <td class="num"><?= rupiah($it['est_price']) ?></td><td class="num"><?= rupiah($it['qty']*$it['est_price']) ?></td>
        <td><?= e($it['specification'] ?? '-') ?></td></tr>
    <?php endforeach; if (!$items): ?><tr><td colspan="8">Tidak ada item.</td></tr><?php endif; ?>
  </table></div>
</div>

<div class="card">
  <h3>⚡ Aksi Workflow</h3>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
  <?php $back = '<input type="hidden" name="back_detail" value="1"><input type="hidden" name="id" value="' . $id . '">'; ?>

  <?php if ($row['status'] === 'DRAFT' && ($isOwner || is_in_role(['admin']))): ?>
    <form method="post" action="kebutuhan.php"><?= $back ?><input type="hidden" name="action" value="submit"><button class="btn btn-primary">🚀 Ajukan Sekarang</button></form>
    <a class="btn btn-outline" href="kebutuhan_form.php?id=<?= $id ?>">✏️ Edit Draft</a>
  <?php endif; ?>

  <?php if ($row['status'] === 'DIAJUKAN' && is_in_role(['admin','staff_pengadaan'])): ?>
    <form method="post" action="kebutuhan.php"><?= $back ?><input type="hidden" name="action" value="verify"><button class="btn btn-teal">✅ Verifikasi (Bagian Pengadaan)</button></form>
  <?php endif; ?>

  <?php if ($row['status'] === 'DIVERIFIKASI'): ?>
    <?php if ($pemohonPos === 'Kepala Divisi'): ?>
      <?php if (is_in_role(['pimpinan','admin'])): ?>
      <form method="post" action="kebutuhan.php"><?= $back ?><input type="hidden" name="action" value="approve1"><button class="btn btn-success">✔ Setujui Langsung (Direktur Operasional)</button></form>
      <?php endif; ?>
    <?php else: ?>
      <?php if (is_in_role(['kepala_divisi','admin'])): ?>
      <form method="post" action="kebutuhan.php"><?= $back ?><input type="hidden" name="action" value="approve1"><button class="btn btn-success">✔ Setujui Step 1 (Kepala Divisi)</button></form>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (is_in_role(['admin','staff_pengadaan'])): ?>
    <form method="post" action="kebutuhan.php" onsubmit="return confirm('Buat draft rencana pengadaan dari item ini?')"><?= $back ?><input type="hidden" name="action" value="to_plan"><button class="btn btn-warning">🗂 Masukkan Rencana Pengadaan</button></form>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($row['status'] === 'DISETUJUI_KABAG' && is_in_role(['pimpinan','admin'])): ?>
    <form method="post" action="kebutuhan.php"><?= $back ?><input type="hidden" name="action" value="approve2"><button class="btn btn-success">✔ Setujui Step 2 (Direktur Operasional)</button></form>
  <?php endif; ?>

  <?php if ($row['status'] === 'FINALISASI_KEUANGAN'): ?>
    <p style="width:100%;color:var(--muted)">Pengadaan langsung: finalisasi anggaran oleh Kepala Divisi Keuangan & Umum diperlukan sebelum proses pengadaan.</p>
    <?php if (is_in_role(['admin'])): ?>
      <form method="post" action="kebutuhan.php"><?= $back ?><input type="hidden" name="action" value="finance"><button class="btn btn-success">💰 Finalisasi Anggaran (Kadiv Keuangan & Umum)</button></form>
    <?php elseif (is_in_role(['kepala_divisi'])): ?>
      <?php $okFin = false; try { $st=ssoDb()->prepare("SELECT division FROM users WHERE id=?"); $st->execute([$_SESSION['user_id']]); $okFin = $st->fetchColumn()==='Keuangan'; } catch(Throwable $e){}
      if ($okFin): ?>
      <form method="post" action="kebutuhan.php"><?= $back ?><input type="hidden" name="action" value="finance"><button class="btn btn-success">💰 Finalisasi Anggaran</button></form>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($row['status'] === 'DALAM_PROSES_PENGADAAN'): ?>
    <a class="btn btn-primary" href="penerimaan_form.php?need_id=<?= $id ?>">📦 Catat Penerimaan</a>
    <?php if (is_in_role(['admin','staff_pengadaan','kepala_bidang','kepala_divisi'])): ?>
    <form method="post" action="kebutuhan.php"><?= $back ?><input type="hidden" name="action" value="receive"><button class="btn btn-success">✔ Tandai DITERIMA</button></form>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!in_array($row['status'], ['DRAFT','DITERIMA','DITOLAK','MASUK_RENCANA_PENGADAAN']) && is_in_role(['kepala_divisi','pimpinan','admin'])): ?>
    <form method="post" action="kebutuhan.php" onsubmit="return prompt('Alasan penolakan?')!==null">
      <?= $back ?><input type="hidden" name="action" value="reject">
      <input type="hidden" name="reason" value="" onfocus="">
      <button class="btn btn-danger" onclick="var r=prompt('Alasan penolakan:');if(r===null)return false;this.form.reason.value=r;">✖ Tolak</button>
    </form>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
