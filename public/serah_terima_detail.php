<?php
/** Detail serah terima + cetak PDF. */
require_once __DIR__ . '/../includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$h = q("SELECT h.*, uc1.name AS pemberi, uc1.position AS pemberi_jab,
               uc2.name AS penerima, uc2.position AS penerima_jab, uc2.nama_bidang AS penerima_bidang
        FROM handovers h
        LEFT JOIN user_cache uc1 ON uc1.user_id = h.from_user_id
        LEFT JOIN user_cache uc2 ON uc2.user_id = h.to_user_id WHERE h.id=?", [$id])->fetch();
if (!$h) { echo 'Data tidak ditemukan'; exit; }

$pageTitle = 'Serah Terima ' . $h['handover_no']; $active = 'perlengkapan';
require __DIR__ . '/../includes/header.php';
$base = dirname(__DIR__);
$sigGPath = $h['sig_giver'] ? $base . '/' . $h['sig_giver'] : null;
$sigRPath = $h['sig_receiver'] ? $base . '/' . $h['sig_receiver'] : null;
?>
<div class="card" style="max-width:860px;margin:0 auto">
  <h3>🤝 Berita Acara Serah Terima — <?= e($h['handover_no']) ?>
    <a class="btn btn-sm btn-teal" href="serah_terima_pdf.php?id=<?= (int)$h['id'] ?>" target="_blank">📄 Cetak PDF</a>
  </h3>
  <table class="tbl">
    <tr><th>Tanggal</th><td><?= tgl($h['handover_date']) ?></td></tr>
    <tr><th>Referensi</th><td><?= e(str_replace('_', ' ', $h['ref_type'] ?? '-')) ?><?= $h['ref_id'] ? ' #' . (int)$h['ref_id'] : '' ?></td></tr>
    <tr><th>Pihak Menyerahkan</th><td><b><?= e($h['pemberi'] ?? '-') ?></b> — <?= e($h['pemberi_jab'] ?? '') ?></td></tr>
    <tr><th>Pihak Menerima</th><td><b><?= e($h['penerima'] ?? '-') ?></b> — <?= e($h['penerima_jab'] ?? '') ?><?= $h['penerima_bidang'] ? ' (' . e($h['penerima_bidang']) . ')' : '' ?></td></tr>
    <tr><th>Detail Item</th><td><?= nl2br(e($h['items_desc'])) ?></td></tr>
    <?php if ($h['note']): ?><tr><th>Catatan</th><td><?= nl2br(e($h['note'])) ?></td></tr><?php endif; ?>
  </table>
  <div class="form-row" style="margin-top:16px">
    <div class="card" style="text-align:center">
      <small class="muted">Tanda Tangan — Pihak Menyerahkan</small><br>
      <?php if ($sigGPath && file_exists($sigGPath)): ?><img src="../<?= e($h['sig_giver']) ?>" style="max-height:110px"><?php else: ?><div style="height:110px"></div><?php endif; ?>
      <div><b><?= e($h['pemberi'] ?? '-') ?></b></div>
    </div>
    <div class="card" style="text-align:center">
      <small class="muted">Tanda Tangan — Pihak Menerima</small><br>
      <?php if ($sigRPath && file_exists($sigRPath)): ?><img src="../<?= e($h['sig_receiver']) ?>" style="max-height:110px"><?php else: ?><div style="height:110px"></div><?php endif; ?>
      <div><b><?= e($h['penerima'] ?? '-') ?></b></div>
    </div>
  </div>
  <a class="btn btn-outline" href="serah_terima.php">← Kembali ke Daftar</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
