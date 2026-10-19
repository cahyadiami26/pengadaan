<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Detail Rencana Pengadaan';
$active = 'rencana_pengadaan';

$id = (int)($_GET['id'] ?? 0);
$row = q("SELECT * FROM procurement_plans WHERE id=?", [$id])->fetch();
if (!$row) { flash_set('danger', 'Data tidak ditemukan.'); header('Location: rencana_pengadaan.php'); exit; }
$items = q("SELECT * FROM procurement_plan_items WHERE plan_id=?", [$id])->fetchAll();
$total = 0; foreach ($items as $it) $total += $it['qty'] * $it['est_price'];

$order = ['DRAFT','DIAJUKAN','DISSETUJUI_KADIV','DISETUJUI_DIREKTUR','FINAL_KEUANGAN','DALAM_PROSES_PENGADAAN','DITERIMA'];
$curIdx = array_search($row['status'], $order);
$labels = ['DRAFT','DIAJUKAN','DISETUJUI KADIV (Secretariat/Umum&Keu)','DISETUJUI DIREKTUR OPS','FINAL FINANCE','DALAM PROSES PENGADAAN','DITERIMA'];

require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h3>🗂️ <?= e($row['plan_no']) ?> <?= status_badge($row['status']) ?>
    <span>
      <?php if (is_in_role(['admin','staff_pengadaan']) && $row['status']==='DRAFT'): ?><a class="btn btn-sm btn-outline" href="rencana_pengadaan_form.php?id=<?= $id ?>">✏️ Edit</a><?php endif; ?>
      <a class="btn btn-sm btn-outline" href="laporan_pdf.php?jenis=rencana&id=<?= $id ?>">🖨 PDF</a>
      <a class="btn btn-sm btn-outline" href="rencana_pengadaan.php">← Kembali</a>
    </span>
  </h3>
  <div class="workflow">
    <?php foreach ($labels as $i => $lb): if ($row['status']==='DITOLAK' && $i>1) continue; ?>
      <span class="wf-step <?= $curIdx!==false && $i < $curIdx ? 'done' : ($i == $curIdx ? 'current' : '') ?>"><?= $lb ?></span>
      <?php if ($i < count($labels)-1 && !($curIdx!==false && $i>=$curIdx)) echo '<span style="align-self:center">→</span>'; ?>
    <?php endforeach; ?>
    <?php if ($row['status']==='DITOLAK'): ?><span class="wf-step" style="background:#fee2e2;color:#991b1b">DITOLAK</span><?php endif; ?>
  </div>

  <div class="grid grid-2">
    <table class="tbl">
      <tr><th>Tanggal</th><td><?= tgl($row['plan_date']) ?></td></tr>
      <tr><th>Unit Pemohon</th><td><?= e($row['requesting_units'] ?? '-') ?></td></tr>
      <tr><th>Anggaran</th><td><b><?= rupiah($row['budget']) ?></b></td></tr>
      <tr><th>Prioritas</th><td><?= e($row['priority']) ?></td></tr>
      <tr><th>Dibuat oleh</th><td><?= e(user_name_of($row['created_by'])) ?></td></tr>
    </table>
    <table class="tbl">
      <tr><th>Persetujuan Kadiv 1</th><td><?= $row['approved1_by'] ? e(user_name_of($row['approved1_by'])).' · '.tgl($row['approved1_at'],true) : '-' ?></td></tr>
      <tr><th>Persetujuan Direktur Ops</th><td><?= $row['approved2_by'] ? e(user_name_of($row['approved2_by'])).' · '.tgl($row['approved2_at'],true) : '-' ?></td></tr>
      <tr><th>Final Finance</th><td><?= $row['finance_by'] ? e(user_name_of($row['finance_by'])).' · '.tgl($row['finance_at'],true) : '-' ?></td></tr>
      <tr><th>Catatan</th><td><?= nl2br(e($row['note'] ?? '-')) ?></td></tr>
    </table>
  </div>
</div>

