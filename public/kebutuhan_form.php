<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Form Pengajuan Kebutuhan';
$active = 'kebutuhan';

// Hanya kepala bidang / kepala divisi (atau admin) yang dapat mengajukan
if (!in_array($_SESSION['app_role'], ['kepala_bidang','kepala_divisi','admin'])) {
    flash_set('danger', 'Pengajuan kebutuhan hanya dapat dilakukan oleh Kepala Bidang / Kepala Divisi.');
    header('Location: kebutuhan.php'); exit;
}

$id = (int)($_GET['id'] ?? 0);
$row = $id ? q("SELECT * FROM needs_requests WHERE id=?", [$id])->fetch() : null;
$items = $row ? q("SELECT * FROM needs_items WHERE need_id=?", [$id])->fetchAll() : [];

$cats = ['asset' => 'Asset', 'inventaris' => 'Inventaris', 'peralatan_kantor' => 'Peralatan/Perlengkapan Kantor', 'sparepart' => 'Sparepart', 'barang_habis_pakai' => 'Barang Habis Pakai'];
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h3><?= $row ? '✏️ Edit Draft ' . e($row['request_no']) : '➕ Pengajuan Kebutuhan Baru' ?></h3>
  <p style="color:var(--muted);margin-bottom:14px">Diajukan oleh <b><?= e($_SESSION['user_name']) ?></b> — <?= e($_SESSION['position'] ?? '') ?> · Bidang: <?= e($_SESSION['nama_bidang'] ?? '-') ?> · Divisi: <?= e($_SESSION['nama_divisi'] ?? '-') ?></p>
  <form method="post" action="kebutuhan.php">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>">
    <div class="form-row">
      <div class="form-group"><label>Keperluan / Alasan *</label><textarea name="purpose" rows="2" required placeholder="cth: Penunjang operasional rapat divisi"><?= e($row['purpose'] ?? '') ?></textarea></div>
      <div class="form-group" style="max-width:220px"><label>Tanggal Dibutuhkan</label><input type="date" name="needed_date" value="<?= e($row['needed_date'] ?? '') ?>"></div>
    </div>

    <label>Item yang Diajukan *</label>
    <div class="table-wrap"><table class="tbl" id="itemTable">
      <tr><th style="width:30%">Nama Barang/Jasa</th><th>Kategori</th><th style="width:80px">Jumlah</th><th style="width:90px">Satuan</th><th style="width:150px">Estimasi Harga/sat.</th><th>Spesifikasi</th><th style="width:40px"></th></tr>
      <?php if (!$items): ?>
      <tr>
        <td><input type="text" name="item_name[]" placeholder="Nama barang"></td>
        <td><select name="category[]"><?php foreach ($cats as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></td>
        <td><input type="number" name="qty[]" min="1" value="1"></td>
        <td><input type="text" name="unit[]" value="unit"></td>
        <td><input type="number" name="est_price[]" min="0" placeholder="0"></td>
        <td><input type="text" name="specification[]"></td>
        <td><button type="button" class="btn btn-sm btn-outline" onclick="this.closest('tr').remove()">✖</button></td>
      </tr>
      <?php else: foreach ($items as $it): ?>
      <tr>
        <td><input type="text" name="item_name[]" value="<?= e($it['item_name']) ?>"></td>
        <td><select name="category[]"><?php foreach ($cats as $k=>$v): ?><option value="<?= $k ?>" <?= $it['category']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></td>
        <td><input type="number" name="qty[]" min="1" value="<?= (int)$it['qty'] ?>"></td>
        <td><input type="text" name="unit[]" value="<?= e($it['unit']) ?>"></td>
        <td><input type="number" name="est_price[]" min="0" value="<?= (float)$it['est_price'] ?>"></td>
        <td><input type="text" name="specification[]" value="<?= e($it['specification']) ?>"></td>
        <td><button type="button" class="btn btn-sm btn-outline" onclick="this.closest('tr').remove()">✖</button></td>
      </tr>
      <?php endforeach; endif; ?>
    </table></div>
    <button type="button" class="btn btn-sm btn-outline" style="margin-top:8px"
      onclick="document.getElementById('itemTable').insertAdjacentHTML('beforeend', document.querySelector('#itemRowTpl').innerHTML)">➕ Tambah Item</button>
    <template id="itemRowTpl"><tr>
      <td><input type="text" name="item_name[]"></td>
      <td><select name="category[]"><?php foreach ($cats as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></td>
      <td><input type="number" name="qty[]" min="1" value="1"></td>
      <td><input type="text" name="unit[]" value="unit"></td>
      <td><input type="number" name="est_price[]" min="0"></td>
      <td><input type="text" name="specification[]"></td>
      <td><button type="button" class="btn btn-sm btn-outline" onclick="this.closest('tr').remove()">✖</button></td>
    </tr></template>

    <div style="margin-top:16px">
      <button class="btn btn-primary">💾 Simpan Draft</button>
      <a class="btn btn-outline" href="kebutuhan.php">Batal</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
