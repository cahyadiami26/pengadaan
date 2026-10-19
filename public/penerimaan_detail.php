<?php
/**
 * Detail Penerimaan Barang/Jasa.
 * - Menampilkan checklist & item.
 * - Aksi per item: "Teruskan ke Stok/Aset" (push_to_stock) sesuai kategori item
 *   (atk/perlengkapan/sparepart/barang habis pakai/peralatan -> supplies; asset/inventaris -> assets + QR).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Detail Penerimaan'; $active = 'penerimaan';
$id = (int)($_GET['id'] ?? 0);
$r = q("SELECT r.*, uc.name AS penerima FROM receptions r LEFT JOIN user_cache uc ON uc.user_id=r.received_by WHERE r.id=?", [$id])->fetch();
if (!$r) { require __DIR__ . '/../includes/header.php'; echo '<div class="empty-state">Penerimaan tidak ditemukan.</div>'; require __DIR__ . '/../includes/footer.php'; exit; }
$items = q("SELECT * FROM reception_items WHERE reception_id=? ORDER BY id", [$id])->fetchAll();
$plan  = $r['plan_id'] ? q("SELECT plan_no,status FROM procurement_plans WHERE id=?", [$r['plan_id']])->fetch() : null;
$need  = $r['need_id'] ? q("SELECT request_no,status FROM needs_requests WHERE id=?", [$r['need_id']])->fetch() : null;
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:1020px">
<h3>📦 <?= e($r['receive_no']) ?> <?= status_badge($r['status']) ?></h3>
<table class="tbl">
  <tr><th>Tanggal Penerimaan</th><td><?= tgl($r['receive_date']) ?></td><th>Supplier</th><td><?= e($r['supplier'] ?: '-') ?></td></tr>
  <tr><th>No. Invoice/DO</th><td><?= e($r['invoice_no'] ?: '-') ?></td><th>Diterima Oleh</th><td><?= e($r['penerima'] ?? user_name_of($r['received_by'])) ?></td></tr>
  <tr><th>Rencana Terkait</th><td><?= $plan ? '<a href="rencana_pengadaan_detail.php?id='.(int)$r['plan_id'].'">'.e($plan['plan_no']).'</a> '.status_badge($plan['status']) : '-' ?></td>
      <th>Kebutuhan Terkait</th><td><?= $need ? '<a href="kebutuhan_detail.php?id='.(int)$r['need_id'].'">'.e($need['request_no']).'</a> '.status_badge($need['status']) : '-' ?></td></tr>
  <tr><th colspan="4">Checklist:
    <?= $r['chk_qty'] ? '✔ Jumlah sesuai' : '✘ Jumlah sesuai' ?> ·
    <?= $r['chk_spec'] ? '✔ Spesifikasi sesuai' : '✘ Spesifikasi sesuai' ?> ·
    <?= $r['chk_condition'] ? '✔ Kondisi baik' : '✘ Kondisi baik' ?> ·
    <?= $r['chk_docs'] ? '✔ Dokumen lengkap' : '✘ Dokumen lengkap' ?>
  </td></tr>
  <?php if ($r['note']): ?><tr><th>Catatan</th><td colspan="3"><?= nl2br(e($r['note'])) ?></td></tr><?php endif; ?>
</table>

<h4 style="margin:16px 0 8px">Item yang Diterima</h4>
<div class="table-wrap"><table class="tbl">
<tr><th>Nama Item</th><th>Kategori</th><th class="num">Qty Pesan</th><th class="num">Qty Terima</th><th class="num">Harga</th><th>Status Stok</th><th>Aksi</th></tr>
<?php foreach ($items as $it): ?>
<tr>
  <td><?= e($it['item_name']) ?><?= $it['asset_id'] ? '<br><small><a href="aset_detail.php?id='.(int)$it['asset_id'].'">→ lihat aset dibuat</a></small>' : '' ?></td>
  <td><?= e(str_replace('_',' ',$it['category'])) ?></td>
  <td class="num"><?= (int)$it['qty_ordered'] ?></td>
  <td class="num"><?= (int)$it['qty_received'] ?> <?= e($it['unit']) ?></td>
  <td class="num"><?= rupiah($it['price']) ?></td>
  <td><?= $it['to_stock'] ? '<span class="badge badge-success">SUDAH MASUK STOK</span>' : '<span class="badge badge-warning">BELUM</span>' ?></td>
  <td>
    <?php if (is_in_role(['admin','staff_pengadaan']) && !$it['to_stock'] && (int)$it['qty_received'] > 0): ?>
    <form method="post" action="penerimaan.php" onsubmit="return confirm('Teruskan item ini ke modul stok/aset?')">
      <input type="hidden" name="action" value="push_to_stock"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
      <input type="hidden" name="rid" value="<?= (int)$id ?>">
      <button class="btn btn-sm btn-teal">⬆️ Teruskan ke Stok</button>
    </form>
    <?php else: ?>—<?php endif; ?>
  </td>
</tr>
<?php endforeach; if (!$items): ?><tr><td colspan="7" class="muted center">Belum ada item.</td></tr><?php endif; ?>
</table></div>

<div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
  <?php if (is_in_role(['admin','staff_pengadaan'])): ?>
  <a class="btn btn-sm btn-outline" href="penerimaan_form.php">➕ Penerimaan Baru</a>
  <?php endif; ?>
  <a class="btn btn-sm btn-teal" href="laporan_pdf.php?jenis=penerimaan&id=<?= (int)$id ?>">📄 Berita Acara PDF</a>
  <a class="btn btn-sm btn-outline" href="penerimaan.php">← Kembali</a>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