<div class="card">
  <h3>Item Rencana — Estimasi Total: <b style="color:var(--navy)"><?= rupiah($total) ?></b></h3>
  <div class="table-wrap"><table class="tbl">
    <tr><th>#</th><th>Barang/Jasa</th><th>Kategori</th><th class="num">Jumlah</th><th>Satuan</th><th class="num">Estimasi Harga</th><th class="num">Estimasi Total</th></tr>
    <?php foreach ($items as $i => $it): ?>
    <tr><td><?= $i+1 ?></td><td><?= e($it['item_name']) ?></td><td><?= e(str_replace('_',' ',$it['category'])) ?></td>
        <td class="num"><?= (int)$it['qty'] ?></td><td><?= e($it['unit']) ?></td>
        <td class="num"><?= rupiah($it['est_price']) ?></td><td class="num"><?= rupiah($it['qty']*$it['est_price']) ?></td></tr>
    <?php endforeach; if (!$items): ?><tr><td colspan="7">Tidak ada item.</td></tr><?php endif; ?>
  </table></div>
</div>

<div class="card">
  <h3>⚡ Aksi Workflow</h3>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
  <?php $back = '<input type="hidden" name="back_detail" value="1"><input type="hidden" name="id" value="' . $id . '">'; ?>
  <?php if ($row['status'] === 'DRAFT' && is_in_role(['admin','staff_pengadaan'])): ?>
    <form method="post" action="rencana_pengadaan.php"><?= $back ?><input type="hidden" name="action" value="submit"><button class="btn btn-primary">🚀 Ajukan Rencana</button></form>
  <?php endif; ?>
  <?php if ($row['status'] === 'DIAJUKAN' && is_in_role(['kepala_divisi','admin'])): ?>
    <form method="post" action="rencana_pengadaan.php"><?= $back ?><input type="hidden" name="action" value="approve1"><button class="btn btn-success">✔ Setujui (Kadiv Secretariat / Umum & Keuangan)</button></form>
  <?php endif; ?>
  <?php if ($row['status'] === 'DISSETUJUI_KADIV' && is_in_role(['pimpinan','admin'])): ?>
    <form method="post" action="rencana_pengadaan.php"><?= $back ?><input type="hidden" name="action" value="approve2"><button class="btn btn-success">✔ Setujui (Direktur Operasional)</button></form>
  <?php endif; ?>
  <?php if ($row['status'] === 'DISETUJUI_DIREKTUR' && is_in_role(['kepala_divisi','admin'])): ?>
    <form method="post" action="rencana_pengadaan.php"><?= $back ?><input type="hidden" name="action" value="finance"><button class="btn btn-teal">💰 Final Rencana ke Finance (Kadiv Umum & Keuangan)</button></form>
  <?php endif; ?>
  <?php if ($row['status'] === 'FINAL_KEUANGAN' && is_in_role(['admin','staff_pengadaan'])): ?>
    <form method="post" action="rencana_pengadaan.php"><?= $back ?><input type="hidden" name="action" value="process"><button class="btn btn-warning">🚧 Mulai Proses Pengadaan</button></form>
  <?php endif; ?>
  <?php if ($row['status'] === 'DALAM_PROSES_PENGADAAN'): ?>
    <a class="btn btn-primary" href="penerimaan_form.php?plan_id=<?= $id ?>">📦 Catat Penerimaan</a>
    <form method="post" action="rencana_pengadaan.php"><?= $back ?><input type="hidden" name="action" value="receive"><button class="btn btn-success">✔ Tandai DITERIMA</button></form>
  <?php endif; ?>
  <?php if (!in_array($row['status'], ['DRAFT','DITERIMA','DITOLAK']) && is_in_role(['kepala_divisi','pimpinan','admin'])): ?>
    <form method="post" action="rencana_pengadaan.php"><?= $back ?><input type="hidden" name="action" value="reject">
      <button class="btn btn-danger" onclick="var r=prompt('Alasan penolakan:');if(r===null)return false;this.form.reason.value=r;">✖ Tolak</button>
      <input type="hidden" name="reason" value=""></form>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
