<?php
/** Daftar serah terima barang (riwayat distribusi). */
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Serah Terima Barang'; $active = 'perlengkapan';
require __DIR__ . '/../includes/header.php';

$rows = q("SELECT h.*, uc1.name AS pemberi, uc2.name AS penerima, uc2.position AS penerima_jabatan
           FROM handovers h
           LEFT JOIN user_cache uc1 ON uc1.user_id = h.from_user_id
           LEFT JOIN user_cache uc2 ON uc2.user_id = h.to_user_id
           ORDER BY h.id DESC LIMIT 200")->fetchAll();
?>
<div class="card">
  <h3>🤝 Riwayat Serah Terima / Distribusi
    <?php if (is_in_role(['admin','staff_pengadaan'])): ?>
    <a class="btn btn-sm btn-primary" href="serah_terima_form.php?ref_type=lainnya">➕ Serah Terima Baru</a>
    <?php endif; ?>
  </h3>
  <div class="table-wrap"><table class="tbl">
    <tr><th>No. Serah Terima</th><th>Tanggal</th><th>Dari</th><th>Kepada</th><th>Referensi</th><th>Detail Item</th><th>Aksi</th></tr>
    <?php foreach ($rows as $h): ?>
    <tr>
      <td><b><?= e($h['handover_no']) ?></b></td>
      <td><?= tgl($h['handover_date']) ?></td>
      <td><?= e($h['pemberi'] ?? '-') ?></td>
      <td><?= e($h['penerima'] ?? '-') ?><br><small class="muted"><?= e($h['penerima_jabatan'] ?? '') ?></small></td>
      <td><small><?= e(str_replace('_', ' ', $h['ref_type'] ?? '')) ?><?= $h['ref_id'] ? ' #' . (int)$h['ref_id'] : '' ?></small></td>
      <td><?= e(mb_strimwidth($h['items_desc'] ?? '', 0, 60, '…')) ?></td>
      <td style="white-space:nowrap">
        <a class="btn btn-sm btn-outline" href="serah_terima_detail.php?id=<?= (int)$h['id'] ?>">Detail</a>
        <a class="btn btn-sm btn-teal" href="serah_terima_pdf.php?id=<?= (int)$h['id'] ?>" target="_blank">📄 PDF</a>
      </td>
    </tr>
    <?php endforeach; if (!$rows): ?>
    <tr><td colspan="7"><div class="empty-state"><div class="big">🤝</div>Belum ada data serah terima.</div></td></tr>
    <?php endif; ?>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
